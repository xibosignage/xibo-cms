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

/**
 * Class ByteFormatter
 * @package Xibo\Helper
 */
class ByteFormatter
{
    /**
     * Format Bytes
     * http://stackoverflow.com/questions/2510434/format-bytes-to-kilobytes-megabytes-gigabytes
     * @param int $size The file size in bytes
     * @param int $precision The precision to go to
     * @param bool $si Use SI units or not
     * @return string The Formatted string with suffix
     */
    public static function format($size, $precision = 2, $si = false)
    {
        if ($size <= 0) {
            return 0;
        }

        if ($si === false) {
            // IEC prefixes (binary)
            $suffixes = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');
            $mod   = 1024;
            $base = log($size) / log($mod);
        } else {
            // SI prefixes (decimal)
            $suffixes = array('B', 'kB', 'MB', 'GB', 'TB', 'PB');
            $mod   = 1000;
            $base = log($size) / log($mod);
        }

        return round(pow($mod, $base - floor($base)), $precision) . ' ' . $suffixes[(int)floor($base)];
    }

    /**
     * @param $val
     * @return int|string
     */
    public static function toBytes($val) {

        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = substr($val, 0, -1);

        switch($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }
}