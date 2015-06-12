<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (TestCase.php)
 */


class TestCase extends PHPUnit_Framework_TestCase
{
    private $url = '';

    protected function start()
    {
        $this->url = 'http://172.28.128.3/api';
    }

    protected function url($url)
    {
         return $this->url . $url;
    }

    /**
     * @param int $length
     * @return string
     */
    protected function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}