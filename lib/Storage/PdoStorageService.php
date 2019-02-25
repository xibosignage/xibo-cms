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

use Xibo\Exception\DeadlockException;
use Xibo\Service\ConfigService;
use Xibo\Service\LogService;

/**
 * Class PDOConnect
 * Manages global connection state and the creation of connections
 * @package Xibo\Storage
 */
class PdoStorageService implements StorageServiceInterface
{
    /**
     * @var \PDO[] The connection
     */
	private $conn = [];

    /** @var array Statistics */
    private static $stats = [];

    /** @var  string */
    private static $_version;

    /**
     * Logger
     * @var LogService
     */
    private $log;

    /**
     * PDOConnect constructor.
     * @param LogService $logger
     */
	public function __construct($logger = null)
    {
        $this->log = $logger;
    }

    /** @inheritdoc */
    public function setConnection($name = 'default')
    {
        // Create a new connection
        $this->conn[$name] = PdoStorageService::newConnection();
        return $this;
    }

    /** @inheritdoc */
    public function close($name = null)
    {
        if ($name !== null && isset($this->conn[$name])) {
            $this->conn[$name] = null;
            unset($this->conn[$name]);
        } else {
            foreach ($this->conn as &$conn) {
                $conn = null;
            }
            $this->conn = [];
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
        $dsn = PdoStorageService::createDsn(ConfigService::$dbConfig['host'], ConfigService::$dbConfig['name']);

		// Open the connection and set the error mode
		$conn = new \PDO($dsn, ConfigService::$dbConfig['user'], ConfigService::$dbConfig['password']);
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
	public function connect($host, $user, $pass, $name = null)
    {
        if (!isset($this->conn['default']))
		    $this->close('default');

        $dsn = PdoStorageService::createDsn($host, $name);

        // Open the connection and set the error mode
		$this->conn['default'] = new \PDO($dsn, $user, $pass);
		$this->conn['default']->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->conn['default']->query("SET NAMES 'utf8'");

		return $this->conn['default'];
	}

    /** @inheritdoc */
    public function getConnection($name = 'default')
    {
        if (!isset($this->conn[$name]))
            $this->conn[$name] = PdoStorageService::newConnection();

        return $this->conn[$name];
    }

    /** @inheritdoc */
    public function exists($sql, $params, $connection = null, $reconnect = false)
    {
        if ($this->log != null)
            $this->log->sql($sql, $params);

        if ($connection === null)
            $connection = 'default';

        try {
            $sth = $this->getConnection($connection)->prepare($sql);
            $sth->execute($params);

            $this->incrementStat($connection, 'exists');

            if ($sth->fetch())
                return true;
            else
                return false;

        } catch (\PDOException $PDOException) {
            // Throw if we're not expected to reconnect.
            if (!$reconnect)
                throw $PDOException;

            $errorCode = isset($PDOException->errorInfo[1]) ? $PDOException->errorInfo[1] : $PDOException->getCode();

            if ($errorCode != 2006) {
                throw $PDOException;
            } else {
                $this->close($connection);
                return $this->exists($sql, $params, $connection, false);
            }
        }
    }

    /** @inheritdoc */
    public function insert($sql, $params, $connection = null, $reconnect = false)
	{
        if ($this->log != null)
            $this->log->sql($sql, $params);

        if ($connection === null)
            $connection = 'default';

        try {
            if (!$this->getConnection($connection)->inTransaction())
                $this->getConnection($connection)->beginTransaction();

            $sth = $this->getConnection($connection)->prepare($sql);

            $sth->execute($params);

            $this->incrementStat($connection, 'insert');

            return intval($this->getConnection($connection)->lastInsertId());

        } catch (\PDOException $PDOException) {
            // Throw if we're not expected to reconnect.
            if (!$reconnect)
                throw $PDOException;

            $errorCode = isset($PDOException->errorInfo[1]) ? $PDOException->errorInfo[1] : $PDOException->getCode();

            if ($errorCode != 2006) {
                throw $PDOException;
            } else {
                $this->close($connection);
                return $this->insert($sql, $params, $connection, false);
            }
        }
    }

	/** @inheritdoc */
	public function update($sql, $params, $connection = null, $reconnect = false)
	{
        if ($this->log != null)
            $this->log->sql($sql, $params);

        if ($connection === null)
            $connection = 'default';

        try {
            if (!$this->getConnection($connection)->inTransaction())
                $this->getConnection($connection)->beginTransaction();

            $sth = $this->getConnection($connection)->prepare($sql);

            $sth->execute($params);

            $rows = $sth->rowCount();

            $this->incrementStat($connection, 'update');

            return $rows;

        } catch (\PDOException $PDOException) {
            // Throw if we're not expected to reconnect.
            if (!$reconnect)
                throw $PDOException;

            $errorCode = isset($PDOException->errorInfo[1]) ? $PDOException->errorInfo[1] : $PDOException->getCode();

            if ($errorCode != 2006) {
                throw $PDOException;
            } else {
                $this->close($connection);
                return $this->update($sql, $params, $connection, false);
            }
        }
	}

    /** @inheritdoc */
	public function select($sql, $params, $connection = null, $reconnect = false)
	{
        if ($this->log != null)
            $this->log->sql($sql, $params);

        if ($connection === null)
            $connection = 'default';

        try {
            $sth = $this->getConnection($connection)->prepare($sql);

            $sth->execute($params);

            $this->incrementStat($connection, 'select');

            return $sth->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\PDOException $PDOException) {
            // Throw if we're not expected to reconnect.
            if (!$reconnect)
                throw $PDOException;

            $errorCode = isset($PDOException->errorInfo[1]) ? $PDOException->errorInfo[1] : $PDOException->getCode();

            if ($errorCode != 2006) {
                throw $PDOException;
            } else {
                $this->close($connection);
                return $this->select($sql, $params, $connection, false);
            }
        }
	}

	/** @inheritdoc */
	public function isolated($sql, $params, $connection = null, $reconnect = false)
    {
        // Should we log?
        if ($this->log != null)
            $this->log->sql($sql, $params);

        if ($connection === null)
            $connection = 'isolated';

        try {
            $sth = $this->getConnection($connection)->prepare($sql);

            $sth->execute($params);

            $this->incrementStat('isolated', 'update');

        } catch (\PDOException $PDOException) {
            // Throw if we're not expected to reconnect.
            if (!$reconnect)
                throw $PDOException;

            $errorCode = isset($PDOException->errorInfo[1]) ? $PDOException->errorInfo[1] : $PDOException->getCode();

            if ($errorCode != 2006) {
                throw $PDOException;
            } else {
                $this->close($connection);
                return $this->isolated($sql, $params, $connection, false);
            }
        }
    }

    /** @inheritdoc */
    public function updateWithDeadlockLoop($sql, $params, $connection = null)
    {
        $maxRetries = 2;

        // Should we log?
        if ($this->log != null)
            $this->log->sql($sql, $params);

        if ($connection === null)
            $connection = 'isolated';

        // Prepare the statement
        $statement = $this->getConnection($connection)->prepare($sql);

        // Deadlock protect this statement
        $success = false;
        $retries = $maxRetries;
        do {
            try {
                $this->incrementStat($connection, 'update');
                $statement->execute($params);
                // Successful
                $success = true;

            } catch (\PDOException $PDOException) {
                $errorCode = isset($PDOException->errorInfo[1]) ? $PDOException->errorInfo[1] : $PDOException->getCode();

                if ($errorCode != 1213 && $errorCode != 1205)
                    throw $PDOException;
            }

            if ($success)
                break;

            // Sleep a bit, give the DB time to breathe
            $queryHash = substr($sql, 0, 15) . '... [' . md5($sql . json_encode($params)) . ']';
            $this->log->debug('Retrying query after a short nap, try: ' . (3 - $retries) . '. Query Hash: ' . $queryHash);
            usleep(10000);

        } while ($retries--);

        if (!$success)
            throw new DeadlockException(__('Failed to write to database after %d retries. Please try again later.', $maxRetries));
    }

    /** @inheritdoc */
    public function commitIfNecessary($name = 'default')
    {
        if ($this->getConnection($name)->inTransaction()) {
            $this->incrementStat($name, 'commit');
            $this->getConnection($name)->commit();
        }
    }

    /**
     * Set the TimeZone for this connection
     * @param string $timeZone e.g. -8:00
     * @param string $connection
     */
    public function setTimeZone($timeZone, $connection = 'default')
    {
        $this->getConnection($connection)->query('SET time_zone = \'' . $timeZone . '\';');

        $this->incrementStat($connection, 'utility');
	}

    /**
     * PDO stats
     * @return array
     */
    public function stats()
    {
        return self::$stats;
    }

    /** @inheritdoc */
    public function incrementStat($connection, $key)
    {
        $currentCount = (isset(self::$stats[$connection][$key])) ? self::$stats[$connection][$key] : 0;
        self::$stats[$connection][$key] = $currentCount + 1;
    }

    /**
     * Statically increment stats
     * @param $connection
     * @param $key
     */
    public static function incrementStatStatic($connection, $key)
    {
        $currentCount = (isset(self::$stats[$connection][$key])) ? self::$stats[$connection][$key] : 0;
        self::$stats[$connection][$key] = $currentCount + 1;
    }

    /**
     * @inheritdoc
     */
    public function getVersion()
    {
        if (self::$_version === null) {

            $results = $this->select('SELECT version() AS v', []);

            if (count($results) <= 0)
                return null;

            self::$_version = explode('-', $results[0]['v'])[0];
        }

        return self::$_version;
    }
}