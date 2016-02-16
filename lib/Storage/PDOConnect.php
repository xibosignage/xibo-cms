<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
 *
 * This file (PDOConnect.php) is part of Xibo.
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

namespace Xibo\Storage;

use Xibo\Helper\Config;

/**
 * Class PDOConnect
 * Manages global connection state and the creation of connections
 * @package Xibo\Storage
 */
class PDOConnect
{
    /**
     * @var \PDO The connection
     */
	private static $conn = NULL;

	private function __construct() {}

	/**
	 * Opens a connection using the Stored Credentials and store it globally
	 * @return \PDO
	 */
	public static function init()
    {
		if (!self::$conn) {
			self::$conn = PDOConnect::newConnection();
		}

		return self::$conn;
	}

    /**
     * Closes the stored connection
     */
    public static function close()
    {
        if (self::$conn) {
            self::$conn = null;
        }
    }

    /**
     * Create a DSN from the host/db name
     * @param string $host
     * @param string[Optional] $name
     * @return string
     */
    private static function createDsn($host, $name = null)
    {
        if (strstr($host, ':')) {
            $hostParts = explode(':', $host);
            $dsn = 'mysql:host=' . $hostParts[0] . ';port=' . $hostParts[1] . ';';
        }
        else {
            $dsn = 'mysql:host=' . $host . ';';
        }

        if ($name != null)
            $dsn .= 'dbname=' . $name . ';';

        return $dsn;
    }

    /**
     * Open a new connection using the stored details
     * @return \PDO
     */
	public static function newConnection()
    {
        $dsn = PDOConnect::createDsn(Config::$dbConfig['host'], Config::$dbConfig['name']);

		// Open the connection and set the error mode
		$conn = new \PDO($dsn, Config::$dbConfig['user'], Config::$dbConfig['password']);
		$conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

		$conn->query("SET NAMES 'utf8'");

		return $conn;
	}

    /**
     * Open a connection with the specified details
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param string[Optional] $name
     * @return \PDO
     */
	public static function connect($host, $user, $pass, $name = null)
    {
		if (!self::$conn) {
			self::close();
		}

        $dsn = PDOConnect::createDsn($host, $name);

        // Open the connection and set the error mode
		self::$conn = new \PDO($dsn, $user, $pass);
		self::$conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

		self::$conn->query("SET NAMES 'utf8'");
		

		return self::$conn;
	}

    /**
     * Check to see if the query returns records
     * @param string $sql
     * @param array[mixed] $params
     * @return bool
     */
    public static function exists($sql, $params)
    {
        $dbh = PDOConnect::init();
        $sth = $dbh->prepare($sql);
        $sth->execute($params);

        if ($sth->fetch())
            return true;
        else
            return false;
    }
    
    /**
     * Run Insert SQL
     * @param string $sql
     * @param array $params
     * @param \PDO[Optional] $dbh
     * @return int
     * @throws \PDOException
     */
    public static function insert($sql, $params, $dbh = null)
	{
        $transaction = false;

        if ($dbh == null) {
            $dbh = PDOConnect::init();
            $transaction = true;
        }

        if ($transaction && !$dbh->inTransaction())
            $dbh->beginTransaction();

        $sth = $dbh->prepare($sql);

        $sth->execute($params);

        return intval($dbh->lastInsertId());
    }

	/**
	 * Run Update SQL
	 * @param string $sql
	 * @param array $params
     * @param \PDO[Optional] $dbh
	 * @throws \PDOException
	 */
	public static function update($sql, $params, $dbh = null)
	{
        $transaction = false;

        if ($dbh == null) {
            $dbh = PDOConnect::init();
            $transaction = true;
        }

        if ($transaction && !$dbh->inTransaction())
            $dbh->beginTransaction();

        $sth = $dbh->prepare($sql);

        $sth->execute($params);
	}

	/**
	 * Run Select SQL
	 * @param $sql
	 * @param $params
	 * @return array
	 * @throws \PDOException
	 */
	public static function select($sql, $params)
	{
        $dbh = PDOConnect::init();
        $sth = $dbh->prepare($sql);

        $sth->execute($params);

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
     * Set the TimeZone for this connection
	 * @param \PDO $connection
	 * @param string $timeZone e.g. -8:00
	 */
	public static function setTimeZone($timeZone, $connection = null)
	{
        if ($connection == null)
            $connection = PDOConnect::init();

		$connection->query('SET time_zone = \'' . $timeZone . '\';');
	}
}