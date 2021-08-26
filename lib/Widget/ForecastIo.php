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
 * __('Feels Like')
 * __('Right now')
 * __('Pressure')
 * __('Visibility')
 * __('TODAY')
 * __('RIGHT NOW')
 */

namespace Xibo\Widget;

use Carbon\Carbon;
use Carbon\Factory;
use GuzzleHttp\Client;
use Respect\Validation\Validator as v;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\Media;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Weather\DarkSkyProvider;
use Xibo\Weather\OpenWeatherMapProvider;

/**
 * Class ForecastIo
 * @package Xibo\Widget
 */
class ForecastIo extends ModuleWidget
{
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
        return 'forecastio-designer-javascript';
    }

    /** @inheritDoc */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Weather';
            $module->type = 'forecastio';
            $module->class = 'Xibo\Widget\ForecastIo';
            $module->description = 'Weather module showing Current and Daily forecasts.';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->settings = [];
            $module->defaultDuration = 60;
            $module->installName = 'forecastio';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /** @inheritDoc */
    public function installFiles()
    {
        // Extends parent's method
        parent::installFiles();
        
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-image-render.js')->save();

        foreach ($this->mediaFactory->createModuleFileFromFolder($this->resourceFolder) as $media) {
            /* @var Media $media */
            $media->save();
        }
    }

    /** @inheritDoc */
    public function settingsForm()
    {
        return 'forecastio-form-settings';
    }

    /** @inheritDoc */
    public function settings(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        // Process any module settings you asked for.
        $apiKey = $sanitizedParams->getString('apiKey');
        $owmApiKey = $sanitizedParams->getString('owmApiKey');
        $owmIsPaidPlan = $sanitizedParams->getCheckbox('owmIsPaidPlan');
        $cachePeriod = $sanitizedParams->getInt('cachePeriod', ['default' => 1440]);

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
     *      name="currentTemplate",
     *      in="formData",
     *      description="Current template, Pass only with overrideTemplate set to 1 ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="currentTemplate_advanced",
     *      in="formData",
     *      description="A flag (0, 1), Should text area by presented as a visual editor?",
     *      type="integer",
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
     *      name="dailyTemplate_advanced",
     *      in="formData",
     *      description="A flag (0, 1), Should text area by presented as a visual editor?",
     *      type="integer",
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
        
        if ($this->getOption('overrideTemplate') == 1) {
            $this->setRawNode('styleSheet', $request->getParam('styleSheet', null));
            $this->setRawNode('currentTemplate', $request->getParam('currentTemplate', null));
            $this->setOption('currentTemplate_advanced', $sanitizedParams->getCheckbox('currentTemplate_advanced'));
            $this->setRawNode('dailyTemplate', $request->getParam('dailyTemplate', null));
            $this->setOption('dailyTemplate_advanced', $sanitizedParams->getCheckbox('dailyTemplate_advanced'));
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
     * @throws \Xibo\Support\Exception\ConfigurationException
     */
    public function unitsAvailable()
    {
        return $this->getProvider()::unitsAvailable();
    }

    /**
     * Languages supported by Forecast.IO API
     * @return array The Supported Language
     * @throws \Xibo\Support\Exception\ConfigurationException
     */
    public function supportedLanguages()
    {
        return $this->getProvider()::supportedLanguages();
    }

    /**
     * @inheritDoc
     * @throws \Xibo\Support\Exception\GeneralException
     */
     public function getTab($tab)
     {
         if ($tab == 'forecast') {
             // Return a current day weather forecast, for displayId 0 (meaning preview)
             $data = $this->getForecastData(0);

             $rows = [];
             foreach ((array)$data->getCurrentDay() as $key => $value) {
                 if (stripos($key, 'time')) {
                     $value = Carbon::createFromTimestamp($value)->format(DateFormatHelper::getSystemFormat());
                 }
                 $rows[] = array('forecast' => __('Forecast'), 'key' => $key, 'value' => $value);
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
            ->setLocation(round($defaultLat, 3), round($defaultLong, 3))
            ->setUnits($this->getOption('units', 'auto'))
            ->setLang($this->getOption('lang', 'en'));
    }

    /**
     * @return \Xibo\Weather\WeatherProvider
     * @throws \Xibo\Support\Exception\ConfigurationException
     */
    protected function getProvider()
    {
        // Don't do anything if we don't have an API Key
        $apiKey = $this->getSetting('apiKey');
        $owmApiKey = $this->getSetting('owmApiKey');
        if ($apiKey == '' && $owmApiKey == '') {
            throw new ConfigurationException('Incorrectly configured module');
        }

        // We need to pick the provider based on whether we have a DarkSky or OpenWeatherMap API key.
        return ((empty($owmApiKey))
            ? (new DarkSkyProvider($this->getPool()))->setKey($apiKey)
            : (new OpenWeatherMapProvider($this->getPool()))->setKey($owmApiKey))
            ->setCachePeriod($this->getSetting('cachePeriod', 1440))
            ->setOptions([
                'isPaidPlan' => $this->getSetting('owmIsPaidPlan', 0)
            ]);
    }

    /**
     * @param array|\Xibo\Weather\Forecast $data
     * @param $source
     * @param null $timezone
     * @return string|string[]
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
                if (isset($data[$replace])) {
                    $source = str_replace($sub, $data[$replace], $source);
                }
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
        $dailyTemplate = null;
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

        // Templates
        if ($this->getOption('overrideTemplate') == 0) {
            // Get CSS and HTML from the default templates
            $template = $this->getTemplateById($this->getOption('templateId'));
            
            if (isset($template)) {
                $body = $template['main'];
                $dailyTemplate = $template['daily'];
                $styleSheet = $template['css'];
                $widgetOriginalWidth = $template['widgetOriginalWidth'];
                $widgetOriginalHeight = $template['widgetOriginalHeight'];
            } else {
                throw new InvalidArgumentException(__('Template not found, please edit the Widget and select another.'), 'templateId');
            }
            
        } else {
            // Get CSS and HTML from the override input fields
            $body = $this->parseLibraryReferences($this->isPreview(), $this->getRawNode('currentTemplate', ''));
            $dailyTemplate = $this->parseLibraryReferences($this->isPreview(), $this->getRawNode('dailyTemplate', ''));
            $styleSheet = $this->getRawNode('styleSheet', '');
            $widgetOriginalWidth = intval($this->getOption('widgetOriginalWidth'));
            $widgetOriginalHeight = intval($this->getOption('widgetOriginalHeight'));
        }
        
        // Parse library references
        $body = $this->parseLibraryReferences($this->isPreview(), $body);
        $dailyTemplate = $this->parseLibraryReferences($this->isPreview(), $dailyTemplate);

        // Attribution
        $body = str_replace('[Attribution]', $foreCast->getAttribution(), $body);

        // Parse translations
        $body = $this->parseTranslations($body);
        $dailyTemplate = $this->parseTranslations($dailyTemplate);
        
        // Provide the background images to the templates styleSheet
        // TODO: the way this works is super odd
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
          ], $styleSheet, null, $lang
        );

        $headContent = '
            <link href="' . $this->getResourceUrl('vendor/bootstrap.min.css')  . '" rel="stylesheet" media="screen">
            <link href="' . $this->getResourceUrl('forecastio/weather-icons.min.css') . '" rel="stylesheet" media="screen">
            <link href="' . $this->getResourceUrl('forecastio/font-awesome.min.css')  . '" rel="stylesheet" media="screen">
            <link href="' . $this->getResourceUrl('forecastio/animate.css')  . '" rel="stylesheet" media="screen">
            <style type="text/css"> body { background-color: transparent }</style>
            <style type="text/css">
                ' . $this->parseLibraryReferences($this->isPreview(), $styleSheet) . '
            </style>
        ';

        // Add our fonts.css file
        $headContent .= '<link href="' . (($this->isPreview()) ? $this->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

        // Replace any icon sets
        // TODO: I don't think this functionality exists anymore.
        $data['head'] = str_replace('[[ICONS]]', $this->getResourceUrl('forecastio/' . $this->getOption('icons')), $headContent);

        // Get the JavaScript node
        $javaScript = $this->parseLibraryReferences($this->isPreview(), $this->getRawNode('javaScript', ''));

        // Handle the daily template (if its here)
        $dailySubs = '';
        $matches = '';
        preg_match_all('/\[dailyForecast.*?\]/', $body, $matches);

        // Substitute
        foreach ($matches[0] as $sub) {
            $replace = str_replace('[', '', str_replace(']', '', $sub));

            // Does the dailyForecast tag have a number of days parameter?
            $maxDays = count($daily);
            $offset = 0;
            if (stripos($replace, '|') > -1) {
                $quantity = explode('|', $replace);
                $iterations = $quantity[1];
                
                if (count($quantity) > 1) {
                    $offset = $quantity[2];
                }

                $stopPosition = (($iterations + $offset) > $maxDays) ? $maxDays : $iterations + $offset;
            } else {
                $stopPosition = $maxDays;
            }

            // Pull it out, and run substitute over it for each day
            // Substitute for every day (i.e. 7 times).
            for ($i = $offset; $i < $stopPosition; $i++) {
                $this->getLog()->debug('Substitiution for Daily, day ' . $i);
                $dailySubs .= $this->makeSubstitutions($daily[$i], $dailyTemplate, $foreCast->getTimezone(), $lang);
            }

            // Substitute the completed template
            $body = str_replace($sub, $dailySubs, $body);
        }

        // Run replace over the main template
        $data['body'] = $this->makeSubstitutions($currently, $body, $foreCast->getTimezone(), $lang);

        // JavaScript to control the size (override the original width and height so that the widget gets blown up )
        $options = array(
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'widgetDesignWidth' => $widgetOriginalWidth,
            'widgetDesignHeight'=> $widgetOriginalHeight
        );

        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-image-render.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript">var xiboICTargetId = ' . $this->getWidgetId() . ';</script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-interactive-control.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript">xiboIC.lockAllInteractions();</script>';
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

    /** @inheritDoc */
    public function hasTemplates()
    {
        return true;
    }
}
