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


use GuzzleHttp\Exception\RequestException;
use Xibo\Exception\GeneralException;

class OpenWeatherMapProvider implements WeatherProvider
{
    use WeatherProviderTrait;

    private $apiUrl = 'https://api.openweathermap.org/data/2.5/';
    private $forecast5day = 'forecast';

    /**
     * @inheritDoc
     */
    public function getAttribution()
    {
        return 'CC BY-SA 4.0';
    }

    /**
     * @inheritDoc
     */
    public function getCurrentDay()
    {
        // Convert units to an acceptable format
        $units = in_array($this->units, ['auto', 'us', 'uk2']) ? 'imperial' : 'metric';

        $url = $this->apiUrl . 'weather'
            . '?lat=' . $this->lat
            . '&lon=' . $this->long
            . '&units=' . $units
            . '&lang=' . $this->lang
            . '&appid=[API_KEY]';

        // Query the OWM api
        $data = $this->queryApi($url);

        // Parse into our forecast.
        $timezoneOffset = (int)$data['timezone'] / 3600;
        $this->timezone = (new \DateTimeZone(($timezoneOffset < 0 ? '-' : '+') . abs($timezoneOffset)))->getName();

        // Temperature and Wind Speed Unit Mappings
        $unit = $this->getUnit($this->units);

        // Load this data into our objects
        $this->currentDay = new ForecastDay();
        $this->currentDay->temperatureUnit = $unit['tempUnit'] ?: 'C';
        $this->currentDay->windSpeedUnit = $unit['windUnit'] ?: 'KPH';
        $this->currentDay->visibilityDistanceUnit = $unit['visibilityUnit'] ?: 'km';
        $this->processItemIntoDay($this->currentDay, $data, $units);

        return $this->currentDay;
    }

    /**
     * @inheritDoc
     */
    public function getForecast()
    {
        // Convert units to an acceptable format
        $units = in_array($this->units, ['auto', 'us', 'uk2']) ? 'imperial' : 'metric';

        $url = $this->apiUrl . $this->forecast5day
            . '?lat=' . $this->lat
            . '&lon=' . $this->long
            . '&units=' . $units
            . '&lang=' . $this->lang
            . '&appid=[API_KEY]';

        $data = $this->queryApi($url);

        // Temperature and Wind Speed Unit Mappings
        $unit = $this->getUnit($this->units);

        // Clear our any forecast we have already
        $this->forecast = [];

        foreach ($data['list'] as $item) {
            $day = new ForecastDay();
            $day->temperatureUnit = $unit['tempUnit'] ?: 'C';
            $day->windSpeedUnit = $unit['windUnit'] ?: 'KPH';
            $day->visibilityDistanceUnit = $unit['visibilityUnit'] ?: 'km';
            $this->processItemIntoDay($day, $item, $units);

            // Assign this day to the forecast
            $this->forecast[] = $day;
        }

        return $this->forecast;
    }

    /**
     * @param \Xibo\Weather\ForecastDay $day
     * @param array $item
     * @param $requestUnit
     */
    private function processItemIntoDay($day, $item, $requestUnit)
    {
        $day->time = $item['dt'];
        $day->summary = ucfirst($item['weather'][0]['description']);

        // Temperature
        // imperial = F
        // metric = C
        $day->temperature = $item['main']['temp'];
        $day->apparentTemperature = $item['main']['feels_like'];
        $day->temperatureHigh = $item['main']['temp_max'] ?? $day->temperature;
        $day->temperatureLow = $item['main']['temp_min'] ?? $day->temperature;

        if ($requestUnit === 'metric' && $day->temperatureUnit === 'F') {
            // Convert C to F
            $day->temperature = ($day->temperature) * 9 / 5 + 32;
            $day->apparentTemperature = ($day->apparentTemperature) * 9 / 5 + 32;
            $day->temperatureHigh = ($day->temperatureHigh) * 9 / 5 + 32;
            $day->temperatureLow = ($day->temperatureLow) * 9 / 5 + 32;

        } else if ($requestUnit === 'imperial' && $day->temperatureUnit === 'C') {
            // Convert F to C
            $day->temperature = ($day->temperature - 32) * 5 / 9;
            $day->apparentTemperature = ($day->apparentTemperature - 32) * 5 / 9;
            $day->temperatureHigh = ($day->temperatureHigh - 32) * 5 / 9;
            $day->temperatureLow = ($day->temperatureLow - 32) * 5 / 9;
        }

        // Round those off
        $day->temperatureRound = round($day->temperature, 0);
        $day->apparentTemperatureRound = round($day->apparentTemperature, 0);
        $day->temperatureMaxRound = round($day->temperatureHigh, 0);
        $day->temperatureMinRound = round($day->temperatureLow, 0);

        // Humidity
        $day->humidityPercent = $item['main']['humidity'];
        $day->humidity = $day->humidityPercent / 100;

        // Pressure
        $day->pressure = $item['main']['pressure'];

        // Wind
        // metric = meters per second
        // imperial = miles per hour
        $day->windSpeed = $item['wind']['speed'];
        $day->windBearing = $item['wind']['deg'];

        if ($requestUnit === 'metric' && $day->windSpeedUnit !== 'MPS') {
            // We have MPS and need to go to something else
            if ($day->windSpeedUnit === 'MPH') {
                // Convert MPS to MPH
                $day->windSpeed = round($day->windSpeed * 2.237, 2);
            } else if ($day->windSpeedUnit === 'KPH') {
                // Convert MPS to KPH
                $day->windSpeed = round($day->windSpeed * 3.6, 2);
            }
        } else if ($requestUnit === 'imperial' && $day->windSpeedUnit !== 'MPH') {
            if ($day->windSpeedUnit === 'MPS') {
                // Convert MPH to MPS
                $day->windSpeed = round($day->windSpeed / 2.237, 2);
            } else if ($day->windSpeedUnit === 'KPH') {
                // Convert MPH to KPH
                $day->windSpeed = round($day->windSpeed * 1.609344, 2);
            }
        }

        // Wind direction
        foreach (self::cardinalDirections() as $dir => $angles) {
            if ($day->windBearing >= $angles[0] && $day->windBearing < $angles[1]) {
                $day->windDirection = $dir;
                break;
            }
        }

        // Clouds
        $day->cloudCover = $item['clouds']['all'] / 100;

        // Visibility
        // metric = meters
        // imperial = meters?
        $day->visibility = $item['visibility'] ?? null;

        // Always in meters
        if ($day->visibilityDistanceUnit === 'mi') {
            // Convert meters to miles
            $day->visibility = $day->visibility / 1609;
        } else if ($day->visibilityDistanceUnit === 'km') {
            // Convert meters to KM
            $day->visibility = $day->visibility / 1000;
        }

        // not available
        $day->dewPoint = '--';
        $day->uvIndex = '--';
        $day->ozone = '--';

        // Map icon
        $icons = self::iconMap();
        $icon = $item['weather'][0]['icon'];

        $this->logger->debug('Icon is: ' . $icon);

        $day->icon = $icons['backgrounds'][$icon] ?? 'wi-na';
        $day->wicon = $icons['weather-icons'][$icon] ?? 'wi-na';
    }

    /**
     * @inheritDoc
     */
    public static function supportedLanguages()
    {
        return [
            ['id' => 'af', 'value' => __('Afrikaans')],
            ['id' => 'ar', 'value' => __('Arabic')],
            ['id' => 'az', 'value' => __('Azerbaijani')],
            ['id' => 'bg', 'value' => __('Bulgarian')],
            ['id' => 'ca', 'value' => __('Catalan')],
            ['id' => 'zh_cn', 'value' => __('Chinese Simplified')],
            ['id' => 'zh_tw', 'value' => __('Chinese Traditional')],
            ['id' => 'cz', 'value' => __('Czech')],
            ['id' => 'da', 'value' => __('Danish')],
            ['id' => 'de', 'value' => __('German')],
            ['id' => 'el', 'value' => __('Greek')],
            ['id' => 'en', 'value' => __('English')],
            ['id' => 'eu', 'value' => __('Basque')],
            ['id' => 'fa', 'value' => __('Persian (Farsi)')],
            ['id' => 'fi', 'value' => __('Finnish')],
            ['id' => 'fr', 'value' => __('French')],
            ['id' => 'gl', 'value' => __('Galician')],
            ['id' => 'he', 'value' => __('Hebrew')],
            ['id' => 'hi', 'value' => __('Hindi')],
            ['id' => 'hr', 'value' => __('Croatian')],
            ['id' => 'hu', 'value' => __('Hungarian')],
            ['id' => 'id', 'value' => __('Indonesian')],
            ['id' => 'it', 'value' => __('Italian')],
            ['id' => 'ja', 'value' => __('Japanese')],
            ['id' => 'kr', 'value' => __('Korean')],
            ['id' => 'la', 'value' => __('Latvian')],
            ['id' => 'lt', 'value' => __('Lithuanian')],
            ['id' => 'mk', 'value' => __('Macedonian')],
            ['id' => 'no', 'value' => __('Norwegian')],
            ['id' => 'nl', 'value' => __('Dutch')],
            ['id' => 'pl', 'value' => __('Polish')],
            ['id' => 'pt', 'value' => __('Portuguese')],
            ['id' => 'pt_br', 'value' => __('Português Brasil')],
            ['id' => 'ro', 'value' => __('Romanian')],
            ['id' => 'ru', 'value' => __('Russian')],
            ['id' => 'se', 'value' => __('Swedish')],
            ['id' => 'sk', 'value' => __('Slovak')],
            ['id' => 'sl', 'value' => __('Slovenian')],
            ['id' => 'es', 'value' => __('Spanish')],
            ['id' => 'sr', 'value' => __('Serbian')],
            ['id' => 'th', 'value' => __('Thai')],
            ['id' => 'tr', 'value' => __('Turkish')],
            ['id' => 'uk', 'value' => __('Ukrainian')],
            ['id' => 'vi', 'value' => __('Vietnamese')],
            ['id' => 'zu', 'value' => __('Zulu')]
        ];
    }

    /**
     * @return array
     */
    private function iconMap()
    {
        return [
            'weather-icons' => [
                '01d' => 'wi-day-sunny',
                '01n' => 'wi-night-clear',
                '02d' => 'wi-day-partly-cloudy',
                '02n' => 'wi-night-partly-cloudy',
                '03d' => 'wi-cloudy',
                '03n' => 'wi-night-cloudy',
                '04d' => 'wi-day-partly-cloudy',
                '04n' => 'wi-night-partly-cloudy',
                '09d' => 'wi-rain',
                '09n' => 'wi-night-rain',
                '10d' => 'wi-rain',
                '10n' => 'wi-night-rain',
                '11d' => 'wi-day-thunderstorm',
                '11n' => 'wi-night-thunderstorm',
                '13d' => 'wi-day-snow',
                '13n' => 'wi-night-snow',
                '50d' => 'wi-day-fog',
                '50n' => 'wi-night-fog'
            ],
            'backgrounds' => [
                '01d' => 'clear-day',
                '01n' => 'clear-night',
                '02d' => 'partly-cloudy-day',
                '02n' => 'partly-cloudy-night',
                '03d' => 'cloudy',
                '03n' => 'cloudy',
                '04d' => 'partly-cloudy-day',
                '04n' => 'partly-cloudy-night',
                '09d' => 'rain',
                '09n' => 'rain',
                '10d' => 'rain',
                '10n' => 'rain',
                '11d' => 'wind',
                '11n' => 'wind',
                '13d' => 'snow',
                '13n' => 'snow',
                '50d' => 'fog',
                '50n' => 'fog'
            ]
        ];
    }

    /**
     * @param string $url
     * @return array
     * @throws \Xibo\Exception\GeneralException
     */
    private function queryApi(string $url): array
    {
        $cache = $this->pool->getItem('/weather/owm/' . md5($url));
        $data = $cache->get();

        if ($cache->isMiss()) {
            $cache->lock();

            $url = str_replace('[API_KEY]', $this->apiKey, $url);

            try {
                $response = $this->client->get($url);

                // Success?
                if ($response->getStatusCode() != 200) {
                    throw new GeneralException('Non-200 response from Open Weather Map');
                }

                // Parse out header and body
                $data = json_decode($response->getBody(), true);

                // Cache
                $cache->set($data);
                $cache->expiresAfter($this->cachePeriod);
                $this->pool->saveDeferred($cache);

            } catch (RequestException $e) {
                $this->logger->error('Unable to reach Open Weather Map API: ' . $e->getMessage());
                throw new GeneralException('API responded with an error.');
            }
        } else {
            $this->logger->debug('Getting Forecast from cache');
        }

        return $data;
    }
}