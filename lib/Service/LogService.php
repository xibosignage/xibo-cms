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

use Carbon\Carbon;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Xibo\Helper\DatabaseLogHandler;
use Xibo\Storage\PdoStorageService;

/**
 * Class LogService
 * @package Xibo\Service
 */
class LogService implements LogServiceInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $log;

    /**
     * The Log Mode
     * @var string
     */
    private $mode;

    /**
     * The user Id
     * @var int
     */
    private $userId = 0;

    /**
     * The User IP Address
     */
    private $ipAddress;

    /**
     * Audit Log Statement
     * @var \PDOStatement
     */
    private $_auditLogStatement;

    /**
     * The History session id.
     */
    private $sessionHistoryId = 0;

    /**
     * The API requestId.
     */
    private $requestId = 0;

    /**
     * @inheritdoc
     */
    public function __construct($logger, $mode = 'production')
    {
        $this->log = $logger;
        $this->mode = $mode;
    }

    /** @inheritDoc */
    public function getLoggerInterface(): LoggerInterface
    {
        return $this->log;
    }

    /**
     * @inheritdoc
     */
    public function setIpAddress($ip)
    {
        $this->ipAddress = $ip;
    }

    /**
     * @inheritdoc
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    /**
     * @inheritdoc
     */
    public function setSessionHistoryId($sessionHistoryId)
    {
        $this->sessionHistoryId = $sessionHistoryId;
    }

    public function setRequestId($requestId)
    {
        $this->requestId = $requestId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getSessionHistoryId(): ?int
    {
        return $this->sessionHistoryId;
    }

    public function getRequestId(): ?int
    {
        return $this->requestId;
    }

    /**
     * @inheritdoc
     */
    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    /**
     * @inheritdoc
     */
    public function audit($entity, $entityId, $message, $object)
    {
        $this->debug(sprintf(
            'Audit Trail message recorded for %s with id %d. Message: %s from IP %s, session %d',
            $entity,
            $entityId,
            $message,
            $this->ipAddress,
            $this->sessionHistoryId
        ));

        if ($this->_auditLogStatement == null) {
            // Use the default connection
            //  audit log should rollback on failure.
            $dbh = PdoStorageService::newConnection('default');
            $this->_auditLogStatement = $dbh->prepare('
                INSERT INTO `auditlog` (
                    `logDate`,
                    `userId`,
                    `entity`,
                    `message`,
                    `entityId`,
                    `objectAfter`,
                    `ipAddress`,
                    `sessionHistoryId`,
                    `requestId`
                )
                VALUES (
                    :logDate,
                    :userId,
                    :entity,
                    :message,
                    :entityId,
                    :objectAfter,
                    :ipAddress,
                    :sessionHistoryId,
                    :requestId
                )
            ');
        }

        // If we aren't a string then encode
        if (!is_string($object)) {
            $object = json_encode($object);
        }

        // Although we use the default connection, track audit status separately.
        PdoStorageService::incrementStat('audit', 'insert');

        $this->_auditLogStatement->execute([
            'logDate' => Carbon::now()->format('U'),
            'userId' => $this->userId,
            'entity' => $entity,
            'message' => $message,
            'entityId' => $entityId,
            'ipAddress' => $this->ipAddress,
            'objectAfter' => $object,
            'sessionHistoryId' => $this->sessionHistoryId,
            'requestId' => $this->requestId
        ]);
    }

    /**
     * @inheritdoc
     */
    public function sql($sql, $params, $logAsError = false)
    {
        if (strtolower($this->mode) == 'test' || $logAsError) {
            $paramSql = '';
            foreach ($params as $key => $param) {
                $paramSql .= 'SET @' . $key . '=\'' . $param . '\';' . PHP_EOL;
            }

            ($logAsError)
                ? $this->log->error($paramSql . str_replace(':', '@', $sql))
                : $this->log->debug($paramSql . str_replace(':', '@', $sql));
        }
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
        foreach ($this->log->getHandlers() as $handler) {
            if ($handler instanceof DatabaseLogHandler) {
                $handler->setLevel($level);
            }
        }
    }
}
