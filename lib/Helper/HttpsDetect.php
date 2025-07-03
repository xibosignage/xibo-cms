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


namespace Xibo\Helper;

use Slim\Http\ServerRequest;

/**
 * Class HttpsDetect
 * @package Xibo\Helper
 */
class HttpsDetect
{
    /**
     * Get the root of the web server
     *  this should only be used if you're planning to append the path
     * @return string
     */
    public function getRootUrl(): string
    {
        $url = $this->getScheme() . '://' . $this->getHost();
        if (($this->getScheme() === 'https' && $this->getPort() !== 443)
            || ($this->getScheme() === 'http' && $this->getPort() !== 80)
        ) {
            $url .= sprintf(':%s', $this->getPort());
        }

        return $url;
    }

    /**
     * @deprecated use getRootUrl
     * @return string
     */
    public function getUrl(): string
    {
        return $this->getRootUrl();
    }

    /**
     * Get the base URL for the instance
     *  this should give us the CMS URL including alias and file
     * @param \Slim\Http\ServerRequest|null $request
     * @return string
     */
    public function getBaseUrl(?ServerRequest $request = null): string
    {
        // Check REQUEST_URI is set. IIS doesn't set it, so we need to build it
        // Attribution:
        // Code snippet from http://support.ecenica.com/web-hosting/scripting/troubleshooting-scripting-errors/how-to-fix-server-request_uri-php-error-on-windows-iis/
        // Released under BSD License
        // Copyright (c) 2009, Ecenica Limited All rights reserved.
        if (!isset($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
            if (isset($_SERVER['QUERY_STRING'])) {
                $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
            }
        }
        // End Code Snippet

        // The request URL should be everything after the host, i.e:
        // /xmds.php?file=
        // /xibo/xmds.php?file=
        // /playersoftware
        // /xibo/playersoftware
        $requestUri = explode('?', htmlentities($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'));
        $baseUrl = $this->getRootUrl() . '/' . ltrim($requestUri[0], '/');

        // We use the path, if provided, to remove any known path information
        // i.e. if we're running in a sub-folder we might be on /xibo/playersoftware
        // in which case we want to remove /playersoftware to get to /xibo which is the base path.
        $path = $request?->getUri()?->getPath() ?? '';
        if (!empty($path)) {
            $baseUrl = str_replace($path, '', $baseUrl);
        }

        return $baseUrl;
    }

    /**
     * @return string
     */
    public function getScheme(): string
    {
        return ($this->isHttps()) ? 'https' : 'http';
    }

    /**
     * Get Host
     * @return string
     */
    public function getHost(): string
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            $httpHost = htmlentities($_SERVER['HTTP_HOST'], ENT_QUOTES, 'UTF-8');
            if (str_contains($httpHost, ':')) {
                $hostParts = explode(':', $httpHost);

                return $hostParts[0];
            }

            return $httpHost;
        }

        return $_SERVER['SERVER_NAME'];
    }

    /**
     * Get Port
     * @return int
     */
    public function getPort(): int
    {
        if (isset($_SERVER['HTTP_HOST']) && str_contains($_SERVER['HTTP_HOST'], ':')) {
            $hostParts = explode(':', htmlentities($_SERVER['HTTP_HOST'], ENT_QUOTES, 'UTF-8'));
            return $hostParts[1];
        }

        return ($this->isHttps() ? 443 : 80);
    }

    /**
     * Is HTTPs?
     * @return bool
     */
    public static function isHttps(): bool
    {
        return (
            (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https')
        );
    }

    /**
     * @param \Xibo\Service\ConfigServiceInterface $config
     * @param \Psr\Http\Message\RequestInterface $request
     * @return bool
     */
    public static function isShouldIssueSts($config, $request): bool
    {
        // We might need to issue STS headers
        $whiteListLoadBalancers = $config->getSetting('WHITELIST_LOAD_BALANCERS');
        $originIp = $_SERVER['REMOTE_ADDR'] ?? '';
        $forwardedProtoHttps = (
            strtolower($request->getHeaderLine('HTTP_X_FORWARDED_PROTO')) === 'https'
            && $originIp != ''
            && (
                $whiteListLoadBalancers === '' || in_array($originIp, explode(',', $whiteListLoadBalancers))
            )
        );

        return (
            ($request->getUri()->getScheme() == 'https' || $forwardedProtoHttps)
            && $config->getSetting('ISSUE_STS', 0) == 1
        );
    }

    /**
     * @param \Xibo\Service\ConfigServiceInterface $config
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    public static function decorateWithSts($config, $response)
    {
        return $response->withHeader(
            'strict-transport-security',
            'max-age=' . $config->getSetting('STS_TTL', 600)
        );
    }

    /**
     * @param \Xibo\Service\ConfigServiceInterface $config
     * @param \Psr\Http\Message\RequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    public static function decorateWithStsIfNecessary($config, $request, $response)
    {
        if (self::isShouldIssueSts($config, $request)) {
            return self::decorateWithSts($config, $response);
        } else {
            return $response;
        }
    }
}
