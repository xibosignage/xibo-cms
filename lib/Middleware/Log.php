<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;
use Slim\App as App;
use Xibo\Helper\RouteLogProcessor;

/**
 * Log Middleware
 */
class Log implements Middleware
{
    private App $app;

    /**
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
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $container = $this->app->getContainer();

        self::addLogProcessorToLogger($container->get('logger'), $request, $this->app->getBasePath());

        return $handler->handle($request);
    }

    /**
     * @param LoggerInterface $logger
     * @param Request $request
     * @param string $basePath Base path to strip from the request path, if present
     */
    public static function addLogProcessorToLogger(
        LoggerInterface $logger,
        Request $request,
        string $basePath = '',
    ): void {
        $path = $request->getUri()->getPath();
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }

        $logger->pushProcessor(new RouteLogProcessor(
            $path,
            $request->getMethod(),
        ));
    }
}
