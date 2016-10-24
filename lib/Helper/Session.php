<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2016 Daniel Garner
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
namespace Xibo\Helper;

use Xibo\Service\LogService;
use Xibo\Storage\PdoStorageService;

/**
 * Class Session
 * @package Xibo\Helper
 */
class Session implements \SessionHandlerInterface
{
    private $maxLifetime;
    private $key;

    /**
     * Refresh expiry
     * @var bool
     */
    public $refreshExpiry = true;

    /**
     * Expiry time
     * @var int
     */
    private $sessionExpiry = 0;

    /**
     * Is the session expired?
     * @var bool
     */
    private $expired = true;

    /**
     * The UserId whom owns this session
     * @var int
     */
    private $userId = 0;

    /**
     * @var bool Whether gc() has been called
     */
    private $gcCalled = false;

    /**
     * Prune this key?
     * @var bool
     */
    private $pruneKey = false;

    /**
     * The database connection
     * @var PdoStorageService
     */
    private $pdo = null;

    /**
     * Log
     * @var LogService
     */
    private $log;

    /**
     * Session constructor.
     * @param LogService $log
     */
    function __construct($log)
    {
        $this->log = $log;

        session_set_save_handler(
            array(&$this, 'open'),
            array(&$this, 'close'),
            array(&$this, 'read'),
            array(&$this, 'write'),
            array(&$this, 'destroy'),
            array(&$this, 'gc')
        );

        register_shutdown_function('session_write_close');

        // Start the session
        session_cache_limiter(false);
        session_start();
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
    {
        $this->maxLifetime = ini_get('session.gc_maxlifetime');
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        try {
            // Commit
            $this->commit();
        } catch (\PDOException $e) {
            $this->log->error('Error closing session: %s', $e->getMessage());
        }

        try {

            // Prune this session if necessary
            if ($this->pruneKey || $this->gcCalled) {
                $db = new PdoStorageService($this->log);
                $db->setConnection();

                if ($this->pruneKey) {
                    $db->update('DELETE FROM `session` WHERE session_id = :session_id', array('session_id' => $this->key));
                }

                if ($this->gcCalled) {
                    // Delete sessions older than 10 times the max lifetime
                    $db->update('DELETE FROM `session` WHERE IsExpired = 1 AND session_expiration < :expiration', array('expiration' => (time() - ($this->maxLifetime * 10))));

                    // Update expired sessions as expired
                    $db->update('UPDATE `session` SET IsExpired = 1 WHERE session_expiration < :expiration', array('expiration' => time()));
                }

                $db->commitIfNecessary();
                $db->close();
            }

        } catch (\PDOException $e) {
            $this->log->error('Error closing session: %s', $e->getMessage());
        }

        // Close
        $this->getDb()->close();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    function read($key)
    {
        $empty = '';
        $this->key = $key;

        $userAgent = substr($_SERVER['HTTP_USER_AGENT'], 0, 253);

        try {
            $dbh = $this->getDb();

            // Start a transaction
            $this->beginTransaction();

            // Get this session
            $sth = $dbh->getConnection()->prepare('SELECT `session_data`, `isexpired`, `useragent`, `session_expiration`, `userId` FROM `session` WHERE `session_id` = :session_id');
            $sth->execute(array('session_id' => $key));

            if (!$row = $sth->fetch()) {
                // Key doesn't exist yet
                $this->expired = false;
                return settype($empty, "string");
            }

            // Check the session hasn't expired
            if ($row['session_expiration'] < time())
                $this->expired = true;
            else
                $this->expired = $row['isexpired'];

            // What happens if the UserAgent has changed?
            if ($row['useragent'] != $userAgent) {
                // Force delete this session
                $this->expired = 1;
                $this->pruneKey = true;

                throw new \Exception('Different UserAgent');
            }

            $this->userId = $row['userId'];
            $this->sessionExpiry = $row['session_expiration'];

            return ($row['session_data']);

        } catch (\Exception $e) {
            $this->log->error('Error reading session: %s', $e->getMessage());

            return settype($empty, "string");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write($key, $val)
    {
        $newExp = time() + $this->maxLifetime;

        $lastAccessed = date("Y-m-d H:i:s");

        try {
            $sql = '
                  INSERT INTO `session` (session_id, session_data, session_expiration, lastaccessed, userid, isexpired, useragent, remoteaddr)
                    VALUES (:session_id, :session_data, :session_expiration, :lastAccessed, :userId, :expired, :useragent, :remoteaddr)
                    ON DUPLICATE KEY UPDATE
                      `session_data` = :session_data2,
                      `userId` = :userId2,
                      `session_expiration` = :session_expiration2,
                      `isExpired` = :expired2,
                      `lastaccessed` = :lastAccessed2
                ';

            $params = [
                'session_id' => $key,
                'session_data' => $val,
                'session_data2' => $val,
                'session_expiration' => $newExp,
                'session_expiration2' => ($this->refreshExpiry) ? $newExp : $this->sessionExpiry,
                'lastAccessed' => $lastAccessed,
                'lastAccessed2' => $lastAccessed,
                'userId' => $this->userId,
                'userId2' => $this->userId,
                'expired' => ($this->expired) ? 1 : 0,
                'expired2' => ($this->expired) ? 1 : 0,
                'useragent' => substr($_SERVER['HTTP_USER_AGENT'], 0, 253),
                'remoteaddr' => $_SERVER['REMOTE_ADDR']
            ];

            //

            $this->getDb()->update($sql, $params);

        } catch (\PDOException $e) {
            $this->log->error('Error writing session data: %s', $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($key)
    {
        try {
            $this->getDb()->update('DELETE FROM `session` WHERE session_id = :session_id', array('session_id', $key));
        } catch (\PDOException $e) {
            $this->log->error('Error destroying session: %s', $e->getMessage());
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxLifetime)
    {
        $this->gcCalled = true;
        return true;
    }

    /**
     * Sets the User Id
     * @param $userId
     */
    public function setUser($userId)
    {
        $this->log->debug('Setting user Id to %d', $userId);
        $_SESSION['userid'] = $userId;
        $this->userId = $userId;
    }

    /**
     * Updates the session ID with a new one
     */
    public function regenerateSessionId()
    {
        session_regenerate_id(false);

        $newKey = session_id();

        try {
            // Swap the ID's
            $this->getDb()->update('UPDATE `session` SET session_id = :new_session_id WHERE session_id = :session_id', array('session_id' => $this->key, 'new_session_id' => $newKey));

            $this->key = $newKey;

            return true;

        } catch (\PDOException $e) {
            $this->log->error('Error regenerating session: %s', $e->getMessage());
            return false;
        }
    }

    /**
     * Set this session to expired
     * @param $isExpired
     */
    public function setIsExpired($isExpired)
    {
        $this->expired = $isExpired;
    }

    /**
     * Store a variable in the session
     * @param string $key
     * @param mixed $secondKey
     * @param mixed|null $value
     * @return mixed
     */
    public static function set($key, $secondKey, $value = null)
    {
        if (func_num_args() == 2) {
            $_SESSION[$key] = $secondKey;
            return $secondKey;
        } else {
            if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key]))
                $_SESSION[$key] = [];

            $_SESSION[$key][(string) $secondKey] = $value;
            return $value;
        }
    }

    /**
     * Get the Value from the position denoted by the 2 keys provided
     * @param string $key
     * @param string [Optional] $secondKey
     * @return bool
     */
    public static function get($key, $secondKey = NULL)
    {
        if ($secondKey != NULL) {
            if (isset($_SESSION[$key][$secondKey]))
                return $_SESSION[$key][$secondKey];
        } else {
            if (isset($_SESSION[$key]))
                return $_SESSION[$key];
        }

        return false;
    }

    /**
     * Is the session expired?
     * @return bool
     */
    public function isExpired()
    {
        return $this->expired;
    }

    /**
     * Get a Database
     * @return PdoStorageService
     */
    private function getDb()
    {
        if ($this->pdo == null)
            $this->pdo = (new PdoStorageService($this->log))->setConnection();

        return $this->pdo;
    }

    /**
     * Helper method to begin a transaction.
     *
     * MySQLs default isolation, REPEATABLE READ, causes deadlock for different sessions
     * due to http://www.mysqlperformanceblog.com/2013/12/12/one-more-innodb-gap-lock-to-avoid/ .
     * So we change it to READ COMMITTED.
     */
    private function beginTransaction()
    {
        if (!$this->getDb()->getConnection()->inTransaction() && DBVERSION > 122) {
            try {
                $this->getDb()->getConnection()->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            } catch (\PDOException $e) {
                // https://github.com/xibosignage/xibo/issues/787
                // this only works if BINLOG format is set to MIXED or ROW
                $this->log->error('Unable to set session transaction isolation level, message = ' . $e->getMessage());
            }
            $this->getDb()->getConnection()->beginTransaction();
        }
    }

    /**
     * Commit
     */
    private function commit()
    {
        if ($this->getDb()->getConnection()->inTransaction())
            $this->getDb()->getConnection()->commit();
    }
}

?>