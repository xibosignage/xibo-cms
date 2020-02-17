<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App as App;
use Slim\Routing\RouteContext;
use Xibo\Storage\AccessTokenRepository;

/**
 * Class ApiAuthenticationOAuth
 * @package Xibo\Middleware
 */
class ApiAuthenticationOAuth implements Middleware
{
    /* @var App $app */
    private $app;

    /**
     * ApiAuthenticationOAuth constructor.
     * @param $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     * @throws OAuthServerException
     * @throws \Xibo\Exception\NotFoundException
     * @throws \Xibo\Exception\ConfigurationException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        /* @var \Xibo\Entity\User $user */
        $user = null;

        // Setup the authorization server
        $this->app->getContainer()->set('server', function (ContainerInterface $container) {
            // oAuth Resource
            $logger = $container->get('logger');
            $apiKeyPaths = $container->get('configService')->apiKeyPaths;

            $accessTokenRepository = new AccessTokenRepository($logger);

            return new ResourceServer(
                $accessTokenRepository,
                $apiKeyPaths['publicKeyPath']
            );
        });

        /** @var ResourceServer $server */
        $server =  $this->app->getContainer()->get('server');
        $validatedRequest = $server->validateAuthenticatedRequest($request);

        // We have a valid JWT/token
        // get our user from it.
        $userFactory = $this->app->getContainer()->get('userFactory');

        // What type of Access Token to we have? Client Credentials or AuthCode
        // client_credentials grants are issued with the correct oauth_user_id in the token, so we don't need to
        // distinguish between them here! nice!
        $userId = $validatedRequest->getAttribute('oauth_user_id');

        $user = $userFactory->getById($userId);

        $user->setChildAclDependencies($this->app->getContainer()->get('userGroupFactory'), $this->app->getContainer()->get('pageFactory'));
        $user->load();

        // We must check whether this user has access to the route they have requested.
        // Get the current route pattern
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $resource = $route->getPattern();

        // Allow public routes
        if (!in_array($resource, $validatedRequest->getAttribute('publicRoutes', []))) {
            $request = $request->withAttribute('public', false);

            // Do they have permission?
            $user->routeAuthentication(
                $resource,
                $request->getMethod(),
                $validatedRequest->getAttribute('oauth_scopes')
            );
        } else {
            $validatedRequest = $validatedRequest->withAttribute('public', true);
        }

        $newRequest = $validatedRequest->withAttribute('name', 'API');

        $this->app->getContainer()->set('user', $user);

        return $handler->handle($newRequest);
    }
}