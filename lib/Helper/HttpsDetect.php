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
    public function isHttps()
    {
        return (
            (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https')
        );
    }
}