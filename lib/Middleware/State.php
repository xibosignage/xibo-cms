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
use Xibo\Exception\InstanceSuspendedException;
use Xibo\Factory\ModuleFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\Config;
use Xibo\Helper\NullSession;
use Xibo\Helper\Session;
use Xibo\Helper\Translate;

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
                        'jsLocale' => Translate::GetJsLocale(),
                        'jsShortLocale' => ((strlen(Translate::GetJsLocale()) > 2) ? substr(Translate::GetJsLocale(), 0, 2) : Translate::GetJsLocale()),
                        'calendarLanguage' => ((strlen(Translate::GetJsLocale()) <= 2) ? Translate::GetJsLocale() . '-' . strtoupper(Translate::GetJsLocale()) : Translate::GetJsLocale())
                    ],
                    'translations' => '{}',
                    'libraryUpload' => [
                        'maxSize' => ByteFormatter::toBytes(Config::getMaxUploadSize()),
                        'maxSizeMessage' => sprintf(__('This form accepts files up to a maximum size of %s'), Config::getMaxUploadSize()),
                        'validExt' => implode('|', ModuleFactory::getValidExtensions())
                    ]
                ));
            }

            // Check to see if the instance has been suspended, if so call the special route
            if (Config::GetSetting('INSTANCE_SUSPENDED') == 1)
                throw new InstanceSuspendedException();

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

        // Create a session
        $app->container->singleton('session', function() use ($app) {
            if ($app->getName() == 'web' || $app->getName() == 'auth')
                return new Session();
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
            \Xibo\Helper\Log::debug('Configuring %d additional log handlers from Config', count(Config::$logHandlers));
            foreach (Config::$logHandlers as $handler) {
                $app->logWriter->addHandler($handler);
            }
        }

        // Configure any extra log processors
        if (Config::$logProcessors != null && is_array(Config::$logProcessors)) {
            \Xibo\Helper\Log::debug('Configuring %d additional log processors from Config', count(Config::$logProcessors));
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
}