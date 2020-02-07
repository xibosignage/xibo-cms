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

use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App as App;
use Xibo\Storage\AccessTokenRepository;

class ApiAuthenticationOAuth implements Middleware
{
    /* @var App $app */
    private $app;

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
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $userFactory = $this->app->getContainer()->get('userFactory');
        /* @var \Xibo\Entity\User $user */
        $user = null;
        // Setup the authorization server

        $this->app->getContainer()->set('server', function (ContainerInterface $container) {
            // oAuth Resource
            $logger = $container->get('logger');
            $apiKeyPaths = $container->get('configService')->apiKeyPaths;
            $encryptionKey = $apiKeyPaths['publicKeyPath'];
            $accessTokenRepository = new AccessTokenRepository($logger);

            $server = new ResourceServer(
                $accessTokenRepository,
                $encryptionKey
            );

            return $server;
        });

        /** @var ResourceServer $server */
        $server =  $this->app->getContainer()->get('server');
        $validatedRequest = $server->validateAuthenticatedRequest($request);
        $userId = $validatedRequest->getAttribute('oauth_user_id');

        $user = $userFactory->getById($userId);

        $user->setChildAclDependencies($this->app->getContainer()->get('userGroupFactory'), $this->app->getContainer()->get('pageFactory'));

        $user->load();

        $newRequest = $validatedRequest->withAttribute('currentUser', $user)
                                       ->withAttribute('name', 'API');

        return $handler->handle($newRequest);
    }
}