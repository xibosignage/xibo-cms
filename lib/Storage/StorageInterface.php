<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (StorageInterface.php)
 */


namespace Xibo\Storage;


use Xibo\Helper\Log;

interface StorageInterface
{
    /**
     * PDOConnect constructor.
     * @param Log $logger
     */
    public function __construct($logger);

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
     * @param \PDO[Optional] $dbh
     * @return int
     * @throws \PDOException
     */
    public function insert($sql, $params, $dbh = null);

    /**
     * Run Update SQL
     * @param string $sql
     * @param array $params
     * @param \PDO[Optional] $dbh
     * @throws \PDOException
     */
    public function update($sql, $params, $dbh = null);

    /**
     * Run Select SQL
     * @param $sql
     * @param $params
     * @return array
     * @throws \PDOException
     */
    public function select($sql, $params);

    /**
     * Commit if necessary
     * @param \PDO $pdo
     */
    public function commitIfNecessary($pdo = null);

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