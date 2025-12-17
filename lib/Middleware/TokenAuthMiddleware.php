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

use Illuminate\Support\Str;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Xibo\Helper\HttpsDetect;
use Xibo\Helper\LinkSigner;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Preview Auth Middleware
 * -----------------------
 * This middleware protects various routes in the Preview Entrypoint
 * It ensures they have a valid link before rendering
 */
class TokenAuthMiddleware implements MiddlewareInterface
{
    private ?string $encryptionKey = null;

    /**
     * FeatureAuth constructor.
     * @param ContainerInterface $container
     */
    public function __construct(private ContainerInterface $container)
    {
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Xibo\Support\Exception\AccessDeniedException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->encryptionKey == null) {
            $this->encryptionKey = $this->container->get('configService')->getApiKeyDetails()['encryptionKey'];
        }

        // Check the link.
        $params = $this->getSanitizer($request->getParams());

        // Has the URL expired
        if (time() > $params->getInt('X-Amz-Expires')) {
            throw new AccessDeniedException(__('Expired'));
        }

        // Validate the URL.
        $signature = $params->getString('X-Amz-Signature');

        $calculatedSignature = \Xibo\Helper\LinkSigner::getSignature(
            (new HttpsDetect())->getRootUrl(),
            $request->getUri()->getPath(),
            $params->getInt('X-Amz-Expires'),
            $this->encryptionKey,
            $params->getString('X-Amz-Date'),
            true,
        );

        if ($signature !== $calculatedSignature) {
            throw new AccessDeniedException(__('Invalid URL'));
        }

        // Authed via a token
        $request = $request
            ->withAttribute('_entryPoint', 'preview')
            ->withAttribute('authedViaToken', true);

        // Add a CORS header
        return $handler->handle($request)->withHeader('Access-Control-Allow-Origin', '*');
    }

    /**
     * Sign the given link with a signed URL
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param string $url
     * @param int $expiry
     * @param string $encryptionKey
     * @return string
     */
    public static function sign(
        ServerRequestInterface $request,
        string $url,
        int $expiry,
        string $encryptionKey
    ): string {
        $rootUrl = (new HttpsDetect())->getBaseUrl($request);

        if ($request->getAttribute('_entryPoint') != 'web') {
            $url = str_replace('/api/', '/', $url);
        }

        return $rootUrl . $url . (Str::contains($url, '?') ? '&' : '?') . LinkSigner::getSignature(
            $rootUrl,
            $url,
            $expiry,
            $encryptionKey,
        );
    }

    /**
     * @param $getParams
     * @return \Xibo\Support\Sanitizer\SanitizerInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function getSanitizer($getParams): SanitizerInterface
    {
        return $this->container->get('sanitizerService')->getSanitizer($getParams);
    }
}
