<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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


use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App as App;
class ApiAuthorizationOAuth implements Middleware
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

        // DI in the server
        $container->set('server', function(ContainerInterface $container) {
            $server = new \League\OAuth2\Server\AuthorizationServer;
            // oAuth Resource
            $server->setSessionStorage(new \Xibo\Storage\ApiSessionStorage($container->get('store')));
            $server->setAccessTokenStorage(new \Xibo\Storage\ApiAccessTokenStorage($container->get('store')));
            $server->setRefreshTokenStorage(new \Xibo\Storage\ApiRefreshTokenStorage($container->get('store')));
            $server->setClientStorage(new \Xibo\Storage\ApiClientStorage($container->get('store')));
            $server->setScopeStorage(new \Xibo\Storage\ApiScopeStorage($container->get('store')));
            $server->setAuthCodeStorage(new \Xibo\Storage\ApiAuthCodeStorage($container->get('store')));

            // Allow auth code grant
            $authCodeGrant = new \League\OAuth2\Server\Grant\AuthCodeGrant();
            $server->addGrantType($authCodeGrant);

            // Allow client credentials grant
            $clientCredentialsGrant = new \League\OAuth2\Server\Grant\ClientCredentialsGrant();
            $server->addGrantType($clientCredentialsGrant);

            // Add refresh tokens
            $refreshTokenGrant = new \League\OAuth2\Server\Grant\RefreshTokenGrant();
            $server->addGrantType($refreshTokenGrant);

            // Default scope
            $server->setDefaultScope('all');

            return $server;
        });

        return $handler->handle($request);
    }
}