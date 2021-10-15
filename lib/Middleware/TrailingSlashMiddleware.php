<?php
/*
 * Copyright (C) 2021 Xibo Signage Ltd
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

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;

/**
 * Trailing Slash Middleware
 *  this middleware is used for routes contained inside a directory
 *  Apache automatically adds a trailing slash to these URLs, Nginx does not.
 *  Slim treats trailing slashes differently to non-trailing slashes
 *  We need to mimic Apache for the director route.
 * @package Xibo\Middleware
 */
class TrailingSlashMiddleware implements MiddlewareInterface
{
    /* @var App $app */
    private $app;

    /**
     * Storage constructor.
     * @param $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }
    /**
     * Middleware process
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $uri = $request->getUri();
        $path = $uri->getPath();

        if ($path === $this->app->getBasePath()) {
            // Add a trailing slash for the route middleware to match
            $request = $request->withUri($uri->withPath($path . '/'));
        }

        return $handler->handle($request);
    }
}
