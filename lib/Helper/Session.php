<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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
namespace Xibo\Helper;

use Carbon\Carbon;
use Xibo\Service\LogServiceInterface;
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
     * @var LogServiceInterface
     */
    private LogServiceInterface $log;

    /**
     * Session constructor.
     * @param LogServiceInterface $log
     */
    public function __construct(LogServiceInterface $log)
    {
        $this->log = $log;
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName): bool
    {
        //$this->log->debug('Session open');
        $this->maxLifetime = ini_get('session.gc_maxlifetime');
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        //$this->log->debug('Session close');

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
                    $db->update('DELETE FROM `session` WHERE session_id = :session_id', [
                        'session_id' => $this->key,
                    ]);
                }

                if ($this->gcCalled) {
                    // Delete sessions older than 10 times the max lifetime
                    $db->update('DELETE FROM `session` WHERE IsExpired = 1 AND session_expiration < :expiration', [
                        'expiration' => Carbon::now()->subSeconds($this->maxLifetime * 10)->format('U'),
                    ]);

                    // Update expired sessions as expired
                    $db->update('UPDATE `session` SET IsExpired = 1 WHERE session_expiration < :expiration', [
                        'expiration' => Carbon::now()->format('U'),
                    ]);
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
    public function read($key): false|string
    {
        //$this->log->debug('Session read');

        $data = '';
        $this->key = $key;

        $userAgent = substr(htmlspecialchars($_SERVER['HTTP_USER_AGENT']), 0, 253);

        try {
            $dbh = $this->getDb();

            // Start a transaction
            $this->beginTransaction();

            // Get this session
            $sth = $dbh->getConnection()->prepare('
                SELECT `session_data`, `isexpired`, `useragent`, `session_expiration`, `userId` 
                  FROM `session`
                 WHERE `session_id` = :session_id
            ');
            $sth->execute(['session_id' => $key]);

            $row = $sth->fetch();
            if (!$row) {
                // New session.
                $this->insertSession(
                    $key,
                    '',
                    Carbon::now()->format('U'),
                    Carbon::now()->addSeconds($this->maxLifetime)->format('U'),
                );

                $this->expired = false;
            } else {
                // Existing session
                // Check the session hasn't expired
                if ($row['session_expiration'] < Carbon::now()->format('U')) {
                    $this->expired = true;
                } else {
                    $this->expired = $row['isexpired'];
                }

                // What happens if the UserAgent has changed?
                if ($row['useragent'] != $userAgent) {
                    // Force delete this session
                    $this->expired = 1;
                    $this->pruneKey = true;
                }

                $this->userId = $row['userId'];
                $this->sessionExpiry = $row['session_expiration'];

                // Set the session data (expired or not)
                $data = $row['session_data'];
            }

            return (string)$data;
        } catch (\Exception $e) {
            $this->log->error('Error reading session: %s', $e->getMessage());

            return $data;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write($id, $data): bool
    {
        //$this->log->debug('Session write');

        // What should we do with expiry?
        $expiry = ($this->refreshExpiry)
            ? Carbon::now()->addSeconds($this->maxLifetime)->format('U')
            : $this->sessionExpiry;

        try {
            $this->updateSession($id, $data, Carbon::now()->format('U'), $expiry);
        } catch (\PDOException $e) {
            $this->log->error('Error writing session data: %s', $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($id): bool
    {
        //$this->log->debug('Session destroy');
        try {
            $this->getDb()->update('DELETE FROM `session` WHERE session_id = :session_id', ['session_id' => $id]);
        } catch (\PDOException $e) {
            $this->log->error('Error destroying session: %s', $e->getMessage());
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($max_lifetime): false|int
    {
        //$this->log->debug('Session gc');
        $this->gcCalled = true;
        return true;
    }

    /**
     * Sets the User Id
     * @param $userId
     */
    public function setUser($userId): void
    {
        //$this->log->debug('Setting user Id to %d', $userId);
        $_SESSION['userid'] = $userId;
        $this->userId = $userId;
    }

    /**
     * Updates the session ID with a new one
     */
    public function regenerateSessionId(): void
    {
        //$this->log->debug('Session regenerate');
        session_regenerate_id(true);

        $this->key = session_id();
    }

    /**
     * Set this session to expired
     * @param $isExpired
     */
    public function setIsExpired($isExpired): void
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
    public static function set(string $key, mixed $secondKey, mixed $value = null): mixed
    {
        if (func_num_args() == 2) {
            $_SESSION[$key] = $secondKey;
            return $secondKey;
        } else {
            if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
                $_SESSION[$key] = [];
            }

            $_SESSION[$key][(string) $secondKey] = $value;
            return $value;
        }
    }

    /**
     * Get the Value from the position denoted by the 2 keys provided
     * @param string $key
     * @param string $secondKey
     * @return bool
     */
    public static function get(string $key, ?string $secondKey = null): mixed
    {
        if ($secondKey != null) {
            if (isset($_SESSION[$key][$secondKey])) {
                return $_SESSION[$key][$secondKey];
            }
        } else {
            if (isset($_SESSION[$key])) {
                return $_SESSION[$key];
            }
        }

        return false;
    }

    /**
     * Is the session expired?
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expired;
    }

    /**
     * Get a Database
     * @return PdoStorageService
     */
    private function getDb(): PdoStorageService
    {
        if ($this->pdo == null) {
            $this->pdo = (new PdoStorageService($this->log))->setConnection();
        }

        return $this->pdo;
    }

    /**
     * Helper method to begin a transaction.
     *
     * MySQLs default isolation, REPEATABLE READ, causes deadlock for different sessions
     * due to http://www.mysqlperformanceblog.com/2013/12/12/one-more-innodb-gap-lock-to-avoid/ .
     * So we change it to READ COMMITTED.
     */
    private function beginTransaction(): void
    {
        if (!$this->getDb()->getConnection()->inTransaction()) {
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
    private function commit(): void
    {
        if ($this->getDb()->getConnection()->inTransaction()) {
            $this->getDb()->getConnection()->commit();
        }
    }

    /**
     * Insert session
     * @param $key
     * @param $data
     * @param $lastAccessed
     * @param $expiry
     */
    private function insertSession($key, $data, $lastAccessed, $expiry): void
    {
        //$this->log->debug('Session insert');

        $this->insertSessionHistory();

        $sql = '
          INSERT INTO `session` (
             `session_id`,
             `session_data`,
             `session_expiration`,
             `lastaccessed`,
             `userid`,
             `isexpired`,
             `useragent`,
             `remoteaddr`
             )
            VALUES (
            :session_id,
            :session_data,
            :session_expiration,
            :lastAccessed,
            :userId,
            :expired,
            :useragent,
            :remoteaddr
          )
        ';

        $params = [
            'session_id' => $key,
            'session_data' => $data,
            'session_expiration' => $expiry,
            'lastAccessed' => Carbon::createFromTimestamp($lastAccessed)->format(DateFormatHelper::getSystemFormat()),
            'userId' => $this->userId,
            'expired' => ($this->expired) ? 1 : 0,
            'useragent' => substr(htmlspecialchars($_SERVER['HTTP_USER_AGENT']), 0, 253),
            'remoteaddr' => $this->getIp()
        ];

        $this->getDb()->update($sql, $params);
    }

    private function insertSessionHistory(): void
    {
        $sql = '
        INSERT INTO `session_history` (`ipAddress`, `userAgent`, `startTime`, `userId`, `lastUsedTime`)
            VALUES (:ipAddress, :userAgent, :startTime, :userId, :lastUsedTime)
        ';

        $params = [
            'ipAddress' => $this->getIp(),
            'userAgent' => substr(htmlspecialchars($_SERVER['HTTP_USER_AGENT']), 0, 253),
            'startTime' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            'userId' => $this->userId,
            'lastUsedTime' => Carbon::now()->format(DateFormatHelper::getSystemFormat())
        ];

        $id = $this->getDb()->insert($sql, $params);

        $this->set('sessionHistoryId', $id);
    }

    /**
     * Update Session
     * @param $key
     * @param $data
     * @param $lastAccessed
     * @param $expiry
     */
    private function updateSession($key, $data, $lastAccessed, $expiry): void
    {
        //$this->log->debug('Session update');

        $this->updateSessionHistory();

        $sql = '
            UPDATE `session` SET
              session_data = :session_data,
              session_expiration = :session_expiration,
              LastAccessed = :lastAccessed,
              userID = :userId,
              IsExpired = :expired
            WHERE session_id = :session_id
        ';

        $params = [
            'session_data' => $data,
            'session_expiration' => $expiry,
            'lastAccessed' => Carbon::createFromTimestamp($lastAccessed)->format(DateFormatHelper::getSystemFormat()),
            'userId' => $this->userId,
            'expired' => ($this->expired) ? 1 : 0,
            'session_id' => $key
        ];

        $this->getDb()->update($sql, $params);
    }

    /**
     * Updates the session history
     */
    private function updateSessionHistory(): void
    {
        $sql = '
            UPDATE `session_history` SET
              lastUsedTime = :lastUsedTime, userID = :userId
            WHERE sessionId = :sessionId
        ';

        $params = [
            'lastUsedTime' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
            'userId' => $this->userId,
            'sessionId' => $_SESSION['sessionHistoryId'],
        ];

        $this->getDb()->update($sql, $params);
    }

    /**
     * Get the Client IP Address
     * @return string
     */
    private function getIp(): string
    {
        $clientIp = '';
        $keys = array('X_FORWARDED_FOR', 'HTTP_X_FORWARDED_FOR', 'CLIENT_IP', 'REMOTE_ADDR');
        foreach ($keys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP) !== false) {
                $clientIp = $_SERVER[$key];
                break;
            }
        }
        return $clientIp;
    }

    /**
     * @param $userId
     */
    public function expireAllSessionsForUser($userId): void
    {
        $this->getDb()->update('UPDATE `session` SET IsExpired = 1 WHERE userID  = :userId', [
            'userId' => $userId
        ]);
    }
}
