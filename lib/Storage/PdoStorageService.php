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

namespace Xibo\Storage;

use Xibo\Service\ConfigService;
use Xibo\Service\LogService;
use Xibo\Support\Exception\DeadlockException;

/**
 * Class PDOConnect
 * Manages global connection state and the creation of connections
 * @package Xibo\Storage
 */
class PdoStorageService implements StorageServiceInterface
{
    /** @var \PDO[] An array of connections */
    private static $conn = [];

    /** @var array Statistics */
    private static $stats = [];

    /** @var  string */
    private static $version;

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
        self::$conn[$name] = PdoStorageService::newConnection($name);
        return $this;
    }

    /** @inheritdoc */
    public function close($name = null)
    {
        if ($name !== null && isset(self::$conn[$name])) {
            self::$conn[$name] = null;
            unset(self::$conn[$name]);
        } else {
            foreach (self::$conn as &$conn) {
                $conn = null;
            }
            self::$conn = [];
        }
    }

    /**
     * Create a DSN from the host/db name
     * @param string $host
     * @param string|null $name
     * @return string
     */
    private static function createDsn($host, $name = null)
    {
        if (strstr($host, ':')) {
            $hostParts = explode(':', $host);
            $dsn = 'mysql:host=' . $hostParts[0] . ';port=' . $hostParts[1] . ';';
        } else {
            $dsn = 'mysql:host=' . $host . ';';
        }

        if ($name != null) {
            $dsn .= 'dbname=' . $name . ';';
        }

        return $dsn;
    }

    /**
     * @inheritDoc
     */
    public static function newConnection(string $name)
    {
        // If we already have a connection, return it.
        if (isset(self::$conn[$name])) {
            return self::$conn[$name];
        }

        $dsn = PdoStorageService::createDsn(ConfigService::$dbConfig['host'], ConfigService::$dbConfig['name']);

        $opts = [];
        if (!empty(ConfigService::$dbConfig['ssl']) && ConfigService::$dbConfig['ssl'] !== 'none') {
            $opts[\PDO::MYSQL_ATTR_SSL_CA] = ConfigService::$dbConfig['ssl'];
            $opts[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = ConfigService::$dbConfig['sslVerify'];
        }

        // Open the connection and set the error mode
        $conn = new \PDO(
            $dsn,
            ConfigService::$dbConfig['user'],
            ConfigService::$dbConfig['password'],
            $opts
        );
        $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $conn->query("SET NAMES 'utf8mb4'");

        return $conn;
    }

    /** @inheritDoc */
    public function connect($host, $user, $pass, $name = null, $ssl = null, $sslVerify = true)
    {
        if (!isset(self::$conn['default'])) {
            $this->close('default');
        }

        $dsn = PdoStorageService::createDsn($host, $name);

        $opts = [];
        if (!empty($ssl) && $ssl !== 'none') {
            $opts[\PDO::MYSQL_ATTR_SSL_CA] = $ssl;
            $opts[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $sslVerify;
        }

        // Open the connection and set the error mode
        self::$conn['default'] = new \PDO($dsn, $user, $pass, $opts);
        self::$conn['default']->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::$conn['default']->query("SET NAMES 'utf8mb4'");

        return self::$conn['default'];
    }

    /** @inheritdoc */
    public function getConnection($name = 'default')
    {
        if (!isset(self::$conn[$name])) {
            self::$conn[$name] = PdoStorageService::newConnection($name);
        }

        return self::$conn[$name];
    }

    /** @inheritdoc */
    public function exists($sql, $params, $connection = 'default', $reconnect = false, $close = false)
    {
        if ($this->log != null) {
            $this->log->sql($sql, $params);
        }

        try {
            $sth = $this->getConnection($connection)->prepare($sql);
            $sth->execute($params);
            $exists = $sth->fetch();
            $this->incrementStat($connection, 'exists');

            if ($close) {
                $this->close($connection);
            }

            if ($exists) {
                return true;
            } else {
                return false;
            }
        } catch (\PDOException $PDOException) {
            // Throw if we're not expected to reconnect.
            if (!$reconnect) {
                throw $PDOException;
            }

            $errorCode = $PDOException->errorInfo[1] ?? $PDOException->getCode();

            if ($errorCode != 2006) {
                throw $PDOException;
            } else {
                $this->close($connection);
                return $this->exists($sql, $params, $connection, false, $close);
            }
        } catch (\ErrorException $exception) {
            // Super odd we'd get one of these
            // we're trying to catch "Error while sending QUERY packet."
            if (!$reconnect) {
                throw $exception;
            }

            // Try again
            $this->close($connection);
            return $this->exists($sql, $params, $connection, false, $close);
        }
    }

    /** @inheritdoc */
    public function insert($sql, $params, $connection = 'default', $reconnect = false, $transaction = true, $close = false)
    {
        if ($this->log != null) {
            $this->log->sql($sql, $params);
        }

        try {
            if ($transaction && !$this->getConnection($connection)->inTransaction()) {
                $this->getConnection($connection)->beginTransaction();
            }
            $sth = $this->getConnection($connection)->prepare($sql);

            $sth->execute($params);
            $id = intval($this->getConnection($connection)->lastInsertId());

            $this->incrementStat($connection, 'insert');
            if ($close) {
                $this->close($connection);
            }
            return $id;
        } catch (\PDOException $PDOException) {
            // Throw if we're not expected to reconnect.
            if (!$reconnect) {
                throw $PDOException;
            }

            $errorCode = $PDOException->errorInfo[1] ?? $PDOException->getCode();

            if ($errorCode != 2006) {
                throw $PDOException;
            } else {
                $this->close($connection);
                return $this->insert($sql, $params, $connection, false, $transaction, $close);
            }
        } catch (\ErrorException $exception) {
            // Super odd we'd get one of these
            // we're trying to catch "Error while sending QUERY packet."
            if (!$reconnect) {
                throw $exception;
            }

            // Try again
            $this->close($connection);
            return $this->insert($sql, $params, $connection, false, $transaction, $close);
        }
    }

    /** @inheritdoc */
    public function update($sql, $params, $connection = 'default', $reconnect = false, $transaction = true, $close = false)
    {
        if ($this->log != null) {
            $this->log->sql($sql, $params);
        }

        try {
            if ($transaction && !$this->getConnection($connection)->inTransaction()) {
                $this->getConnection($connection)->beginTransaction();
            }

            $sth = $this->getConnection($connection)->prepare($sql);

            $sth->execute($params);

            $rows = $sth->rowCount();

            $this->incrementStat($connection, 'update');
            if ($close) {
                $this->close($connection);
            }

            return $rows;
        } catch (\PDOException $PDOException) {
            // Throw if we're not expected to reconnect.
            if (!$reconnect) {
                throw $PDOException;
            }

            $errorCode = $PDOException->errorInfo[1] ?? $PDOException->getCode();

            if ($errorCode != 2006) {
                throw $PDOException;
            } else {
                $this->close($connection);
                return $this->update($sql, $params, $connection, false, $transaction, $close);
            }
        } catch (\ErrorException $exception) {
            // Super odd we'd get one of these
            // we're trying to catch "Error while sending QUERY packet."
            if (!$reconnect) {
                throw $exception;
            }

            // Try again
            $this->close($connection);
            return $this->update($sql, $params, $connection, false, $transaction, $close);
        }
    }

    /** @inheritdoc */
    public function select($sql, $params, $connection = 'default', $reconnect = false, $close = false)
    {
        if ($this->log != null) {
            $this->log->sql($sql, $params);
        }

        try {
            $sth = $this->getConnection($connection)->prepare($sql);

            $sth->execute($params);
            $records = $sth->fetchAll(\PDO::FETCH_ASSOC);

            $this->incrementStat($connection, 'select');

            if ($close) {
                $this->close($connection);
            }
            return $records;
        } catch (\PDOException $PDOException) {
            $errorCode = $PDOException->errorInfo[1] ?? $PDOException->getCode();

            // syntax error, log the sql and params in error level.
            if ($errorCode == 1064 && $this->log != null) {
                $this->log->sql($sql, $params, true);
            }

            // Throw if we're not expected to reconnect.
            if (!$reconnect) {
                throw $PDOException;
            }

            if ($errorCode != 2006) {
                throw $PDOException;
            } else {
                $this->close($connection);
                return $this->select($sql, $params, $connection, false, $close);
            }
        } catch (\ErrorException $exception) {
            // Super odd we'd get one of these
            // we're trying to catch "Error while sending QUERY packet."
            if (!$reconnect) {
                throw $exception;
            }

            // Try again
            $this->close($connection);
            return $this->select($sql, $params, $connection, false, $close);
        }
    }

    /** @inheritdoc */
    public function updateWithDeadlockLoop($sql, $params, $connection = 'default', $transaction = true, $close = false)
    {
        $maxRetries = 2;

        // Should we log?
        if ($this->log != null) {
            $this->log->sql($sql, $params);
        }

        // Start a transaction?
        if ($transaction && !$this->getConnection($connection)->inTransaction()) {
            $this->getConnection($connection)->beginTransaction();
        }

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
                $errorCode = $PDOException->errorInfo[1] ?? $PDOException->getCode();

                if ($errorCode != 1213 && $errorCode != 1205) {
                    throw $PDOException;
                }
            }

            if ($success) {
                break;
            }

            // Sleep a bit, give the DB time to breathe
            $queryHash = substr($sql, 0, 15) . '... [' . md5($sql . json_encode($params)) . ']';
            $this->log->debug('Retrying query after a short nap, try: ' . (3 - $retries)
                . '. Query Hash: ' . $queryHash);

            usleep(10000);
        } while ($retries--);

        if (!$success) {
            throw new DeadlockException(sprintf(
                __('Failed to write to database after %d retries. Please try again later.'),
                $maxRetries
            ));
        }

        if ($close) {
            $this->close($connection);
        }
    }

    /** @inheritdoc */
    public function commitIfNecessary($name = 'default', $close = false)
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
        self::$stats['connections'] = count(self::$conn);
        return self::$stats;
    }

    /** @inheritdoc */
    public static function incrementStat($connection, $key)
    {
        $currentCount = (isset(self::$stats[$connection][$key])) ? self::$stats[$connection][$key] : 0;
        self::$stats[$connection][$key] = $currentCount + 1;
    }

    /**
     * @inheritdoc
     */
    public function getVersion()
    {
        if (self::$version === null) {
            $results = $this->select('SELECT version() AS v', []);

            if (count($results) <= 0) {
                return null;
            }

            self::$version = explode('-', $results[0]['v'])[0];
        }

        return self::$version;
    }
}
