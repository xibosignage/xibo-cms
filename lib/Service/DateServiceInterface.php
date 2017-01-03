<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (DateInterface.php)
 */

namespace Xibo\Service;

/**
 * Interface DateServiceInterface
 * @package Xibo\Service
 */
interface DateServiceInterface
{
    /**
     * Get a local date
     * @param int|\Jenssegers\Date\Date $timestamp
     * @param string $format
     * @param string $timezone
     * @return string
     */
    public function getLocalDate($timestamp = NULL, $format = NULL, $timezone = NULL);


    /**
     * Get the default date format
     * @return string
     */
    public function getSystemFormat();

    /**
     * Get Date from String
     * @param string $string
     * @param string $format
     * @return \Jenssegers\Date\Date
     */
    public function parse($string = null, $format = null);

    /**
     * Set Locale
     * @param $identifier
     */
    public function setLocale($identifier);

    /**
     * Timezone identifiers
     * @return array
     */
    public function timezoneList();
    
    /**
     * Extract only a time format from mask
     * @param $format
     * @return string
     */
    public function extractTimeFormat($format);

    /**
     * Converts a format to moment
     *  inspired by http://stackoverflow.com/questions/30186611/php-dateformat-to-moment-js-format
     * @param $format
     * @return string
     */
    public function convertPhpToMomentFormat($format);

    /**
     * Converts a format to bootstrap date picker
     *  inspired by http://stackoverflow.com/questions/30186611/php-dateformat-to-moment-js-format
     * @param $format
     * @param $includeTime
     * @return string
     */
    public function convertPhpToBootstrapFormat($format, $includeTime = true);
}