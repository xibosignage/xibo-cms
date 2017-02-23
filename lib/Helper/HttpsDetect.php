<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (HttpsDetect.php)
 */


namespace Xibo\Helper;


use Slim\Slim;

/**
 * Class HttpsDetect
 * @package Xibo\Helper
 */
class HttpsDetect
{
    /**
     * @param Slim $slim
     * @return string
     */
    public function getUrl($slim)
    {
        $url = $this->getScheme() . '://' . $slim->request()->getHost();
        if (($this->getScheme() === 'https' && $slim->request()->getPort() !== 443) || ($this->getScheme() === 'http' && $slim->request()->getPort() !== 80)) {
            $url .= sprintf(':%s', $slim->request()->getPort());
        }

        return $url;
    }

    /**
     * @return string
     */
    public function getScheme()
    {
        return ($this->detect()) ? 'https' : 'http';
    }

    /**
     * @return bool
     */
    public function detect()
    {
        return (
            (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https')
        );
    }
}