<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;

/**
 * This middleware is used to register connectors.
 */
class ConnectorMiddleware implements MiddlewareInterface
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
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $app = $this->app;

        // Set connectors
        self::setConnectors($app);

        // Next middleware
        return $handler->handle($request);
    }

    /**
     * Set connectors
     * @param \Slim\App $app
     * @return void
     */
    public static function setConnectors(App $app)
    {
        // Dynamically load any connectors?
        $container = $app->getContainer();

        /** @var \Xibo\Factory\ConnectorFactory $connectorFactory */
        $connectorFactory = $container->get('connectorFactory');
        foreach ($connectorFactory->query(['isEnabled' => 1, 'isVisible' => 1]) as $connector) {
            try {
                // Create a connector, add in platform settings and register it with the dispatcher.
                $connectorFactory->create($connector)->registerWithDispatcher($container->get('dispatcher'));
            } catch (\Exception $exception) {
                // Log and ignore.
                $container->get('logger')->error('Incorrectly configured connector. e=' . $exception->getMessage());
            }
        }
    }
}
