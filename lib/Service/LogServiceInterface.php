<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (LogServiceInterface.php)
 */


namespace Xibo\Service;

/**
 * Interface LogServiceInterface
 * @package Xibo\Service
 */
interface LogServiceInterface
{
    /**
     * Log constructor.
     * @param \Slim\Log $logger
     * @param string $mode
     */
    public function __construct($logger, $mode = 'production');

    /**
     * Set the user Id
     * @param int $userId
     */
    public function setUserId($userId);

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
}