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
            if (!Environment::migrationPending() && $app->configService->getSetting('DEFAULTS_IMPORTED') == 0) {

                $folder = $app->configService->uri('layouts', true);

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
                $app->configService->changeSetting('DEFAULTS_IMPORTED', 1);

                // Install files
                $app->container->get('\Xibo\Controller\Library')->installAllModuleFiles();
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

                    // Is the CMS Docker stack in DEV mode? (this will be true for dev and test)
                    if (Environment::isDevMode()) {
                        $notifications[] = $factory->create('CMS IN DEV MODE');
                        $extraNotifications++;
                    } else {
                        // We're not in DEV mode and therefore install/index.php shouldn't be there.
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
                    }

                    // Language match?
                    if (Translate::getRequestedLanguage() != Translate::GetLocale()) {
                        $notifications[] = $factory->create(__('Your requested language %s could not be loaded.', Translate::getRequestedLanguage()));
                        $extraNotifications++;
                    }

                    // User notifications
                    $notifications = array_merge($notifications, $factory->getMine());

                    // Get the current route pattern
                    $resource = $app->router->getCurrentRoute()->getPattern();

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

            $resource = $app->router->getCurrentRoute()->getPattern();
            if (!$app->request()->isAjax() && $app->user->isPasswordChangeRequired == 1 && $resource != '/user/page/password') {
                $app->redirectTo('user.force.change.password.page');
            }
        });

        $this->next->call();
    }
}