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

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Xibo\Helper\Random;

/**
 * CSP middleware to output CSP headers and add a CSP nonce to the view layer.
 */
class Csp implements Middleware
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * Call middleware
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Generate a nonce
        $nonce = Random::generateString(8);

        // Create CSP header
        $csp = 'object-src \'none\'; script-src \'nonce-' . $nonce . '\'';
        $csp .= ' \'unsafe-inline\' \'unsafe-eval\' \'strict-dynamic\' https: http:;';
        $csp .= ' base-uri \'self\';';
        $csp .= ' frame-ancestors \'self\';';

        // Store it for use in the stack if needed
        $request = $request->withAttribute('cspNonce', $nonce);

        // Assign it to our view
        $this->container->get('view')->offsetSet('cspNonce', $nonce);

        // Call next middleware.
        $response = $handler->handle($request);

        // Add our header
        return $response->withAddedHeader('Content-Security-Policy', $csp);
    }
}
