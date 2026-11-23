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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CORS middleware for preview.
 *  OPTIONS requests are handled via a dedicated route in web/preview/index.php
 */
class CorsPreviewMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request->withAttribute('_entryPoint', 'preview'));

        // Is CORS required?
        $origin = $request->getHeaderLine('Origin');
        $host = $request->getUri()->getHost();

        if ($request->getHeaderLine('Sec-Fetch-Site') === 'cross-site'
            || $request->getHeaderLine('Sec-Fetch-Mode') === 'cors'
            || $origin === 'null'
            || ($origin !== '' && !str_contains($origin, $host))
        ) {
            return $this->addCorsHeaders($response);
        }

        return $response;
    }

    private function addCorsHeaders(ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->withHeader(
                'Access-Control-Allow-Headers',
                'Content-Type, X-Requested-With, Accept, Origin, X-PREVIEW-JWT'
            );
    }
}
