<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (HttpsDetect.php)
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
    public function getHost()
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            if (strpos($_SERVER['HTTP_HOST'], ':') !== false) {
                $hostParts = explode(':', $_SERVER['HTTP_HOST']);

                return $hostParts[0];
            }

            return $_SERVER['HTTP_HOST'];
        }

        return $_SERVER['SERVER_NAME'];
    }

    /**
     * Get Port
     * @return int
     */
    public function getPort()
    {
        if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], ':') !== false) {
            $hostParts = explode(':', $_SERVER['HTTP_HOST']);
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
