<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2019 Spring Signage Ltd
 * Author: Emmanuel Blindauer
 * Based on SAMLAuthentication.php
 *
 * This file (CASAuthentication.php) is part of Xibo.
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
use Xibo\Entity\User;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Random;

/**
 * Class CASAuthentication
 * @package Xibo\Middleware
 *
 * Provide CAS authentication to Xibo configured via settings.php.
 */
class CASAuthentication extends Middleware
{
    public static function casRoutes()
    {
        return array(
            '/cas/login',
            '/cas/logout',
        );
    }


    /**
     * Uses a Hook to check every call for authorization
     * Will redirect to the login route if the user is unauthorized
     *
     * @throws \RuntimeException if there isn't a login route
     */
    public function call()
    {
        $app = $this->app;

        // Create a user
        $app->user = $app->userFactory->create();

        // Register CAS routes.
        $app->excludedCsrfRoutes = CASAuthentication::casRoutes();
        $app->logoutRoute = 'cas.logout';

        $app->map('/cas/login', function () use ($app) {

            // Initiate CAS SSO
            $sso_host = $app->configService->casSettings['config']['server'];
            $sso_port = $app->configService->casSettings['config']['port'];
            $sso_uri = $app->configService->casSettings['config']['uri'];
            \phpCAS::client(CAS_VERSION_2_0, $sso_host, intval($sso_port), $sso_uri, true, null);
            \phpCAS::setNoCasServerValidation();

            //Login happens here
            \phpCAS::forceAuthentication();

            $username = \phpCAS::getUser();

            try {
                $user = $app->userFactory->getByName($username);
            } catch (NotFoundException $e) {
                throw new AccessDeniedException("Unknown user");
            }
            if (isset($user) && $user->userId > 0) {
                // Load User
                $user->setChildAclDependencies($app->userGroupFactory, $app->pageFactory);
                $user->load();

                // Overwrite our stored user with this new object.
                $this->app->user = $user;

                // Set the user factory ACL dependencies (used for working out intra-user permissions)
                $app->userFactory->setAclDependencies($user, $app->userFactory);

                // Switch Session ID's
                $this->app->session->setIsExpired(0);
                $this->app->session->regenerateSessionId();
                $this->app->session->setUser($user->userId);

                // Audit Log
                // Set the userId on the log object
                $this->app->logService->setUserId($user->userId);
                $this->app->logService->audit('User', $user->userId, 'Login Granted via CAS', [
                    'IPAddress' => $this->app->request()->getIp(),
                    'UserAgent' => $this->app->request()->getUserAgent()
                ]);
            }
            $app->redirect($app->urlFor('home'));
        })->via('GET','POST')->setName('cas.login');;

        // Service for the logout of the user.
        // End the CAS session and the application session
        $app->get('/cas/logout', function () use ($app) {
            // The order is first: local session to destroy, second the cas session
            // because phpCAS::logout() redirects to CAS server
            $loginController = $app->container->get('\Xibo\Controller\Login');
            $loginController->logout(false);
            $sso_host = $app->configService->casSettings['config']['server'];
            $sso_port = $app->configService->casSettings['config']['port'];
            $sso_uri = $app->configService->casSettings['config']['uri'];
            \phpCAS::client(CAS_VERSION_2_0, $sso_host, intval($sso_port), $sso_uri, true, null);
            \phpCAS::logout();

        })->setName('cas.logout');

        // Create a function which we will call should the request be for a protected page
        // and the user not yet be logged in.
        $redirectToLogin = function () use ($app) {
            if ($app->request->isAjax()) {
                $state = $app->state;
                /* @var ApplicationState $state */
                // Return a JSON response which tells the App to redirect to the login page
                $app->response()->header('Content-Type', 'application/json');
                $state->Login();
                echo $state->asJson();
                $app->stop();
            }
            else {
                // We redirect to login and not cas.login to let the user choose
                // the source of authentication
                $app->redirect($app->urlFor('login'));
            }
        };

        // Define a callable to check the route requested in before.dispatch
        $isAuthorised = function () use ($app, $redirectToLogin) {
            /** @var \Xibo\Entity\User $user */
            $user = $app->user;

            // Get the current route pattern
            $resource = $app->router->getCurrentRoute()->getPattern();

            // Pass the page factory into the user object, so that it can check its page permissions
            $user->setChildAclDependencies($app->userGroupFactory, $app->pageFactory);

            // Check to see if this is a public resource (there are only a few, so we have them in an array)
            if (!in_array($resource, $app->publicRoutes) && !in_array($resource, CASAuthentication::casRoutes())) {
                $app->public = false;
                // Need to check
                if ($user->hasIdentity() && !$app->session->isExpired()) {
                    // Replace our user with a fully loaded one
                    $user = $app->userFactory->getById($user->userId);
                    $user->setChildAclDependencies($app->userGroupFactory, $app->pageFactory);
                    $user->load();
                    $app->logService->setUserId($user->userId);
                    $user->routeAuthentication($resource);

                    // We are authenticated, override with the populated user object
                    $app->user = $user;
                }
                else {
                    // Store the current route so we can come back to it after login
                    $app->flash('priorRoute', $app->request()->getRootUri() . $app->request()->getResourceUri());
                    $redirectToLogin();
                }
            }
            else {
                $app->public = true;
                // If we are expired and come from ping/clock, then we redirect
                if ($app->session->isExpired() && ($resource == '/login/ping' || $resource == 'clock')) {
                    $redirectToLogin();
                }
            }
        };

        $app->hook('slim.before.dispatch', $isAuthorised);

        $this->next->call();
    }
}
