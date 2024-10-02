<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

namespace Xibo\Connector;

use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Str;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\ScheduleCriteriaRequestEvent;
use Xibo\Event\ScheduleCriteriaRequestInterface;
use Xibo\Event\WidgetDataRequestEvent;
use Xibo\Event\XmdsWeatherRequestEvent;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Sanitizer\SanitizerInterface;
use Xibo\Widget\DataType\Forecast;
use Xibo\Widget\Provider\DataProviderInterface;

/**
 * A connector to get data from the Open Weather Map API for use by the Weather Widget
 */
class OpenWeatherMapConnector implements ConnectorInterface
{
    use ConnectorTrait;

    private $apiUrl = 'https://api.openweathermap.org/data/';
    private $forecastCurrent = '2.5/weather';
    private $forecast3Hourly = '2.5/forecast';
    private $forecastDaily = '2.5/forecast/daily';
    private $forecastCombinedV3 = '3.0/onecall';

    /** @var string */
    protected $timezone;

    /** @var \Xibo\Widget\DataType\Forecast */
    protected $currentDay;

    public function registerWithDispatcher(EventDispatcherInterface $dispatcher): ConnectorInterface
    {
        $dispatcher->addListener(WidgetDataRequestEvent::$NAME, [$this, 'onDataRequest']);
        $dispatcher->addListener(ScheduleCriteriaRequestEvent::$NAME, [$this, 'onScheduleCriteriaRequest']);
        $dispatcher->addListener(XmdsWeatherRequestEvent::$NAME, [$this, 'onXmdsWeatherRequest']);
        return $this;
    }

    public function getSourceName(): string
    {
        return 'openweathermap';
    }

    public function getTitle(): string
    {
        return 'Open Weather Map';
    }

    public function getDescription(): string
    {
        return 'Get Weather data from Open Weather Map API';
    }

    public function getThumbnail(): string
    {
        return 'theme/default/img/connectors/owm.png';
    }

    public function getSettingsFormTwig(): string
    {
        return 'openweathermap-form-settings';
    }

    public function processSettingsForm(SanitizerInterface $params, array $settings): array
    {
        if (!$this->isProviderSetting('owmApiKey')) {
            $settings['owmApiKey'] = $params->getString('owmApiKey');
            $settings['owmIsPaidPlan'] = $params->getCheckbox('owmIsPaidPlan');
            $settings['cachePeriod'] = $params->getInt('cachePeriod');
            $settings['xmdsCachePeriod'] = $params->getInt('xmdsCachePeriod');
        }
        return $settings;
    }

    /**
     * If the requested dataSource is forecastio, get the data, process it and add to dataProvider
     *
     * @param WidgetDataRequestEvent $event
     * @return void
     */
    public function onDataRequest(WidgetDataRequestEvent $event)
    {
        if ($event->getDataProvider()->getDataSource() === 'forecastio') {
            if (empty($this->getSetting('owmApiKey'))) {
                $this->getLogger()->debug('onDataRequest: Open Weather Map not configured.');
                return;
            }

            $event->stopPropagation();

            if ($this->isProviderSetting('apiUrl')) {
                $this->apiUrl = $this->getSetting('apiUrl');
            }

            try {
                $this->getWeatherData($event->getDataProvider());

                // If we've got data, then set our cache period.
                $event->getDataProvider()->setCacheTtl($this->getSetting('cachePeriod', 3600));
                $event->getDataProvider()->setIsHandled();
            } catch (\Exception $exception) {
                $this->getLogger()->error('onDataRequest: Failed to get results. e = ' . $exception->getMessage());
                $event->getDataProvider()->addError(__('Unable to get weather results.'));
            }
        }
    }

    /**
     * Get a combined forecast
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function getWeatherData(DataProviderInterface $dataProvider)
    {
        // Convert units to an acceptable format
        $units = in_array($dataProvider->getProperty('units', 'auto'), ['auto', 'us', 'uk2']) ? 'imperial' : 'metric';

        // Temperature and Wind Speed Unit Mappings
        $unit = $this->getUnit($dataProvider->getProperty('units'));

        if ($dataProvider->getProperty('useDisplayLocation') == 0) {
            $providedLat = $dataProvider->getProperty('latitude', $dataProvider->getDisplayLatitude());
            $providedLon = $dataProvider->getProperty('longitude', $dataProvider->getDisplayLongitude());
        } else {
            $providedLat = $dataProvider->getDisplayLatitude();
            $providedLon = $dataProvider->getDisplayLongitude();
        }

        // Build the URL
        $url = '?lat=' . $providedLat
            . '&lon=' . $providedLon
            . '&units=' . $units
            . '&lang=' . $dataProvider->getProperty('lang', 'en')
            . '&appid=[API_KEY]';

        // Cache expiry date
        $cacheExpire = Carbon::now()->addSeconds($this->getSetting('cachePeriod'));

        if ($this->getSetting('owmIsPaidPlan') ?? 0 == 1) {
            // We build our data from multiple API calls
            // Current data first.
            $data = $this->queryApi($this->apiUrl . $this->forecastCurrent . $url, $cacheExpire);
            $data['current'] = $this->parseCurrentIntoFormat($data);

            // initialize timezone
            $timezoneOffset = (int)$data['timezone'];

            // Calculate the number of whole hours in the offset
            $offsetHours = floor($timezoneOffset / 3600);

            // Calculate the remaining minutes after extracting the whole hours
            $offsetMinutes = ($timezoneOffset % 3600) / 60;

            // Determine the sign of the offset (positive or negative)
            $sign = $offsetHours < 0 ? '-' : '+';

            // Ensure the format is as follows: +/-hh:mm
            $formattedOffset = sprintf("%s%02d:%02d", $sign, abs($offsetHours), abs($offsetMinutes));

            // Get the timezone name
            $this->timezone = (new \DateTimeZone($formattedOffset))->getName();

            // Pick out the country
            $country = $data['sys']['country'] ?? null;

            $this->getLogger()->debug('Trying to determine units for Country: ' . $country);

            // If we don't have a unit, then can we base it on the timezone we got back?
            if ($dataProvider->getProperty('units', 'auto') === 'auto' && $country !== null) {
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
            $data['daily'] = $this->queryApi(
                $this->apiUrl . $this->forecastDaily . $url,
                $cacheExpire->copy()->addDay()->startOfDay()
            )['list'];
        } else {
            // We use one call API 3.0
            $data = $this->queryApi($this->apiUrl . $this->forecastCombinedV3 . $url, $cacheExpire);

            $this->timezone = $data['timezone'];

            // Country based on timezone (this is harder than using the real country)
            if ($dataProvider->getProperty('units', 'auto') === 'auto') {
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
        $this->getLogger()->debug('Using units: ' . json_encode($unit));

        $forecasts = [];

        // Parse into our forecast.
        // Load this data into our objects
        $this->currentDay = new Forecast();
        $this->currentDay->temperatureUnit = $unit['tempUnit'] ?: 'C';
        $this->currentDay->windSpeedUnit = $unit['windUnit'] ?: 'KPH';
        $this->currentDay->visibilityDistanceUnit = $unit['visibilityUnit'] ?: 'km';
        $this->currentDay->location = $data['name'] ?? '';
        $this->processItemIntoDay($this->currentDay, $data['current'], $units, true);

        $countForecast = 0;
        // Process each day into a forecast
        foreach ($data['daily'] as $dayItem) {
            // Skip first item as this is the currentDay
            if ($countForecast++ === 0) {
                continue;
            }

            $day = new Forecast();
            $day->temperatureUnit = $this->currentDay->temperatureUnit;
            $day->windSpeedUnit = $this->currentDay->windSpeedUnit;
            $day->visibilityDistanceUnit = $this->currentDay->visibilityDistanceUnit;
            $day->location = $this->currentDay->location;
            $this->processItemIntoDay($day, $dayItem, $units);

            $forecasts[] = $day;
        }

        // Enhance the currently with the high/low from the first daily forecast
        $this->currentDay->temperatureHigh = $forecasts[0]->temperatureHigh;
        $this->currentDay->temperatureMaxRound = $forecasts[0]->temperatureMaxRound;
        $this->currentDay->temperatureLow = $forecasts[0]->temperatureLow;
        $this->currentDay->temperatureMinRound = $forecasts[0]->temperatureMinRound;
        $this->currentDay->temperatureMorning = $forecasts[0]->temperatureMorning;
        $this->currentDay->temperatureMorningRound = $forecasts[0]->temperatureMorningRound;
        $this->currentDay->temperatureNight = $forecasts[0]->temperatureNight;
        $this->currentDay->temperatureNightRound = $forecasts[0]->temperatureNightRound;
        $this->currentDay->temperatureEvening = $forecasts[0]->temperatureEvening;
        $this->currentDay->temperatureEveningRound = $forecasts[0]->temperatureEveningRound;
        $this->currentDay->temperatureMean = $forecasts[0]->temperatureMean;
        $this->currentDay->temperatureMeanRound = $forecasts[0]->temperatureMeanRound;

        if ($dataProvider->getProperty('dayConditionsOnly', 0) == 1) {
            // Swap the night icons for their day equivalents
            $this->currentDay->icon = str_replace('-night', '', $this->currentDay->icon);
            $this->currentDay->wicon = str_replace('-night', '', $this->currentDay->wicon);
        }

        $dataProvider->addItem($this->currentDay);

        if (count($forecasts) > 0) {
            foreach ($forecasts as $forecast) {
                $dataProvider->addItem($forecast);
            }
        }

        $dataProvider->addOrUpdateMeta('Attribution', 'Powered by OpenWeather');
    }

    /**
     * @param string $url
     * @param Carbon $cacheExpiresAt
     * @return array
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function queryApi(string $url, Carbon $cacheExpiresAt): array
    {
        $cache = $this->pool->getItem('/weather/owm/' . md5($url));
        $data = $cache->get();

        if ($cache->isMiss()) {
            $cache->lock();
            $this->getLogger()->debug('Getting Forecast from API');

            $url = str_replace('[API_KEY]', $this->getSetting('owmApiKey'), $url);

            try {
                $response = $this->getClient()->get($url);

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
                $this->getLogger()->error('Unable to reach Open Weather Map API: '
                    . str_replace($this->getSetting('owmApiKey'), '[API_KEY]', $e->getMessage()));
                throw new GeneralException('API responded with an error.');
            }
        } else {
            $this->getLogger()->debug('Getting Forecast from cache');
        }

        return $data;
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
        $day->windDirection = '--';
        if ($day->windBearing !== null && $day->windBearing !== 0) {
            foreach (self::cardinalDirections() as $dir => $angles) {
                if ($day->windBearing >= $angles[0] && $day->windBearing < $angles[1]) {
                    $day->windDirection = $dir;
                    break;
                }
            }
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

    /**
     * @param ScheduleCriteriaRequestInterface $event
     * @return void
     * @throws ConfigurationException
     */
    public function onScheduleCriteriaRequest(ScheduleCriteriaRequestInterface $event): void
    {
        // Initialize Open Weather Schedule Criteria parameters
        $event->addType('weather', __('Weather'))
            ->addMetric('condition', __('Weather Condition'))
                ->addValues('dropdown', [
                    'thunderstorm' => __('Thunderstorm'),
                    'drizzle' => __('Drizzle'),
                    'rain' => __('Rain'),
                    'snow' => __('Snow'),
                    'clear' => __('Clear'),
                    'clouds' => __('Clouds')
                ])
            ->addMetric('temp_imperial', __('Temperature (Imperial)'))
                ->addValues('number', [])
            ->addMetric('temp_metric', __('Temperature (Metric)'))
                ->addValues('number', [])
            ->addMetric('feels_like_imperial', __('Apparent Temperature (Imperial)'))
                ->addValues('number', [])
            ->addMetric('feels_like_metric', __('Apparent Temperature (Metric)'))
                ->addValues('number', [])
            ->addMetric('wind_speed', __('Wind Speed'))
                ->addValues('number', [])
            ->addMetric('wind_direction', __('Wind Direction'))
                ->addValues('dropdown', [
                    'N' => __('North'),
                    'NE' => __('Northeast'),
                    'E' => __('East'),
                    'SE' => __('Southeast'),
                    'S' => __('South'),
                    'SW' => __('Southwest'),
                    'W' => __('West'),
                    'NW' => __('Northwest'),
                ])
            ->addMetric('wind_degrees', __('Wind Direction (degrees)'))
                ->addValues('number', [])
            ->addMetric('humidity', __('Humidity (Percent)'))
                ->addValues('number', [])
            ->addMetric('pressure', __('Pressure'))
                ->addValues('number', [])
            ->addMetric('visibility', __('Visibility (meters)'))
                ->addValues('number', []);
    }

    /**
     * @param $item
     * @param $unit
     * @param $requestUnit
     * @return array
     */
    private function processXmdsWeatherData($item, $unit, $requestUnit): array
    {
        $windSpeedUnit = $unit['windUnit'] ?? 'KPH';
        $visibilityDistanceUnit = $unit['visibilityUnit'] ?? 'km';

        // var to store output/response
        $data = array();

        // format the weather condition
        $data['weather_condition'] = str_replace(' ', '_', strtolower($item['weather'][0]['main']));

        // Temperature
        // imperial = F
        // metric = C
        $tempImperial = $item['temp'];
        $apparentTempImperial = $item['feels_like'];

        // Convert F to C
        $tempMetric = ($tempImperial - 32) * 5 / 9;
        $apparentTempMetric = ($apparentTempImperial - 32) * 5 / 9;

        // Round those temperature values
        $data['weather_temp_imperial'] = round($tempImperial, 0);
        $data['weather_feels_like_imperial'] = round($apparentTempImperial, 0);
        $data['weather_temp_metric'] = round($tempMetric, 0);
        $data['weather_feels_like_metric'] = round($apparentTempMetric, 0);

        // Humidity
        $data['weather_humidity'] = $item['humidity'];

        // Pressure
        // received in hPa, display in mB
        $data['weather_pressure'] = $item['pressure'] / 100;

        // Wind
        // metric = meters per second
        // imperial = miles per hour
        $data['weather_wind_speed'] = $item['wind_speed'] ?? $item['speed'] ?? null;
        $data['weather_wind_degrees'] = $item['wind_deg'] ?? $item['deg'] ?? null;

        if ($requestUnit === 'metric' && $windSpeedUnit !== 'MPS') {
            // We have MPS and need to go to something else
            if ($windSpeedUnit === 'MPH') {
                // Convert MPS to MPH
                $data['weather_wind_degrees'] = round($data['weather_wind_degrees'] * 2.237, 2);
            } else if ($windSpeedUnit === 'KPH') {
                // Convert MPS to KPH
                $data['weather_wind_degrees'] = round($data['weather_wind_degrees'] * 3.6, 2);
            }
        } else if ($requestUnit === 'imperial' && $windSpeedUnit !== 'MPH') {
            if ($windSpeedUnit === 'MPS') {
                // Convert MPH to MPS
                $data['weather_wind_degrees'] = round($data['weather_wind_degrees'] / 2.237, 2);
            } else if ($windSpeedUnit === 'KPH') {
                // Convert MPH to KPH
                $data['weather_wind_degrees'] = round($data['weather_wind_degrees'] * 1.609344, 2);
            }
        }

        // Wind direction
        $data['weather_wind_direction'] = '--';
        if ($data['weather_wind_degrees'] !== null && $data['weather_wind_degrees'] !== 0) {
            foreach (self::cardinalDirections() as $dir => $angles) {
                if ($data['weather_wind_degrees'] >= $angles[0] && $data['weather_wind_degrees'] < $angles[1]) {
                    $data['weather_wind_direction'] = $dir;
                    break;
                }
            }
        }

        // Visibility
        // metric = meters
        // imperial = meters?
        $data['weather_visibility'] = $item['visibility'] ?? '--';

        if ($data['weather_visibility'] !== '--') {
            // Always in meters
            if ($visibilityDistanceUnit === 'mi') {
                // Convert meters to miles
                $data['weather_visibility'] = $data['weather_visibility'] / 1609;
            } else {
                if ($visibilityDistanceUnit === 'km') {
                    // Convert meters to KM
                    $data['weather_visibility'] = $data['weather_visibility'] / 1000;
                }
            }
        }

        return $data;
    }

    /**
     * @param XmdsWeatherRequestEvent $event
     * @return void
     * @throws GeneralException|\SoapFault
     */
    public function onXmdsWeatherRequest(XmdsWeatherRequestEvent $event): void
    {
        // check for API Key
        if (empty($this->getSetting('owmApiKey'))) {
            $this->getLogger()->debug('onXmdsWeatherRequest: Open Weather Map not configured.');

            throw new \SoapFault(
                'Receiver',
                'Open Weather Map API key is not configured'
            );
        }

        $latitude = $event->getLatitude();
        $longitude = $event->getLongitude();

        // Cache expiry date
        $cacheExpire = Carbon::now()->addHours($this->getSetting('xmdsCachePeriod'));

        // use imperial as the default units, so we can get the right value when converting to metric
        $units = 'imperial';

        // Temperature and Wind Speed Unit Mappings
        $unit = $this->getUnit('auto');

        // Build the URL
        $url = '?lat=' . $latitude
            . '&lon=' . $longitude
            . '&units=' . $units
            . '&appid=[API_KEY]';

        // check API plan
        if ($this->getSetting('owmIsPaidPlan') ?? 0 == 1) {
            // use weather data endpoints for Paid Plan
            $data = $this->queryApi($this->apiUrl . $this->forecastCurrent . $url, $cacheExpire);
            $data['current'] = $this->parseCurrentIntoFormat($data);

            // Pick out the country
            $country = $data['sys']['country'] ?? null;

            // If we don't have a unit, then can we base it on the timezone we got back?
            if ($country !== null) {
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
        } else {
            // We use one call API 3.0 for Free Plan
            $data = $this->queryApi($this->apiUrl . $this->forecastCombinedV3 . $url, $cacheExpire);

            // Country based on timezone (this is harder than using the real country)
            if (Str::startsWith($data['timezone'], 'America')) {
                $unit = $this->getUnit('us');
            } else if ($data['timezone'] === 'Europe/London') {
                $unit = $this->getUnit('uk2');
            } else {
                $unit = $this->getUnit('si');
            }
        }

        // process weather data
        $weatherData = $this->processXmdsWeatherData($data['current'], $unit, 'imperial');

        // Set the processed weather data in the event as a JSON-encoded string
        $event->setWeatherData(json_encode($weatherData));
    }
}
