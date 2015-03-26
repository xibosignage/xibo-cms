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


use Slim\Middleware;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Log;
use Xibo\Helper\Theme;

class State extends Middleware
{
    public function call()
    {
        // Setup the translations for gettext
        \Xibo\Helper\Translate::InitLocale();

        // Inject
        // The state of the application response
        $this->app->container->singleton('state', function() { return new ApplicationState(); });

        // Create a session
        $this->app->container->singleton('session', function() { return new \Xibo\Helper\Session(); });
        $this->app->session->Get('nothing');

        // Configure the timezone information
        date_default_timezone_set(\Xibo\Helper\Config::GetSetting("defaultTimezone"));

        // Do we need SSL/STS?
        // Deal with HTTPS/STS config
        if (\Kit::isSSL()) {
            \Kit::IssueStsHeaderIfNecessary();
        }
        else {
            if (\Xibo\Helper\Config::GetSetting('FORCE_HTTPS', 0) == 1) {
                $redirect = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                header("Location: $redirect");
                exit();
            }
        }

        // Configure logging
        if (strtolower($this->app->getMode()) == 'test') {
            $this->app->config('debug', true);
            $this->app->config('log.level', \Slim\Log::DEBUG);
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        }
        else {
            // TODO: Use the log levels defined in the config
            $this->app->config('log.level', \Slim\Log::ERROR);
            error_reporting(0);
        }

        $app = $this->app;

        // Attach a hook to log the route
        $this->app->hook('slim.before.dispatch', function() use ($app) {
            // Configure some things in the theme
            // Set the form method based on the route
            // this is a bit of fluff really, as this is just a naming convention
            // all forms are requested with the route: /controller/form
            $pattern = explode('/', $this->app->router->getCurrentRoute()->getPattern());
            switch ($pattern[count($pattern) - 1]) {

                case 'add':
                    Theme::Set('form_method', 'put');
                    break;

                case 'delete':
                    Theme::Set('form_method', 'delete');
                    break;

                default:
                    Theme::Set('form_method', 'post');
            }

            Log::debug('called %s', $this->app->router->getCurrentRoute()->getPattern());
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