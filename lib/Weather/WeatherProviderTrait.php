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


use Psr\Log\NullLogger;
use Xibo\Service\LogServiceInterface;

trait WeatherProviderTrait
{
    /** @var \Stash\Interfaces\PoolInterface */
    protected $pool;

    /** @var \GuzzleHttp\Client */
    protected $client;

    /** @var \Psr\Log\NullLogger|LogServiceInterface */
    protected $logger;

    /** @var array $options */
    protected $options;

    /** @var string The API Key */
    protected $apiKey;

    protected $cachePeriod = 14400;

    protected $lat;
    protected $long;
    protected $units;
    protected $lang;

    /** @var string */
    protected $timezone;

    /** @var \Xibo\Weather\Forecast */
    protected $currentDay;

    /** @var \Xibo\Weather\Forecast[] */
    protected $forecast;

    // <editor-fold desc="Getters/Setters">

    /**
     * Constructor.
     * @param \Stash\Interfaces\PoolInterface $pool
     */
    public function __construct($pool)
    {
        $this->pool = $pool;
        $this->logger = new NullLogger();
    }

    /** @inheritDoc */
    public function enableLogging($logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /** @inheritDoc */
    public function setHttpClient($client)
    {
        $this->client = $client;
        return $this;
    }

    /** @inheritDoc */
    public function setOptions(array $options)
    {
        if (isset($options['url'])) {
            $this->apiUrl = $options['url'];
        }
        $this->options = $options;
        return $this;
    }

    /** @inheritDoc */
    public function setKey(string $key)
    {
        $this->apiKey = $key;
        return $this;
    }

    /** @inheritDoc */
    public function setCachePeriod(int $cachePeriod)
    {
        $this->cachePeriod = $cachePeriod;
        return $this;
    }

    /** @inheritDoc */
    public function setLocation($lat, $long)
    {
        $this->logger->debug('setLocation: Lat = ' . $lat . ', Long = ' . $long);
        $this->lat = $lat;
        $this->long = $long;
        return $this;
    }

    /** @inheritDoc */
    public function setLang($lang)
    {
        $this->logger->debug('setLang: Lang = ' . $lang);
        $this->lang = $lang;
        return $this;
    }

    /** @inheritDoc */
    public function setUnits($units)
    {
        $this->logger->debug('setUnits: Units = ' . $units);
        $this->units = $units;
        return $this;
    }

    // </editor-fold>

    /**
     * @inheritDoc
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /** @inheritDoc */
    public static function unitsAvailable()
    {
        return [
            ['id' => 'auto', 'value' => 'Automatically select based on geographic location', 'tempUnit' => '', 'windUnit' => '', 'visibilityUnit' => ''],
            ['id' => 'ca', 'value' => 'Canada', 'tempUnit' => 'C', 'windUnit' => 'KPH', 'visibilityUnit' => 'km'],
            ['id' => 'si', 'value' => 'Standard International Units', 'tempUnit' => 'C', 'windUnit' => 'MPS', 'visibilityUnit' => 'km'],
            ['id' => 'uk2', 'value' => 'United Kingdom', 'tempUnit' => 'C', 'windUnit' => 'MPH', 'visibilityUnit' => 'mi'],
            ['id' => 'us', 'value' => 'United States', 'tempUnit' => 'F', 'windUnit' => 'MPH', 'visibilityUnit' => 'mi'],
        ];
    }

    /**
     * @param $code
     * @return mixed|null
     */
    public function getUnit($code)
    {
        foreach (self::unitsAvailable() as $unit) {
            if ($unit['id'] == $code) {
                return $unit;
            }
        }
        return null;
    }

    /**
     * @return array
     */
    private static function cardinalDirections()
    {
        return [
            'N' => [337.5, 22.5],
            'NE' => [22.5, 67.5],
            'E' => [67.5, 112.5],
            'SE' => [112.5, 157.5],
            'S' => [157.5, 202.5],
            'SW' => [202.5, 247.5],
            'W' => [247.5, 292.5],
            'NW' => [292.5, 337.5]
        ];
    }
}