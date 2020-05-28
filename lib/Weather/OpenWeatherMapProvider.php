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
use Illuminate\Support\Str;
use Jenssegers\Date\Date;
use Xibo\Exception\GeneralException;

class OpenWeatherMapProvider implements WeatherProvider
{
    use WeatherProviderTrait;

    private $apiUrl = 'https://api.openweathermap.org/data/2.5/';
    private $forecastCurrent = 'weather';
    private $forecast3Hourly = 'forecast';
    private $forecastDaily = 'forecast/daily';
    private $forecastCombined = 'onecall';
    private $forecastUv = 'uvi';

    /**
     * @inheritDoc
     */
    public function getAttribution()
    {
        return 'Powered by OpenWeather';
    }

    /** @inheritDoc */
    public function getCurrentDay()
    {
        if ($this->currentDay === null) {
            $this->get();
        }

        return $this->currentDay;
    }

    /** @inheritDoc */
    public function getForecast()
    {
        if ($this->forecast === null) {
            $this->get();
        }

        return $this->forecast;
    }

    /**
     * Get a combined forecast
     * @throws \Xibo\Exception\GeneralException
     */
    private function get()
    {
        // Convert units to an acceptable format
        $units = in_array($this->units, ['auto', 'us', 'uk2']) ? 'imperial' : 'metric';

        // Temperature and Wind Speed Unit Mappings
        $unit = $this->getUnit($this->units);

        // Build the URL
        $url = '?lat=' . $this->lat
            . '&lon=' . $this->long
            . '&units=' . $units
            . '&lang=' . $this->lang
            . '&appid=[API_KEY]';

        // Cache expiry date
        $cacheExpire = Date::now()->addSeconds($this->cachePeriod);

        if ($this->options['isPaidPlan'] ?? 0 == 1) {
            // We build our data from multiple API calls
            // Current data first.
            $data = $this->queryApi($this->apiUrl . $this->forecastCurrent . $url, $cacheExpire);
            $data['current'] = $this->parseCurrentIntoFormat($data);

            $timezoneOffset = (int)$data['timezone'] / 3600;
            $this->timezone = (new \DateTimeZone(($timezoneOffset < 0 ? '-' : '+') . abs($timezoneOffset)))->getName();

            // Pick out the country
            $country = $data['sys']['country'] ?? null;

            $this->logger->debug('Trying to determine units for Country: ' . $country);

            // If we don't have a unit, then can we base it on the timezone we got back?
            if ($this->units === 'auto' && $country !== null) {
                // Pick out some countries to set the units
                if ($country === 'GB') {
                    $unit = $this->getUnit('uk2');
                } else if ($country === 'US') {
                    $unit = $this->getUnit('us');
                } else if ($country === 'CA') {
                    $unit = $this->getUnit('ca');
                } else {
                    $unit = $this->getUnit('si');
                }
            }

            // Then the 16 day forecast API, which we will cache a day
            $data['daily'] = $this->queryApi($this->apiUrl . $this->forecastDaily . $url, $cacheExpire->copy()->addDay()->startOfDay())['list'];
        } else {
            // We use onecall
            $data = $this->queryApi($this->apiUrl . $this->forecastCombined . $url, $cacheExpire);

            $this->timezone = $data['timezone'];

            // Country based on timezone (this is harder than using the real country)
            if ($this->units === 'auto') {
                if (Str::startsWith($this->timezone, 'America')) {
                    $unit = $this->getUnit('us');
                } else if ($this->timezone === 'Europe/London') {
                    $unit = $this->getUnit('uk2');
                } else {
                    $unit = $this->getUnit('si');
                }
            }
        }

        // Using units:
        $this->logger->debug('Using units: ' . json_encode($unit));

        // Parse into our forecast.
        // Load this data into our objects
        $this->currentDay = new Forecast();
        $this->currentDay->temperatureUnit = $unit['tempUnit'] ?: 'C';
        $this->currentDay->windSpeedUnit = $unit['windUnit'] ?: 'KPH';
        $this->currentDay->visibilityDistanceUnit = $unit['visibilityUnit'] ?: 'km';
        $this->processItemIntoDay($this->currentDay, $data['current'], $units, true);

        // Process each day into a forecast
        foreach ($data['daily'] as $dayItem) {
            $day = new Forecast();
            $day->temperatureUnit = $this->currentDay->temperatureUnit;
            $day->windSpeedUnit = $this->currentDay->windSpeedUnit;
            $day->visibilityDistanceUnit = $this->currentDay->visibilityDistanceUnit;
            $this->processItemIntoDay($day, $dayItem, $units);

            $this->forecast[] = $day;
        }

        // Enhance the currently with the high/low from the first daily forecast
        $this->currentDay->temperatureHigh = $this->forecast[0]->temperatureHigh;
        $this->currentDay->temperatureMaxRound = $this->forecast[0]->temperatureMaxRound;
        $this->currentDay->temperatureLow = $this->forecast[0]->temperatureLow;
        $this->currentDay->temperatureMinRound = $this->forecast[0]->temperatureMinRound;
        $this->currentDay->temperatureMorning = $this->forecast[0]->temperatureMorning;
        $this->currentDay->temperatureMorningRound = $this->forecast[0]->temperatureMorningRound;
        $this->currentDay->temperatureNight = $this->forecast[0]->temperatureNight;
        $this->currentDay->temperatureNightRound = $this->forecast[0]->temperatureNightRound;
        $this->currentDay->temperatureEvening = $this->forecast[0]->temperatureEvening;
        $this->currentDay->temperatureEveningRound = $this->forecast[0]->temperatureEveningRound;
        $this->currentDay->temperatureMean = $this->forecast[0]->temperatureMean;
        $this->currentDay->temperatureMeanRound = $this->forecast[0]->temperatureMeanRound;
    }

    /**
     * Parse the response from the current API into the format provided by the onecall API
     * this means easier processing down the line
     * @param array $source
     * @return array
     */
    private function parseCurrentIntoFormat(array $source): array
    {
        return [
            'timezone' => $source['timezone'],
            'dt' => $source['dt'],
            'sunrise' => $source['sys']['sunrise'],
            'sunset' => $source['sys']['sunset'],
            'temp' => $source['main']['temp'],
            'feels_like' => $source['main']['feels_like'],
            'pressure' => $source['main']['pressure'],
            'humidity' => $source['main']['humidity'],
            'dew_point' => null,
            'uvi' => null,
            'clouds' => $source['clouds']['all'],
            'visibility' => $source['visibility'] ?? 0,
            'wind_speed' => $source['wind']['speed'],
            'wind_deg' => $source['wind']['deg'] ?? 0,
            'weather' => $source['weather'],
        ];
    }

    /**
     * @param \Xibo\Weather\Forecast $day
     * @param array $item
     * @param $requestUnit
     * @param bool $isCurrent
     */
    private function processItemIntoDay($day, $item, $requestUnit, $isCurrent = false)
    {
        $day->time = $item['dt'];
        $day->sunRise = $item['sunrise'];
        $day->sunSet = $item['sunset'];
        $day->summary = ucfirst($item['weather'][0]['description']);

        // Temperature
        // imperial = F
        // metric = C
        if ($isCurrent) {
            $day->temperature = $item['temp'];
            $day->apparentTemperature = $item['feels_like'];
            $day->temperatureHigh = $day->temperature;
            $day->temperatureLow = $day->temperature;
            $day->temperatureNight = $day->temperature;
            $day->temperatureEvening = $day->temperature;
            $day->temperatureMorning = $day->temperature;
        } else {
            $day->temperature = $item['temp']['day'];
            $day->apparentTemperature = $item['feels_like']['day'];
            $day->temperatureHigh = $item['temp']['max'] ?? $day->temperature;
            $day->temperatureLow = $item['temp']['min'] ?? $day->temperature;
            $day->temperatureNight = $item['temp']['night'];
            $day->temperatureEvening = $item['temp']['eve'];
            $day->temperatureMorning = $item['temp']['morn'];
        }

        if ($requestUnit === 'metric' && $day->temperatureUnit === 'F') {
            // Convert C to F
            $day->temperature = ($day->temperature) * 9 / 5 + 32;
            $day->apparentTemperature = ($day->apparentTemperature) * 9 / 5 + 32;
            $day->temperatureHigh = ($day->temperatureHigh) * 9 / 5 + 32;
            $day->temperatureLow = ($day->temperatureLow) * 9 / 5 + 32;
            $day->temperatureNight = ($day->temperatureNight) * 9 / 5 + 32;
            $day->temperatureEvening = ($day->temperatureEvening) * 9 / 5 + 32;
            $day->temperatureMorning = ($day->temperatureMorning) * 9 / 5 + 32;

        } else if ($requestUnit === 'imperial' && $day->temperatureUnit === 'C') {
            // Convert F to C
            $day->temperature = ($day->temperature - 32) * 5 / 9;
            $day->apparentTemperature = ($day->apparentTemperature - 32) * 5 / 9;
            $day->temperatureHigh = ($day->temperatureHigh - 32) * 5 / 9;
            $day->temperatureLow = ($day->temperatureLow - 32) * 5 / 9;
            $day->temperatureNight = ($day->temperatureNight - 32) * 5 / 9;
            $day->temperatureEvening = ($day->temperatureEvening - 32) * 5 / 9;
            $day->temperatureMorning = ($day->temperatureMorning - 32) * 5 / 9;
        }

        // Work out the mean
        $day->temperatureMean = ($day->temperatureHigh + $day->temperatureLow) / 2;

        // Round those off
        $day->temperatureRound = round($day->temperature, 0);
        $day->temperatureNightRound = round($day->temperatureNight, 0);
        $day->temperatureMorningRound = round($day->temperatureMorning, 0);
        $day->temperatureEveningRound = round($day->temperatureEvening, 0);
        $day->apparentTemperatureRound = round($day->apparentTemperature, 0);
        $day->temperatureMaxRound = round($day->temperatureHigh, 0);
        $day->temperatureMinRound = round($day->temperatureLow, 0);
        $day->temperatureMeanRound = round($day->temperatureMean, 0);

        // Humidity
        $day->humidityPercent = $item['humidity'];
        $day->humidity = $day->humidityPercent / 100;

        // Pressure
        // received in hPa, display in mB
        $day->pressure = $item['pressure'] / 100;

        // Wind
        // metric = meters per second
        // imperial = miles per hour
        $day->windSpeed = $item['wind_speed'] ?? $item['speed'] ?? null;
        $day->windBearing = $item['wind_deg'] ?? $item['deg'] ?? null;

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
        if ($day->windBearing !== null && $day->windBearing !== 0) {
            foreach (self::cardinalDirections() as $dir => $angles) {
                if ($day->windBearing >= $angles[0] && $day->windBearing < $angles[1]) {
                    $day->windDirection = $dir;
                    break;
                }
            }
        } else {
            $day->windDirection = '--';
        }

        // Clouds
        $day->cloudCover = $item['clouds'];

        // Visibility
        // metric = meters
        // imperial = meters?
        $day->visibility = $item['visibility'] ?? '--';

        if ($day->visibility !== '--') {
            // Always in meters
            if ($day->visibilityDistanceUnit === 'mi') {
                // Convert meters to miles
                $day->visibility = $day->visibility / 1609;
            } else {
                if ($day->visibilityDistanceUnit === 'km') {
                    // Convert meters to KM
                    $day->visibility = $day->visibility / 1000;
                }
            }
        }

        // not available
        $day->dewPoint = $item['dew_point'] ?? '--';
        $day->uvIndex = $item['uvi'] ?? '--';
        $day->ozone = '--';

        // Map icon
        $icons = self::iconMap();
        $icon = $item['weather'][0]['icon'];
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
                '02d' => 'wi-day-cloudy',
                '02n' => 'wi-night-partly-cloudy',
                '03d' => 'wi-cloudy',
                '03n' => 'wi-night-cloudy',
                '04d' => 'wi-day-cloudy',
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
     * @param \Jenssegers\Date\Date $cacheExpiresAt
     * @return array
     * @throws \Xibo\Exception\GeneralException
     */
    private function queryApi(string $url, Date $cacheExpiresAt): array
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
                $cache->expiresAt($cacheExpiresAt);
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