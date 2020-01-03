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

use League\OAuth2\Server\ResourceServer;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App as App;
use Slim\Routing\RouteContext;

class ApiAuthenticationOAuth implements Middleware
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

        $container->set('server', function (ContainerInterface $container) {
            // oAuth Resource
            $sessionStorage = new \Xibo\Storage\ApiSessionStorage($container->get('store'));
            $accessTokenStorage = new \Xibo\Storage\ApiAccessTokenStorage($container->get('store'));
            $clientStorage = new \Xibo\Storage\ApiClientStorage($container->get('store'));
            $scopeStorage = new \Xibo\Storage\ApiScopeStorage($container->get('store'));

            $server = new \League\OAuth2\Server\ResourceServer(
                $sessionStorage,
                $accessTokenStorage,
                $clientStorage,
                $scopeStorage
            );

            return $server;
        });

        // Validate we are a valid auth
        /* @var ResourceServer $server */
        $server = $container->get('server');

        $server->isValidRequest(false);

        /* @var \Xibo\Entity\User $user */
        $user = null;

        // What type of access has been requested?
        if ($server->getAccessToken()->getSession()->getOwnerType() == 'user') {
            $user = $container->get('userFactory')->getById($server->getAccessToken()->getSession()->getOwnerId());
        } else {
            $user = $container->get('userFactory')->loadByClientId($server->getAccessToken()->getSession()->getOwnerId());
        }

        $user->setChildAclDependencies($container->get('userGroupFactory'),$container->get('pageFactory'));

        $user->load();

        $newRequest = $request->withAttribute('currentUser', $user);
        $newerRequest = $newRequest->withAttribute('name', 'API');

        // Get the current route pattern
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $resource = $route->getPattern();

        // Do they have permission?
        $user->routeAuthentication($resource, $request->getMethod(), $server->getAccessToken()->getScopes());

        // Call the next middleware
        return $handler->handle($newerRequest);
    }
}