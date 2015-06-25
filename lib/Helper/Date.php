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

    public static function getClock()
    {
        return Date::getLocalDate(null, 'H:i T');
    }

    public static function getSystemClock()
    {
        return Date::getSystemDate(null, 'H:i T');
    }

    /**
     * Get a local date
     * @param int $timestamp
     * @param string $format
     * @return string
     */
    public static function getLocalDate($timestamp = NULL, $format = NULL)
    {
        if ($timestamp == NULL)
            $timestamp = time();

        if ($format == NULL)
            $format = Date::getDefaultFormat();

        if (Date::getCalendarType() == 'Jalali') {
            return JDateTime::date($format, $timestamp, false);
        }
        else {
            return \Jenssegers\Date\Date::createFromTimestamp($timestamp)->format($format);
        }
    }

    /**
     * Get a system date
     * @param null $timestamp
     * @param null $format
     * @return string
     */
    public static function getSystemDate($timestamp = NULL, $format = NULL)
    {
        if ($timestamp == NULL)
            $timestamp = time();

        if ($format == NULL)
            $format = 'Y-m-d H:i:s';

        // Always return ISO formatted dates
        return gmdate($format, $timestamp);
    }

    /**
     * Get midnight system date
     * @param string $timestamp
     * @param string $format
     * @return string
     */
    public static function getMidnightSystemDate($timestamp = NULL, $format = NULL)
    {
        if ($timestamp == NULL)
            $timestamp = time();

        // Get the timestamp and Trim the hours off it.
        $dateTime = new DateTime();
        $dateTime->setTimestamp($timestamp);
        $dateTime->setTime(0, 0, 0);

        return self::getSystemDate($dateTime->getTimestamp(), $format);
    }

    /**
     * Get the Calendar Type
     * @return string
     */
    public static function getCalendarType()
    {
        return Config::GetSetting('CALENDAR_TYPE');
    }

    /**
     * Get the default date format
     * @return string
     */
    public static function getDefaultFormat()
    {
        return Config::GetSetting('DATE_FORMAT');
    }

    /**
     * Gets a Unix Timestamp from a textual date time string
     * @param string $date
     * @return int
     */
    public static function getTimestampFromString($date)
    {
        return Date::fromString($date)->format('U');
    }

    /**
     * Get Date from String
     * @param $string
     * @return \Jenssegers\Date\Date
     */
    public static function fromString($string)
    {
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
            return new \Jenssegers\Date\Date($string);
        }
    }

    public static function getTimestampFromTimeString($time)
    {
        return strtotime($time);
    }

    public static function getIsoDateFromString($date)
    {
        return date('Y-m-d H:i:s', self::getTimestampFromString($date));
    }

    /**
     * Gets a Unix Timestamp from a textual UTC date time string
     * @param $date
     * @return int
     */
    public static function getDateFromGregorianString($date)
    {
        return strtotime($date);
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
