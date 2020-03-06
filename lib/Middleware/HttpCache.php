<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 * Copyright (c) 2012-2015 Josh Lockhart
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
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Class HttpCache
 * Http cache
 * @package Xibo\Middleware
 */
class HttpCache implements Middleware
{
    /**
     * Cache-Control type (public or private)
     *
     * @var string
     */
    protected $type;

    /**
     * Cache-Control max age in seconds
     *
     * @var int
     */
    protected $maxAge;

    /**
     * Cache-Control includes must-revalidate flag
     *
     * @var bool
     */
    protected $mustRevalidate;

    public function __construct($type = 'private', $maxAge = 86400, $mustRevalidate = false)
    {
        $this->type = $type;
        $this->maxAge = $maxAge;
        $this->mustRevalidate = $mustRevalidate;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

        // Cache-Control header
        if (!$response->hasHeader('Cache-Control')) {
            if ($this->maxAge === 0) {
                $response = $response->withHeader('Cache-Control', sprintf(
                    '%s, no-cache%s',
                    $this->type,
                    $this->mustRevalidate ? ', must-revalidate' : ''
                ));
            } else {
                $response = $response->withHeader('Cache-Control', sprintf(
                    '%s, max-age=%s%s',
                    $this->type,
                    $this->maxAge,
                    $this->mustRevalidate ? ', must-revalidate' : ''
                ));
            }
        }

        // ETag header and conditional GET check
        $etag = $response->getHeader('ETag');
        $etag = reset($etag);

        if ($etag) {
            $ifNoneMatch = $request->getHeaderLine('If-None-Match');

            if ($ifNoneMatch) {
                $etagList = preg_split('@\s*,\s*@', $ifNoneMatch);
                if (in_array($etag, $etagList) || in_array('*', $etagList)) {
                    return $response->withStatus(304);
                }
            }
        }


        // Last-Modified header and conditional GET check
        $lastModified = $response->getHeaderLine('Last-Modified');

        if ($lastModified) {
            if (!is_integer($lastModified)) {
                $lastModified = strtotime($lastModified);
            }

            $ifModifiedSince = $request->getHeaderLine('If-Modified-Since');

            if ($ifModifiedSince && $lastModified <= strtotime($ifModifiedSince)) {
                return $response->withStatus(304);
            }
        }

        return $response;
    }
}