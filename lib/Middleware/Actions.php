<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Actions.php) is part of Xibo.
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
use Xibo\Controller\Library;
use Xibo\Factory\LayoutFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Log;
use Xibo\Helper\Theme;
use Xibo\Helper\Translate;

class Actions extends Middleware
{
    public function call()
    {
        $app = $this->app;

        // Process notifications
        // Attach a hook to log the route
        $app->hook('slim.before.dispatch', function() use ($app) {

            // Process Actions
            if (!Config::isUpgradePending() && Config::GetSetting('DEFAULTS_IMPORTED') == 0) {

                $folder = Theme::uri('layouts', true);

                foreach (array_diff(scandir($folder), array('..', '.')) as $file) {
                    if (stripos($file, '.zip')) {
                        $layout = LayoutFactory::createFromZip($folder . '/' . $file, null, 1, false, false, true);
                        $layout->save([
                            'audit' => false
                        ]);
                    }
                }

                // Install files
                Library::installAllModuleFiles();

                Config::ChangeSetting('DEFAULTS_IMPORTED', 1);
            }

            // Handle if we are an upgrade
            // Get the current route pattern
            $resource = $app->router->getCurrentRoute()->getPattern();

            // Get an array of excluded routes
            $excludedRoutes = array_merge($app->publicRoutes, ['/update', '/update/step/:id'], $this->app->excludedCsrfRoutes);

            // Does the version in the DB match the version of the code?
            // If not then we need to run an upgrade.
            if (Config::isUpgradePending() && !in_array($resource, $excludedRoutes)) {
                Log::debug('%s not in excluded routes, redirecting. ', $resource);
                $app->redirectTo('upgrade.view');
            }

            $notifications = [];

            if ($app->user->userTypeId == 1 && file_exists(PROJECT_ROOT . '/web/install/index.php')) {
                Log::notice('Install.php exists and shouldn\'t');

                $notifications[] = __('There is a problem with this installation. "install.php" should be deleted.');
            }

            // Language match?
            if (Translate::getRequestedLanguage() != Translate::GetLocale()) {
                $notifications[] = __('Your requested language %s could not be loaded.', Translate::getRequestedLanguage());
            }

            $app->view()->appendData(['notifications' => $notifications]);
        });

        $this->next->call();
    }
}