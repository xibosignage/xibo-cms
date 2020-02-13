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

use League\OAuth2\Server\Grant\AuthCodeGrant;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App as App;
use Xibo\Exception\ConfigurationException;
use Xibo\Storage\AuthCodeRepository;
use Xibo\Storage\RefreshTokenRepository;

/**
 * Class ApiAuthorizationOAuth
 * @package Xibo\Middleware
 */
class ApiAuthorizationOAuth implements Middleware
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
            $apiKeyPaths = $container->get('configService')->apiKeyPaths;
            $privateKey = $apiKeyPaths['privateKeyPath'];
            $encryptionKey = $apiKeyPaths['encryptionKey'];

            try {

                $server = new \League\OAuth2\Server\AuthorizationServer(
                    new \Xibo\Storage\ApiClientStorage($container->get('store'), $logger),
                    new \Xibo\Storage\AccessTokenRepository($logger),
                    new \Xibo\Storage\ScopeRepository(),
                    $privateKey,
                    $encryptionKey
                );

                $server->enableGrantType(
                    new \League\OAuth2\Server\Grant\ClientCredentialsGrant(),
                    new \DateInterval('PT1H')
                );

                $server->enableGrantType(
                    new AuthCodeGrant(
                        new AuthCodeRepository(),
                        new RefreshTokenRepository(),
                        new \DateInterval('PT10M')
                    ),
                    new \DateInterval('PT1H')
                );

                // Default scope
                $server->setDefaultScope('all');

                return $server;
            } catch (\LogicException $exception) {
                $logger->error($exception->getMessage());
                throw new ConfigurationException('API configuration problem, consult your administrator');
            }
        });

        return $handler->handle($request);
    }
}