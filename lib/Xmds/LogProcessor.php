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
    private $uid;

    /**
     * Log Processor
     * @param Log $log
     * @param string $method
     */
    public function __construct($log, $uid, $method = 'POST')
    {
        $this->log = $log;
        $this->uid = $uid;
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
     * Get Log Level
     * @return int
     */
    public function getLevel()
    {
        return $this->log->getLevel();
    }

    /**
     * Get UID
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
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