<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

Class PDOConnect {

	private static $conn = NULL;

	private function __construct() {}

	public static function init() {
		if (!self::$conn) {
			self::$conn = PDOConnect::newConnection();
		}

		return self::$conn;
	}

	public static function newConnection() {
		global $dbhost;
		global $dbuser;
		global $dbpass;
		global $dbname;

		$dbport = '';

		if (strstr($dbhost, ':')) {
			$hostParts = explode(':', $dbhost);
			$dsn = 'mysql:host=' . $hostParts[0] . ';port=' . $hostParts[1] . ';dbname=' . $dbname . ';';
		}
		else {
			$dsn = 'mysql:host=' . $dbhost . ';dbname=' . $dbname . ';';
		}

		//echo 'init ' . $dsn , ' user ' . $dbuser . ' pass ' . $dbpass;

		// Open the connection and set the error mode
		$conn = new PDO($dsn, $dbuser, $dbpass);
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$conn->query("SET NAMES 'utf8'");

		return $conn;
	}

	public static function connect($dbhost, $dbuser, $dbpass, $dbname = '') {
		if (!self::$conn) {
			self::close();
		}
			
		$dbport = '';

		if (strstr($dbhost, ':')) {
			$hostParts = explode(':', $dbhost);
			$dsn = 'mysql:host=' . $hostParts[0] . ';port=' . $hostParts[1] . ';';
		}
		else {
			$dsn = 'mysql:host=' . $dbhost . ';';
		}

		if ($dbname != '')
			$dsn .= 'dbname=' . $dbname . ';';

		//echo 'connect ' . $dsn , ' user ' . $dbuser . ' pass ' . $dbpass;

		// Open the connection and set the error mode
		self::$conn = new PDO($dsn, $dbuser, $dbpass);
		self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		self::$conn->query("SET NAMES 'utf8'");
		

		return self::$conn;
	}

	public static function close() {
		if (self::$conn) {
			self::$conn = null;
		}
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
}