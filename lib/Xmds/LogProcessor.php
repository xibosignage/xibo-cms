<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (LogProcessor.php)
 */


namespace Xibo\Xmds;

use Slim\Log;

/**
 * Class LogProcessor
 * @package Xibo\Xmds
 */
class LogProcessor
{
    /** @var  Log */
    private $log;
    private $displayId;
    private $route;
    private $method;

    /**
     * Log Processor
     * @param Log $log
     * @param string $method
     */
    public function __construct($log, $method = 'POST')
    {
        $this->log = $log;
        $this->method = $method;
    }

    /**
     * @param $route
     */
    public function setRoute($route)
    {
        $this->route = $route;
    }

    /**
     * @param $displayId
     * @param bool $isAuditing
     */
    public function setDisplay($displayId, $isAuditing)
    {
        if ($isAuditing)
            $this->log->setLevel(\Xibo\Service\LogService::resolveLogLevel('debug'));

        $this->displayId = $displayId;
    }

    /**
     * @param array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        $record['extra']['displayId'] = $this->displayId;
        $record['extra']['route'] = $this->route;
        $record['extra']['method'] = $this->method;

        return $record;
    }
}