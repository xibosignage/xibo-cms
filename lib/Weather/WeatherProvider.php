<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

namespace Xibo\Weather;


use Xibo\Service\LogServiceInterface;

interface WeatherProvider
{
    /**
     * @param \Psr\Log\NullLogger|LogServiceInterface $logger
     * @return $this
     */
    public function enableLogging($logger);

    /**
     * @param \GuzzleHttp\ClientInterface $client
     * @return $this
     */
    public function setHttpClient($client);

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options);

    /**
     * @param string $key
     * @return $this
     */
    public function setKey(string $key);

    /**
     * @param int $cachePeriod
     * @return $this
     */
    public function setCachePeriod(int $cachePeriod);

    /**
     * @param $lat
     * @param $long
     * @return $this
     */
    public function setLocation($lat, $long);

    /**
     * @param $lang
     * @return $this
     */
    public function setLang($lang);

    /**
     * @param $units
     * @return $this
     */
    public function setUnits($units);

    /**
     * @return string
     */
    public function getAttribution();

    /**
     * @return \Xibo\Weather\Forecast
     * @throws \Xibo\Exception\GeneralException
     */
    public function getCurrentDay();

    /**
     * @return \Xibo\Weather\Forecast[]
     * @throws \Xibo\Exception\GeneralException
     */
    public function getForecast();

    /**
     * Get TimeZone
     * @return string
     */
    public function getTimezone();

    /**
     * Units supported by Forecast.IO API
     * @return array The Units Available (temperature, wind speed and visible distance)
     */
    public static function unitsAvailable();

    /**
     * Languages supported by Forecast.IO API
     * @return array The Supported Language
     */
    public static function supportedLanguages();
}