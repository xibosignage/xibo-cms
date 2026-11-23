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


namespace Xibo\Helper;

use Monolog\LogRecord;

/**
 * Class RouteLogProcessor
 *  a process to add route/method information to the log record
 * @package Xibo\Helper
 */
class RouteLogProcessor
{
    /**
     * Log Processor
     * @param string $route
     * @param string $method
     */
    public function __construct(
        private readonly string $route,
        private readonly string $method
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $record['extra']['method'] = $this->method;
        $record['extra']['route'] = $this->route;
        return $record;
    }
}
