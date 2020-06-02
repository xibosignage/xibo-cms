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
 *
 * 
 * Template strings to be translated, that will be used to replace tags in the ||tag|| format
 * __('Wind')
 * __('Humidity')
 * __('Precipitation')
 * __('Feels Like')
 * __('Right now')
 * __('Minimum temperature')
 * __('Maximum temperature')
 * __('Pressure')
 * __('Visibility')
 * __('TODAY')
 * __('RIGHT NOW')
 */

namespace Xibo\Widget;

use Carbon\Carbon;
use Carbon\Factory;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Respect\Validation\Validator as v;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\Media;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Translate;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Weather\DarkSkyProvider;
use Xibo\Weather\OpenWeatherMapProvider;

/**
 * Class Weather
 * @package Xibo\Widget
 */
class Weather extends ModuleWidget
{
    const WEATHER_BACKGROUNDS = array(
        "cloudy-image",
        "day-cloudy-image",
        "day-sunny-image",
        "fog-image",
        "hail-image",
        "night-clear-image",
        "night-partly-cloudy-image",
        "rain-image",
        "snow-image",
        "windy-image"
    );

    const WEATHER_SNIPPETS_CURRENT = array(
        'time',
        'sunSet',
        'sunRise',
        'summary',
        'icon',
        'wicon',
        'temperature',
        'temperatureRound',
        'temperatureNight',
        'temperatureNightRound',
        'temperatureMorning',
        'temperatureMorningRound',
        'temperatureEvening',
        'temperatureEveningRound',
        'temperatureHigh',
        'temperatureMaxRound',
        'temperatureLow',
        'temperatureMinRound',
        'temperatureMean',
        'temperatureMeanRound',
        'apparentTemperature',
        'apparentTemperatureRound',
        'dewPoint',
        'humidity',
        'humidityPercent',
        'pressure',
        'windSpeed',
        'windBearing',
        'windDirection',
        'cloudCover',
        'uvIndex',
        'visibility',
        'ozone',
        'temperatureUnit',
        'windSpeedUnit',
        'visibilityDistanceUnit'
    );
    
    const WEATHER_SNIPPETS_FORECAST = array(
        'time',
        'sunSet',
        'sunRise',
        'summary',
        'icon',
        'wicon',
        'temperature',
        'temperatureRound',
        'temperatureNight',
        'temperatureNightRound',
        'temperatureMorning',
        'temperatureMorningRound',
        'temperatureEvening',
        'temperatureEveningRound',
        'temperatureHigh',
        'temperatureMaxRound',
        'temperatureLow',
        'temperatureMinRound',
        'temperatureMean',
        'temperatureMeanRound',
        'apparentTemperature',
        'apparentTemperatureRound',
        'dewPoint',
        'humidity',
        'humidityPercent',
        'pressure',
        'windSpeed',
        'windBearing',
        'windDirection',
        'cloudCover',
        'uvIndex',
        'visibility',
        'ozone',
        'temperatureUnit',
        'windSpeedUnit',
        'visibilityDistanceUnit'
    );

    private $resourceFolder;
    protected $codeSchemaVersion = 1;

    /** @inheritDoc */
    public function init()
    {
        $this->resourceFolder = PROJECT_ROOT . '/modules/weather/player';

        // Initialise extra validation rules
        v::with('Xibo\\Validation\\Rules\\');
    }

    /** @inheritDoc */
    public function layoutDesignerJavaScript()
    {
        return 'weather-designer-javascript';
    }

    /** @inheritDoc */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Weather Tiles';
            $module->type = 'weather';
            $module->class = 'Xibo\Widget\Weather';
            $module->description = 'Weather Tiles';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->settings = [];
            $module->defaultDuration = 60;
            $module->installName = 'weather';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /** @inheritDoc */
    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-image-render.js')->save();

        foreach ($this->mediaFactory->createModuleFileFromFolder($this->resourceFolder) as $media) {
            /* @var Media $media */
            $media->save();
        }
    }

    /** @inheritDoc */
    public function settingsForm()
    {
        return 'weather-form-settings';
    }

    /** @inheritDoc */
    public function settings(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Process any module settings you asked for.
        $apiKey = $sanitizedParams->getString('apiKey');
        $owmApiKey = $sanitizedParams->getString('owmApiKey');
        $owmIsPaidPlan = $sanitizedParams->getCheckbox('owmIsPaidPlan');
        $cachePeriod = $sanitizedParams->getInt('cachePeriod', ['default' => 300]);

        if ($this->module->enabled != 0) {
            if ($apiKey == '' && $owmApiKey == '')
                throw new InvalidArgumentException(__('Missing API Key'), 'apiKey');

            if ($cachePeriod <= 0)
                throw new InvalidArgumentException(__('Cache period must be a positive number'), 'cachePeriod');
        }

        $this->module->settings['apiKey'] = $apiKey;
        $this->module->settings['owmApiKey'] = $owmApiKey;
        $this->module->settings['owmIsPaidPlan'] = $owmIsPaidPlan;
        $this->module->settings['cachePeriod'] = $cachePeriod;

        return $response;
    }

    /**
     * Edit Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?weather",
     *  operationId="WidgetWeatherEdit",
     *  tags={"widget"},
     *  summary="Edit Weather Widget",
     *  description="Edit Weather Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The WidgetId to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="name",
     *      in="formData",
     *      description="Optional Widget Name",
     *      type="string",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="Widget Duration",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="useDuration",
     *      in="formData",
     *      description="(0, 1) Select 1 only if you will provide duration parameter as well",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="enableStat",
     *      in="formData",
     *      description="The option (On, Off, Inherit) to enable the collection of Widget Proof of Play statistics",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="useDisplayLocation",
     *      in="formData",
     *      description="Flag (0, 1) Use the location configured on display",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="longitude",
     *      in="formData",
     *      description="The longitude for this weather widget, only pass if useDisplayLocation set to 0",
     *      type="number",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="latitude",
     *      in="formData",
     *      description="The latitude for this weather widget, only pass if useDisplayLocation set to 0",
     *      type="number",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="templateId",
     *      in="formData",
     *      description="Use pre-configured templates, available options: weather-module0-5day, weather-module0-singleday, weather-module0-singleday2, weather-module1l, weather-module1p, weather-module2l, weather-module2p, weather-module3l, weather-module3p, weather-module4l, weather-module4p, weather-module5l, weather-module6v, weather-module6h",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="units",
     *      in="formData",
     *      description="Units you would like to use, available options: auto, ca, si, uk2, us",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="updateInterval",
     *      in="formData",
     *      description="Update interval in minutes, should be kept as high as possible, if data change once per hour, this should be set to 60",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="lang",
     *      in="formData",
     *      description="Language you'd like to use, supported languages ar, az, be, bs, cs, de, en, el, es, fr, hr, hu, id, it, is, kw, nb, nl, pl, pt, ru, sk, sr, sv, tet, tr, uk, x-pig-latin, zh, zh-tw",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dayConditionsOnly",
     *      in="formData",
     *      description="Flag (0, 1) Would you like to only show the Daytime weather conditions",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="overrideTemplate",
     *      in="formData",
     *      description="flag (0, 1) set to 0 and use templateId or set to 1 and provide whole template in the next parameters",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="widgetOriginalWidth",
     *      in="formData",
     *      description="This is the intended Width of the template and is used to scale the Widget within it's region when the template is applied, Pass only with overrideTemplate set to 1",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="widgetOriginalHeight",
     *      in="formData",
     *      description="This is the intended Height of the template and is used to scale the Widget within it's region when the template is applied, Pass only with overrideTemplate set to 1",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="template",
     *      in="formData",
     *      description="Current template, Pass only with overrideTemplate set to 1 ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="styleSheet",
     *      in="formData",
     *      description="Optional StyleSheet, Pass only with overrideTemplate set to 1 ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="styleSheet",
     *      in="formData",
     *      description="Optional JavaScript, Pass only with overrideTemplate set to 1 ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Widget")
     *  )
     * )
     *
     * @inheritDoc
     */
    public function edit(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $this->setDuration($sanitizedParams->getInt('duration', ['default' => $this->getDuration()]));
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setOption('name', $sanitizedParams->getString('name'));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));
        $this->setOption('useDisplayLocation', $sanitizedParams->getCheckbox('useDisplayLocation'));
        $this->setOption('longitude', $sanitizedParams->getDouble('longitude'));
        $this->setOption('latitude', $sanitizedParams->getDouble('latitude'));
        $this->setOption('templateId', $sanitizedParams->getString('templateId'));
        $this->setOption('overrideTemplate', $sanitizedParams->getCheckbox('overrideTemplate'));
        $this->setOption('units', $sanitizedParams->getString('units'));
        $this->setOption('updateInterval', $sanitizedParams->getInt('updateInterval', ['default' => 60]));
        $this->setOption('lang', $sanitizedParams->getString('lang', ['default' => 'en']));
        $this->setOption('dayConditionsOnly', $sanitizedParams->getCheckbox('dayConditionsOnly'));
        $this->setOption('weatherType', $sanitizedParams->getString('weatherType'));
        $this->setOption('showMainBackground', $sanitizedParams->getCheckbox('showMainBackground'));

        // Background images
        foreach (self::WEATHER_BACKGROUNDS as $background) {
            $this->setOption($background, $sanitizedParams->getString($background));
            if($sanitizedParams->getString($background) != '') {
                $this->setOption($background, $sanitizedParams->getString($background));
            } else {
                $this->setOption($background, $background);
            }
        }

        // If weather type is forecast, get extra options
        if ($this->getOption('weatherType') == 'forecast') {
            $this->setOption('dayOffset', $sanitizedParams->getInt('dayOffset', ['default' => 0]));
            $this->setOption('numDays', $sanitizedParams->getInt('numDays', ['default' => 1]));
            $this->setOption('daysCols', $sanitizedParams->getInt('daysCols', ['default' => 1]));
            $this->setOption('daysRows', $sanitizedParams->getInt('daysRows', ['default' => 1]));
        }

        if ($this->getOption('overrideTemplate') == 1) {
            $this->setRawNode('styleSheet', $request->getParam('styleSheet', null));
            $this->setRawNode('template', $request->getParam('template', null));
            $this->setOption('widgetOriginalWidth', $sanitizedParams->getInt('widgetOriginalWidth'));
            $this->setOption('widgetOriginalHeight', $sanitizedParams->getInt('widgetOriginalHeight'));
        }

        $this->setRawNode('javaScript', $request->getParam('javaScript', ''));

        // Save the widget
        $this->isValid();
        $this->saveWidget();

        return $response;
    }

    /**
     * Units supported by Forecast.IO API
     * @return array The Units Available (temperature, wind speed and visible distance)
     */
    public function unitsAvailable()
    {
        return array(
            array('id' => 'auto', 'value' => 'Automatically select based on geographic location', 'tempUnit' => '', 'windUnit' => '', 'visibilityUnit' => ''),
            array('id' => 'ca', 'value' => 'Canada', 'tempUnit' => 'C', 'windUnit' => 'KPH', 'visibilityUnit' => 'km'),
            array('id' => 'si', 'value' => 'Standard International Units', 'tempUnit' => 'C', 'windUnit' => 'MPS', 'visibilityUnit' => 'km'),
            array('id' => 'uk2', 'value' => 'United Kingdom', 'tempUnit' => 'C', 'windUnit' => 'MPH', 'visibilityUnit' => 'mi'),
            array('id' => 'us', 'value' => 'United States', 'tempUnit' => 'F', 'windUnit' => 'MPH', 'visibilityUnit' => 'km'),
        );
    }

    /**
     * Languages supported by Forecast.IO API
     * @return array The Supported Language
     */
    public function supportedLanguages()
    {
        return array(
            array('id' => 'ar', 'value' => __('Arabic')),
            array('id' => 'az', 'value' => __('Azerbaijani')),
            array('id' => 'be', 'value' => __('Belarusian')),
            array('id' => 'bs', 'value' => __('Bosnian')),
            array('id' => 'bg', 'value' => __('Bulgarian')),
            array('id' => 'ca', 'value' => __('Catalan')),
            array('id' => 'kw', 'value' => __('Cornish')),
            array('id' => 'zh', 'value' => __('Simplified Chinese')),
            array('id' => 'zh-tw', 'value' => __('Traditional Chinese')),
            array('id' => 'hr', 'value' => __('Croatian')),
            array('id' => 'cs', 'value' => __('Czech')),
            array('id' => 'da', 'value' => __('Danish')),
            array('id' => 'nl', 'value' => __('Dutch')),
            array('id' => 'ka', 'value' => __('Georgian')),
            array('id' => 'de', 'value' => __('German')),
            array('id' => 'el', 'value' => __('Greek')),
            array('id' => 'en', 'value' => __('English')),
            array('id' => 'et', 'value' => __('Estonian')),
            array('id' => 'fi', 'value' => __('Finnish')),
            array('id' => 'fr', 'value' => __('French')),
            array('id' => 'hu', 'value' => __('Hungarian')),
            array('id' => 'is', 'value' => __('Icelandic')),
            array('id' => 'id', 'value' => __('Indonesian')),
            array('id' => 'it', 'value' => __('Italian')),
            array('id' => 'ja', 'value' => __('Japanese')),
            array('id' => 'nb', 'value' => __('Norwegian Bokmål')),
            array('id' => 'pl', 'value' => __('Polish')),
            array('id' => 'pt', 'value' => __('Portuguese')),
            array('id' => 'ru', 'value' => __('Russian')),
            array('id' => 'sr', 'value' => __('Serbian')),
            array('id' => 'sk', 'value' => __('Slovak')),
            array('id' => 'sl', 'value' => __('Slovenian')),
            array('id' => 'es', 'value' => __('Spanish')),
            array('id' => 'sv', 'value' => __('Swedish')),
            array('id' => 'tet', 'value' => __('Tetum')),
            array('id' => 'tr', 'value' => __('Turkish')),
            array('id' => 'uk', 'value' => __('Ukrainian')),
            array('id' => 'x-pig-latin', 'value' => __('lgpay Atinlay'))
        );
    }

    /**
     * @inheritDoc
     * @throws \Xibo\Support\Exception\GeneralException
     */
     public function getTab($tab)
     {
         if ($tab == 'forecast') {
             $data = $this->getForecastData(0);

             $rows = array();
             foreach ($data->getCurrentDay() as $key => $value) {
                 if (stripos($key, 'time')) {
                     $value = Carbon::createFromTimestamp($value)->format(DateFormatHelper::getSystemFormat());
                 }
                 $rows[] = array('forecast' => __('Current'), 'key' => $key, 'value' => $value);
             }
             return ['forecast' => $rows];
         } else if ($tab == 'exporttemplate') {
             return [
                 'template' => json_encode([
                     'id' => 'uniqueId',
                     'value' => 'title',
                     'designWidth' => $this->getOption('designWidth'),
                     'designHeight' => $this->getOption('designHeight'),
                     'main' => $this->getRawNode('template'),
                     'css' => $this->getRawNode('styleSheet'),                     
                     'widgetOriginalWidth' => intval($this->getOption('widgetOriginalWidth')),
                     'widgetOriginalHeight' => intval($this->getOption('widgetOriginalHeight')),
                     'image' => 'preview-image'
                 ], JSON_PRETTY_PRINT)
             ];
         } else {
             return [];
         }
     }

    /**
     * Get the forecast data for the provided display id
     * @param int $displayId
     * @return \Xibo\Weather\WeatherProvider
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function getForecastData($displayId)
    {
        // Get the Lat/Long
        $defaultLat = $this->getConfig()->getSetting('DEFAULT_LAT');
        $defaultLong = $this->getConfig()->getSetting('DEFAULT_LONG');

        if ($this->getOption('useDisplayLocation') == 1) {
            // Use the display ID or the default.
            if ($displayId != 0) {

                $display = $this->displayFactory->getById($displayId);

                if ($display->latitude != '' && $display->longitude != ''
                    && v::latitude()->validate($display->latitude)
                    && v::longitude()->validate($display->longitude)
                ) {
                    $defaultLat = $display->latitude;
                    $defaultLong = $display->longitude;
                } else {
                    $this->getLog()->info('Warning, display ' .  $display->display . ' does not have a lat/long or they are invalid, and yet a forecast widget is set to use display location.');
                }
            }
        } else {
            $defaultLat = $this->getOption('latitude', $defaultLat);
            $defaultLong = $this->getOption('longitude', $defaultLong);
        }

        if (!v::longitude()->validate($defaultLong) || !v::latitude()->validate($defaultLat)) {
            $this->getLog()->error('Weather widget configured with incorrect lat/long. WidgetId is ' . $this->getWidgetId()
                . ', Lat is ' . $defaultLat . ', Lng is ' . $defaultLong);
            throw new InvalidArgumentException(__('Lat/Long invalid'), 'geolocation');
        }

        // Create a provider
        return $this->getProvider()
            ->setHttpClient(new Client($this->getConfig()->getGuzzleProxy(['connect_timeout' => 20])))
            //->enableLogging($this->getLog())
            ->setCachePeriod($this->getSetting('cachePeriod', 14400))
            ->setLocation($defaultLat, $defaultLong)
            ->setUnits($this->getOption('units', 'auto'))
            ->setLang($this->getOption('lang', 'en'));
    }

    /**
     * @return \Xibo\Weather\WeatherProvider
     * @throws \Xibo\Support\Exception\ConfigurationException
     */
    private function getProvider()
    {
        // Don't do anything if we don't have an API Key
        $apiKey = $this->getSetting('apiKey');
        $owmApiKey = $this->getSetting('owmApiKey');
        if ($apiKey == '' && $owmApiKey == '') {
            throw new ConfigurationException('Incorrectly configured module');
        }

        // We need to pick the provider based on whether we have a DarkSky or OpenWeatherMap API key.
        return (empty($owmApiKey))
            ? (new DarkSkyProvider($this->getPool()))->setKey($apiKey)
            : (new OpenWeatherMapProvider($this->getPool()))->setKey($owmApiKey);
    }

    /**
     * @param $data
     * @param $source
     * @param null $timezone
     * @param string $language
     * @return string
     */
    private function makeSubstitutions($data, $source, $timezone = NULL, $language = 'en')
    {
        $carbonFactory = new Factory(['locale' => $language], Carbon::class);

        // Convert to an array if necessary
        if (is_object($data)) {
            $data = (array)$data;
        }

        // Replace all matches.
        $matches = '';
        preg_match_all('/\[.*?\]/', $source, $matches);

        // Substitute
        foreach ($matches[0] as $sub) {
            $replace = str_replace('[', '', str_replace(']', '', $sub));

            // Handling for date/time
            if (stripos($replace, 'time|') > -1) {
                $timeSplit = explode('|', $replace);

                $this->getLog()->debug('Time Substitution for source time ' . $data['time'] . ' and timezone ' . $timezone . ', format ' . $timeSplit[1]);

                $time = $carbonFactory->parse($data['time'], $timezone)->translatedFormat($timeSplit[1]);

                $this->getLog()->debug('Time Substitution: ' . (string)($time));

                // Pull time out of the array
                $source = str_replace($sub, $time, $source);
            } else {
                // Match that in the array
                if (isset($data[$replace]))
                    $source = str_replace($sub, $data[$replace], $source);
            }
        }

        return $source;
    }

    /**
     * @inheritDoc
     */
    public function getResource($displayId = 0)
    {
        $body = null;
        $styleSheet = null;
        $widgetOriginalWidth = null;
        $widgetOriginalHeight = null;

        // Behave exactly like the client.
        try {
            $foreCast = $this->getForecastData($displayId);

            // Both current and forecast templates are required by this module.
            $currently = $foreCast->getCurrentDay();
            $daily = $foreCast->getForecast();

        } catch (GeneralException $exception) {
            // The player should keep its cache
            return '';
        }

        // Are we set to only show daytime weather conditions?
        if ($this->getOption('dayConditionsOnly') == 1) {
            // Swap the night icons for their day equivalents
            $currently->icon = str_replace('-night', '', $currently->icon);
            $currently->wicon = str_replace('-night', '', $currently->wicon);
        }

        // Do we need to override the language?
        $lang = $this->getOption('lang', 'en');

        $data = [];

        // Replace the View Port Width?
        $data['viewPortWidth'] = $this->isPreview() ? $this->region->width : '[[ViewPortWidth]]';

        // Common styles
        $styleSheet .= "
            #content {
                height: 100%;
            }

            .bg-div {
                background-repeat: no-repeat;
                background-size: cover;
                background-position: left;
                width: 100%;
                height: 100%;
            }

            .footer-powered-by {
                color: #333;
                font-size: 12px;
                background-color: #f9f9f9;
                position: absolute;
                bottom: 0;
                left: 0;
                width: 100%;
                text-align: center;
                opacity: 0.85;
            }

            .footer-powered-by a {
                text-decoration: none;
                color: inherit;
            }
        ";

        // Custom backgrounds
        $styleSheet .= "
            .bg-cloudy {
                background-image: url('[" . $this->getOption('cloudy-image') . "]');
            }

            .bg-partly-cloudy-day {
                background-image: url('[" . $this->getOption('day-cloudy-image') . "]');
            }

            .bg-clear-day {
                background-image: url('[" . $this->getOption('day-sunny-image') . "]');
            }

            .bg-fog {
                background-image: url('[" . $this->getOption('fog-image') . "]');
            }

            .bg-sleet {
                background-image: url('[" . $this->getOption('hail-image') . "]');
            }

            .bg-clear-night {
                background-image: url('[" . $this->getOption('night-clear-image') . "]');
            }

            .bg-partly-cloudy-night {
                background-image: url('[" . $this->getOption('night-partly-cloudy-image') . "]');
            }

            .bg-rain {
                background-image: url('[" . $this->getOption('rain-image') . "]');
            }

            .bg-snow {
                background-image: url('[" . $this->getOption('snow-image') . "]');
            }

            .bg-wind {
                background-image: url('[" . $this->getOption('windy-image') . "]');
            }
        ";

        if($this->getOption('showMainBackground') != 1) {
            $styleSheet .= "
                .bg-div-main {
                    background-image: none;
                }
            ";
        }
        
        if($this->getOption('overrideTemplate') == 0 ) {
            // Get CSS and HTML from the default templates
            $template = $this->getTemplateById($this->getOption('templateId'));
            
            if (isset($template)) {
                $body = $template['main'];
                $styleSheet .= $template['css'];
                $widgetOriginalWidth = $template['widgetOriginalWidth'];
                $widgetOriginalHeight = $template['widgetOriginalHeight'];
            }
            
        } else {
            // Get CSS and HTML from the override input fields
            
            $body = $this->parseLibraryReferences($this->isPreview(), $this->getRawNode('template', ''));
            $styleSheet .= $this->getRawNode('styleSheet', '');
            $widgetOriginalWidth = intval($this->getOption('widgetOriginalWidth'));
            $widgetOriginalHeight = intval($this->getOption('widgetOriginalHeight'));
        }
        
        // Parse library references
        $body = $this->parseLibraryReferences($this->isPreview(), $body);

        // Parse translations
        $body = $this->parseTranslations($body);
        
        // Provide the background images to the templates styleSheet
        $styleSheet = $this->makeSubstitutions([
            'cloudy-image' => $this->getResourceUrl('weather/wi-cloudy.jpg'),
            'day-cloudy-image' => $this->getResourceUrl('weather/wi-day-cloudy.jpg'),
            'day-sunny-image' => $this->getResourceUrl('weather/wi-day-sunny.jpg'),
            'fog-image' => $this->getResourceUrl('weather/wi-fog.jpg'),
            'hail-image' => $this->getResourceUrl('weather/wi-hail.jpg'),
            'night-clear-image' => $this->getResourceUrl('weather/wi-night-clear.jpg'),
            'night-partly-cloudy-image' => $this->getResourceUrl('weather/wi-night-partly-cloudy.jpg'),
            'rain-image' => $this->getResourceUrl('weather/wi-rain.jpg'),
            'snow-image' => $this->getResourceUrl('weather/wi-snow.jpg'),
            'windy-image' => $this->getResourceUrl('weather/wi-windy.jpg'),
          ], $styleSheet, null, $lang
        );

        $headContent = '
            <link href="' . $this->getResourceUrl('weather/weather-icons.min.css') . '" rel="stylesheet" media="screen">
            <link href="' . $this->getResourceUrl('weather/font-awesome.min.css')  . '" rel="stylesheet" media="screen">
            <link href="' . $this->getResourceUrl('weather/animate.css')  . '" rel="stylesheet" media="screen">
            <style type="text/css"> body { background-color: transparent }</style>
            <style type="text/css">
                ' . $this->parseLibraryReferences($this->isPreview(), $styleSheet) . '
            </style>
        ';

        // Add our fonts.css file
        $headContent .= '<link href="' . (($this->isPreview()) ? $this->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

        // Replace any icon sets
        $data['head'] = str_replace('[[ICONS]]', $this->getResourceUrl('weather/' . $this->getOption('icons')), $headContent);

        // Get the JavaScript node
        $javaScript = $this->parseLibraryReferences($this->isPreview(), $this->getRawNode('javaScript', ''));

        // Build template
        $template = '<div class="weather-content bg-div bg-div-main bg-[icon]">';

         // Process days
        if ($this->getOption('weatherType') == 'forecast') {
            $numDays = $this->getOption('numDays');
            $daysOffset = $this->getOption('dayOffset');
            $daysCols = intval($this->getOption('daysCols'));
            $daysRows = intval($this->getOption('daysRows'));

            $stopPosition = (($numDays+$daysOffset) > 7) ? 7 : $numDays+$daysOffset;
            for ($i=$daysOffset; $i < $stopPosition; $i++) {
                $this->getLog()->debug('Substitution for Daily, day ' . $i);
                $template .= '<div class="multi-element">';
                $template .= $this->makeSubstitutions($daily[$i], $body, $foreCast->getTimezone(), $lang);
                $template .= '</div>';
            }
        } else {
            $template .= $this->makeSubstitutions($currently, $body, $foreCast->getTimezone(), $lang);
        }

        // Close main div in template and add the footer
        $template .= '
            <div class="footer-powered-by">
                <a href="#" target="_blank">' . $foreCast->getAttribution() . '</a>
            </div>
        </div>';

        // Run replace over the main template ( if type is forecast, it will replace at least the background image)
        $data['body'] = $this->makeSubstitutions($currently, $template, $foreCast->getTimezone(), $lang);

        // JavaScript to control the size (override the original width and height so that the widget gets blown up )
        $options = array(
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'widgetDesignWidth' => $widgetOriginalWidth,
            'widgetDesignHeight'=> $widgetOriginalHeight
        );

        if($this->getOption('weatherType') == 'forecast') {
            $options['numCols'] = $daysCols;
            $options['numRows'] = $daysRows;
        }

        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-image-render.js') . '"></script>';
        $javaScriptContent .= '<script>

            var options = ' . json_encode($options) . '

            $(document).ready(function() {
                $("body").xiboLayoutScaler(options);
                $("#content").find("img").xiboImageRender(options);
            });
        </script>';
        $javaScriptContent .= $javaScript;

        // Replace the After body Content
        $data['javaScript'] = $javaScriptContent;

        // Return that content.
        return $this->renderTemplate($data);
    }

    /** @inheritdoc */
    public function isValid()
    {
        if ($this->getOption('overrideTemplate') == 0 && ( $this->getOption('templateId') == '' || $this->getOption('templateId') == null))
            throw new InvalidArgumentException(__('Please choose a template'), 'templateId');

        if ($this->getOption('weatherType') == 'forecast') {
            if ($this->getOption('numDays') == '' || $this->getOption('numDays') <= 0) {
                throw new InvalidArgumentException(__('Please enter a positive number of days.'), 'numDays');
            }

            if ($this->getOption('daysRows') == '' || $this->getOption('daysRows') <= 0) {
                throw new InvalidArgumentException(__('Please enter a positive number of rows.'), 'daysRows');
            }

            if ($this->getOption('daysCols') == '' || $this->getOption('daysCols') <= 0) {
                throw new InvalidArgumentException(__('Please enter a positive number of columns.'), 'daysCols');
            }
        }

        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new InvalidArgumentException(__('Please enter a duration'), 'duration');

        if ($this->getOption('useDisplayLocation') == 0) {
            // Validate lat/long
            if (!v::latitude()->validate($this->getOption('latitude')))
                throw new InvalidArgumentException(__('The latitude entered is not valid.'), 'latitude');

            if (!v::longitude()->validate($this->getOption('longitude')))
                throw new InvalidArgumentException(__('The longitude entered is not valid.'), 'longitude');
        }

        return self::$STATUS_VALID;
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        $cachePeriod = $this->getSetting('cachePeriod', 3600);
        $updateInterval = $this->getOption('updateInterval', 60) * 60;

        return max($cachePeriod, $updateInterval);
    }

    /** @inheritdoc */
    public function getCacheKey($displayId)
    {
        return $this->getWidgetId() . (($displayId === 0 || $this->getOption('useDisplayLocation') == 1) ? '_' . $displayId : '');
    }

    /** @inheritdoc */
    public function isCacheDisplaySpecific()
    {
        return ($this->getOption('useDisplayLocation') == 1);
    }

    /**
     * @return false|string
     */
    public function getWeatherLanguage()
    {
        $supportedLanguages[] = $this->supportedLanguages();

        foreach ($supportedLanguages as $language) {
            foreach ($language as $lang) {
                if ($lang['id'] === strtolower(translate::getJsLocale())) {
                    return strtolower(translate::getJsLocale());
                }
                else {
                    continue;
                }
            }
        }
        if (strlen(translate::getJsLocale()) > 2 && Str::contains(translate::getJsLocale(), '-')) {
                return substr(translate::getJsLocale(), 0, 2);
        } else {
                return 'en';
        }
    }

    /**
     * @return array
     */
    public function getBackgroundList()
    {
        return self::WEATHER_BACKGROUNDS;
    }

    /**
     * @return \Xibo\Entity\Media[]
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getBackgroundOptions()
    {
        $initBackgrounds = [];

        foreach (self::WEATHER_BACKGROUNDS as $background) {
            if($this->getOption($background) != $background) {
                $initBackgrounds[] = $this->getOption($background);
            }
        }

        return $this->mediaFactory->query(null, array('disableUserCheck' => 1, 'id' => $initBackgrounds, 'allModules' => 1, 'type' => 'image'));
    }

    /**
     * @return mixed
     */
    public function getWeatherSnippets()
    {
        $snippets['current'] = self::WEATHER_SNIPPETS_CURRENT;
        $snippets['forecast'] = self::WEATHER_SNIPPETS_FORECAST;

        return $snippets;
    }

    /** @inheritDoc */
    public function hasTemplates()
    {
        return true;
    }
}
