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


/**
 * Class Forecast
 *  this class represents a weather forecast, either current or future.
 * @package Xibo\Weather
 */
class Forecast
{
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
}