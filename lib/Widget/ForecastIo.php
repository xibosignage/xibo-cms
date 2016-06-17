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
use Xibo\Entity\Media;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\ModuleFactory;


class ForecastIo extends ModuleWidget
{
    const API_ENDPOINT = 'https://api.forecast.io/forecast/';

    private $resourceFolder;
    protected $codeSchemaVersion = 1;

    /**
     * ForecastIo constructor.
     */
    public function init()
    {
        $this->resourceFolder = PROJECT_ROOT . '/web/modules/forecastio';
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
            $module->name = 'Forecast IO';
            $module->type = 'forecastio';
            $module->class = 'Xibo\Widget\ForecastIo';
            $module->description = 'Weather forecasting from Forecast IO';
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
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/xibo-layout-scaler.js')->save();

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
     */
    public function settings()
    {
        // Process any module settings you asked for.
        $apiKey = $this->getSanitizer()->getString('apiKey');

        if ($apiKey == '')
            throw new \InvalidArgumentException(__('Missing API Key'));

        $this->module->settings['apiKey'] = $apiKey;
        $this->module->settings['cachePeriod'] = $this->getSanitizer()->getInt('cachePeriod', 300);
    }

    /**
     * Loads templates for this module
     */
    private function loadTemplates()
    {
        // Scan the folder for template files
        foreach (glob(PROJECT_ROOT . '/modules/forecastio/*.template.json') as $template) {
            // Read the contents, json_decode and add to the array
            $this->module->settings['templates'][] = json_decode(file_get_contents($template), true);
        }

        $this->getLog()->debug(count($this->module->settings['templates']));
    }

    /**
     * Templates available
     * @return array
     */
    public function templatesAvailable()
    {
        if (!isset($this->module->settings['templates']))
            $this->loadTemplates();

        return $this->module->settings['templates'];
    }

    public function validate()
    {
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new \InvalidArgumentException(__('Please enter a duration'));
    }

    /**
     * Add Media to the Database
     */
    public function add()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('useDisplayLocation', $this->getSanitizer()->getCheckbox('useDisplayLocation'));
        $this->setOption('color', $this->getSanitizer()->getString('color'));
        $this->setOption('longitude', $this->getSanitizer()->getDouble('longitude'));
        $this->setOption('latitude', $this->getSanitizer()->getDouble('latitude'));
        $this->setOption('templateId', $this->getSanitizer()->getString('templateId'));
        $this->setOption('icons', $this->getSanitizer()->getString('icons'));
        $this->setOption('overrideTemplate', $this->getSanitizer()->getCheckbox('overrideTemplate'));
        $this->setOption('size', $this->getSanitizer()->getInt('size'));
        $this->setOption('units', $this->getSanitizer()->getString('units'));
        $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 60));
        $this->setOption('lang', $this->getSanitizer()->getString('lang'));
        $this->setOption('dayConditionsOnly', $this->getSanitizer()->getCheckbox('dayConditionsOnly'));

        $this->setRawNode('styleSheet', $this->getSanitizer()->getParam('styleSheet', null));
        $this->setRawNode('currentTemplate', $this->getSanitizer()->getParam('currentTemplate', null));
        $this->setRawNode('dailyTemplate', $this->getSanitizer()->getParam('dailyTemplate', null));
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
        $this->setOption('color', $this->getSanitizer()->getString('color'));
        $this->setOption('longitude', $this->getSanitizer()->getDouble('longitude'));
        $this->setOption('latitude', $this->getSanitizer()->getDouble('latitude'));
        $this->setOption('templateId', $this->getSanitizer()->getString('templateId'));
        $this->setOption('icons', $this->getSanitizer()->getString('icons'));
        $this->setOption('overrideTemplate', $this->getSanitizer()->getCheckbox('overrideTemplate'));
        $this->setOption('size', $this->getSanitizer()->getInt('size'));
        $this->setOption('units', $this->getSanitizer()->getString('units'));
        $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 60));
        $this->setOption('lang', $this->getSanitizer()->getString('lang'));
        $this->setOption('dayConditionsOnly', $this->getSanitizer()->getCheckbox('dayConditionsOnly'));

        $this->setRawNode('styleSheet', $this->getSanitizer()->getParam('styleSheet', null));
        $this->setRawNode('currentTemplate', $this->getSanitizer()->getParam('currentTemplate', null));
        $this->setRawNode('dailyTemplate', $this->getSanitizer()->getParam('dailyTemplate', null));
        $this->setRawNode('javaScript', $this->getSanitizer()->getParam('javaScript', ''));

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    public function iconsAvailable()
    {
        // Scan the forecast io folder for icons
        $icons = array();

        foreach (array_diff(scandir($this->resourceFolder), array('..', '.')) as $file) {
            if (stripos($file, '.png'))
                $icons[] = array('id' => $file, 'value' => ucfirst(str_replace('-', ' ', str_replace('.png', '', $file))));
        }

        return $icons;
    }

    /**
     * Units supported by Forecast.IO API
     * @return array The Units Available
     */
    public function unitsAvailable()
    {
        return array(
            array('id' => 'auto', 'value' => 'Automatically select based on geographic location', 'tempUnit' => ''),
            array('id' => 'ca', 'value' => 'Canada', 'tempUnit' => 'F'),
            array('id' => 'si', 'value' => 'Standard International Units', 'tempUnit' => 'C'),
            array('id' => 'uk', 'value' => 'United Kingdom', 'tempUnit' => 'C'),
            array('id' => 'us', 'value' => 'United States', 'tempUnit' => 'F'),
        );
    }

    /**
     * Languages supported by Forecast.IO API
     * @return array The Supported Language
     */
    public function supportedLanguages()
    {
        return array(
            array('id' => 'en', 'value' => __('English')),
            array('id' => 'bs', 'value' => __('Bosnian')),
            array('id' => 'de', 'value' => __('German')),
            array('id' => 'es', 'value' => __('Spanish')),
            array('id' => 'fr', 'value' => __('French')),
            array('id' => 'it', 'value' => __('Italian')),
            array('id' => 'nl', 'value' => __('Dutch')),
            array('id' => 'pl', 'value' => __('Polish')),
            array('id' => 'pt', 'value' => __('Portuguese')),
            array('id' => 'ru', 'value' => __('Russian')),
            array('id' => 'tet', 'value' => __('Tetum')),
            array('id' => 'tr', 'value' => __('Turkish')),
            array('id' => 'x-pig-latin', 'value' => __('lgpay Atinlay'))
        );
    }

    /**
     * Get Tab
     */
    public function getTab($tab)
    {
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
    }

    /**
     * Get the forecast data for the provided display id
     * @param int $displayId
     * @return array
     */
    private function getForecastData($displayId)
    {
        $defaultLat = $this->getConfig()->GetSetting('DEFAULT_LAT');
        $defaultLong = $this->getConfig()->GetSetting('DEFAULT_LONG');

        if ($this->getOption('useDisplayLocation') == 1) {
            // Use the display ID or the default.
            if ($displayId != 0) {

                $display = $this->displayFactory->getById($displayId);

                if ($display->latitude != '' && $display->longitude != '') {
                    $defaultLat = $display->latitude;
                    $defaultLong = $display->longitude;
                } else {
                    $this->getLog()->error('Warning, display %s does not have a lat/long and yet a forecast widget is set to use display location.', $display->display);
                }
            }
        } else {
            $defaultLat = $this->getOption('latitude', $defaultLat);
            $defaultLong = $this->getOption('longitude', $defaultLong);
        }

        $apiKey = $this->getSetting('apiKey');
        if ($apiKey == '')
            die(__('Incorrectly configured module'));

        // Query the API and Dump the Results.
        $apiOptions = array('units' => $this->getOption('units', 'auto'), 'lang' => $this->getOption('lang', 'en'), 'exclude' => 'flags,minutely,hourly');

        $cache = $this->getPool()->getItem('forecast/' . md5($defaultLat . $defaultLong . implode('.', $apiOptions)));
        $data = $cache->get();

        if ($cache->isMiss()) {
            $this->getLog()->notice('Getting Forecast from the API');
            if (!$data = $this->get($defaultLat, $defaultLong, null, $apiOptions)) {
                return false;
            }

            // Cache
            $cache->set($data);
            $cache->expiresAfter($this->getSetting('cachePeriod'));
            $this->getPool()->saveDeferred($cache);
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

        // Temperature Unit Mappings
        $temperatureUnit = '';
        foreach ($this->unitsAvailable() as $unit) {
            if ($unit['id'] == $this->getOption('units', 'auto')) {
                $temperatureUnit = $unit['tempUnit'];
                break;
            }
        }

        // Are we set to only show daytime weather conditions?
        if ($this->getOption('dayConditionsOnly') == 1) {
            if ($data->currently->icon == 'partly-cloudy-night')
                $data->currently->icon = 'clear-day';
        }

        $data->currently->wicon = (isset($icons[$data->currently->icon]) ? $icons[$data->currently->icon] : $icons['unmapped']);
        $data->currently->temperatureFloor = (isset($data->currently->temperature) ? floor($data->currently->temperature) : '--');
        $data->currently->summary = (isset($data->currently->summary) ? $data->currently->summary : '--');
        $data->currently->weekSummary = (isset($data->daily->summary) ? $data->daily->summary : '--');
        $data->currently->temperatureUnit = $temperatureUnit;

        // Convert a stdObject to an array
        $data = json_decode(json_encode($data), true);

        // Process the icon for each day
        for ($i = 0; $i < 7; $i++) {
            // Are we set to only show daytime weather conditions?
            if ($this->getOption('dayConditionsOnly') == 1) {
                if ($data['daily']['data'][$i]['icon'] == 'partly-cloudy-night')
                    $data['daily']['data'][$i]['icon'] = 'clear-day';
            }

            $data['daily']['data'][$i]['wicon'] = (isset($icons[$data['daily']['data'][$i]['icon']]) ? $icons[$data['daily']['data'][$i]['icon']] : $icons['unmapped']);
            $data['daily']['data'][$i]['temperatureMaxFloor'] = (isset($data['daily']['data'][$i]['temperatureMax'])) ? floor($data['daily']['data'][$i]['temperatureMax']) : '--';
            $data['daily']['data'][$i]['temperatureMinFloor'] = (isset($data['daily']['data'][$i]['temperatureMin'])) ? floor($data['daily']['data'][$i]['temperatureMin']) : '--';
            $data['daily']['data'][$i]['temperatureFloor'] = ($data['daily']['data'][$i]['temperatureMinFloor'] != '--' && $data['daily']['data'][$i]['temperatureMaxFloor'] != '--') ? floor((($data['daily']['data'][$i]['temperatureMinFloor'] + $data['daily']['data'][$i]['temperatureMaxFloor']) / 2)) : '--';
            $data['daily']['data'][$i]['temperatureUnit'] = $temperatureUnit;
        }

        return $data;
    }

    private function makeSubstitutions($data, $source)
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

                $time = $this->getDate()->getLocalDate($data['time'], $timeSplit[1]);

                $this->getLog()->info('Time: ' . $time);

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
     */
    public function getResource($displayId = 0)
    {
        // Behave exactly like the client.
        if (!$foreCast = $this->getForecastData($displayId))
            return '';

        // Do we need to override the language?
        // TODO: I don't like this date fix, the library should really check the file exists?
        if ($this->getOption('lang', 'en') != 'en' && file_exists(PROJECT_ROOT . '/vendor/jenssegers/date/src/Lang/' . $this->getOption('lang') . '.php')) {
            $this->getDate()->setLocale($this->getOption('lang'));
        }

        $data = [];
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        $headContent = '
            <link href="' . $this->getResourceUrl('forecastio/weather-icons.min.css') . '" rel="stylesheet" media="screen">
            <style type="text/css">
                .container { color: ' . $this->getOption('color', '000') . '; }
                #content { zoom: ' . $this->getOption('size', 1) . '; }
                ' . $this->parseLibraryReferences($isPreview, $this->getRawNode('styleSheet', null)) . '
            </style>
        ';

        // Add our fonts.css file
        $headContent .= '<link href="' . (($isPreview) ? $this->getApp()->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

        // Replace any icon sets
        $data['head'] = str_replace('[[ICONS]]', $this->getResourceUrl('forecastio/' . $this->getOption('icons')), $headContent);

        // Make some body content
        $body = $this->parseLibraryReferences($isPreview, $this->getRawNode('currentTemplate', null));
        $dailyTemplate = $this->parseLibraryReferences($isPreview, $this->getRawNode('dailyTemplate', null));

        // Get the JavaScript node
        $javaScript = $this->parseLibraryReferences($isPreview, $this->getRawNode('javaScript', ''));

        // Handle the daily template (if its here)
        if (stripos($body, '[dailyForecast]')) {
            // Pull it out, and run substitute over it for each day
            $dailySubs = '';
            // Substitute for every day (i.e. 7 times).
            for ($i = 0; $i < 7; $i++) {
                $dailySubs .= $this->makeSubstitutions($foreCast['daily']['data'][$i], $dailyTemplate);
            }

            // Substitute the completed template
            $body = str_replace('[dailyForecast]', $dailySubs, $body);
        }

        // Run replace over the main template
        $data['body'] = $this->makeSubstitutions($foreCast['currently'], $body);


        // JavaScript to control the size (override the original width and height so that the widget gets blown up )
        $options = array(
            'previewWidth' => $this->getSanitizer()->getDouble('width', 0),
            'previewHeight' => $this->getSanitizer()->getDouble('height', 0),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0)
        );

        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script>

            var options = ' . json_encode($options) . '

            $(document).ready(function() {
                $("body").xiboLayoutScaler(options);
            });
        </script>';
        $javaScriptContent .= $javaScript;

        // Replace the After body Content
        $data['javaScript'] = $javaScriptContent;

        // Update and save widget if we've changed our assignments.
        if ($this->hasMediaChanged())
            $this->widget->save(['saveWidgetOptions' => false, 'notifyDisplays' => true]);

        // Return that content.
        return $this->renderTemplate($data);
    }

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
}
