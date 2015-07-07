<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (LogProcessor.php)
 */


namespace Xibo\Xmds;

class LogProcessor
{
    private $displayId;
    private $route;
    private $method;

    /**
     * Log Processor
     * @param string $method
     */
    public function __construct($method = 'POST')
    {
        $this->method = $method;
    }

    public function setRoute($route)
    {
        $this->route = $route;
    }

    public function setDisplay($displayId)
    {
        $this->displayId = $displayId;
    }

    public function __invoke(array $record)
    {
        $record['extra']['displayId'] = $this->displayId;
        $record['extra']['route'] = $this->route;
        $record['extra']['method'] = $this->method;

        return $record;
    }
}