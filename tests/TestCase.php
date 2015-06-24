<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (TestCase.php)
 */

namespace Xibo\Tests;

class TestCase extends \PHPUnit_Framework_TestCase
{
    private $url = '';

    protected function start()
    {
        $this->url = 'http://172.28.128.3/api';
    }

    protected function url($url, $data = [])
    {
         return $this->url . $url . ((is_array($data) && count($data) > 0) ? '?' . http_build_query($data) : '');
    }
}