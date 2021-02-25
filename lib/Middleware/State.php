<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015-2018 Spring Signage Ltd
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
use Stash\Pool;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Xibo\Exception\InstanceSuspendedException;
use Xibo\Exception\UpgradePendingException;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Environment;
use Xibo\Helper\NullSession;
use Xibo\Helper\Session;
use Xibo\Helper\Translate;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\HelpService;
use Xibo\Service\ImageProcessingService;
use Xibo\Service\ModuleService;
use Xibo\Service\ReportService;
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

        // Set state
        State::setState($app);

        // Attach a hook to log the route
        $this->app->hook('slim.before.dispatch', function() use ($app) {

            // Do we need SSL/STS?
            // If we are behind a load balancer we should look at HTTP_X_FORWARDED_PROTO
            // if a whitelist of IP address is provided, we should check it, otherwise trust
            $whiteListLoadBalancers = $app->configService->getSetting('WHITELIST_LOAD_BALANCERS');
            $originIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            $forwardedProtoHttps = (
                strtolower($app->request()->headers('HTTP_X_FORWARDED_PROTO', 'http')) === 'https'
                && $originIp != ''
                && (
                    $whiteListLoadBalancers === '' || in_array($originIp, explode(',', $whiteListLoadBalancers))
                )
            );

            if ($app->request()->getScheme() == 'https' || $forwardedProtoHttps) {
                if ($app->configService->getSetting('ISSUE_STS', 0) == 1)
                    $app->response()->header('strict-transport-security', 'max-age=' . $app->configService->getSetting('STS_TTL', 600));

            } else {
                // Get the current route pattern
                $resource = $app->router->getCurrentRoute()->getPattern();

                // Allow non-https access to the clock page, otherwise force https
                if ($resource !== '/clock' && $app->configService->getSetting('FORCE_HTTPS', 0) == 1) {
                    $redirect = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                    header("Location: $redirect");
                    $app->halt(302);
                }
            }

            // Check to see if the instance has been suspended, if so call the special route
            if ($app->configService->getSetting('INSTANCE_SUSPENDED') == 1)
                throw new InstanceSuspendedException();

            // Get to see if upgrade is pending
            if (Environment::migrationPending())
                throw new UpgradePendingException();

            // Reset the ETAGs for GZIP
            $requestEtag = $app->request()->headers->get('IF_NONE_MATCH');
            if ($requestEtag) {
                $app->request()->headers->set('IF_NONE_MATCH', str_replace('-gzip', '', $requestEtag));
            }

            // Handle correctly outputting cache headers for AJAX requests
            // IE cache busting
            if ($app->getName() === 'web' && $app->request()->isAjax() && $app->request()->isGet()) {
                $app->response()->headers->set('Cache-control', 'no-cache');
                $app->response()->headers->set('Cache-control', 'no-store');
                $app->response()->headers->set('Pragma', 'no-cache');
                $app->response()->headers->set('Expires', '0');
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
        //$app->logService->debug('Set State');

        // Set the root Uri
        State::setRootUri($app);

        // Set the config dependencies
        $app->configService->setDependencies($app->store, $app->rootUri);

        // Register the date service
        $app->container->singleton('dateService', function() use ($app) {
            if ($app->configService->getSetting('CALENDAR_TYPE') == 'Jalali')
                $date = new \Xibo\Service\DateServiceJalali();
            else
                $date = new \Xibo\Service\DateServiceGregorian();

            $date->setLocale(Translate::GetLocale(2));

            return $date;
        });

        // Register the sanitizer
        $app->container->singleton('sanitizerService', function($container) {
            $sanitizer = new SanitizeService($container->dateService);
            $sanitizer->setRequest($container->request);
            return $sanitizer;
        });

        // Register the event dispatcher
        $app->container->singleton('dispatcher', function($container) {
            return new EventDispatcher();
        });

        // Register the report service
        $app->container->singleton('reportService', function($container) use($app){
            return new ReportService(
                $app,
                $container->state,
                $container->store,
                $container->timeSeriesStore,
                $container->logService,
                $container->configService,
                $container->dateService,
                $container->sanitizerService,
                $container->savedReportFactory
            );
        });

        // Register the image processing service
        $app->container->singleton('imageProcessingService', function($container) {
            $imageProcessingService = new ImageProcessingService();
            $imageProcessingService->setDependencies(
                $container->logService
            );
            return $imageProcessingService;
        });

        // Register Controllers with DI
        self::registerControllersWithDi($app);

        // Register Factories with DI
        self::registerFactoriesWithDi($app->container);

        $app->container->singleton('moduleService', function () use($app) {
            return new ModuleService(
                $app,
                $app->store,
                $app->pool,
                $app->logService,
                $app->configService,
                $app->dateService,
                $app->sanitizerService,
                $app->dispatcher
            );
        });

        // Set some public routes
        $app->publicRoutes = [
            '/login', '/login/forgotten', '/clock', '/about', '/login/ping',
            '/rss/:psk',
            '/sssp_config.xml',
            '/sssp_dl.wgt',
            '/playersoftware/:nonce/sssp_dl.wgt',
            '/playersoftware/:nonce/sssp_config.xml',
            '/tfa'
        ];

        // The state of the application response
        $app->container->singleton('state', function() { return new ApplicationState(); });

        // Setup the translations for gettext
        Translate::InitLocale($app->configService);

        // Default timezone
        date_default_timezone_set($app->configService->getSetting("defaultTimezone"));

        // Configure the cache
        self::configureCache($app->container, $app->configService, $app->logWriter->getWriter());

        // Register the help service
        $app->container->singleton('helpService', function($container) use ($app) {
            return new HelpService(
                $container->store,
                $container->configService,
                $container->pool,
                ($app->router()->getCurrentRoute() !== null) ? $app->router()->getCurrentRoute()->getPattern() : null
            );
        });

        // Create a session
        $app->container->singleton('session', function() use ($app) {
            if ($app->getName() == 'web' || $app->getName() == 'auth')
                return new Session($app->logService);
            else
                return new NullSession();
        });

        // We use Slim::flash() so we must immediately start a session (boo)
        $app->container->session->set('init', '1');

        // App Mode
        $mode = $app->configService->getSetting('SERVER_MODE');
        $app->logService->setMode($mode);

        // Configure logging
        if (Environment::isForceDebugging() || strtolower($mode) == 'test') {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            $app->getLog()->setLevel(\Slim\Log::DEBUG);
        }
        else {

            // Log level
            $level = \Xibo\Service\LogService::resolveLogLevel($app->configService->getSetting('audit'));
            $restingLevel = \Xibo\Service\LogService::resolveLogLevel($app->configService->getSetting('RESTING_LOG_LEVEL'));

            if ($level > $restingLevel) {
                // Do we allow the log level to be this high
                $elevateUntil = $app->configService->getSetting('ELEVATE_LOG_UNTIL');

                if (intval($elevateUntil) < time()) {
                    // Elevation has expired, revert log level
                    $app->configService->changeSetting('audit', $app->configService->getSetting('RESTING_LOG_LEVEL'));

                    $level = $restingLevel;
                }
            }

            $app->getLog()->setLevel($level);
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
     * Set additional middleware
     * @param Slim $app
     */
    public static function setMiddleWare($app)
    {
        // Handle additional Middleware
        if (isset($app->configService->middleware) && is_array($app->configService->middleware)) {
            foreach ($app->configService->middleware as $object) {
                $app->add($object);
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

        if ($configService->getCacheDrivers() != null && is_array($configService->getCacheDrivers())) {
            $drivers = $configService->getCacheDrivers();
        } else {
            // File System Driver
            $realPath = realpath($configService->getSetting('LIBRARY_LOCATION'));
            $cachePath = ($realPath) ? $realPath . '/cache/' : $configService->getSetting('LIBRARY_LOCATION') . 'cache/';

            $drivers[] = new \Stash\Driver\FileSystem(['path' => $cachePath]);
        }

        // Create a composite driver
        $composite = new Composite(['drivers' => $drivers]);

        // Create a pool using this driver set
        $container->singleton('pool', function() use ($logWriter, $composite, $configService) {
            $pool = new Pool($composite);
            $pool->setLogger($logWriter);
            $pool->setNamespace($configService->getCacheNamespace());
            $configService->setPool($pool);
            return $pool;
        });
    }

    /**
     * Register controllers with DI
     * @param Slim $app
     */
    public static function registerControllersWithDi($app)
    {
        $app->container->singleton('\Xibo\Controller\Applications', function($container) {
            return new \Xibo\Controller\Applications(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->session,
                $container->store,
                $container->applicationFactory,
                $container->applicationRedirectUriFactory,
                $container->applicationScopeFactory,
                $container->userFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\AuditLog', function($container) {
            return new \Xibo\Controller\AuditLog(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->auditLogFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Campaign', function($container) {
            return new \Xibo\Controller\Campaign(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->campaignFactory,
                $container->layoutFactory,
                $container->permissionFactory,
                $container->userGroupFactory,
                $container->tagFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Clock', function($container) {
            return new \Xibo\Controller\Clock(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->session
            );
        });

        $app->container->singleton('\Xibo\Controller\Command', function($container) {
            return new \Xibo\Controller\Command(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->commandFactory,
                $container->displayProfileFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\DataSet', function($container) {
            return new \Xibo\Controller\DataSet(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->dataSetFactory,
                $container->dataSetColumnFactory,
                $container->userFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\DataSetColumn', function($container) {
            return new \Xibo\Controller\DataSetColumn(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->dataSetFactory,
                $container->dataSetColumnFactory,
                $container->dataSetColumnTypeFactory,
                $container->dataTypeFactory,
                $container->pool
            );
        });

        $app->container->singleton('\Xibo\Controller\DataSetData', function($container) {
            return new \Xibo\Controller\DataSetData(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->dataSetFactory,
                $container->mediaFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\DataSetRss', function($container) {
            return new \Xibo\Controller\DataSetRss(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->dataSetRssFactory,
                $container->dataSetFactory,
                $container->dataSetColumnFactory,
                $container->pool,
                $container->store
            );
        });

        $app->container->singleton('\Xibo\Controller\DayPart', function($container) {
            return new \Xibo\Controller\DayPart(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->dayPartFactory,
                $container->displayGroupFactory,
                $container->displayFactory,
                $container->layoutFactory,
                $container->mediaFactory,
                $container->scheduleFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Display', function($container) {
            return new \Xibo\Controller\Display(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->store,
                $container->pool,
                $container->playerActionService,
                $container->displayFactory,
                $container->displayGroupFactory,
                $container->logFactory,
                $container->layoutFactory,
                $container->displayProfileFactory,
                $container->mediaFactory,
                $container->scheduleFactory,
                $container->displayEventFactory,
                $container->requiredFileFactory,
                $container->tagFactory,
                $container->notificationFactory,
                $container->userGroupFactory,
                $container->playerVersionFactory,
                $container->dayPartFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\DisplayGroup', function($container) {
            return new \Xibo\Controller\DisplayGroup(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->playerActionService,
                $container->displayFactory,
                $container->displayGroupFactory,
                $container->layoutFactory,
                $container->moduleFactory,
                $container->mediaFactory,
                $container->commandFactory,
                $container->scheduleFactory,
                $container->tagFactory,
                $container->campaignFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\DisplayProfile', function($container) {
            return new \Xibo\Controller\DisplayProfile(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->pool,
                $container->displayProfileFactory,
                $container->commandFactory,
                $container->playerVersionFactory,
                $container->dayPartFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Error', function($container) use ($app) {
            $controller =  new \Xibo\Controller\Error(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService
            );

            return $controller->setApp($app);
        });

        $app->container->singleton('\Xibo\Controller\Fault', function($container) {
            return new \Xibo\Controller\Fault(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->store,
                $container->logFactory,
                $container->displayFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Help', function($container) {
            return new \Xibo\Controller\Help(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->helpFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\IconDashboard', function($container) {
            return new \Xibo\Controller\IconDashboard(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService
            );
        });

        $app->container->singleton('\Xibo\Controller\Layout', function($container) {
            return new \Xibo\Controller\Layout(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->session,
                $container->userFactory,
                $container->resolutionFactory,
                $container->layoutFactory,
                $container->moduleFactory,
                $container->permissionFactory,
                $container->userGroupFactory,
                $container->tagFactory,
                $container->mediaFactory,
                $container->dataSetFactory,
                $container->campaignFactory,
                $container->displayGroupFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Library', function($container) {
            return new \Xibo\Controller\Library(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->store,
                $container->pool,
                $container->dispatcher,
                $container->userFactory,
                $container->moduleFactory,
                $container->tagFactory,
                $container->mediaFactory,
                $container->widgetFactory,
                $container->permissionFactory,
                $container->layoutFactory,
                $container->playlistFactory,
                $container->userGroupFactory,
                $container->displayGroupFactory,
                $container->regionFactory,
                $container->dataSetFactory,
                $container->displayFactory,
                $container->scheduleFactory,
                $container->dayPartFactory,
                $container->playerVersionFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Logging', function($container) {
            return new \Xibo\Controller\Logging(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->store,
                $container->logFactory,
                $container->displayFactory,
                $container->userFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Login', function($container) {
            return new \Xibo\Controller\Login(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->session,
                $container->userFactory,
                $container->pool,
                $container->store
            );
        });

        $app->container->singleton('\Xibo\Controller\Maintenance', function($container) {
            return new \Xibo\Controller\Maintenance(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->store,
                $container->taskFactory,
                $container->mediaFactory,
                $container->layoutFactory,
                $container->widgetFactory,
                $container->displayGroupFactory,
                $container->displayFactory,
                $container->scheduleFactory,
                $container->playerVersionFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\MediaManager', function($container) {
            return new \Xibo\Controller\MediaManager(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->moduleFactory,
                $container->layoutFactory,
                $container->regionFactory,
                $container->playlistFactory,
                $container->widgetFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\PlaylistDashboard', function($container) {
            return new \Xibo\Controller\PlaylistDashboard(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->playlistFactory,
                $container->moduleFactory,
                $container->widgetFactory,
                $container->layoutFactory,
                $container->displayGroupFactory

            );
        });

        $app->container->singleton('\Xibo\Controller\Module', function($container) {
            return new \Xibo\Controller\Module(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->store,
                $container->moduleFactory,
                $container->playlistFactory,
                $container->mediaFactory,
                $container->permissionFactory,
                $container->userGroupFactory,
                $container->widgetFactory,
                $container->transitionFactory,
                $container->regionFactory,
                $container->layoutFactory,
                $container->displayGroupFactory,
                $container->widgetAudioFactory,
                $container->displayFactory,
                $container->scheduleFactory,
                $container->dataSetFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Notification', function($container) {
            return new \Xibo\Controller\Notification(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->notificationFactory,
                $container->userNotificationFactory,
                $container->displayGroupFactory,
                $container->userGroupFactory,
                $container->displayNotifyService
            );
        });

        $app->container->singleton('\Xibo\Controller\PlayerSoftware', function($container) {
            return new \Xibo\Controller\PlayerSoftware(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->pool,
                $container->mediaFactory,
                $container->playerVersionFactory,
                $container->displayProfileFactory,
                $container->moduleFactory,
                $container->layoutFactory,
                $container->widgetFactory,
                $container->displayGroupFactory,
                $container->displayFactory,
                $container->scheduleFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Playlist', function($container) {
            return new \Xibo\Controller\Playlist(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->playlistFactory,
                $container->regionFactory,
                $container->mediaFactory,
                $container->permissionFactory,
                $container->transitionFactory,
                $container->widgetFactory,
                $container->moduleFactory,
                $container->userGroupFactory,
                $container->userFactory,
                $container->tagFactory,
                $container->layoutFactory,
                $container->displayFactory,
                $container->scheduleFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Preview', function($container) {
            return new \Xibo\Controller\Preview(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->layoutFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Region', function($container) {
            return new \Xibo\Controller\Region(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->session,
                $container->regionFactory,
                $container->widgetFactory,
                $container->permissionFactory,
                $container->transitionFactory,
                $container->moduleFactory,
                $container->layoutFactory,
                $container->userGroupFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Report', function($container) {
            return new \Xibo\Controller\Report(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->store,
                $container->timeSeriesStore,
                $container->reportService,
                $container->reportScheduleFactory,
                $container->savedReportFactory,
                $container->mediaFactory,
                $container->userFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Resolution', function($container) {
            return new \Xibo\Controller\Resolution(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->resolutionFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Schedule', function($container) {
            return new \Xibo\Controller\Schedule(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->session,
                $container->pool,
                $container->scheduleFactory,
                $container->displayGroupFactory,
                $container->campaignFactory,
                $container->commandFactory,
                $container->displayFactory,
                $container->layoutFactory,
                $container->mediaFactory,
                $container->dayPartFactory,
                $container->scheduleReminderFactory,
                $container->scheduleExclusionFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Sessions', function($container) {
            return new \Xibo\Controller\Sessions(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->store,
                $container->sessionFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Settings', function($container) {
            return new \Xibo\Controller\Settings(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->layoutFactory,
                $container->userGroupFactory,
                $container->transitionFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Stats', function($container) {
            return new \Xibo\Controller\Stats(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->store,
                $container->timeSeriesStore,
                $container->reportService,
                $container->displayFactory,
                $container->layoutFactory,
                $container->mediaFactory,
                $container->userFactory,
                $container->userGroupFactory,
                $container->displayGroupFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\StatusDashboard', function($container) {
            return new \Xibo\Controller\StatusDashboard(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->store,
                $container->pool,
                $container->userFactory,
                $container->displayFactory,
                $container->displayGroupFactory,
                $container->mediaFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Task', function($container) {
            return new \Xibo\Controller\Task(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->store,
                $container->timeSeriesStore,
                $container->pool,
                $container->taskFactory,
                $container->userFactory,
                $container->userGroupFactory,
                $container->layoutFactory,
                $container->displayFactory,
                $container->mediaFactory,
                $container->notificationFactory,
                $container->userNotificationFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Tag', function($container) {
            return new \Xibo\Controller\Tag(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->store,
                $container->displayGroupFactory,
                $container->layoutFactory,
                $container->tagFactory,
                $container->userFactory,
                $container->displayFactory,
                $container->mediaFactory,
                $container->scheduleFactory,
                $container->campaignFactory,
                $container->playlistFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Template', function($container) {
            return new \Xibo\Controller\Template(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->layoutFactory,
                $container->tagFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\Transition', function($container) {
            return new \Xibo\Controller\Transition(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->transitionFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\User', function($container) {
            return new \Xibo\Controller\User(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->userFactory,
                $container->userTypeFactory,
                $container->userGroupFactory,
                $container->pageFactory,
                $container->permissionFactory,
                $container->layoutFactory,
                $container->applicationFactory,
                $container->campaignFactory,
                $container->mediaFactory,
                $container->scheduleFactory,
                $container->displayFactory,
                $container->sessionFactory,
                $container->displayGroupFactory,
                $container->widgetFactory,
                $container->playerVersionFactory,
                $container->playlistFactory,
                $container->dataSetFactory,
                $container->dayPartFactory
            );
        });

        $app->container->singleton('\Xibo\Controller\UserGroup', function($container) {
            return new \Xibo\Controller\UserGroup(
                $container->logService,
                $container->sanitizerService,
                $container->state,
                $container->user,
                $container->helpService,
                $container->dateService,
                $container->configService,
                $container->userGroupFactory,
                $container->pageFactory,
                $container->permissionFactory,
                $container->userFactory
            );
        });
    }

    /**
     * Register Factories with DI
     * @param Set $container
     */
    public static function registerFactoriesWithDi($container)
    {
        $container->singleton('applicationFactory', function($container) {
            return new \Xibo\Factory\ApplicationFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->applicationRedirectUriFactory,
                $container->applicationScopeFactory
            );
        });

        $container->singleton('applicationRedirectUriFactory', function($container) {
            return new \Xibo\Factory\ApplicationRedirectUriFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('applicationScopeFactory', function($container) {
            return new \Xibo\Factory\ApplicationScopeFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('auditLogFactory', function($container) {
            return new \Xibo\Factory\AuditLogFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('bandwidthFactory', function($container) {
            return new \Xibo\Factory\BandwidthFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('campaignFactory', function($container) {
            return new \Xibo\Factory\CampaignFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory,
                $container->permissionFactory,
                $container->scheduleFactory,
                $container->displayFactory,
                $container->tagFactory
            );
        });

        $container->singleton('commandFactory', function($container) {
            return new \Xibo\Factory\CommandFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory,
                $container->permissionFactory
            );
        });

        $container->singleton('dataSetColumnFactory', function($container) {
            return new \Xibo\Factory\DataSetColumnFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->dataTypeFactory,
                $container->dataSetColumnTypeFactory
            );
        });

        $container->singleton('dataSetColumnTypeFactory', function($container) {
            return new \Xibo\Factory\DataSetColumnTypeFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('dataSetFactory', function($container) {
            return new \Xibo\Factory\DataSetFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory,
                $container->configService,
                $container->pool,
                $container->dataSetColumnFactory,
                $container->permissionFactory,
                $container->displayFactory,
                $container->dateService
            );
        });

        $container->singleton('dataSetRssFactory', function($container) {
            return new \Xibo\Factory\DataSetRssFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory
            );
        });

        $container->singleton('dataTypeFactory', function($container) {
            return new \Xibo\Factory\DataTypeFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('dayPartFactory', function($container) {
            return new \Xibo\Factory\DayPartFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory
            );
        });

        $container->singleton('displayFactory', function($container) {
            return new \Xibo\Factory\DisplayFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory,
                $container->displayNotifyService,
                $container->configService,
                $container->displayGroupFactory,
                $container->displayProfileFactory
            );
        });

        $container->singleton('displayEventFactory', function($container) {
            return new \Xibo\Factory\DisplayEventFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('displayGroupFactory', function($container) {
            return new \Xibo\Factory\DisplayGroupFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory,
                $container->permissionFactory,
                $container->tagFactory
            );
        });

        $container->singleton('displayProfileFactory', function($container) {
            return new \Xibo\Factory\DisplayProfileFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->configService,
                $container->dispatcher,
                $container->commandFactory
            );
        });

        $container->singleton('helpFactory', function($container) {
            return new \Xibo\Factory\HelpFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('layoutFactory', function($container) {
            return new \Xibo\Factory\LayoutFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory,
                $container->configService,
                $container->dateService,
                $container->dispatcher,
                $container->permissionFactory,
                $container->regionFactory,
                $container->tagFactory,
                $container->campaignFactory,
                $container->mediaFactory,
                $container->moduleFactory,
                $container->resolutionFactory,
                $container->widgetFactory,
                $container->widgetOptionFactory,
                $container->playlistFactory,
                $container->widgetAudioFactory
            );
        });

        $container->singleton('logFactory', function($container) {
            return new \Xibo\Factory\LogFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('mediaFactory', function($container) {
            return new \Xibo\Factory\MediaFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory,
                $container->configService,
                $container->permissionFactory,
                $container->tagFactory,
                $container->playlistFactory
            );
        });

        $container->singleton('moduleFactory', function($container) {
            return new \Xibo\Factory\ModuleFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory,
                $container->moduleService,
                $container->widgetFactory,
                $container->regionFactory,
                $container->playlistFactory,
                $container->mediaFactory,
                $container->dataSetFactory,
                $container->dataSetColumnFactory,
                $container->transitionFactory,
                $container->displayFactory,
                $container->commandFactory,
                $container->scheduleFactory,
                $container->permissionFactory,
                $container->userGroupFactory
            );
        });

        $container->singleton('notificationFactory', function($container) {
            return new \Xibo\Factory\NotificationFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory,
                $container->userGroupFactory,
                $container->displayGroupFactory
            );
        });

        $container->singleton('pageFactory', function($container) {
            return new \Xibo\Factory\PageFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('permissionFactory', function($container) {
            return new \Xibo\Factory\PermissionFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('playerVersionFactory', function($container) {
            return new \Xibo\Factory\PlayerVersionFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory,
                $container->configService,
                $container->mediaFactory
            );
        });

        $container->singleton('playlistFactory', function($container) {
            return new \Xibo\Factory\PlaylistFactory(
                $container->store,
                $container->logService,
                $container->configService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory,
                $container->dateService,
                $container->permissionFactory,
                $container->widgetFactory,
                $container->tagFactory
            );
        });

        $container->singleton('regionFactory', function($container) {
            return new \Xibo\Factory\RegionFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->dateService,
                $container->permissionFactory,
                $container->regionOptionFactory,
                $container->playlistFactory
            );
        });

        $container->singleton('regionOptionFactory', function($container) {
            return new \Xibo\Factory\RegionOptionFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('requiredFileFactory', function($container) {
            return new \Xibo\Factory\RequiredFileFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('reportScheduleFactory', function($container) {
            return new \Xibo\Factory\ReportScheduleFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory,
                $container->configService,
                $container->pool,
                $container->dateService
            );
        });

        $container->singleton('resolutionFactory', function($container) {
            return new \Xibo\Factory\ResolutionFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('savedReportFactory', function($container) {
            return new \Xibo\Factory\SavedReportFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory,
                $container->configService,
                $container->mediaFactory
            );
        });

        $container->singleton('scheduleFactory', function($container) {
            return new \Xibo\Factory\ScheduleFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->configService,
                $container->pool,
                $container->dateService,
                $container->displayGroupFactory,
                $container->dayPartFactory,
                $container->userFactory,
                $container->scheduleReminderFactory,
                $container->scheduleExclusionFactory
            );
        });

        $container->singleton('scheduleReminderFactory', function($container) {
            return new \Xibo\Factory\ScheduleReminderFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory,
                $container->configService
            );
        });

        $container->singleton('scheduleExclusionFactory', function($container) {
            return new \Xibo\Factory\ScheduleExclusionFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('sessionFactory', function($container) {
            return new \Xibo\Factory\SessionFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->dateService
            );
        });

        $container->singleton('tagFactory', function($container) {
            return new \Xibo\Factory\TagFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('taskFactory', function($container) {
            return new \Xibo\Factory\TaskFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('transitionFactory', function($container) {
            return new \Xibo\Factory\TransitionFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('userFactory', function($container) {
            return new \Xibo\Factory\UserFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->configService,
                $container->permissionFactory,
                $container->userOptionFactory,
                $container->applicationScopeFactory
            );
        });

        $container->singleton('userGroupFactory', function($container) {
            return new \Xibo\Factory\UserGroupFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory
            );
        });

        $container->singleton('userNotificationFactory', function($container) {
            return new \Xibo\Factory\UserNotificationFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory
            );
        });

        $container->singleton('userOptionFactory', function($container) {
            return new \Xibo\Factory\UserOptionFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('userTypeFactory', function($container) {
            return new \Xibo\Factory\UserTypeFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('widgetFactory', function($container) {
            return new \Xibo\Factory\WidgetFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService,
                $container->user,
                $container->userFactory,
                $container->dateService,
                $container->widgetOptionFactory,
                $container->widgetMediaFactory,
                $container->widgetAudioFactory,
                $container->permissionFactory,
                $container->displayFactory
            );
        });

        $container->singleton('widgetMediaFactory', function($container) {
            return new \Xibo\Factory\WidgetMediaFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('widgetAudioFactory', function($container) {
            return new \Xibo\Factory\WidgetAudioFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });

        $container->singleton('widgetOptionFactory', function($container) {
            return new \Xibo\Factory\WidgetOptionFactory(
                $container->store,
                $container->logService,
                $container->sanitizerService
            );
        });
    }
}