<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (StorageInterface.php)
 */


namespace Xibo\Storage;

use Xibo\Exception\DeadlockException;
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
     * Set a connection
     * @param string $name
     * @return $this
     */
    public function setConnection($name = 'default');

    /**
     * Closes the stored connection
     * @param string|null $name The name of the connection, or null for all connections
     */
    public function close($name = null);

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
     * @param string $name The connection name
     * @return \PDO
     */
    public function getConnection($name = 'default');

    /**
     * Check to see if the query returns records
     * @param string $sql
     * @param array[mixed] $params
     * @param string|null $connection
     * @param bool $reconnect
     * @return bool
     */
    public function exists($sql, $params, $connection = null, $reconnect = false);

    /**
     * Run Insert SQL
     * @param string $sql
     * @param array $params
     * @param string|null $connection
     * @param bool $reconnect
     * @return int
     * @throws \PDOException
     */
    public function insert($sql, $params, $connection = null, $reconnect = false);

    /**
     * Run Update SQL
     * @param string $sql
     * @param array $params
     * @param string|null $connection
     * @param bool $reconnect
     * @return int affected rows
     * @throws \PDOException
     */
    public function update($sql, $params, $connection = null, $reconnect = false);

    /**
     * Run Select SQL
     * @param $sql
     * @param $params
     * @param string|null $connection
     * @param bool $reconnect
     * @return array
     * @throws \PDOException
     */
    public function select($sql, $params, $connection = null, $reconnect = false);

    /**
     * Run SQL in an isolated connection/transaction
     * @param $sql
     * @param $params
     * @param string|null $connection
     * @param bool $reconnect
     * @return mixed
     */
    public function isolated($sql, $params, $connection = null, $reconnect = false);

    /**
     * Run the SQL statement with a deadlock loop
     * @param $sql
     * @param $params
     * @param string|null $connection
     * @return mixed
     * @throws DeadlockException
     */
    public function updateWithDeadlockLoop($sql, $params, $connection = null);

    /**
     * Commit if necessary
     * @param $name
     */
    public function commitIfNecessary($name = 'default');

    /**
     * Set the TimeZone for this connection
     * @param string|null $connection
     * @param string $timeZone e.g. -8:00
     */
    public function setTimeZone($timeZone, $connection = null);

    /**
     * PDO stats
     * @return array
     */
    public function stats();

    /**
     * @param $connection
     * @param $key
     * @return mixed
     */
    public function incrementStat($connection, $key);

    /**
     * Get the Storage engine version
     * @return string
     */
    public function getVersion();
}