<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (State.php) is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\Middleware;

use Slim\Helper\Set;
use Slim\Middleware;
use Slim\Slim;
use Stash\Driver\Composite;
use Stash\Driver\Ephemeral;
use Stash\Driver\FileSystem;
use Stash\Pool;
use Xibo\Exception\InstanceSuspendedException;
use Xibo\Exception\UpgradePendingException;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\NullSession;
use Xibo\Helper\Session;
use Xibo\Helper\Translate;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\FactoryService;
use Xibo\Service\HelpService;
use Xibo\Service\ModuleService;
use Xibo\Service\PlayerActionService;
use Xibo\Service\SanitizeService;

/**
 * Class State
 * @package Xibo\Middleware
 */
class State extends Middleware
{
    public function call()
    {
        $app = $this->app;

        // Handle additional Middleware
        if (isset($app->configService->middleware) && is_array($app->configService->middleware)) {
            foreach ($app->configService->middleware as $object) {
                $app->add($object);
            }
        }

        // Set state
        State::setState($app);

        // Attach a hook to log the route
        $this->app->hook('slim.before.dispatch', function() use ($app) {

            // Do we need SSL/STS?
            if ($app->request()->getScheme() == 'https') {
                if ($app->configService->GetSetting('ISSUE_STS', 0) == 1)
                    $app->response()->header('strict-transport-security', 'max-age=' . $app->configService->GetSetting('STS_TTL', 600));
            }
            else {
                if ($app->configService->GetSetting('FORCE_HTTPS', 0) == 1) {
                    $redirect = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                    header("Location: $redirect");
                    $app->halt(302);
                }
            }

            // Check to see if the instance has been suspended, if so call the special route
            if ($app->configService->GetSetting('INSTANCE_SUSPENDED') == 1)
                throw new InstanceSuspendedException();

            // Get to see if upgrade is pending
            if ($app->configService->isUpgradePending() && $app->getName() != 'web')
                throw new UpgradePendingException();

            // Reset the ETAGs for GZIP
            if ($requestEtag = $app->request->headers->get('IF_NONE_MATCH')) {
                $app->request->headers->set('IF_NONE_MATCH', str_replace('-gzip', '', $requestEtag));
            }
        });

        // Attach a hook to be called after the route has been dispatched
        $this->app->hook('slim.after.dispatch', function() use ($app) {
            // On our way back out the onion, we want to render the controller.
            if ($app->controller != null)
                $app->controller->render();
        });

        // Next middleware
        $this->next->call();
    }

    /**
     * @param Slim $app
     */
    public static function setState($app)
    {
        // Set the root Uri
        State::setRootUri($app);

        // Set the config dependencies
        $app->configService->setDependencies($app->store, $app->rootUri);

        // Register the date service
        $app->container->singleton('dateService', function() use ($app) {
            if ($app->configService->GetSetting('CALENDAR_TYPE') == 'Jalali')
                $date = new \Xibo\Service\DateServiceJalali($app);
            else
                $date = new \Xibo\Service\DateServiceGregorian($app);

            $date->setLocale(Translate::GetLocale(2));

            return $date;
        });

        // Register the sanitizer
        $app->container->singleton('sanitizerService', function($container) {
            $sanitizer = new SanitizeService($container->dateService);
            $sanitizer->setRequest($container->request);
            return $sanitizer;
        });

        // Register Controllers with DI
        self::registerControllersWithDi($app);

        // Register Factories with DI
        self::registerFactoriesWithDi($app);

        // Player Action Helper
        $app->container->singleton('playerActionService', function() use($app) {
            return new PlayerActionService($app);
        });

        // Set some public routes
        $app->publicRoutes = array('/login', '/logout', '/clock', '/about', '/login/ping');

        // The state of the application response
        $app->container->singleton('state', function() { return new ApplicationState(); });

        // Setup the translations for gettext
        Translate::InitLocale($app->configService);

        // Config Version
        $app->configService->Version();

        // Default timezone
        date_default_timezone_set($app->configService->GetSetting("defaultTimezone"));

        // Configure the cache
        self::configureCache($app->container, $app->configService, $app->logWriter->getWriter());

        // Register the help service
        $app->container->singleton('helpService', function($container) {
            return new HelpService($container->store, $container->configService, $container->pool);
        });

        // Create a session
        $app->container->singleton('session', function() use ($app) {
            if ($app->getName() == 'web' || $app->getName() == 'auth')
                return new Session($app->logService);
            else
                return new NullSession();
        });

        // App Mode
        $mode = $app->configService->GetSetting('SERVER_MODE');
        $app->logService->setMode($mode);

        // Configure logging
        if (strtolower($mode) == 'test') {
            $app->config('log.level', \Slim\Log::DEBUG);
        }
        else {
            $app->config('log.level', \Xibo\Service\LogService::resolveLogLevel($app->configService->GetSetting('audit', 'error')));
        }

        // Configure any extra log handlers
        if ($app->configService->logHandlers != null && is_array($app->configService->logHandlers)) {
            $app->logService->debug('Configuring %d additional log handlers from Config', count($app->configService->logHandlers));
            foreach ($app->configService->logHandlers as $handler) {
                $app->logWriter->addHandler($handler);
            }
        }

        // Configure any extra log processors
        if ($app->configService->logProcessors != null && is_array($app->configService->logProcessors)) {
            $app->logService->debug('Configuring %d additional log processors from Config', count($app->configService->logProcessors));
            foreach ($app->configService->logProcessors as $processor) {
                $app->logWriter->addProcessor($processor);
            }
        }
    }

    /**
     * Set the Root URI
     * @param Slim $app
     */
    public static function setRootUri($app)
    {
        // Set the root Uri
        $app->rootUri = $app->request->getRootUri() . '/';

        // Static source, so remove index.php from the path
        // this should only happen if rewrite is disabled
        $app->rootUri = str_replace('/index.php', '', $app->rootUri);

        switch ($app->getName()) {

            case 'install':
                $app->rootUri = str_replace('/install', '', $app->rootUri);
                break;

            case 'api':
                $app->rootUri = str_replace('/api', '', $app->rootUri);
                break;

            case 'auth':
                $app->rootUri = str_replace('/api/authorize', '', $app->rootUri);
                break;

            case 'maintenance':
                $app->rootUri = str_replace('/maintenance', '', $app->rootUri);
                break;
        }
    }

    /**
     * Configure the Cache
     * @param Set $container
     * @param ConfigServiceInterface $configService
     * @param \PSR\Log\LoggerInterface $logWriter
     */
    public static function configureCache($container, $configService, $logWriter)
    {
        $drivers = [];

        if ($configService->cacheDrivers != null && is_array($configService->cacheDrivers)) {
            $drivers = $configService->cacheDrivers;
        } else {
            // File System Driver
            $drivers[] = new FileSystem(['path' => $configService->GetSetting('LIBRARY_LOCATION') . 'cache/']);
        }

        // Always add the Ephemeral driver
        $drivers[] = new Ephemeral();

        // Create a composite driver
        $composite = new Composite(['drivers' => $drivers]);

        // Create a pool using this driver set
        $container->singleton('pool', function() use ($logWriter, $composite) {
            $pool = new Pool($composite);
            $pool->setLogger($logWriter);
            $pool->setNamespace('Xibo');
            return $pool;
        });
    }

    /**
     * Register controllers with DI
     * @param Slim $app
     */
    public static function registerControllersWithDi($app)
    {
        $app->container->singleton('\Xibo\Controller\Applications', function() {
            return new \Xibo\Controller\Applications();
        });

        $app->container->singleton('\Xibo\Controller\Campaign', function() {
            return new \Xibo\Controller\Campaign();
        });

        $app->container->singleton('\Xibo\Controller\Clock', function() {
            return new \Xibo\Controller\Clock();
        });

        $app->container->singleton('\Xibo\Controller\Command', function() {
            return new \Xibo\Controller\Command();
        });

        $app->container->singleton('\Xibo\Controller\DataSet', function() {
            return new \Xibo\Controller\DataSet();
        });

        $app->container->singleton('\Xibo\Controller\DataSetColumn', function() {
            return new \Xibo\Controller\DataSetColumn();
        });

        $app->container->singleton('\Xibo\Controller\DataSetData', function() {
            return new \Xibo\Controller\DataSetData();
        });

        $app->container->singleton('\Xibo\Controller\Display', function() {
            return new \Xibo\Controller\Display();
        });

        $app->container->singleton('\Xibo\Controller\DisplayGroup', function() {
            return new \Xibo\Controller\DisplayGroup();
        });

        $app->container->singleton('\Xibo\Controller\DisplayProfile', function() {
            return new \Xibo\Controller\DisplayProfile();
        });

        $app->container->singleton('\Xibo\Controller\Error', function() {
            return new \Xibo\Controller\Error();
        });

        $app->container->singleton('\Xibo\Controller\Fault', function() {
            return new \Xibo\Controller\Fault();
        });

        $app->container->singleton('\Xibo\Controller\Help', function() {
            return new \Xibo\Controller\Help();
        });

        $app->container->singleton('\Xibo\Controller\IconDashboard', function() {
            return new \Xibo\Controller\IconDashboard();
        });

        $app->container->singleton('\Xibo\Controller\Layout', function() {
            return new \Xibo\Controller\Layout();
        });

        $app->container->singleton('\Xibo\Controller\Library', function() {
            return new \Xibo\Controller\Library();
        });

        $app->container->singleton('\Xibo\Controller\Logging', function() {
            return new \Xibo\Controller\Logging();
        });

        $app->container->singleton('\Xibo\Controller\Login', function() {
            return new \Xibo\Controller\Login();
        });

        $app->container->singleton('\Xibo\Controller\Maintenance', function() {
            return new \Xibo\Controller\Maintenance();
        });

        $app->container->singleton('\Xibo\Controller\MediaManager', function() {
            return new \Xibo\Controller\MediaManager();
        });

        $app->container->singleton('\Xibo\Controller\Module', function() {
            return new \Xibo\Controller\Module();
        });

        $app->container->singleton('\Xibo\Controller\Playlist', function() {
            return new \Xibo\Controller\Playlist();
        });

        $app->container->singleton('\Xibo\Controller\Preview', function() {
            return new \Xibo\Controller\Preview();
        });

        $app->container->singleton('\Xibo\Controller\Region', function() {
            return new \Xibo\Controller\Region();
        });

        $app->container->singleton('\Xibo\Controller\Resolution', function() {
            return new \Xibo\Controller\Resolution();
        });

        $app->container->singleton('\Xibo\Controller\Schedule', function() {
            return new \Xibo\Controller\Schedule();
        });

        $app->container->singleton('\Xibo\Controller\Sessions', function() {
            return new \Xibo\Controller\Sessions();
        });

        $app->container->singleton('\Xibo\Controller\Settings', function() {
            return new \Xibo\Controller\Settings();
        });

        $app->container->singleton('\Xibo\Controller\Stats', function() {
            return new \Xibo\Controller\Stats();
        });

        $app->container->singleton('\Xibo\Controller\StatusDashboard', function() {
            return new \Xibo\Controller\StatusDashboard();
        });

        $app->container->singleton('\Xibo\Controller\Template', function() {
            return new \Xibo\Controller\Template();
        });

        $app->container->singleton('\Xibo\Controller\Transition', function() {
            return new \Xibo\Controller\Transition();
        });

        $app->container->singleton('\Xibo\Controller\Upgrade', function() {
            return new \Xibo\Controller\Upgrade();
        });

        $app->container->singleton('\Xibo\Controller\User', function() {
            return new \Xibo\Controller\User();
        });

        $app->container->singleton('\Xibo\Controller\UserGroup', function() {
            return new \Xibo\Controller\UserGroup();
        });
    }

    /**
     * Register Factories with DI
     * @param Slim $app
     */
    public static function registerFactoriesWithDi($app)
    {
        $app->container->singleton('moduleService', function () use($app) {
            return new ModuleService($app);
        });
        $app->container->singleton('factoryService', function ($container) {
            return new FactoryService($container);
        });
        $app->container->singleton('\Xibo\Factory\ApplicationFactory', function($container) {
            return new \Xibo\Factory\ApplicationFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\ApplicationRedirectUriFactory', function($container) {
            return new \Xibo\Factory\ApplicationRedirectUriFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\AuditLogFactory', function($container) {
            return new \Xibo\Factory\AuditLogFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\BandwidthFactory', function($container) {
            return new \Xibo\Factory\BandwidthFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\CampaignFactory', function($container) {
            return new \Xibo\Factory\CampaignFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\CommandFactory', function($container) {
            return new \Xibo\Factory\CommandFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\DataSetColumnFactory', function($container) {
            return new \Xibo\Factory\DataSetColumnFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\DataSetColumnTypeFactory', function($container) {
            return new \Xibo\Factory\DataSetColumnTypeFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\DataSetFactory', function($container) {
            return new \Xibo\Factory\DataSetFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\DataTypeFactory', function($container) {
            return new \Xibo\Factory\DataTypeFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\DisplayFactory', function($container) {
            return new \Xibo\Factory\DisplayFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\DisplayGroupFactory', function($container) {
            return new \Xibo\Factory\DisplayGroupFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\DisplayProfileFactory', function($container) {
            return new \Xibo\Factory\DisplayProfileFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\HelpFactory', function($container) {
            return new \Xibo\Factory\HelpFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\LayoutFactory', function($container) {
            return new \Xibo\Factory\LayoutFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\LogFactory', function($container) {
            return new \Xibo\Factory\LogFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\MediaFactory', function($container) {
            return new \Xibo\Factory\MediaFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\ModuleFactory', function($container) {
            return new \Xibo\Factory\ModuleFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\PageFactory', function($container) {
            return new \Xibo\Factory\PageFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\PermissionFactory', function($container) {
            return new \Xibo\Factory\PermissionFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\PlaylistFactory', function($container) {
            return new \Xibo\Factory\PlaylistFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\RegionFactory', function($container) {
            return new \Xibo\Factory\RegionFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\RegionOptionFactory', function($container) {
            return new \Xibo\Factory\RegionOptionFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\RequiredFileFactory', function($container) {
            return new \Xibo\Factory\RequiredFileFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\ResolutionFactory', function($container) {
            return new \Xibo\Factory\ResolutionFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\ScheduleFactory', function($container) {
            return new \Xibo\Factory\ScheduleFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\SessionFactory', function($container) {
            return new \Xibo\Factory\SessionFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\SettingsFactory', function($container) {
            return new \Xibo\Factory\SettingsFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\TagFactory', function($container) {
            return new \Xibo\Factory\TagFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\TransitionFactory', function($container) {
            return new \Xibo\Factory\TransitionFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\UpgradeFactory', function($container) {
            return new \Xibo\Factory\UpgradeFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\UserFactory', function($container) {
            return new \Xibo\Factory\UserFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\UserGroupFactory', function($container) {
            return new \Xibo\Factory\UserGroupFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\UserOptionFactory', function($container) {
            return new \Xibo\Factory\UserOptionFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\UserTypeFactory', function($container) {
            return new \Xibo\Factory\UserTypeFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\WidgetFactory', function($container) {
            return new \Xibo\Factory\WidgetFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\WidgetMediaFactory', function($container) {
            return new \Xibo\Factory\WidgetMediaFactory($container);
        });
        $app->container->singleton('\Xibo\Factory\WidgetOptionFactory', function($container) {
            return new \Xibo\Factory\WidgetOptionFactory($container);
        });
    }
}