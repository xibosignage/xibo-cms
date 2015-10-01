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
use Xibo\Factory\UserFactory;

class ApiAuthenticationOAuth extends Middleware
{
    public function call()
    {
        $app = $this->app;

        $isAuthorised = function() use ($app) {
            // Validate we are a valid auth
            /* @var ResourceServer $server */
            $server = $this->app->server;

            $app->server->isValidRequest(false);

            // What type of access has been requested?
            if ($server->getAccessToken()->getSession()->getOwnerType() == 'user')
                $this->app->user = UserFactory::loadById($server->getAccessToken()->getSession()->getOwnerId());
            else
                $this->app->user = UserFactory::loadByClientId($server->getAccessToken()->getSession()->getOwnerId());

            // Get the current route pattern
            $resource = $app->router->getCurrentRoute()->getPattern();

            // Do they have permission?
            $this->app->user->routeAuthentication($resource);
        };

        $app->hook('slim.before.dispatch', $isAuthorised);

        // Call the next middleware
        $this->next->call();
    }
}