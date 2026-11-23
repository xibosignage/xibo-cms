<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */


namespace Xibo\Storage;

use Xibo\Service\LogService;
use Xibo\Support\Exception\DeadlockException;

/**
 * Interface StorageInterface
 * @package Xibo\Storage
 */
interface StorageServiceInterface
{
    /** The connection whose writes commit straight away, outside any request transaction. */
    public const ISOLATED_CONNECTION = 'isolated';

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
     * @param $name string The name of the connection, e.g. "default"
     * @return \PDO
     */
    public static function newConnection(string $name);

    /**
     * Open a connection with the specified details
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param string|null $name
     * @param string|null $ssl
     * @param boolean $sslVerify
     * @return \PDO
     */
    public function connect($host, $user, $pass, $name = null, $ssl = null, $sslVerify = true);

    /**
     * Get the Raw Connection
     * @param string $name The connection name
     * @return \PDO
     */
    public function getConnection($name = 'default');

    /**
     * Check to see if the query returns records
     * @param string $sql
     * @param array $params
     * @param string|null $connection Note: the transaction for non-default connections is not automatically committed
     * @param bool $reconnect
     * @param bool $close
     * @return bool
     */
    public function exists($sql, $params, $connection = 'default', $reconnect = false, $close = false);

    /**
     * Run Insert SQL
     * @param string $sql
     * @param array $params
     * @param string|null $connection Note: the transaction for non-default connections is not automatically committed
     * @param bool $reconnect
     * @param bool $close
     * @return int
     * @throws \PDOException
     */
    public function insert($sql, $params, $connection = 'default', $reconnect = false, $transaction = true, $close = false);

    /**
     * Run Update SQL
     * @param string $sql
     * @param array $params
     * @param string|null $connection Note: the transaction for non-default connections is not automatically committed
     * @param bool $reconnect
     * @param bool $transaction If we are already in a transaction, then do nothing. Otherwise, start one.
     * @param bool $close
     * @return int affected rows
     * @throws \PDOException
     */
    public function update($sql, $params, $connection = 'default', $reconnect = false, $transaction = true, $close = false);

    /**
     * Run Select SQL
     * @param $sql
     * @param $params
     * @param string|null $connection Note: the transaction for non-default connections is not automatically committed
     * @param bool $reconnect
     * @param bool $close
     * @return array
     * @throws \PDOException
     */
    public function select($sql, $params, $connection = 'default', $reconnect = false, $close = false);

    /**
     * Run the SQL statement with a deadlock loop
     * @param $sql
     * @param $params
     * @param string|null $connection Note: the transaction for non-default connections is not automatically committed
     * @param bool $close
     * @param bool $transaction If we are already in a transaction, then do nothing. Otherwise, start one.
     * @return mixed
     * @throws DeadlockException
     */
    public function updateWithDeadlockLoop($sql, $params, $connection = 'default', $transaction = true, $close = false);

    /**
     * Commit if necessary
     * @param $name
     * @param bool $close
     */
    public function commitIfNecessary($name = 'default', $close = false);

    /**
     * Roll back the open transaction, if there is one, and drop anything waiting for it to commit (afterCommit()).
     * @param string $name
     */
    public function rollBackIfNecessary(string $name = 'default'): void;

    /**
     * Runs $callback after the current transaction commits (now if there is none), dropping it on rollback.
     * @param callable $callback
     * @param string $connection
     */
    public function afterCommit(callable $callback, string $connection = 'default'): void;

    /**
     * Runs $callback after the current transaction rolls back (including a connection closed with the
     * transaction open), to undo work done outside the database. It is dropped when the transaction commits. When
     * no transaction is open, one is started, so the writes that follow are undone with it.
     * @param callable $callback
     * @param string $connection
     */
    public function afterRollback(callable $callback, string $connection = 'default'): void;

    /**
     * Takes a named database lock for this connection, waiting up to $timeout seconds for another holder to let go,
     * and keeps it until the current transaction commits or rolls back (a transaction is started when none is
     * open). A second request taking the same lock waits until the first one's writes are committed. They are
     * visible to it only when its transaction has read nothing before the lock, or runs READ COMMITTED: under
     * REPEATABLE READ, a read made before the lock keeps showing the data from before the other commit.
     * The lock is only for this database, also when several databases share the MySQL server.
     * @param string $name
     * @param int $timeout seconds
     * @param string $connection
     * @return bool false when the lock could not be taken in time
     */
    public function lockUntilCommit(string $name, int $timeout, string $connection = 'default'): bool;

    /**
     * Set the TimeZone for this connection
     * @param string|null $connection
     * @param string $timeZone e.g. -8:00
     */
    public function setTimeZone($timeZone, $connection = 'default');

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
    public static function incrementStat($connection, $key);

    /**
     * Get the Storage engine version
     * @return string
     */
    public function getVersion();
}
