<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-13 Daniel Garner
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
use DateTime;
use jDateTime;


class Date
{
    private static $timezones = null;

    /**
     * Get a local date
     * @param int|\Jenssegers\Date\Date $timestamp
     * @param string $format
     * @return string
     */
    public static function getLocalDate($timestamp = NULL, $format = NULL)
    {
        if ($format == NULL)
            $format = Date::getSystemFormat();

        if ($timestamp instanceof \Jenssegers\Date\Date)
            return $timestamp->format($format);

        if ($timestamp == NULL)
            $timestamp = time();

        if (Date::getCalendarType() == 'Jalali') {
            return JDateTime::date($format, $timestamp, false);
        }
        else {
            return \Jenssegers\Date\Date::createFromTimestamp($timestamp)->format($format);
        }
    }

    /**
     * Get a system date
     * @param string|\Jenssegers\Date\Date $timestamp
     * @param string $format
     * @return string
     */
    public static function getSystemDate($timestamp = NULL, $format = NULL)
    {
        if ($format == NULL)
            $format = Date::getSystemFormat();

        if ($timestamp instanceof \Jenssegers\Date\Date)
            return $timestamp->format($format);

        if ($timestamp == NULL)
            $timestamp = time();

        // Always return ISO formatted dates
        return gmdate($format, $timestamp);
    }

    /**
     * Get the Calendar Type
     * @return string
     */
    private static function getCalendarType()
    {
        return Config::GetSetting('CALENDAR_TYPE');
    }

    /**
     * Get the default date format
     * @return string
     */
    private static function getSystemFormat()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Get Date from String
     * @param string $string
     * @param string $format
     * @return \Jenssegers\Date\Date
     */
    public static function parse($string = null, $format = null)
    {
        if ($string == null)
            $string = Date::getLocalDate();

        if ($format == null)
            $format = Date::getSystemFormat();

        if (Date::getCalendarType() == 'Jalali') {
            // If we are Jalali, then we want to convert from Jalali back to Gregorian.
            // Split the time stamp into its component parts and pass it to the conversion.
            $date = trim($string);

            $split = (stripos($date, ' ') > 0) ? explode(' ', $date) : array($date, '');

            $dateSplit = explode('-', $split[0]);
            $timeSplit = explode(':', $split[1]);

            $date = jDateTime::toGregorian($dateSplit[0], $dateSplit[1], $dateSplit[2]);

            // Create a date out of that string.
            return \Jenssegers\Date\Date::create($date[0], $date[1], $date[2], $timeSplit[0], $timeSplit[1]);
        }
        else {
            return \Jenssegers\Date\Date::createFromFormat($format, $string);
        }
    }

    /**
     * Converts a format to moment
     *  inspired by http://stackoverflow.com/questions/30186611/php-dateformat-to-moment-js-format
     * @param $format
     * @return string
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
     * Converts a format to bootstrap date picker
     *  inspired by http://stackoverflow.com/questions/30186611/php-dateformat-to-moment-js-format
     * @param $format
     * @return string
     */
    public static function convertPhpToBootstrapFormat($format)
    {
        $replacements = [
            'd' => 'dd',
            'D' => '',
            'j' => 'd',
            'l' => '',
            'N' => '',
            'S' => '',
            'w' => '',
            'z' => '',
            'W' => '',
            'F' => 'MM',
            'm' => 'mm',
            'M' => 'M',
            'n' => 'i',
            't' => '', // no equivalent
            'L' => '', // no equivalent
            'o' => 'yyyy',
            'Y' => 'yyyy',
            'y' => 'yy',
            'a' => 'p',
            'A' => 'P',
            'B' => '', // no equivalent
            'g' => 'H',
            'G' => 'h',
            'h' => 'HH',
            'H' => 'hh',
            'i' => 'ii',
            's' => 'ss',
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
        ];
        $momentFormat = strtr($format, $replacements);
        return $momentFormat;
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
            $now = new DateTime();

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
