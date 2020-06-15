<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
    private static $statement;
    protected $level = Logger::ERROR;

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
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * @param int|string $level
     * @return $this|\Monolog\Handler\AbstractHandler
     */
    public function setLevel($level)
    {
        $this->level = Logger::toMonologLevel($level);

        return $this;
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  array $record
     * @return void
     */
    protected function write(array $record)
    {
        if (self::$statement == NULL) {
            $pdo = PdoStorageService::newConnection();

            $SQL = 'INSERT INTO log (runNo, logdate, channel, type, page, function, message, userid, displayid)
                      VALUES (:runNo, :logdate, :channel, :type, :page, :function, :message, :userid, :displayid)
                  ';

            self::$statement = $pdo->prepare($SQL);
        }

        $params = array(
            'runNo' => isset($record['extra']['uid']) ? $record['extra']['uid'] : '',
            'logdate' => $record['datetime']->format("Y-m-d H:i:s"),
            'type' => $record['level_name'],
            'channel' => $record['channel'],
            'page' => isset($record['extra']['route']) ? $record['extra']['route'] : '',
            'function' => isset($record['extra']['method']) ? $record['extra']['method'] : '',
            'message' => $record['message'],
            'userid' => isset($record['extra']['userId']) ? $record['extra']['userId'] : 0,
            'displayid' => isset($record['extra']['displayId']) ? $record['extra']['displayId'] : 0
        );

        try {
            PdoStorageService::incrementStatStatic('log', 'insert');
            self::$statement->execute($params);
        }
        catch (\PDOException $e) {
            // Not sure what we can do here?
        }
    }
}