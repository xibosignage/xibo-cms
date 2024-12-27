<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\Service;

use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Class NullLogService
 * @package Xibo\Service
 */
class NullLogService implements LogServiceInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $log;

    /**
     * @inheritdoc
     */
    public function __construct($logger, $mode = 'production')
    {
        $this->log = $logger;
    }

    /** @inheritDoc */
    public function getLoggerInterface(): LoggerInterface
    {
        return $this->log;
    }

    /**
     * @inheritdoc
     */
    public function setUserId($userId)
    {
       //
    }

    /**
     * @inheritdoc
     */
    public function setIpAddress($ip)
    {
       //
    }

    /**
     * @inheritdoc
     */
    public function setMode($mode)
    {
       //
    }

    /**
     * @inheritdoc
     */
    public function audit($entity, $entityId, $message, $object)
    {
        //
    }

    /**
     * @param $sql
     * @param $params
     * @param false $logAsError
     * @inheritdoc
     */
    public function sql($sql, $params, $logAsError = false)
    {
       //
    }

    /**
     * @inheritdoc
     */
    public function debug($object)
    {
        // Get the calling class / function
        $this->log->debug($this->prepare($object, func_get_args()));
    }

    /**
     * @inheritdoc
     */
    public function notice($object)
    {
        $this->log->notice($this->prepare($object, func_get_args()));
    }

    /**
     * @inheritdoc
     */
    public function info($object)
    {
        $this->log->info($this->prepare($object, func_get_args()));
    }

    /**
     * @inheritdoc
     */
    public function warning($object)
    {
        $this->log->warning($this->prepare($object, func_get_args()));
    }

    /**
     * @inheritdoc
     */
    public function error($object)
    {
        $this->log->error($this->prepare($object, func_get_args()));
    }

    /**
     * @inheritdoc
     */
    public function critical($object)
    {
        $this->log->critical($this->prepare($object, func_get_args()));
    }

    /**
     * @inheritdoc
     */
    public function alert($object)
    {
        $this->log->alert($this->prepare($object, func_get_args()));
    }

    /**
     * @inheritdoc
     */
    public function emergency($object)
    {
        $this->log->emergency($this->prepare($object, func_get_args()));
    }

    /**
     * @inheritdoc
     */
    private function prepare($object, $args)
    {
        if (is_string($object)) {
            array_shift($args);

            if (count($args) > 0)
                $object = vsprintf($object, $args);
        }

        return $object;
    }

    /**
     * @inheritdoc
     */
    public static function resolveLogLevel($level)
    {
        switch (strtolower($level)) {

            case 'emergency':
                return Logger::EMERGENCY;

            case 'alert':
                return Logger::ALERT;

            case 'critical':
                return Logger::CRITICAL;

            case 'warning':
                return Logger::WARNING;

            case 'notice':
                return Logger::NOTICE;

            case 'info':
                return Logger::INFO;

            case 'debug':
                return Logger::DEBUG;

            case 'error':
            default:
                return Logger::ERROR;
        }
    }

    /** @inheritDoc */
    public function setLevel($level)
    {
        //
    }

    public function getUserId(): ?int
    {
        return null;
    }

    public function getSessionHistoryId(): ?int
    {
        return null;
    }

    public function getRequestId(): ?int
    {
        return null;
    }

    public function setSessionHistoryId($sessionHistoryId)
    {
        //
    }

    public function setRequestId($requestId)
    {
        //
    }
}
