<?php
/*
 * Copyright (C) 2021 Xibo Signage Ltd
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

/**
 * Class Profiler
 * @package Xibo\Helper
 */
class Profiler
{
    private static $profiles = [];

    /**
     * @param $key
     * @param null $logger
     */
    public static function start($key, $logger = null)
    {
        $start = microtime(true);
        self::$profiles[$key] = $start;

        if ($logger !== null) {
            $logger->debug('PROFILE: ' . $key . ' - start: ' . $start);
        }
    }

    /**
     * @param $key
     * @param null $logger
     */
    public static function end($key, $logger = null)
    {
        $start = self::$profiles[$key] ?? 0;
        $end = microtime(true);
        unset(self::$profiles[$key]);

        if ($logger !== null) {
            $logger->debug('PROFILE: ' . $key . ' - end: ' . $end
                . ', duration: ' . ($end - $start));
        }
    }
}
