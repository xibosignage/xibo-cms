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
namespace Xibo\Service;
use DateTime;

/**
 * Class DateServiceGregorian
 * @package Xibo\Service
 */
class DateServiceGregorian implements DateServiceInterface
{
    private static $timezones = null;

    /**
     * Get a local date
     * @param int|\Jenssegers\Date\Date $timestamp
     * @param string $format
     * @param string $timezone
     * @return string
     */
    public function getLocalDate($timestamp = NULL, $format = NULL, $timezone = NULL)
    {
        if ($format === NULL)
            $format = $this->getSystemFormat();

        if ($timestamp instanceof \Jenssegers\Date\Date)
            return $timestamp->format($format);

        if ($timestamp === NULL)
            $timestamp = time();

        return \Jenssegers\Date\Date::createFromTimestamp($timestamp, $timezone)->format($format);
    }

    /**
     * Get the default date format
     * @return string
     */
    private function getSystemFormat()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Get Date from String
     * @param string $string
     * @param string $format
     * @return \Jenssegers\Date\Date
     */
    public function parse($string = null, $format = null)
    {
        if ($string === null) {
            $string = $this->getLocalDate();
            $format = null;
        }

        if ($format === null)
            $format = $this->getSystemFormat();

        return ($format == 'U') ? \Jenssegers\Date\Date::createFromTimestamp($string) : \Jenssegers\Date\Date::createFromFormat($format, $string);
    }

    /**
     * @inheritdoc
     */
    public function setLocale($identifier)
    {
        \Jenssegers\Date\Date::setLocale($identifier);
    }

    /**
     * Converts a format to moment
     *  inspired by http://stackoverflow.com/questions/30186611/php-dateformat-to-moment-js-format
     * @param $format
     * @return string
     */
    public function convertPhpToMomentFormat($format)
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
    public function convertPhpToBootstrapFormat($format, $includeTime = true)
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
        ];

        if ($includeTime) {
            $replacements['g'] = 'H';
            $replacements['G'] = 'h';
            $replacements['h'] = 'HH';
            $replacements['H'] = 'hh';
            $replacements['i'] = 'ii';
            $replacements['s'] = 'ss';
        }

        $momentFormat = strtr($format, $replacements);
        return trim($momentFormat, ' :');
    }

    /**
     * Timezone identifiers
     * @return array
     */
    public function timezoneList()
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
