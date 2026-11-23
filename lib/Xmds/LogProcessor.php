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


namespace Xibo\Xmds;

use Monolog\Logger;
use Monolog\LogRecord;
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
                $level = $handler->getLevel()->value;
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

    public function __invoke(LogRecord $record): LogRecord
    {
        $record['extra']['displayId'] = $this->displayId;
        $record['extra']['route'] = $this->route;
        $record['extra']['method'] = $this->method;

        return $record;
    }
}
