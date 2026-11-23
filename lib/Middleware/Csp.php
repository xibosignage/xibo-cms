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
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly bool $preventFraming = true
    ) {
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

        if ($this->preventFraming) {
            $csp .= ' frame-ancestors \'self\';';
        }

        // Store it for use in the stack if needed
        $request = $request->withAttribute('cspNonce', $nonce);

        // Assign it to our view
        $this->container->get('view')->offsetSet('cspNonce', $nonce);

        // Call next middleware.
        $response = $handler->handle($request);

        if ($this->preventFraming) {
            $response = $response->withAddedHeader('X-Frame-Options', 'SAMEORIGIN')
                ->withAddedHeader('X-Content-Type-Options', 'nosniff');
        }

        // Add our header
        return $response->withAddedHeader('Content-Security-Policy', $csp);
    }
}
