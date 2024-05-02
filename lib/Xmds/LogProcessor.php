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


namespace Xibo\Xmds;

use Monolog\Logger;
use Xibo\Helper\DatabaseLogHandler;

/**
 * Class LogProcessor
 * @package Xibo\Xmds
 */
class LogProcessor
{
    /** @var  Logger */
    private $log;
    private $displayId;
    private $route;
    private $method;
    private $uid;

    /**
     * Log Processor
     * @param Logger $log
     * @param $uid
     * @param string $method
     */
    public function __construct($log, $uid, $method = 'POST')
    {
        $this->log = $log;
        $this->uid = $uid;
        $this->method = $method;
    }

    /**
     * @param $route
     */
    public function setRoute($route)
    {
        $this->route = $route;
    }

    /**
     * @param int $displayId
     * @param bool $isAuditing
     */
    public function setDisplay($displayId, $isAuditing)
    {
        if ($isAuditing) {
            foreach ($this->log->getHandlers() as $handler) {
                if ($handler instanceof DatabaseLogHandler) {
                    $handler->setLevel(\Xibo\Service\LogService::resolveLogLevel('debug'));
                }
            }
        }

        $this->displayId = $displayId;
    }

    /**
     * Get Log Level
     * @return int
     */
    public function getLevel()
    {
        $level = Logger::ERROR;

        foreach ($this->log->getHandlers() as $handler) {
            if ($handler instanceof DatabaseLogHandler) {
                $level = $handler->getLevel();
            } else {
                $this->log->error('Log level not set in DatabaseLogHandler');
            }
        }

        return $level;
    }

    /**
     * Get UID
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @param array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        $record['extra']['displayId'] = $this->displayId;
        $record['extra']['route'] = $this->route;
        $record['extra']['method'] = $this->method;

        return $record;
    }
}
