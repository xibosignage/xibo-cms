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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class DateManager
{
    public static function getClock()
    {
        return date("H:i T");
    }
    
    public static function getSystemClock()
    {
        return gmdate("H:i T");
    }
    
    public static function getLocalDate($timestamp = NULL, $format = NULL)
    {
        if ($timestamp == NULL)
            $timestamp = time();

        if ($format == NULL)
            $format = DateManager::getDefaultFormat();
        
        return (DateManager::getCalendarType() == 'Jalali') ? JDateTime::date($format, $timestamp, false) : date($format, $timestamp);
    }
    
    public static function getSystemDate($timestamp = NULL, $format = NULL)
    {
        if ($timestamp == NULL)
            $timestamp = time();

        if ($format == NULL)
            $format = 'Y-m-d H:i:s';

        // Always return ISO formatted dates
        return date($format, $timestamp);
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
    public static function getDateFromString($date)
    {
        $timestamp = strtotime($date);

        // If we are Jalali, then we want to convert from Jalali back to Gregorian. Otherwise assume input is already Gregorian.
        if (Config::GetSetting('CALENDAR_TYPE') == 'Jalali') {
            // Split the time stamp into its component parts and pass it to the conversion.
            $date = jDateTime::toGregorian(date('Y', $timestamp), date('m', $timestamp), date('d', $timestamp));

            // Convert that back into a date using strtotime - the date is now Gregorian
            $timestamp = strtotime($date[0] . '-' . $date[1] . '-' . $date[2] . ' ' . date('H') . ':' . date('i') . ':' . date('s'));
        }

        return $timestamp;
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
} 
?>