<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (ApiAuthenticationOAuth.php) is part of Xibo.
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

use League\OAuth2\Server\ResourceServer;
use Slim\Middleware;

class ApiAuthenticationOAuth extends Middleware
{
    public function call()
    {
        $app = $this->app;

        // oAuth Resource
        $sessionStorage = new \Xibo\Storage\ApiSessionStorage($app->store);
        $accessTokenStorage = new \Xibo\Storage\ApiAccessTokenStorage($app->store);
        $clientStorage = new \Xibo\Storage\ApiClientStorage($app->store);
        $scopeStorage = new \Xibo\Storage\ApiScopeStorage($app->store);

        $server = new \League\OAuth2\Server\ResourceServer(
            $sessionStorage,
            $accessTokenStorage,
            $clientStorage,
            $scopeStorage
        );

        // DI in the server
        $app->server = $server;

        $isAuthorised = function() use ($app) {
            // Validate we are a valid auth
            /* @var ResourceServer $server */
            $server = $this->app->server;

            $server->isValidRequest(false);

            /* @var \Xibo\Entity\User $user */
            $user = null;

            // What type of access has been requested?
            if ($server->getAccessToken()->getSession()->getOwnerType() == 'user')
                $user = $app->userFactory->getById($server->getAccessToken()->getSession()->getOwnerId());
            else
                $user = $app->userFactory->loadByClientId($server->getAccessToken()->getSession()->getOwnerId());

            $user->setChildAclDependencies($app->userGroupFactory, $app->pageFactory);

            $user->load();

            $this->app->user = $user;

            // Get the current route pattern
            $resource = $app->router->getCurrentRoute()->getPattern();

            // Allow public routes
            if (!in_array($resource, $app->publicRoutes)) {
                $app->public = false;

                // Do they have permission?
                $this->app->user->routeAuthentication(
                    $resource,
                    $app->request()->getMethod(),
                    $server->getAccessToken()->getScopes()
                );
            } else {
                $app->public = true;
            }
        };

        $app->hook('slim.before.dispatch', $isAuthorised);

        // Call the next middleware
        $this->next->call();
    }
}