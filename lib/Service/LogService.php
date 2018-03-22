<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Log.php) is part of Xibo.
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


use Xibo\Storage\PdoStorageService;

/**
 * Class LogService
 * @package Xibo\Service
 */
class LogService implements LogServiceInterface
{
    /**
     * @var \Slim\Log
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
     * Audit Log Statement
     * @var \PDOStatement
     */
    private $_auditLogStatement;

    /**
     * @inheritdoc
     */
    public function __construct($logger, $mode = 'production')
    {
        $this->log = $logger;
        $this->mode = $mode;
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
    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    /**
     * @inheritdoc
     */
    public function audit($entity, $entityId, $message, $object)
    {
        $this->debug(sprintf('Audit Trail message recorded for %s with id %d. Message: %s', $entity, $entityId, $message));

        if ($this->_auditLogStatement == null) {
            $dbh = PdoStorageService::newConnection();
            $this->_auditLogStatement = $dbh->prepare('
                INSERT INTO `auditlog` (logDate, userId, entity, message, entityId, objectAfter)
                  VALUES (:logDate, :userId, :entity, :message, :entityId, :objectAfter)
            ');
        }

        // If we aren't a string then encode
        if (!is_string($object))
            $object = json_encode($object);

        PdoStorageService::incrementStatStatic('auditlog', 'insert');

        $this->_auditLogStatement->execute([
            'logDate' => time(),
            'userId' => $this->userId,
            'entity' => $entity,
            'message' => $message,
            'entityId' => $entityId,
            'objectAfter' => $object
        ]);
    }

    /**
     * @inheritdoc
     */
    public function sql($sql, $params)
    {
        if (strtolower($this->mode) == 'test') {
            $paramSql = '';
            foreach ($params as $key => $param) {
                $paramSql .= 'SET @' . $key . '=\'' . $param . '\';' . PHP_EOL;
            }
            $this->log->debug($paramSql . str_replace(':', '@', $sql));
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
                return \Slim\Log::EMERGENCY;

            case 'alert':
                return \Slim\Log::ALERT;

            case 'critical':
                return \Slim\Log::CRITICAL;

            case 'error':
                return \Slim\Log::ERROR;

            case 'warning':
                return \Slim\Log::WARN;

            case 'notice':
                return \Slim\Log::NOTICE;

            case 'info':
                return \Slim\Log::INFO;

            case 'debug':
                return \Slim\Log::DEBUG;

            default:
                return \Slim\Log::ERROR;
        }
    }
}
