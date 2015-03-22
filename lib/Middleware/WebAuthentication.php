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
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\UserFactory;
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
        new Theme($app->user);

        Theme::Set('rootPath', str_replace('/index.php', '', $app->request->getRootUri()));

        // Define a callable to run our hook - curry in the $app object
        $isAuthorised = function () use ($app) {
            $user = $app->user;
            /* @var \Xibo\Entity\User $user */

            $publicRoutes = array('/login', '/logout', '/clock', '/about');

            // Get the current route pattern
            $resource = $app->router->getCurrentRoute()->getPattern();

            // Check to see if this is a public resource (there are only a few, so we have them in an array)
            if (!in_array($resource, $publicRoutes)) {
                $app->public = false;
                // Need to check
                if ($user->hasIdentity() && $app->session->isExpired == 0) {
                    // Replace our user with a fully loaded one
                    $user = UserFactory::getById($user->userId);
                    // Do they have permission?
                    $user->routeAuthentication($resource);

                    $app->user = $user;
                }
                else {
                    // Store the current route so we can come back to it after login
                    $app->flash('priorRoute', $resource);

                    if ($app->request->isAjax()) {
                        // Return a JSON response which tells the App to redirect to the login page
                        $app->state->Login();
                        $app->render('response', array('response' => $app->state));
                        $app->halt(302);
                    }
                    else {
                        // Redirect to login
                        $app->redirect($app->urlFor('login'));
                    }
                }
            }
            else {
                $app->public = true;
            }
        };

        $updateUser = function () use ($app) {
            $user = $app->user;
            /* @var \Xibo\Entity\User $user */

            if (!$app->public && $user->hasIdentity()) {
                $user->lastAccessed = date("Y-m-d H:i:s");
                $user->save();
            }
        };

        $app->hook('slim.before.dispatch', $isAuthorised);
        $app->hook('slim.after.dispatch', $updateUser);

        $this->next->call();
    }
}