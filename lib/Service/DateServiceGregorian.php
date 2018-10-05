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

/**
 * Class DateServiceGregorian
 * @package Xibo\Service
 */
class DateServiceGregorian implements DateServiceInterface
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

        if ($timestamp instanceof \Jenssegers\Date\Date)
            return $timestamp->format($format);

        if ($timestamp === NULL)
            $timestamp = time();

        return \Jenssegers\Date\Date::createFromTimestamp($timestamp, $timezone)->format($format);
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
}
