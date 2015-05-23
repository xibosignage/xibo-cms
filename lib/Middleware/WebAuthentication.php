<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (WebAuthentication.php) is part of Xibo.
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
use Xibo\Factory\UserFactory;
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Log;
use Xibo\Helper\Theme;

class WebAuthentication extends Middleware
{
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
        $app->user = new \Xibo\Entity\User();

        // Initialise a theme
        $redirectToLogin = function () use ($app) {
            Log::debug('Request to redirect to login. Ajax = %d', $app->request->isAjax());
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
                // Redirect to login
                $app->redirect($app->urlFor('login'));
            }
        };

        // Define a callable to run our hook - curry in the $app object
        $isAuthorised = function () use ($app, $redirectToLogin) {
            $user = $app->user;
            /* @var \Xibo\Entity\User $user */

            $publicRoutes = array('/login', '/logout', '/clock', '/about', '/login/ping');

            // Get the current route pattern
            $resource = $app->router->getCurrentRoute()->getPattern();

            // Check to see if this is a public resource (there are only a few, so we have them in an array)
            if (!in_array($resource, $publicRoutes)) {
                $app->public = false;
                // Need to check
                if ($user->hasIdentity() && $app->session->isExpired == 0) {
                    // Replace our user with a fully loaded one
                    $user = UserFactory::loadById($user->userId);

                    // Do they have permission?
                    $user->routeAuthentication($resource);

                    $app->user = $user;

                    // We are authenticated
                    // Handle if we are an upgrade
                    // Does the version in the DB match the version of the code?
                    // If not then we need to run an upgrade.
                    if (DBVERSION != WEBSITE_VERSION && $resource != '/upgrade') {
                        $app->redirectTo('upgrade.view');
                    }
                }
                else {
                    // Store the current route so we can come back to it after login
                    $app->flash('priorRoute', $resource);
                    $app->flash('priorRouteParams', urlencode($app->request()->get()));

                    $redirectToLogin();
                }
            }
            else {
                $app->public = true;

                // If we are expired and come from ping/clock, then we redirect
                if ($app->session->isExpired == 1 && ($resource == '/login/ping' || $resource == 'clock')) {
                    $redirectToLogin();
                }
            }
        };

        $updateUser = function () use ($app) {
            $user = $app->user;
            /* @var \Xibo\Entity\User $user */

            if (!$app->public && $user->hasIdentity()) {
                $user->lastAccessed = date("Y-m-d H:i:s");
                $user->save(false);
            }
        };

        $app->hook('slim.before.dispatch', $isAuthorised);
        $app->hook('slim.after.dispatch', $updateUser);

        $this->next->call();
    }
}