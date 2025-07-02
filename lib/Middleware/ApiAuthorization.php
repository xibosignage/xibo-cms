<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

use Carbon\Carbon;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App as App;
use Slim\Routing\RouteContext;
use Xibo\Factory\ApplicationScopeFactory;
use Xibo\Helper\UserLogProcessor;
use Xibo\OAuth\AccessTokenRepository;
use Xibo\Support\Exception\AccessDeniedException;

/**
 * Class ApiAuthenticationOAuth
 * This middleware protects the API entry point
 * @package Xibo\Middleware
 */
class ApiAuthorization implements Middleware
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
     * @throws \Xibo\Support\Exception\AccessDeniedException
     * @throws \Xibo\Support\Exception\ConfigurationException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        /* @var \Xibo\Entity\User $user */
        $user = null;

        /** @var \Xibo\Service\LogServiceInterface $logger */
        $logger = $this->app->getContainer()->get('logService');

        // Setup the authorization server
        $this->app->getContainer()->set('server', function (ContainerInterface $container) use ($logger) {
            // oAuth Resource
            $apiKeyPaths = $container->get('configService')->getApiKeyDetails();

            $accessTokenRepository = new AccessTokenRepository(
                $logger,
                $container->get('pool'),
                $container->get('applicationFactory')
            );
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
        $user->setChildAclDependencies($this->app->getContainer()->get('userGroupFactory'));
        $user->load();

        // Block access by retired users.
        if ($user->retired === 1) {
            throw new AccessDeniedException(__('Sorry this account does not exist or cannot be authenticated.'));
        }

        // We must check whether this user has access to the route they have requested.
        // Get the current route pattern
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $resource = $route->getPattern();

        // Allow public routes
        if (!in_array($resource, $validatedRequest->getAttribute('publicRoutes', []))) {
            $request = $request->withAttribute('public', false);

            // Check that the Scopes granted to this token are allowed access to the route/method of this request
            /** @var ApplicationScopeFactory $applicationScopeFactory */
            $applicationScopeFactory = $this->app->getContainer()->get('applicationScopeFactory');
            $scopes = $validatedRequest->getAttribute('oauth_scopes');

            // If there are no scopes in the JWT, we should not authorise
            // An older client which makes a request with no scopes should get an access token with
            // all scopes configured for the application
            if (count($scopes) <= 0) {
                throw new AccessDeniedException();
            }

            $logger->debug('Scopes provided with request: ' . count($scopes));

            //iterator, as we need to check all scopes in the request before we deny access.
            $i = 0;
            // Only validate scopes if we have been provided some.
            if (is_array($scopes) && count($scopes) > 0) {
                foreach ($scopes as $scope) {
                    // Valid routes
                    if ($scope !== 'all') {
                        $logger->debug(
                            sprintf(
                                'Test authentication for %s %s against scope %s',
                                $resource,
                                $request->getMethod(),
                                $scope
                            )
                        );

                        // Check the route and request method
                        try {
                            $i++;
                            $checkRoute = $applicationScopeFactory->getById($scope)->checkRoute(
                                $request->getMethod(),
                                $resource
                            );

                            // if we have access to the requested route, break the loop
                            if ($checkRoute) {
                                break;
                            }
                        } catch (AccessDeniedException $notFoundException) {
                            // if we have more scopes, make sure to check all of them for the requested route
                            // if all scopes were checked and we still don't have access, then throw exception
                            if ($i === count($scopes)) {
                                throw new AccessDeniedException(__('Access to this route is denied for this scope'));
                            } else {
                                continue;
                            }
                        }
                    }
                }
            }
        } else {
            $validatedRequest = $validatedRequest->withAttribute('public', true);
        }

        $requestId = $this->app->getContainer()->get('store')->insert('
            INSERT INTO `application_requests_history` (
                userId,
                applicationId,
                url,
                method,
                startTime,
                endTime,
                duration
            ) 
            VALUES (
                :userId,
                :applicationId,
                :url,
                :method,
                :startTime,
                :endTime,
                :duration
            )
        ', [
            'userId' => $user->userId,
            'applicationId' => $validatedRequest->getAttribute('oauth_client_id'),
            'url' => htmlspecialchars($request->getUri()->getPath()),
            'method' => $request->getMethod(),
            'startTime' => Carbon::now(),
            'endTime' => Carbon::now(),
            'duration' => 0
        ], 'api_requests_history');

        $this->app->getContainer()->get('store')->commitIfNecessary('api_requests_history');

        $logger->setUserId($user->userId);
        $this->app->getContainer()->set('user', $user);
        $logger->setRequestId($requestId);

        // Add this request information to the logger.
        $logger->getLoggerInterface()->pushProcessor(new UserLogProcessor(
            $user->userId,
            null,
            $requestId,
        ));

        return $handler->handle($validatedRequest->withAttribute('name', 'API'));
    }
}
