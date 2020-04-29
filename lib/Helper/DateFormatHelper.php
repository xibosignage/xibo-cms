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

use Carbon\Carbon;

/**
 * Class Environment
 * @package Xibo\Helper
 */
class DateFormatHelper
{
    private static $timezones = null;

    /**
     * Get the default date format
     * @return string
     */
    public static function getSystemFormat()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * @inheritdoc
     */
    public static function extractTimeFormat($format)
    {
        $replacements = [
            'd' => '',
            'D' => '',
            'j' => '',
            'l' => '',
            'N' => '',
            'S' => '',
            'w' => '',
            'z' => '',
            'W' => '',
            'F' => '',
            'm' => '',
            'M' => '',
            'n' => '',
            't' => '', // no equivalent
            'L' => '', // no equivalent
            'o' => '',
            'Y' => '',
            'y' => '',
            'a' => 'a',
            'A' => 'A',
            'B' => '', // no equivalent
            'g' => 'g',
            'G' => 'G',
            'h' => 'h',
            'H' => 'H',
            'i' => 'i',
            's' => 's',
            'u' => '',
            'e' => '', // deprecated since version 1.6.0 of moment.js
            'I' => '', // no equivalent
            'O' => '', // no equivalent
            'P' => '', // no equivalent
            'T' => '', // no equivalent
            'Z' => '', // no equivalent
            'c' => '', // no equivalent
            'r' => '', // no equivalent
            'U' => '',
            '-' => '',
            '/' => '',
            '.' => ''
        ];
        $timeOnly = strtr($format, $replacements);
        return trim($timeOnly);
    }

    /**
     * @inheritdoc
     */
    public static function extractDateOnlyFormat($format)
    {
        $replacements = [
            'd' => 'd',
            'D' => 'D',
            'j' => '',
            'l' => '',
            'N' => '',
            'S' => '',
            'w' => '',
            'z' => '',
            'W' => '',
            'F' => '',
            'm' => 'm',
            'M' => 'M',
            'n' => '',
            't' => '', // no equivalent
            'L' => '', // no equivalent
            'o' => '',
            'Y' => 'Y',
            'y' => 'y',
            'a' => '',
            'A' => '',
            'B' => '', // no equivalent
            'g' => '',
            'G' => '',
            'h' => '',
            'H' => '',
            'i' => '',
            's' => '',
            'u' => '',
            'e' => '', // deprecated since version 1.6.0 of moment.js
            'I' => '', // no equivalent
            'O' => '', // no equivalent
            'P' => '', // no equivalent
            'T' => '', // no equivalent
            'Z' => '', // no equivalent
            'c' => '', // no equivalent
            'r' => '', // no equivalent
            'U' => '',
            '-' => '-',
            '/' => '/',
            '.' => '.',
            ':' => ''
        ];
        $timeOnly = strtr($format, $replacements);
        return trim($timeOnly);
    }

    /**
     * @inheritdoc
     */
    public static function convertPhpToMomentFormat($format)
    {
        $replacements = [
            'd' => 'DD',
            'D' => 'ddd',
            'j' => 'D',
            'l' => 'dddd',
            'N' => 'E',
            'S' => 'o',
            'w' => 'e',
            'z' => 'DDD',
            'W' => 'W',
            'F' => 'MMMM',
            'm' => 'MM',
            'M' => 'MMM',
            'n' => 'M',
            't' => '', // no equivalent
            'L' => '', // no equivalent
            'o' => 'YYYY',
            'Y' => 'YYYY',
            'y' => 'YY',
            'a' => 'a',
            'A' => 'A',
            'B' => '', // no equivalent
            'g' => 'h',
            'G' => 'H',
            'h' => 'hh',
            'H' => 'HH',
            'i' => 'mm',
            's' => 'ss',
            'u' => 'SSS',
            'e' => 'zz', // deprecated since version 1.6.0 of moment.js
            'I' => '', // no equivalent
            'O' => '', // no equivalent
            'P' => '', // no equivalent
            'T' => '', // no equivalent
            'Z' => '', // no equivalent
            'c' => '', // no equivalent
            'r' => '', // no equivalent
            'U' => 'X',
        ];
        $momentFormat = strtr($format, $replacements);
        return $momentFormat;
    }

        /**
     * @inheritdoc
     */
    public static function convertMomentToJalaliFormat($format)
    {
        $replacements = [
            'DD' => 'jDD',
            'ddd' => 'ddd',
            'D' => 'jD',
            'dddd' => 'dddd',
            'E' => 'E',
            'e' => 'e',
            'DDD' => 'jDDD',
            'W' => '',
            'MMMM' => 'jMMMM',
            'MM' => 'jMM',
            'MMM' => 'jMMM',
            'M' => 'jM',
            'YYYY' => 'jYYYY',
            'YY' => 'jYY',
            'a' => 'a',
            'A' => 'A',
            'h' => 'h',
            'H' => 'H',
            'hh' => 'hh',
            'HH' => 'HH',
            'mm' => 'mm',
            'ss' => 'ss',
            'SSS' => 'SSS',
            'X' => 'X'
        ];
        $timeOnly = strtr($format, $replacements);
        return trim($timeOnly);
    }

    /**
     * Timezone identifiers
     * @return array
     */
    public static function timezoneList()
    {
        if (self::$timezones === null) {
            self::$timezones = [];
            $offsets = [];
            $now = new Carbon('now');

            foreach (\DateTimeZone::listIdentifiers() as $timezone) {
                $now->setTimezone(new \DateTimeZone($timezone));
                $offsets[] = $offset = $now->getOffset();
                self::$timezones[$timezone] = '(' . self::formatGmtOffset($offset) . ') ' . self::formatTimezoneName($timezone);
            }

            array_multisort($offsets, self::$timezones);
        }

        return self::$timezones;
    }

    private static function formatGmtOffset($offset) {
        $hours = intval($offset / 3600);
        $minutes = abs(intval($offset % 3600 / 60));
        return 'GMT' . ($offset ? sprintf('%+03d:%02d', $hours, $minutes) : '');
    }

    private static function formatTimezoneName($name) {
        $name = str_replace('/', ', ', $name);
        $name = str_replace('_', ' ', $name);
        $name = str_replace('St ', 'St. ', $name);
        return $name;
    }
}