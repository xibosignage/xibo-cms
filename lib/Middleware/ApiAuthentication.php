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

use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App as App;
use Xibo\OAuth\RefreshTokenRepository;
use Xibo\Support\Exception\ConfigurationException;

/**
 * Class ApiAuthentication
 * This middleware protects the AUTH entry point
 * @package Xibo\Middleware
 */
class ApiAuthentication implements Middleware
{
    /* @var App $app */
    private $app;

    /**
     * ApiAuthorizationOAuth constructor.
     * @param $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Xibo\Support\Exception\ConfigurationException
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $app = $this->app;
        $container = $app->getContainer();

        // DI in the server
        $container->set('server', function(ContainerInterface $container) {
            /** @var \Xibo\Service\LogServiceInterface $logger */
            $logger = $container->get('logService');

            // API Keys
            $apiKeyPaths = $container->get('configService')->getApiKeyDetails();
            $privateKey = $apiKeyPaths['privateKeyPath'];
            $encryptionKey = $apiKeyPaths['encryptionKey'];

            try {
                $server = new \League\OAuth2\Server\AuthorizationServer(
                    $container->get('applicationFactory'),
                    new \Xibo\OAuth\AccessTokenRepository($logger, $container->get('pool'), $container->get('applicationFactory')),
                    $container->get('applicationScopeFactory'),
                    $privateKey,
                    $encryptionKey
                );

                // Grant Types
                $server->enableGrantType(
                    new \League\OAuth2\Server\Grant\ClientCredentialsGrant(),
                    new \DateInterval('PT1H')
                );

                $server->enableGrantType(
                    new AuthCodeGrant(
                        new \Xibo\OAuth\AuthCodeRepository(),
                        new \Xibo\OAuth\RefreshTokenRepository($logger, $container->get('pool')),
                        new \DateInterval('PT10M')
                    ),
                    new \DateInterval('PT1H')
                );

                $server->enableGrantType(new RefreshTokenGrant(new RefreshTokenRepository($logger, $container->get('pool'))));

                return $server;
            } catch (\LogicException $exception) {
                $logger->error($exception->getMessage());
                throw new ConfigurationException('API configuration problem, consult your administrator');
            }
        });

        return $handler->handle($request->withAttribute('name', 'auth'));
    }
}