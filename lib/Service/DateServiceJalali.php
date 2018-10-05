<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (DateJalali.php)
 */


namespace Xibo\Service;

/**
 * Class DateServiceJalali
 * @package Xibo\Service
 */
class DateServiceJalali implements DateServiceInterface
{
    use DateServiceTrait;

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

        if ($timestamp === NULL)
            $timestamp = time();

        if (!($timestamp instanceof \Jenssegers\Date\Date))
            $timestamp = \Jenssegers\Date\Date::createFromTimestamp($timestamp, $timezone);

        $jDate = \jDateTime::toJalali($timestamp->year, $timestamp->month, $timestamp->day);

        return \Jenssegers\Date\Date::create($jDate[0], $jDate[1], $jDate[2], $timestamp->hour, $timestamp->minute, $timestamp->second)->format($format);
    }

    /**
     * Get Date from String
     * @param string $string
     * @param string $format
     * @return \Jenssegers\Date\Date
     */
    public function parse($string = null, $format = null)
    {
        // Get a local date (jalali date)
        if ($string === null) {
            $string = $this->getLocalDate();
            $format = null;
        }

        if ($format === null)
            $format = $this->getSystemFormat();

        // We are a timestamp, create a date out of the time stamp directly, timestamps are always calendar agnostic
        if ($format == 'U') {
            return \Jenssegers\Date\Date::createFromFormat($format, $string);
        } else {
            // If we are Jalali, then we want to convert from Jalali back to Gregorian.
            $jDate = \Jenssegers\Date\Date::createFromFormat($format, $string);

            $date = \jDateTime::toGregorian($jDate->year, $jDate->month, $jDate->day);

            // Create a date out of that string.
            return \Jenssegers\Date\Date::create($date[0], $date[1], $date[2], $jDate->hour, $jDate->minute, $jDate->second);
        }
    }

    /**
     * @inheritdoc
     */
    public function convertPhpToMomentFormat($format)
    {
        $replacements = [
            'd' => 'jDD',
            'D' => 'jddd',
            'j' => 'jD',
            'l' => 'jdddd',
            'N' => 'jE',
            'S' => 'jo',
            'w' => 'je',
            'z' => 'jDDD',
            'W' => 'jW',
            'F' => 'jMMMM',
            'm' => 'jMM',
            'M' => 'jMMM',
            'n' => 'jM',
            't' => '', // no equivalent
            'L' => '', // no equivalent
            'o' => 'jYYYY',
            'Y' => 'jYYYY',
            'y' => 'jYY',
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
            'e' => 'jzz', // deprecated since version 1.6.0 of moment.js
            'I' => '', // no equivalent
            'O' => '', // no equivalent
            'P' => '', // no equivalent
            'T' => '', // no equivalent
            'Z' => '', // no equivalent
            'c' => '', // no equivalent
            'r' => '', // no equivalent
            'U' => 'jX',
        ];
        $momentFormat = strtr($format, $replacements);
        return $momentFormat;
    }
}