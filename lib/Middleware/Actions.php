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
use Xibo\Entity\UserNotification;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\UserNotificationFactory;
use Xibo\Helper\Environment;
use Xibo\Helper\Translate;

/**
 * Class Actions
 * Web Actions
 * @package Xibo\Middleware
 */
class Actions extends Middleware
{
    public function call()
    {
        $app = $this->app;

        // Process notifications
        // Attach a hook to log the route
        $app->hook('slim.before.dispatch', function() use ($app) {

            // Process Actions
            if (!Environment::migrationPending() && $app->configService->GetSetting('DEFAULTS_IMPORTED') == 0) {

                $folder = PROJECT_ROOT . '/web/' . $app->configService->uri('layouts', true);

                foreach (array_diff(scandir($folder), array('..', '.')) as $file) {
                    if (stripos($file, '.zip')) {
                        try {
                            /** @var \Xibo\Entity\Layout $layout */
                            $layout = $app->layoutFactory->createFromZip($folder . '/' . $file, null, $app->container->get('userFactory')->getSystemUser()->getId(), false, false, true, false, true, $app->container->get('\Xibo\Controller\Library')->setApp($app));
                            $layout->save([
                                'audit' => false
                            ]);
                        } catch (\Exception $e) {
                            $app->logService->error('Unable to import layout: ' . $file . '. E = ' . $e->getMessage());
                            $app->logService->debug($e->getTraceAsString());
                        }
                    }
                }

                // Layouts imported
                $app->configService->ChangeSetting('DEFAULTS_IMPORTED', 1);

                // Install files
                $app->container->get('\Xibo\Controller\Library')->installAllModuleFiles();
            }

            // Handle if we are an upgrade
            // Get the current route pattern
            $resource = $app->router->getCurrentRoute()->getPattern();

            // Get an array of excluded routes
            if (is_array($this->app->excludedCsrfRoutes))
                $excludedRoutes = array_merge($app->publicRoutes, ['/update', '/update/step/:id'], $this->app->excludedCsrfRoutes);
            else
                $excludedRoutes = array_merge($app->publicRoutes, ['/update', '/update/step/:id']);

            // Does the version in the DB match the version of the code?
            // If not then we need to run an upgrade.
            if (Environment::migrationPending() && !in_array($resource, $excludedRoutes)) {
                $app->logService->debug('%s not in excluded routes, redirecting. ', $resource);
                $app->redirectTo('upgrade.view');
                return;
            }

            // Do not proceed unless we have completed an upgrade
            if (Environment::migrationPending())
                return;

            // Only process notifications if we are a full request
            if (!$app->request()->isAjax()) {
                try {
                    $app->user->routeAuthentication('/drawer');

                    // Notifications
                    $notifications = [];
                    $extraNotifications = 0;

                    /** @var UserNotificationFactory $factory */
                    $factory = $app->userNotificationFactory;

                    if ($app->user->userTypeId == 1 && file_exists(PROJECT_ROOT . '/web/install/index.php')) {
                        $app->logService->notice('Install.php exists and shouldn\'t');

                        $notifications[] = $factory->create(__('There is a problem with this installation. "install.php" should be deleted.'));
                        $extraNotifications++;

                        // Test for web in the URL.
                        $url = $app->request()->getUrl() . $app->request()->getPathInfo();

                        if (!Environment::checkUrl($url)) {
                            $app->logService->notice('Suspicious URL detected - it is very unlikely that /web/ should be in the URL. URL is ' . $url);

                            $notifications[] = $factory->create(__('CMS configuration warning, it is very unlikely that /web/ should be in the URL. This usually means that the DocumentRoot of the web server is wrong and may put your CMS at risk if not corrected.'));
                            $extraNotifications++;
                        }
                    }

                    // Language match?
                    if (Translate::getRequestedLanguage() != Translate::GetLocale()) {
                        $notifications[] = $factory->create(__('Your requested language %s could not be loaded.', Translate::getRequestedLanguage()));
                        $extraNotifications++;
                    }

                    // User notifications
                    $notifications = array_merge($notifications, $factory->getMine());

                    // If we aren't already in a notification interrupt, then check to see if we should be
                    if ($resource != '/drawer/notification/interrupt/:id' && !$app->request()->isAjax()) {
                        foreach ($notifications as $notification) {
                            /** @var UserNotification $notification */
                            if ($notification->isInterrupt == 1 && $notification->read == 0) {
                                $app->flash('interruptedUrl', $app->request()->getResourceUri());
                                $app->redirectTo('notification.interrupt', ['id' => $notification->notificationId]);
                            }
                        }
                    }

                    $app->view()->appendData(['notifications' => $notifications, 'notificationCount' => $factory->countMyUnread() + $extraNotifications]);
                } catch (AccessDeniedException $e) {
                    // Drawer not available
                }
            }
        });

        $this->next->call();
    }
}