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
use Xibo\Factory\ModuleFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\ByteFormatter;
use Xibo\Helper\Config;
use Xibo\Helper\NullSession;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;
use Xibo\Helper\Translate;

class State extends Middleware
{
    public function call()
    {
        $app = $this->app;

        // Setup the translations for gettext
        Translate::InitLocale();

        // Configure the locale for date/time
        Date::setLocale(Translate::GetLocale(2));

        // Inject
        // The state of the application response
        $this->app->container->singleton('state', function() { return new ApplicationState(); });

        // Create a session
        $this->app->container->singleton('session', function() use ($app) {
            if ($app->getName() == 'web' || $app->getName() == 'auth')
                return new Session();
            else
                return new NullSession();
        });
        $this->app->session->get('nothing');

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
        if (strtolower($this->app->getMode()) == 'test') {
            $this->app->config('log.level', \Slim\Log::DEBUG);
        }
        else {
            // TODO: Use the log levels defined in the config
            $this->app->config('log.level', \Slim\Log::ERROR);
        }

        // Attach a hook to log the route
        $this->app->hook('slim.before.dispatch', function() use ($app) {

            $settings = [];
            foreach (Config::GetAll() as $setting) {
                $settings[$setting['setting']] = $setting['value'];
            }

            // Configure some things in the theme
            if ($app->getName() == 'web') {
                $app->view()->appendData(array(
                    'baseUrl' => $app->urlFor('home'),
                    'route' => $app->router()->getCurrentRoute()->getName(),
                    'theme' => Theme::getInstance(),
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
}