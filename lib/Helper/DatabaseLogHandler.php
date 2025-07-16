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

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Xibo\Storage\PdoStorageService;

/**
 * Class DatabaseLogHandler
 * @package Xibo\Helper
 */
class DatabaseLogHandler extends AbstractProcessingHandler
{
    /** @var \PDO */
    private static $pdo;

    /** @var \PDOStatement|null */
    private static $statement;

    /** @var int Log Level */
    protected $level = Logger::ERROR;

    /** @var int Track the number of failures since a success */
    private $failureCount = 0;

    /**
     * @param int $level The minimum logging level at which this handler will be triggered
     */
    public function __construct($level = Logger::ERROR)
    {
        parent::__construct($level);
    }

    /**
     * Gets minimum logging level at which this handler will be triggered.
     *
     * @return int
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @param int|string $level
     * @return $this|\Monolog\Handler\AbstractHandler
     */
    public function setLevel($level): \Monolog\Handler\AbstractHandler
    {
        $this->level = Logger::toMonologLevel($level);

        return $this;
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    protected function write(array $record): void
    {
        if (self::$statement == null) {
            self::$pdo = PdoStorageService::newConnection('log');

            $SQL = '
                INSERT INTO `log` (
                  `runNo`,
                  `logdate`,
                  `channel`,
                  `type`,
                  `page`,
                  `function`,
                  `message`,
                  `userid`,
                  `displayid`,
                  `sessionHistoryId`,
                  `requestId`
                ) VALUES (
                  :runNo,
                  :logdate,
                  :channel,
                  :type,
                  :page,
                  :function,
                  :message,
                  :userid,
                  :displayid,
                  :sessionHistoryId,
                  :requestId
                )
            ';

            self::$statement = self::$pdo->prepare($SQL);
        }

        $params = [
            'runNo' => $record['extra']['uid'] ?? '',
            'logdate' => $record['datetime']->format('Y-m-d H:i:s'),
            'type' => $record['level_name'],
            'channel' => $record['channel'],
            'page' => $record['extra']['route'] ?? '',
            'function' => $record['extra']['method'] ?? '',
            'message' => $record['message'],
            'userid' => $record['extra']['userId'] ?? 0,
            'displayid' => $record['extra']['displayId'] ?? 0,
            'sessionHistoryId' => $record['extra']['sessionHistoryId'] ?? 0,
            'requestId' => $record['extra']['requestId'] ?? 0,
        ];

        try {
            // Insert
            self::$statement->execute($params);

            // Reset failure count
            $this->failureCount = 0;

            // Successful write
            PdoStorageService::incrementStat('log', 'insert');
        } catch (\Exception $e) {
            // Increment failure count
            $this->failureCount++;

            // Try to create a new statement
            if ($this->failureCount <= 1) {
                // Clear the stored statement, and try again
                // this will rebuild the connection
                self::$statement = null;

                // Try again.
                $this->write($record);
            }
            // If the failureCount is > 1, then we ignore the error.
        }
    }

    /**
     * Deleting logs must happen on the same DB connection as the log handler writes logs
     *  otherwise we can end up with a deadlock where the log handler has written things, locked the table
     *  and, we're then trying to get the same lock.
     * @param string $cutOff
     */
    public static function tidyLogs(string $cutOff): void
    {
        try {
            if (self::$pdo === null) {
                self::$pdo = PdoStorageService::newConnection('log');
            }

            $statement = self::$pdo->prepare('DELETE FROM `log` WHERE logdate < :maxage LIMIT 10000');

            do {
                // Execute statement
                $statement->execute(['maxage' => $cutOff]);

                // initialize number of rows deleted
                $rowsDeleted = $statement->rowCount();

                PdoStorageService::incrementStat('log', 'delete');

                // pause for a second
                sleep(2);
            } while ($rowsDeleted > 0);
        } catch (\PDOException) {
        }
    }
}
