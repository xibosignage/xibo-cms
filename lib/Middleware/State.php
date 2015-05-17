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
use Xibo\Helper\Config;
use Xibo\Helper\Log;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;
use Xibo\Helper\Translate;

class State extends Middleware
{
    public function call()
    {
        // Setup the translations for gettext
        Translate::InitLocale();

        // Inject
        // The state of the application response
        $this->app->container->singleton('state', function() { return new ApplicationState(); });

        // Create a session
        $this->app->container->singleton('session', function() { return new Session(); });
        $this->app->session->Get('nothing');

        // Configure the timezone information
        date_default_timezone_set(Config::GetSetting("defaultTimezone"));

        // Do we need SSL/STS?
        // Deal with HTTPS/STS config
        if (\Kit::isSSL()) {
            \Kit::IssueStsHeaderIfNecessary();
        }
        else {
            if (Config::GetSetting('FORCE_HTTPS', 0) == 1) {
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
            // Translations we want always available
            Theme::SetTranslation('multiselect', Theme::Translate('Multiple Items Selected'));
            Theme::SetTranslation('multiselectNoItemsMessage', Theme::Translate('Sorry, no items have been selected.'));
            Theme::SetTranslation('multiselectMessage', Theme::Translate('Caution, you have selected %1 items. Clicking save will run the %2 transaction on all these items.'));
            Theme::SetTranslation('save', Theme::Translate('Save'));
            Theme::SetTranslation('cancel', Theme::Translate('Cancel'));
            Theme::SetTranslation('close', Theme::Translate('Close'));
            Theme::SetTranslation('success', Theme::Translate('Success'));
            Theme::SetTranslation('failure', Theme::Translate('Failure'));
            Theme::SetTranslation('enterText', Theme::Translate('Enter text...'));

            // Configure some things in the theme
            $app->view()->appendData(array(
                'baseUrl' => rtrim(str_replace('index.php', '', $app->request()->getRootUri()), '/') . '/',
                'route' => $app->router()->getCurrentRoute(),
                'theme' => Theme::GetConfig(),
                'settings' => Config::GetAll(),
                'translate' => [
                    'translations' => ((Theme::Get('translations') == '') ? '{}' : Theme::Get('translations')),
                    'jsLocale' => Translate::GetJsLocale(),
                    'calendarLanguage' => ((strlen(Translate::GetJsLocale() <= 2)) ? Translate::GetJsLocale() . '-' . strtoupper(Translate::GetJsLocale()) : Translate::GetJsLocale())
                ]
            ));

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