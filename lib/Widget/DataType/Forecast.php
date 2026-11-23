<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Widget\DataType;

use Xibo\Widget\Definition\DataType;

/**
 * Forecast DataType
 */
class Forecast implements \JsonSerializable, DataTypeInterface
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
    public $location;

    public $temperatureUnit;
    public $windSpeedUnit;
    public $visibilityDistanceUnit;

    /** @inheritDoc */
    public function jsonSerialize(): array
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
            'location' => $this->location,
            'temperatureUnit' => $this->temperatureUnit,
            'windSpeedUnit' => $this->windSpeedUnit,
            'visibilityDistanceUnit' => $this->visibilityDistanceUnit
        ];
    }

    public function getDefinition(): DataType
    {
        $dataType = new DataType();
        $dataType->id = self::$NAME;
        $dataType->name = __('Forecast');
        $dataType
            ->addField('time', 'Time', 'datetime')
            ->addField('sunSet', 'Sun Set', 'datetime')
            ->addField('sunRise', 'Sun Rise', 'datetime')
            ->addField('summary', 'Summary', 'text')
            ->addField('icon', 'Icon', 'text')
            ->addField('wicon', 'Weather Icon', 'text')
            ->addField('temperature', 'Temperature', 'number')
            ->addField('temperatureRound', 'Temperature Round', 'number')
            ->addField('temperatureNight', 'Temperature Night', 'number')
            ->addField('temperatureNightRound', 'Temperature Night Round', 'number')
            ->addField('temperatureMorning', 'Temperature Morning', 'number')
            ->addField('temperatureMorningRound', 'Temperature Morning Round', 'number')
            ->addField('temperatureEvening', 'Temperature Evening', 'number')
            ->addField('temperatureEveningRound', 'Temperature Evening Round', 'number')
            ->addField('temperatureHigh', 'Temperature High', 'number')
            ->addField('temperatureMaxRound', 'Temperature Max Round', 'number')
            ->addField('temperatureLow', 'Temperature Low', 'number')
            ->addField('temperatureMinRound', 'Temperature Min Round', 'number')
            ->addField('temperatureMean', 'Temperature Mean', 'number')
            ->addField('temperatureMeanRound', 'Temperature Mean Round', 'number')
            ->addField('apparentTemperature', 'Apparent Temperature', 'number')
            ->addField('apparentTemperatureRound', 'Apparent Temperature Round', 'number')
            ->addField('dewPoint', 'Dew Point', 'number')
            ->addField('humidity', 'Humidity', 'number')
            ->addField('humidityPercent', 'Humidity Percent', 'number')
            ->addField('pressure', 'Pressure', 'number')
            ->addField('windSpeed', 'Wind Speed', 'number')
            ->addField('windBearing', 'Wind Bearing', 'number')
            ->addField('windDirection', 'Wind Direction', 'text')
            ->addField('cloudCover', 'Cloud Cover', 'number')
            ->addField('uvIndex', 'Uv Index', 'number')
            ->addField('visibility', 'Visibility', 'number')
            ->addField('ozone', 'Ozone', 'number')
            ->addField('location', 'Location', 'text')
            ->addField('temperatureUnit', 'Temperature Unit', 'text')
            ->addField('windSpeedUnit', 'WindSpeed Unit', 'text')
            ->addField('visibilityDistanceUnit', 'VisibilityDistance Unit', 'text');
        return $dataType;
    }
}
