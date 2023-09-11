<?php
/**
 * Copyright (C) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
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

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App as App;
use Slim\Routing\RouteContext;
use Xibo\Entity\User;
use Xibo\Entity\UserNotification;
use Xibo\Factory\UserNotificationFactory;
use Xibo\Helper\Environment;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Actions
 * Web Actions
 * @package Xibo\Middleware
 */
class Actions implements Middleware
{
    /* @var App $app */
    private $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $app = $this->app;
        $container = $app->getContainer();

        // Get the current route pattern
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $resource = $route->getPattern();
        $routeParser = $app->getRouteCollector()->getRouteParser();

        // Do we have a user set?
        /** @var User $user */
        $user = $container->get('user');

        // Import the default layout, if we're a super admin (and we're logged in)
        // TODO: consider if we can remove this entirely in v4.
        if (!Environment::migrationPending()
            && $container->get('configService')->getSetting('DEFAULTS_IMPORTED') == 0
            && $user->isSuperAdmin()
        ) {
            $folder = $container->get('configService')->uri('layouts', true);

            foreach (array_diff(scandir($folder), array('..', '.')) as $file) {
                if (stripos($file, '.zip')) {
                    try {
                        /** @var \Xibo\Entity\Layout $layout */
                        $layout = $container->get('layoutFactory')->createFromZip(
                            $folder . '/' . $file,
                            null,
                            $user->getId(),
                            false,
                            false,
                            true,
                            false,
                            true,
                            $container->get('dataSetFactory'),
                            null,
                            $routeContext->getRouteParser(),
                            $container->get('mediaService'),
                            1
                        );
                        $layout->save([
                            'audit' => false,
                            'import' => true
                        ]);

                        try {
                            $container->get('layoutFactory')->getById($container->get('configService')->getSetting('DEFAULT_LAYOUT'));
                        } catch (NotFoundException $exception) {
                            $container->get('configService')->changeSetting('DEFAULT_LAYOUT', $layout->layoutId);
                        }
                    } catch (\Exception $e) {
                        $container->get('logService')->error('Unable to import layout: ' . $file . '. E = ' . $e->getMessage());
                        $container->get('logService')->debug($e->getTraceAsString());
                    }
                }
            }

            // Layouts imported
            $container->get('configService')->changeSetting('DEFAULTS_IMPORTED', 1);

            // Install files
            $container->get('\Xibo\Controller\Module')->installAllModuleFiles();
        }

        // Do not proceed unless we have completed an upgrade
        if (Environment::migrationPending()) {
            return $handler->handle($request);
        }

        // Only process notifications if we are a full request
        if (!$this->isAjax($request)) {
            if ($user->userId != null
                && $container->get('session')->isExpired() == 0
                && $user->featureEnabled('drawer')
            ) {
                // Notifications
                $notifications = [];
                $extraNotifications = 0;

                /** @var UserNotificationFactory $factory */
                $factory = $container->get('userNotificationFactory');

                // Is the CMS Docker stack in DEV mode? (this will be true for dev and test)
                if (Environment::isDevMode()) {
                    $notifications[] = $factory->create('CMS IN DEV MODE');
                    $extraNotifications++;
                } else {
                    // We're not in DEV mode and therefore install/index.php shouldn't be there.
                    if ($user->userTypeId == 1 && file_exists(PROJECT_ROOT . '/web/install/index.php')) {
                        $container->get('logger')->notice('Install.php exists and shouldn\'t');

                        $notifications[] = $factory->create(__('There is a problem with this installation. "install.php" should be deleted.'));
                        $extraNotifications++;

                        // Test for web in the URL.
                        $url = $request->getUri();

                        if (!Environment::checkUrl($url)) {
                            $container->get('logger')->notice('Suspicious URL detected - it is very unlikely that /web/ should be in the URL. URL is ' . $url);

                            $notifications[] = $factory->create(__('CMS configuration warning, it is very unlikely that /web/ should be in the URL. This usually means that the DocumentRoot of the web server is wrong and may put your CMS at risk if not corrected.'));
                            $extraNotifications++;
                        }
                    }
                }

                // User notifications
                $notifications = array_merge($notifications, $factory->getMine());
                // If we aren't already in a notification interrupt, then check to see if we should be
                if ($resource != '/drawer/notification/interrupt/{id}' && !$this->isAjax($request) && $container->get('session')->isExpired() != 1) {
                    foreach ($notifications as $notification) {
                        /** @var UserNotification $notification */
                        if ($notification->isInterrupt == 1 && $notification->read == 0) {
                            $container->get('flash')->addMessage('interruptedUrl', $resource);
                            return $handler->handle($request)
                                ->withHeader('Location', $routeParser->urlFor('notification.interrupt', ['id' => $notification->notificationId]))
                                ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
                                ->withHeader('Pragma',' no-cache')
                                ->withHeader('Expires',' 0');
                        }
                    }
                }

                $container->get('view')->offsetSet('notifications', $notifications);
                $container->get('view')->offsetSet('notificationCount', $factory->countMyUnread() + $extraNotifications);
            }
        }

        if (!$this->isAjax($request) && $user->isPasswordChangeRequired == 1 && $resource != '/user/page/password') {
            return $handler->handle($request)
                ->withStatus(302)
                ->withHeader('Location', $routeParser->urlFor('user.force.change.password.page'));
        }

        return $handler->handle($request);
    }

    /**
     * Is the provided request from AJAX
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return bool
     */
    private function isAjax(Request $request)
    {
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }
}
