<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2017 Spring Signage Ltd
 * (DateServiceTrait.php)
 */


namespace Xibo\Service;

/**
 * Trait DateServiceTrait
 * @package Xibo\Service
 */
trait DateServiceTrait
{
    private static $timezones = null;

    /**
     * @inheritdoc
     */
    public function setLocale($identifier)
    {
        \Jenssegers\Date\Date::setLocale($identifier);
    }

    /**
     * Get the default date format
     * @return string
     */
    public function getSystemFormat()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * @inheritdoc
     */
    public function extractTimeFormat($format)
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
            ':' => ''
        ];

        if ($includeTime) {
            $replacements['g'] = 'H';
            $replacements['G'] = 'h';
            $replacements['h'] = 'HH';
            $replacements['H'] = 'hh';
            $replacements['i'] = 'ii';
            $replacements['s'] = 'ss';
            $replacements['a'] = 'p';
            $replacements['A'] = 'P';
            $replacements[':'] = ':';
        }

        $momentFormat = strtr($format, $replacements);
        return trim($momentFormat);
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
            $now = new \DateTime();

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