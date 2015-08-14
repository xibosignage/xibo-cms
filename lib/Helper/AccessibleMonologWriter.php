<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (AccessibleMonologWriter.php)
 */


namespace Xibo\Helper;


use Flynsarmy\SlimMonolog\Log\MonologWriter;

class AccessibleMonologWriter extends MonologWriter
{
    public function getWriter()
    {
        return $this->resource;
    }

    public function addProcessor($processor) {
        $this->settings['processors'][] = $processor;
    }
}