<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (StorageInterface.php)
 */


namespace Xibo\Storage;

use Xibo\Service\LogService;

/**
 * Interface StorageInterface
 * @package Xibo\Storage
 */
interface StorageServiceInterface
{
    /**
     * PDOConnect constructor.
     * @param LogService $logger
     */
    public function __construct($logger);

    /**
     * @return $this
     */
    public function setConnection();

    /**
     * Closes the stored connection
     */
    public function close();

    /**
     * Open a new connection using the stored details
     * @return \PDO
     */
    public static function newConnection();

    /**
     * Open a connection with the specified details
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param string[Optional] $name
     * @return \PDO
     */
    public function connect($host, $user, $pass, $name = null);

    /**
     * Get the Raw Connection
     * @return \PDO
     */
    public function getConnection();

    /**
     * Check to see if the query returns records
     * @param string $sql
     * @param array[mixed] $params
     * @return bool
     */
    public function exists($sql, $params);

    /**
     * Run Insert SQL
     * @param string $sql
     * @param array $params
     * @return int
     * @throws \PDOException
     */
    public function insert($sql, $params);

    /**
     * Run Update SQL
     * @param string $sql
     * @param array $params
     * @throws \PDOException
     */
    public function update($sql, $params);

    /**
     * Run Select SQL
     * @param $sql
     * @param $params
     * @return array
     * @throws \PDOException
     */
    public function select($sql, $params);

    /**
     * Run SQL in an isolated connection/transaction
     * @param $sql
     * @param $params
     * @return mixed
     */
    public function isolated($sql, $params);

    /**
     * Commit if necessary
     */
    public function commitIfNecessary();

    /**
     * Set the TimeZone for this connection
     * @param \PDO $connection
     * @param string $timeZone e.g. -8:00
     */
    public function setTimeZone($timeZone, $connection = null);

    /**
     * PDO stats
     * @return array
     */
    public static function stats();
}