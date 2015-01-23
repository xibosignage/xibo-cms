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
    private static $_format = NULL;

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

        if (format == NULL)
            $format = DateManager::getDefaultFormat();
        
        return date($format, $timestamp);
    }
    
    public static function getSystemDate($timestamp = NULL, $format = NULL)
    {
        if ($timestamp == NULL)
            $timestamp = time();

        if (format == NULL)
            $format = DateManager::getDefaultFormat();
        
        return gmdate($format, $timestamp);
    }

    /**
     * Get the default date format
     * @return string
     */
    public static function getDefaultFormat()
    {
        if (self::$_format == NULL)
            self::$_format = Config::GetSetting('DATE_FORMAT');

        return self::$_format;
    }

    /**
     * Gets a Unix Timestamp from a textual date time string
     * @param string $date
     * @return int
     */
    public static function getDateFromString($date)
    {
        return strtotime($date);
    }
} 
?>