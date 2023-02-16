<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\Widget\DataType;

/**
 * Forecast DataType
 */
class Forecast implements \JsonSerializable
{
    public static $NAME = 'forecast';
    public $time;
    public $sunSet;
    public $sunRise;
    public $summary;
    public $icon;
    public $wicon;
    public $temperature;
    public $temperatureRound;
    public $temperatureNight;
    public $temperatureNightRound;
    public $temperatureMorning;
    public $temperatureMorningRound;
    public $temperatureEvening;
    public $temperatureEveningRound;
    public $temperatureHigh;
    public $temperatureMaxRound;
    public $temperatureLow;
    public $temperatureMinRound;
    public $temperatureMean;
    public $temperatureMeanRound;
    public $apparentTemperature;
    public $apparentTemperatureRound;
    public $dewPoint;
    public $humidity;
    public $humidityPercent;
    public $pressure;
    public $windSpeed;
    public $windBearing;
    public $windDirection;
    public $cloudCover;
    public $uvIndex;
    public $visibility;
    public $ozone;

    public $temperatureUnit;
    public $windSpeedUnit;
    public $visibilityDistanceUnit;

    /** @inheritDoc */
    public function jsonSerialize()
    {
        return [
            'time' => $this->time,
            'sunSet' => $this->sunSet,
            'sunRise' => $this->sunRise,
            'summary' => $this->summary,
            'icon' => $this->icon,
            'wicon' => $this->wicon,
            'temperature' => $this->temperature,
            'temperatureRound' => $this->temperatureRound,
            'temperatureNight' => $this->temperatureNight,
            'temperatureNightRound' => $this->temperatureNightRound,
            'temperatureMorning' => $this->temperatureMorning,
            'temperatureMorningRound' => $this->temperatureMorningRound,
            'temperatureEvening' => $this->temperatureEvening,
            'temperatureEveningRound' => $this->temperatureEveningRound,
            'temperatureHigh' => $this->temperatureHigh,
            'temperatureMaxRound' => $this->temperatureMaxRound,
            'temperatureLow' => $this->temperatureLow,
            'temperatureMinRound' => $this->temperatureMinRound,
            'temperatureMean' => $this->temperatureMean,
            'temperatureMeanRound' => $this->temperatureMeanRound,
            'apparentTemperature' => $this->apparentTemperature,
            'apparentTemperatureRound' => $this->apparentTemperatureRound,
            'dewPoint' => $this->dewPoint,
            'humidity' => $this->humidity,
            'humidityPercent' => $this->humidityPercent,
            'pressure' => $this->pressure,
            'windSpeed' => $this->windSpeed,
            'windBearing' => $this->windBearing,
            'windDirection' => $this->windDirection,
            'cloudCover' => $this->cloudCover,
            'uvIndex' => $this->uvIndex,
            'visibility' => $this->visibility,
            'ozone' => $this->ozone,
            'temperatureUnit' => $this->temperatureUnit,
            'windSpeedUnit' => $this->windSpeedUnit,
            'visibilityDistanceUnit' => $this->visibilityDistanceUnit
        ];
    }
}