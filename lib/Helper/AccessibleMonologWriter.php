<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (AccessibleMonologWriter.php)
 */


namespace Xibo\Helper;


use Flynsarmy\SlimMonolog\Log\MonologWriter;
use Monolog\Logger;

class AccessibleMonologWriter extends MonologWriter
{
    /**
     * get the writer
     * @return Logger resource
     */
    public function getWriter()
    {
        return $this->resource;
    }

    public function addHandler($handler) {
        if (!$this->resource)
            $this->settings['handlers'][] = $handler;
        else
            $this->getWriter()->pushHandler($handler);
    }

    public function addProcessor($processor) {
        if (!$this->resource)
            $this->settings['processors'][] = $processor;
        else
            $this->getWriter()->pushProcessor($processor);
    }
}