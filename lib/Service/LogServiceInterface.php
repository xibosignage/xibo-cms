<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

/**
 * Interface LogServiceInterface
 * @package Xibo\Service
 */
interface LogServiceInterface
{
    /**
     * Log constructor.
     * @param Logger $logger
     * @param string $mode
     */
    public function __construct($logger, $mode = 'production');

    /**
     * Set the user Id
     * @param int $userId
     */
    public function setUserId($userId);

    /**
     * Set the User IP Address
     * @param $ip
     * @return mixed
     */
    public function setIpAddress($ip);

    /**
     * @param $mode
     * @return mixed
     */
    public function setMode($mode);

    /**
     * Audit Log
     * @param string $entity
     * @param int $entityId
     * @param string $message
     * @param string|object|array $object
     */
    public function audit($entity, $entityId, $message, $object);

    /**
     * @param $sql
     * @param $params
     * @return mixed
     */
    public function sql($sql, $params);

    /**
     * @param string
     * @return mixed
     */
    public function debug($object);

    /**
     * @param ...$object
     * @return mixed
     */
    public function notice($object);

    /**
     * @param ...$object
     * @return mixed
     */
    public function info($object);

    /**
     * @param ...$object
     * @return mixed
     */
    public function warning($object);

    /**
     * @param ...$object
     * @return mixed
     */
    public function error($object);

    /**
     * @param ...$object
     * @return mixed
     */
    public function critical($object);

    /**
     * @param ...$object
     * @return mixed
     */
    public function alert($object);

    /**
     * @param ...$object
     * @return mixed
     */
    public function emergency($object);

    /**
     * Resolve the log level
     * @param string $level
     * @return int
     */
    public static function resolveLogLevel($level);

    /**
     * Set the log level on all handlers
     * @param $level
     */
    public function setLevel($level);
}