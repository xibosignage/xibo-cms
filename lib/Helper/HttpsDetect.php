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


namespace Xibo\Helper;

/**
 * Class HttpsDetect
 * @package Xibo\Helper
 */
class HttpsDetect
{
    /**
     * @return string
     */
    public function getUrl()
    {
        $url = $this->getScheme() . '://' . $this->getHost();
        if (($this->getScheme() === 'https' && $this->getPort() !== 443) || ($this->getScheme() === 'http' && $this->getPort() !== 80)) {
            $url .= sprintf(':%s', $this->getPort());
        }

        return $url;
    }

    /**
     * @return string
     */
    public function getScheme()
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
    public function getPort()
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
    public static function isHttps()
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
    public static function isShouldIssueSts($config, $request)
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
