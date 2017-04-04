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
        if ($string === null)
            $string = $this->getLocalDate();

        if ($format == 'U') {
            // We are a timestamp, create a date out of the time stamp directly
            return \Jenssegers\Date\Date::createFromFormat($format, $string);
        }

        // If we are Jalali, then we want to convert from Jalali back to Gregorian.
        // Split the time stamp into its component parts and pass it to the conversion.
        $date = trim($string);

        $split = (stripos($date, ' ') > 0) ? explode(' ', $date) : array($date, '');

        $dateSplit = explode('-', $split[0]);
        $timeSplit = explode(':', $split[1]);

        $date = \jDateTime::toGregorian($dateSplit[0], $dateSplit[1], $dateSplit[2]);

        // Create a date out of that string.
        return \Jenssegers\Date\Date::create($date[0], $date[1], $date[2], $timeSplit[0], $timeSplit[1]);
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