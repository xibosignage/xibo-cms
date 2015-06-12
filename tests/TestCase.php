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
}