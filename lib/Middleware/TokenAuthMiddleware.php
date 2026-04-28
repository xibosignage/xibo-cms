<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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
use Xibo\Service\JwtServiceInterface;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\NotFoundException;
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
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Xibo\Support\Exception\AccessDeniedException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->encryptionKey == null) {
            $this->encryptionKey = $this->container->get('configService')->getApiKeyDetails()['encryptionKey'];
        }

        // Check the link.
        $params = $this->getSanitizer($request->getParams());

        // Are we a JWT?
        $previewToken = $request->getHeader('X-PREVIEW-JWT')[0] ?? null;
        $jwt = $params->getString('jwt', ['default' => $previewToken]);
        if (!empty($jwt)) {
            // Yes, validate the JWT
            try {
                // Parse the token and assert its valid
                $token = $this->getJwtService()->validateJwt($jwt);

                // Check claims.
                if (!$token->hasBeenIssuedBy('Preview')) {
                    throw new AccessDeniedException(__('Invalid URL'));
                }

                // We are authenticated via an auth token (e.g. whole resource access)
                $request = $request->withAttribute('authedToken', $token);
            } catch (\Exception) {
                throw new AccessDeniedException(__('Invalid'));
            }
        } else if ($params->hasParam('X-Amz-Signature')) {
            // AMZ Link
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

            // We are authenticated via the token (e.g. single file access)
            $request = $request->withAttribute('authedViaToken', true);
        } else {
            throw new NotFoundException();
        }

        // Handled
        return $handler->handle($request);
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
        // When signing from a preview, use the root url since the alias is included in the entrypoint
        $rootUrl = ($request->getAttribute('_entryPoint') != 'preview')
            ? (new HttpsDetect())->getBaseUrl($request)
            : (new HttpsDetect())->getRootUrl();

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

    /**
     * @return \Xibo\Service\JwtServiceInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function getJwtService(): JwtServiceInterface
    {
        return $this->container->get('jwtService');
    }
}
