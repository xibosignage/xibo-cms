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

use Jenssegers\Date\Date;
use Slim\Middleware;
use Slim\Slim;
use Stash\Driver\Composite;
use Stash\Driver\Ephemeral;
use Stash\Driver\FileSystem;
use Stash\Pool;
use Xibo\Exception\InstanceSuspendedException;
use Xibo\Exception\UpgradePendingException;
use Xibo\Factory\ModuleFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\Config;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\NullSession;
use Xibo\Helper\Session;
use Xibo\Helper\Translate;
use Xibo\Storage\PDOConnect;

class State extends Middleware
{
    public function call()
    {
        $app = $this->app;

        // Set state
        State::setState($app);

        // Attach a hook to log the route
        $this->app->hook('slim.before.dispatch', function() use ($app) {

            $settings = [];
            foreach (Config::GetAll() as $setting) {
                $settings[$setting['setting']] = $setting['value'];
            }

            // Date format
            $settings['DATE_FORMAT_JS'] = \Xibo\Helper\Date::convertPhpToMomentFormat($settings['DATE_FORMAT']);
            $settings['DATE_FORMAT_BOOTSTRAP'] = \Xibo\Helper\Date::convertPhpToBootstrapFormat($settings['DATE_FORMAT']);

            // Configure some things in the theme
            if ($app->getName() == 'web') {
                $app->view()->appendData(array(
                    'baseUrl' => $app->urlFor('home'),
                    'route' => $app->router()->getCurrentRoute()->getName(),
                    'theme' => \Xibo\Helper\Theme::getInstance(),
                    'settings' => $settings,
                    'translate' => [
                        'locale' => Translate::GetLocale(),
                        'jsLocale' => Translate::GetJsLocale(),
                        'jsShortLocale' => ((strlen(Translate::GetJsLocale()) > 2) ? substr(Translate::GetJsLocale(), 0, 2) : Translate::GetJsLocale()),
                        'calendarLanguage' => ((strlen(Translate::GetJsLocale()) <= 2) ? Translate::GetJsLocale() . '-' . strtoupper(Translate::GetJsLocale()) : Translate::GetJsLocale())
                    ],
                    'translations' => '{}',
                    'libraryUpload' => [
                        'maxSize' => ByteFormatter::toBytes(Config::getMaxUploadSize()),
                        'maxSizeMessage' => sprintf(__('This form accepts files up to a maximum size of %s'), Config::getMaxUploadSize()),
                        'validExt' => implode('|', (new ModuleFactory($app))->getValidExtensions())
                    ]
                ));
            }

            // Check to see if the instance has been suspended, if so call the special route
            if (Config::GetSetting('INSTANCE_SUSPENDED') == 1)
                throw new InstanceSuspendedException();

            // Get to see if upgrade is pending
            if (Config::isUpgradePending() && $app->getName() != 'web')
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
        // Register the log service
        $app->container->singleton('logHelper', function() use ($app) {
            return new Log($app->getLog(), $app->getMode());
        });

        // Register the database service
        $app->container->singleton('store', function() use ($app) {
            return new PDOConnect($app->logHelper);
        });

        // Register the help service
        $app->container->singleton('helpService', function() use ($app) {
            return new Help($app);
        });

        // Register Controllers with DI
        self::registerControllersWithDi($app);

        // Register Factories with DI
        self::registerFactoriesWithDi($app);

        // Set some public routes
        $app->publicRoutes = array('/login', '/logout', '/clock', '/about', '/login/ping');

        // Setup the translations for gettext
        Translate::InitLocale();

        // Configure the locale for date/time
        if (Translate::GetLocale(2) != '')
            Date::setLocale(Translate::GetLocale(2));

        // Inject
        // The state of the application response
        $app->container->singleton('state', function() { return new ApplicationState(); });

        // Configure the cache
        self::configureCache($app);

        // Create a session
        $app->container->singleton('session', function() use ($app) {
            if ($app->getName() == 'web' || $app->getName() == 'auth')
                return new Session($app->logHelper);
            else
                return new NullSession();
        });
        $app->session->get('nothing');

        // Do we need SSL/STS?
        // Deal with HTTPS/STS config
        if ($app->request()->getScheme() == 'https') {
            if (Config::GetSetting('ISSUE_STS', 0) == 1)
                $app->response()->header('strict-transport-security', 'max-age=' . Config::GetSetting('STS_TTL', 600));
        }
        else {
            if (Config::GetSetting('FORCE_HTTPS', 0) == 1) {
                $redirect = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                header("Location: $redirect");
                $app->halt(302);
            }
        }

        // Configure logging
        if (strtolower($app->getMode()) == 'test') {
            $app->config('log.level', \Slim\Log::DEBUG);
        }
        else {
            $app->config('log.level', \Xibo\Helper\Log::resolveLogLevel(Config::GetSetting('audit', 'error')));
        }

        // Configure any extra log handlers
        if (Config::$logHandlers != null && is_array(Config::$logHandlers)) {
            $app->logHelper->debug('Configuring %d additional log handlers from Config', count(Config::$logHandlers));
            foreach (Config::$logHandlers as $handler) {
                $app->logWriter->addHandler($handler);
            }
        }

        // Configure any extra log processors
        if (Config::$logProcessors != null && is_array(Config::$logProcessors)) {
            $app->logHelper->debug('Configuring %d additional log processors from Config', count(Config::$logProcessors));
            foreach (Config::$logProcessors as $processor) {
                $app->logWriter->addProcessor($processor);
            }
        }

        State::setRootUri($app);
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
     * @param Slim $app
     */
    public static function configureCache($app)
    {
        $drivers = [];

        if (Config::$cacheDrivers != null && is_array(Config::$cacheDrivers)) {
            $drivers = Config::$cacheDrivers;
        } else {
            // File System Driver
            $drivers[] = new FileSystem(['path' => Config::GetSetting('LIBRARY_LOCATION') . 'cache/']);
        }

        // Always add the Ephemeral driver
        $drivers[] = new Ephemeral();

        // Create a composite driver
        $composite = new Composite(['drivers' => $drivers]);

        // Create a pool using this driver set
        $app->container->singleton('pool', function() use ($app, $composite) {
            $pool = new Pool($composite);
            $pool->setLogger($app->logWriter->getWriter());
            $pool->setNamespace('Xibo');
            return $pool;
        });

        // Pass the pool into the config object
        Config::setPool($app->pool);
    }

    /**
     * Register controllers with DI
     * @param Slim $app
     */
    public static function registerControllersWithDi($app)
    {
        $app->container->singleton('\Xibo\Controller\Applications', function($container) {
            return new \Xibo\Controller\Applications();
        });

        $app->container->singleton('\Xibo\Controller\Campaign', function($container) {
            return new \Xibo\Controller\Campaign();
        });

        $app->container->singleton('\Xibo\Controller\Clock', function($container) {
            return new \Xibo\Controller\Clock();
        });

        $app->container->singleton('\Xibo\Controller\Command', function($container) {
            return new \Xibo\Controller\Command();
        });

        $app->container->singleton('\Xibo\Controller\DataSet', function($container) {
            return new \Xibo\Controller\DataSet();
        });

        $app->container->singleton('\Xibo\Controller\DataSetColumn', function($container) {
            return new \Xibo\Controller\DataSetColumn();
        });

        $app->container->singleton('\Xibo\Controller\DataSetData', function($container) {
            return new \Xibo\Controller\DataSetData();
        });

        $app->container->singleton('\Xibo\Controller\Display', function($container) {
            return new \Xibo\Controller\Display();
        });

        $app->container->singleton('\Xibo\Controller\DisplayGroup', function($container) {
            return new \Xibo\Controller\DisplayGroup();
        });

        $app->container->singleton('\Xibo\Controller\DisplayProfile', function($container) {
            return new \Xibo\Controller\DisplayProfile();
        });

        $app->container->singleton('\Xibo\Controller\Error', function($container) {
            return new \Xibo\Controller\Error();
        });

        $app->container->singleton('\Xibo\Controller\Fault', function($container) {
            return new \Xibo\Controller\Fault();
        });

        $app->container->singleton('\Xibo\Controller\Help', function($container) {
            return new \Xibo\Controller\Help();
        });

        $app->container->singleton('\Xibo\Controller\IconDashboard', function($container) {
            return new \Xibo\Controller\IconDashboard();
        });

        $app->container->singleton('\Xibo\Controller\Layout', function($container) {
            return new \Xibo\Controller\Layout();
        });

        $app->container->singleton('\Xibo\Controller\Library', function($container) {
            return new \Xibo\Controller\Library();
        });

        $app->container->singleton('\Xibo\Controller\Logging', function($container) {
            return new \Xibo\Controller\Logging();
        });

        $app->container->singleton('\Xibo\Controller\Login', function($container) {
            return new \Xibo\Controller\Login();
        });

        $app->container->singleton('\Xibo\Controller\Maintenance', function($container) {
            return new \Xibo\Controller\Maintenance();
        });

        $app->container->singleton('\Xibo\Controller\MediaManager', function($container) {
            return new \Xibo\Controller\MediaManager();
        });

        $app->container->singleton('\Xibo\Controller\Module', function($container) {
            return new \Xibo\Controller\Module();
        });

        $app->container->singleton('\Xibo\Controller\Playlist', function($container) {
            return new \Xibo\Controller\Playlist();
        });

        $app->container->singleton('\Xibo\Controller\Preview', function($container) {
            return new \Xibo\Controller\Preview();
        });

        $app->container->singleton('\Xibo\Controller\Region', function($container) {
            return new \Xibo\Controller\Region();
        });

        $app->container->singleton('\Xibo\Controller\Resolution', function($container) {
            return new \Xibo\Controller\Resolution();
        });

        $app->container->singleton('\Xibo\Controller\Schedule', function($container) {
            return new \Xibo\Controller\Schedule();
        });

        $app->container->singleton('\Xibo\Controller\Sessions', function($container) {
            return new \Xibo\Controller\Sessions();
        });

        $app->container->singleton('\Xibo\Controller\Settings', function($container) {
            return new \Xibo\Controller\Settings();
        });

        $app->container->singleton('\Xibo\Controller\Stats', function($container) {
            return new \Xibo\Controller\Stats();
        });

        $app->container->singleton('\Xibo\Controller\StatusDashboard', function($container) {
            return new \Xibo\Controller\StatusDashboard();
        });

        $app->container->singleton('\Xibo\Controller\Template', function($container) {
            return new \Xibo\Controller\Template();
        });

        $app->container->singleton('\Xibo\Controller\Transition', function($container) {
            return new \Xibo\Controller\Transition();
        });

        $app->container->singleton('\Xibo\Controller\Upgrade', function($container) {
            return new \Xibo\Controller\Upgrade();
        });

        $app->container->singleton('\Xibo\Controller\User', function($container) {
            return new \Xibo\Controller\User();
        });

        $app->container->singleton('\Xibo\Controller\UserGroup', function($container) {
            return new \Xibo\Controller\UserGroup();
        });
    }

    /**
     * Register Factories with DI
     * @param Slim $app
     */
    public static function registerFactoriesWithDi($app)
    {
        // TODO
    }
}