<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
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
 */
namespace Xibo\Widget;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Str;
use Xibo\Exception\InvalidArgumentException;
use Respect\Validation\Validator as v;
use Xibo\Entity\Media;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\ModuleFactory;
use Xibo\Helper\Translate;

/**
 * Class ForecastIo
 * Weather module powered by the DarkSky API
 * @package Xibo\Widget
 */
class ForecastIo extends ModuleWidget
{
    const API_ENDPOINT = 'https://api.darksky.net/forecast/';

    private $resourceFolder;
    protected $codeSchemaVersion = 1;

    /**
     * ForecastIo constructor.
     */
    public function init()
    {
        $this->resourceFolder = PROJECT_ROOT . '/modules/forecastio/player';

        // Initialise extra validation rules
        v::with('Xibo\\Validation\\Rules\\');
    }

    /**
     * Install or Update this module
     * @param ModuleFactory $moduleFactory
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Weather';
            $module->type = 'forecastio';
            $module->class = 'Xibo\Widget\ForecastIo';
            $module->description = 'Weather Powered by DarkSky';
            $module->imageUri = 'forms/library.gif';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->settings = [];
            $module->defaultDuration = 60;

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-image-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/bootstrap.min.css')->save();

        foreach ($this->mediaFactory->createModuleFileFromFolder($this->resourceFolder) as $media) {
            /* @var Media $media */
            $media->save();
        }
    }

    /**
     * Form for updating the module settings
     */
    public function settingsForm()
    {
        return 'forecastio-form-settings';
    }

    /**
     * Process any module settings
     * @throws InvalidArgumentException
     */
    public function settings()
    {
        // Process any module settings you asked for.
        $apiKey = $this->getSanitizer()->getString('apiKey');
        $cachePeriod = $this->getSanitizer()->getInt('cachePeriod', 300);

        if ($this->module->enabled != 0) {
            if ($apiKey == '')
                throw new InvalidArgumentException(__('Missing API Key'), 'apiKey');

            if ($cachePeriod <= 0)
                throw new InvalidArgumentException(__('Cache period must be a positive number'), 'cachePeriod');
        }

        $this->module->settings['apiKey'] = $apiKey;
        $this->module->settings['cachePeriod'] = $cachePeriod;
    }

    public function validate()
    {
        
        if($this->getOption('overrideTemplate') == 0 && ( $this->getOption('templateId') == '' || $this->getOption('templateId') == null) )
            throw new \InvalidArgumentException(__('Please choose a template'));
            
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new \InvalidArgumentException(__('Please enter a duration'));

        if ($this->getOption('useDisplayLocation') == 0) {
            // Validate lat/long
            if (!v::latitude()->validate($this->getOption('latitude')))
                throw new \InvalidArgumentException(__('The latitude entered is not valid.'));

            if (!v::longitude()->validate($this->getOption('longitude')))
                throw new \InvalidArgumentException(__('The longitude entered is not valid.'));
        }
    }

    /**
     * Adds a Weather Widget
     * @SWG\Post(
     *  path="/playlist/widget/forecastIo/{playlistId}",
     *  operationId="WidgetWeatherAdd",
     *  tags={"widget"},
     *  summary="Add a Weather Widget",
     *  description="Add a new Weather Widget to the specified playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The playlist ID to add a Weather widget",
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
     *      name="currentTemplate",
     *      in="formData",
     *      description="Current template, Pass only with overrideTemplate set to 1 ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dailyTemplate",
     *      in="formData",
     *      description="Replaces [dailyForecast] in main template, Pass only with overrideTemplate set to 1 ",
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
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Widget"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new widget",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function add()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('useDisplayLocation', $this->getSanitizer()->getCheckbox('useDisplayLocation'));
        $this->setOption('longitude', $this->getSanitizer()->getDouble('longitude'));
        $this->setOption('latitude', $this->getSanitizer()->getDouble('latitude'));
        $this->setOption('templateId', $this->getSanitizer()->getString('templateId'));
        $this->setOption('overrideTemplate', $this->getSanitizer()->getCheckbox('overrideTemplate'));
        $this->setOption('units', $this->getSanitizer()->getString('units'));
        $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 60));
        $this->setOption('lang', $this->getSanitizer()->getString('lang'));
        $this->setOption('dayConditionsOnly', $this->getSanitizer()->getCheckbox('dayConditionsOnly'));
        
        if( $this->getOption('overrideTemplate') == 1 ){
            $this->setRawNode('styleSheet', $this->getSanitizer()->getParam('styleSheet', null));
            $this->setRawNode('currentTemplate', $this->getSanitizer()->getParam('currentTemplate', null));
            $this->setRawNode('dailyTemplate', $this->getSanitizer()->getParam('dailyTemplate', null));
            $this->setOption('widgetOriginalWidth', $this->getSanitizer()->getInt('widgetOriginalWidth'));
            $this->setOption('widgetOriginalHeight', $this->getSanitizer()->getInt('widgetOriginalHeight'));
        }
        
        $this->setRawNode('javaScript', $this->getSanitizer()->getParam('javaScript', ''));
        
        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Edit Media in the Database
     */
    public function edit()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('useDisplayLocation', $this->getSanitizer()->getCheckbox('useDisplayLocation'));
        $this->setOption('longitude', $this->getSanitizer()->getDouble('longitude'));
        $this->setOption('latitude', $this->getSanitizer()->getDouble('latitude'));
        $this->setOption('templateId', $this->getSanitizer()->getString('templateId'));
        $this->setOption('overrideTemplate', $this->getSanitizer()->getCheckbox('overrideTemplate'));
        $this->setOption('units', $this->getSanitizer()->getString('units'));
        $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 60));
        $this->setOption('lang', $this->getSanitizer()->getString('lang'));
        $this->setOption('dayConditionsOnly', $this->getSanitizer()->getCheckbox('dayConditionsOnly'));
        
        if( $this->getOption('overrideTemplate') == 1 ){
            $this->setRawNode('styleSheet', $this->getSanitizer()->getParam('styleSheet', null));
            $this->setRawNode('currentTemplate', $this->getSanitizer()->getParam('currentTemplate', null));
            $this->setRawNode('dailyTemplate', $this->getSanitizer()->getParam('dailyTemplate', null));
            $this->setOption('widgetOriginalWidth', $this->getSanitizer()->getInt('widgetOriginalWidth'));
            $this->setOption('widgetOriginalHeight', $this->getSanitizer()->getInt('widgetOriginalHeight'));
        }

        $this->setRawNode('javaScript', $this->getSanitizer()->getParam('javaScript', ''));

        // Save the widget
        $this->validate();
        $this->saveWidget();
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
     * Get Tab
     */
     public function getTab($tab)
     {
         if ($tab == 'forecast') {
             if (!$data = $this->getForecastData(0))
                 throw new NotFoundException(__('No data returned, please check error log.'));
             $rows = array();
             foreach ($data['currently'] as $key => $value) {
                 if (stripos($key, 'time')) {
                     $value = $this->getDate()->getLocalDate($value);
                 }
                 $rows[] = array('forecast' => __('Current'), 'key' => $key, 'value' => $value);
             }
             foreach ($data['daily']['data'][0] as $key => $value) {
                 if (stripos($key, 'time')) {
                     $value = $this->getDate()->getLocalDate($value);
                 }
                 $rows[] = array('forecast' => __('Daily'), 'key' => $key, 'value' => $value);
             }
             return ['forecast' => $rows];
         } else if ($tab == 'exporttemplate') {
             return [
                 'template' => json_encode([
                     'id' => 'uniqueId',
                     'value' => 'title',
                     'designWidth' => $this->getOption('designWidth'),
                     'designHeight' => $this->getOption('designHeight'),
                     'main' => $this->getRawNode('currentTemplate'),
                     'daily' => $this->getRawNode('dailyTemplate'),
                     'css' => $this->getRawNode('styleSheet'),                     
                     'widgetOriginalWidth' => intval($this->getOption('widgetOriginalWidth')),
                     'widgetOriginalHeight' => intval($this->getOption('widgetOriginalHeight')),
                     'image' => 'preview-image'
                 ])
             ];
         } else {
             return [];
         }
     }

    /**
     * Get the forecast data for the provided display id
     * @param int $displayId
     * @return array|boolean
     * @throws XiboException
     */
    private function getForecastData($displayId)
    {
        $defaultLat = $this->getConfig()->GetSetting('DEFAULT_LAT');
        $defaultLong = $this->getConfig()->GetSetting('DEFAULT_LONG');

        if ($this->getOption('useDisplayLocation') == 1) {
            // Use the display ID or the default.
            if ($displayId != 0) {

                $display = $this->displayFactory->getById($displayId);

                if ($display->latitude != '' && $display->longitude != '' && v::latitude()->validate($display->latitude) && v::longitude()->validate($display->longitude)) {
                    $defaultLat = $display->latitude;
                    $defaultLong = $display->longitude;
                } else {
                    $this->getLog()->info('Warning, display %s does not have a lat/long or they are invalid, and yet a forecast widget is set to use display location.', $display->display);
                }
            }
        } else {
            $defaultLat = $this->getOption('latitude', $defaultLat);
            $defaultLong = $this->getOption('longitude', $defaultLong);
        }

        if (!v::longitude()->validate($defaultLong) || !v::latitude()->validate($defaultLat)) {
            $this->getLog()->error('Weather widget configured with incorrect lat/long. WidgetId is ' . $this->getWidgetId() . ', Lat is ' . $defaultLat . ', Lng is ' . $defaultLong);
            return false;
        }

        $apiKey = $this->getSetting('apiKey');
        if ($apiKey == '')
            die(__('Incorrectly configured module'));

        // Query the API and Dump the Results.
        $apiOptions = array('units' => $this->getOption('units', 'auto'), 'lang' => $this->getOption('lang', 'en'), 'exclude' => 'minutely,hourly');

        $cache = $this->getPool()->getItem($this->makeCacheKey(md5($defaultLat . $defaultLong . implode('.', $apiOptions))));
        $data = $cache->get();

        if ($cache->isMiss()) {
            $cache->lock();

            $this->getLog()->notice('Getting Forecast from the API');
            if (!$data = $this->get($defaultLat, $defaultLong, null, $apiOptions)) {
                return false;
            }

            // Cache
            $cache->set($data);
            $cache->expiresAfter($this->getSetting('cachePeriod', 14400));
            $this->getPool()->saveDeferred($cache);
        } else {
            $this->getLog()->debug('Getting Forecast from cache');
        }

        // Icon Mappings
        $icons = array(
            'unmapped' => 'wi-alien',
            'clear-day' => 'wi-day-sunny',
            'clear-night' => 'wi-night-clear',
            'rain' => 'wi-rain',
            'snow' => 'wi-snow',
            'sleet' => 'wi-hail',
            'wind' => 'wi-windy',
            'fog' => 'wi-fog',
            'cloudy' => 'wi-cloudy',
            'partly-cloudy-day' => 'wi-day-cloudy',
            'partly-cloudy-night' => 'wi-night-partly-cloudy',
        );

        // Temperature and wind Speed Unit Mappings
        $temperatureUnit = '';
        $windSpeedUnit = '';
        $visibilityDistanceUnit = '';
        foreach ($this->unitsAvailable() as $unit) {
            if ($unit['id'] == $data->flags->units) {
                $temperatureUnit = $unit['tempUnit'];
                $windSpeedUnit = $unit['windUnit'];
                $visibilityDistanceUnit = $unit['visibilityUnit'];
                break;
            }
        }

        // Are we set to only show daytime weather conditions?
        if ($this->getOption('dayConditionsOnly') == 1) {
            if ($data->currently->icon == 'partly-cloudy-night')
                $data->currently->icon = 'clear-day';
        }
        
        // Wind Direction Mappings
        $cardinalDirections = array(
          'N' => array(337.5, 22.5),
          'NE' => array(22.5, 67.5),
          'E' => array(67.5, 112.5),
          'SE' => array(112.5, 157.5),
          'S' => array(157.5, 202.5),
          'SW' => array(202.5, 247.5),
          'W' => array(247.5, 292.5),
          'NW' => array(292.5, 337.5)
        );
        
        $windDirection = '';
        foreach ($cardinalDirections as $dir => $angles) {
          if ($data->currently->windBearing >= $angles[0] && $data->currently->windBearing < $angles[1]) {
            $windDirection = $dir;
            break;
          }
        }

        $data->currently->wicon = (isset($icons[$data->currently->icon]) ? $icons[$data->currently->icon] : $icons['unmapped']);
        $data->currently->temperatureFloor = (isset($data->currently->temperature) ? floor($data->currently->temperature) : '--');
        $data->currently->apparentTemperatureFloor = (isset($data->currently->apparentTemperature) ? floor($data->currently->apparentTemperature) : '--');
        $data->currently->temperatureRound = (isset($data->currently->temperature) ? round($data->currently->temperature, 0) : '--');
        $data->currently->apparentTemperatureRound = (isset($data->currently->apparentTemperature) ? round($data->currently->apparentTemperature, 0) : '--');
        $data->currently->summary = (isset($data->currently->summary) ? $data->currently->summary : '--');
        $data->currently->weekSummary = (isset($data->daily->summary) ? $data->daily->summary : '--');
        $data->currently->temperatureUnit = $temperatureUnit;
        $data->currently->windSpeedUnit = $windSpeedUnit;
        $data->currently->windDirection = $windDirection;
        $data->currently->visibilityDistanceUnit = $visibilityDistanceUnit;
        $data->currently->humidityPercent = (isset($data->currently->humidity)) ? ($data->currently->humidity * 100) : '--';

        // Convert a stdObject to an array
        $data = json_decode(json_encode($data), true);

        //Today Daily values
        $data['currently']['temperatureMaxFloor'] = (isset($data['daily']['data'][0]['temperatureMax'])) ? floor($data['daily']['data'][0]['temperatureMax']) : '--';
        $data['currently']['temperatureMinFloor'] = (isset($data['daily']['data'][0]['temperatureMin'])) ? floor($data['daily']['data'][0]['temperatureMin']) : '--';
        $data['currently']['temperatureMeanFloor'] = ($data['currently']['temperatureMaxFloor'] != '--' && $data['currently']['temperatureMinFloor'] != '--') ? floor((($data['currently']['temperatureMinFloor'] + $data['currently']['temperatureMaxFloor']) / 2)) : '--';
      
        $data['currently']['temperatureMaxRound'] = (isset($data['daily']['data'][0]['temperatureMax'])) ? round($data['daily']['data'][0]['temperatureMax'], 0) : '--';
        $data['currently']['temperatureMinRound'] = (isset($data['daily']['data'][0]['temperatureMin'])) ? round($data['daily']['data'][0]['temperatureMin'], 0) : '--';
        $data['currently']['temperatureMeanRound'] = ($data['currently']['temperatureMaxRound'] != '--' && $data['currently']['temperatureMinRound'] != '--') ? round((($data['currently']['temperatureMinRound'] + $data['currently']['temperatureMaxRound']) / 2), 0) : '--';

        // Process the icon for each day
        for ($i = 0; $i < 7; $i++) {
            // Are we set to only show daytime weather conditions?
            if ($this->getOption('dayConditionsOnly') == 1) {
                if ($data['daily']['data'][$i]['icon'] == 'partly-cloudy-night')
                    $data['daily']['data'][$i]['icon'] = 'clear-day';
            }
            
            // Wind Direction bearing to code
            $windDirectionDaily = '';
            foreach ($cardinalDirections as $dir => $angles) {
              if ($data['daily']['data'][$i]['windBearing'] >= $angles[0] && $data['daily']['data'][$i]['windBearing'] < $angles[1]) {
                $windDirectionDaily = $dir;
                break;
              }
            }

            $data['daily']['data'][$i]['wicon'] = (isset($icons[$data['daily']['data'][$i]['icon']]) ? $icons[$data['daily']['data'][$i]['icon']] : $icons['unmapped']);
            $data['daily']['data'][$i]['temperatureMaxFloor'] = (isset($data['daily']['data'][$i]['temperatureMax'])) ? floor($data['daily']['data'][$i]['temperatureMax']) : '--';
            $data['daily']['data'][$i]['temperatureMinFloor'] = (isset($data['daily']['data'][$i]['temperatureMin'])) ? floor($data['daily']['data'][$i]['temperatureMin']) : '--';
            $data['daily']['data'][$i]['temperatureFloor'] = ($data['daily']['data'][$i]['temperatureMinFloor'] != '--' && $data['daily']['data'][$i]['temperatureMaxFloor'] != '--') ? floor((($data['daily']['data'][$i]['temperatureMinFloor'] + $data['daily']['data'][$i]['temperatureMaxFloor']) / 2)) : '--';
            $data['daily']['data'][$i]['temperatureMaxRound'] = (isset($data['daily']['data'][$i]['temperatureMax'])) ? round($data['daily']['data'][$i]['temperatureMax'], 0) : '--';
            $data['daily']['data'][$i]['temperatureMinRound'] = (isset($data['daily']['data'][$i]['temperatureMin'])) ? round($data['daily']['data'][$i]['temperatureMin'], 0) : '--';
            $data['daily']['data'][$i]['temperatureRound'] = ($data['daily']['data'][$i]['temperatureMinRound'] != '--' && $data['daily']['data'][$i]['temperatureMaxRound'] != '--') ? round((($data['daily']['data'][$i]['temperatureMinRound'] + $data['daily']['data'][$i]['temperatureMaxRound']) / 2), 0) : '--';
            $data['daily']['data'][$i]['temperatureUnit'] = $temperatureUnit;
            $data['daily']['data'][$i]['windSpeedUnit'] = $windSpeedUnit;
            $data['daily']['data'][$i]['visibilityDistanceUnit'] = $visibilityDistanceUnit;
            $data['daily']['data'][$i]['humidityPercent'] = (isset($data['daily']['data'][$i]['humidity'])) ? ($data['daily']['data'][$i]['humidity'] * 100) : '--';
            $data['daily']['data'][$i]['windDirection'] = $windDirectionDaily;
        }

        return $data;
    }

    private function makeSubstitutions($data, $source, $timezone = NULL)
    {
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

                $time = $this->getDate()->getLocalDate($data['time'], $timeSplit[1], $timezone);

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
     * Get Resource
     * @param int $displayId
     * @return mixed
     * @throws XiboException
     */
    public function getResource($displayId = 0)
    {
        // Behave exactly like the client.
        if (!$foreCast = $this->getForecastData($displayId))
            return '';

        // Do we need to override the language?
        // TODO: I don't like this date fix, the library should really check the file exists?
        $lang = $this->getOption('lang', 'en');
        if ($lang != 'en' && file_exists(PROJECT_ROOT . '/vendor/jenssegers/date/src/Lang/' . $lang . '.php')) {
            mb_internal_encoding('UTF-8');
            $this->getLog()->debug('Setting language to: ' . $lang);
            $this->getDate()->setLocale($lang);
        }

        $data = [];
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';
        
        if( $this->getOption('overrideTemplate') == 0 ) {
            
            // Get CSS and HTML from the default templates

            $template = $this->getTemplateById($this->getOption('templateId'));
            
            if (isset($template)) {
                $body = $template['main'];
                $dailyTemplate = $template['daily'];
                $styleSheet = $template['css'];
                $widgetOriginalWidth = $template['widgetOriginalWidth'];
                $widgetOriginalHeight = $template['widgetOriginalHeight'];
            }
            
        } else {
            // Get CSS and HTML from the override input fields
            
            $body = $this->parseLibraryReferences($isPreview, $this->getRawNode('currentTemplate', ''));
            $dailyTemplate = $this->parseLibraryReferences($isPreview, $this->getRawNode('dailyTemplate', ''));
            $styleSheet = $this->getRawNode('styleSheet', '');
            $widgetOriginalWidth = $this->getSanitizer()->int($this->getOption('widgetOriginalWidth'));
            $widgetOriginalHeight = $this->getSanitizer()->int($this->getOption('widgetOriginalHeight'));
        }
        
        // Parse library references
        $body = $this->parseLibraryReferences($isPreview, $body);
        $dailyTemplate = $this->parseLibraryReferences($isPreview, $dailyTemplate);
        
        // Provide the background images to the templates styleSheet
        $styleSheet = $this->makeSubstitutions([
            'cloudy-image' => $this->getResourceUrl('forecastio/wi-cloudy.jpg'),
            'day-cloudy-image' => $this->getResourceUrl('forecastio/wi-day-cloudy.jpg'),
            'day-sunny-image' => $this->getResourceUrl('forecastio/wi-day-sunny.jpg'),
            'fog-image' => $this->getResourceUrl('forecastio/wi-fog.jpg'),
            'hail-image' => $this->getResourceUrl('forecastio/wi-hail.jpg'),
            'night-clear-image' => $this->getResourceUrl('forecastio/wi-night-clear.jpg'),
            'night-partly-cloudy-image' => $this->getResourceUrl('forecastio/wi-night-partly-cloudy.jpg'),            
            'rain-image' => $this->getResourceUrl('forecastio/wi-rain.jpg'),
            'snow-image' => $this->getResourceUrl('forecastio/wi-snow.jpg'),
            'windy-image' => $this->getResourceUrl('forecastio/wi-windy.jpg'),
          ], $styleSheet
        );

        $headContent = '
            <link href="' . $this->getResourceUrl('vendor/bootstrap.min.css')  . '" rel="stylesheet" media="screen">
            <link href="' . $this->getResourceUrl('forecastio/weather-icons.min.css') . '" rel="stylesheet" media="screen">
            <link href="' . $this->getResourceUrl('forecastio/font-awesome.min.css')  . '" rel="stylesheet" media="screen">
            <link href="' . $this->getResourceUrl('forecastio/animate.css')  . '" rel="stylesheet" media="screen">
            <style type="text/css"> body { background-color: transparent }</style>
            <style type="text/css">
                ' . $this->parseLibraryReferences($isPreview, $styleSheet) . '
            </style>
        ';

        // Add our fonts.css file
        $headContent .= '<link href="' . (($isPreview) ? $this->getApp()->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

        // Replace any icon sets
        $data['head'] = str_replace('[[ICONS]]', $this->getResourceUrl('forecastio/' . $this->getOption('icons')), $headContent);

        // Get the JavaScript node
        $javaScript = $this->parseLibraryReferences($isPreview, $this->getRawNode('javaScript', ''));

        // Handle the daily template (if its here)
        $dailySubs = '';
        $matches = '';
        preg_match_all('/\[dailyForecast.*?\]/', $body, $matches);

        // Substitute
        foreach ($matches[0] as $sub) {
            $replace = str_replace('[', '', str_replace(']', '', $sub));
            // Handling for date/time
            $itterations = 7;
            $stopPosition = $itterations;
            $offset = 0;
            if (stripos($replace, '|') > -1) {
                $quantity = explode('|', $replace);
                $itterations = $quantity[1];
                
                if (count($quantity) > 1)
                  $offset = $quantity[2];

                  $stopPosition = (($itterations+$offset) > 7) ? 7 : $itterations+$offset;
            
            }

            // Pull it out, and run substitute over it for each day
            // Substitute for every day (i.e. 7 times).
            for ($i = $offset; $i < $stopPosition; $i++) {
                $this->getLog()->debug('Substitiution for Daily, day ' . $i);
                $dailySubs .= $this->makeSubstitutions($foreCast['daily']['data'][$i], $dailyTemplate, $foreCast['timezone']);
            }
            // Substitute the completed template
            $body = str_replace($sub, $dailySubs, $body);
        }

        // Run replace over the main template
        $data['body'] = $this->makeSubstitutions($foreCast['currently'], $body, $foreCast['timezone']);

        // JavaScript to control the size (override the original width and height so that the widget gets blown up )
        $options = array(
            'previewWidth' => $this->getSanitizer()->getDouble('width', 0),
            'previewHeight' => $this->getSanitizer()->getDouble('height', 0),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0),
            'widgetDesignWidth' => $widgetOriginalWidth,
            'widgetDesignHeight'=> $widgetOriginalHeight
        );

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
        // Using the information you have in your module calculate whether it is valid or not.
        // 0 = Invalid
        // 1 = Valid
        // 2 = Unknown
        return 1;
    }

    public function get($latitude, $longitude, $time = null, $options = array())
    {
        $request_url = self::API_ENDPOINT
            . '[APIKEY]'
            . '/'
            . $latitude
            . ','
            . $longitude
            . ((is_null($time)) ? '' : ','. $time);

        if (!empty($options)) {
            $request_url .= '?'. http_build_query($options);
        }

        $this->getLog()->debug('Calling API with: ' . $request_url);

        $request_url = str_replace('[APIKEY]', $this->getSetting('apiKey'), $request_url);

        // Request
        $client = new Client();

        try {
            $response = $client->get($request_url, $this->getConfig()->getGuzzleProxy(['connect_timeout' => 20]));

            // Success?
            if ($response->getStatusCode() != 200) {
                $this->getLog()->error('ForecastIO API returned %d status. Unable to proceed. Headers = %s', $response->getStatusCode(), var_export($response->getHeaders(), true));

                // See if we can parse the error.
                $body = json_decode($response->getBody());

                $this->getLog()->error('ForecastIO Error: ' . ((isset($body->errors[0])) ? $body->errors[0]->message : 'Unknown Error'));

                return false;
            }

            // Parse out header and body
            $body = json_decode($response->getBody());

            return $body;
        }
        catch (RequestException $e) {
            $this->getLog()->error('Unable to reach Forecast API: %s', $e->getMessage());
            return false;
        }
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        $cachePeriod = $this->getSetting('cachePeriod', 60);
        $updateInterval = $this->getOption('updateInterval', 60);

        return max($cachePeriod, $updateInterval);
    }

    /** @inheritdoc */
    public function getCacheKey($displayId)
    {
        return $this->getWidgetId() . (($displayId === 0 || $this->getOption('useDisplayLocation') == 1) ? '_' . $displayId : '');
    }

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
}
