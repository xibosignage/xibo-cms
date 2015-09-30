<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2014-2015 Daniel Garner
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
use Xibo\Exception\NotFoundException;
use Xibo\Factory\MediaFactory;
use Xibo\Helper\Cache;
use Xibo\Helper\Config;
use Xibo\Helper\Date;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;

class Finance extends ModuleWidget
{
    public $codeSchemaVersion = 1;

    /**
     * Install or Update this module
     */
    public function installOrUpdate()
    {
        if ($this->module == null) {
            // Install
            $module = new \Xibo\Entity\Module();
            $module->name = 'Finance';
            $module->type = 'finance';
            $module->class = 'Xibo\Widget\Finance';
            $module->description = 'Yahoo Finance';
            $module->imageUri = 'forms/library.gif';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->settings = [];

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /**
     * Install Files
     */
    public function installFiles()
    {
        MediaFactory::createModuleSystemFile('modules/vendor/jquery-1.11.1.min.js')->save();
        MediaFactory::createModuleSystemFile('modules/xibo-text-render.js')->save();
        MediaFactory::createModuleSystemFile('modules/xibo-layout-scaler.js')->save();
    }

    /**
     * Loads templates for this module
     */
    private function loadTemplates()
    {
        $this->module->settings['templates'] = [];

        // Scan the folder for template files
        foreach (glob(PROJECT_ROOT . '/modules/finance/*.template.json') as $template) {
            // Read the contents, json_decode and add to the array
            $this->module->settings['templates'][] = json_decode(file_get_contents($template), true);
        }

        Log::debug(count($this->module->settings['templates']));
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

    /**
     * Form for updating the module settings
     */
    public function settingsForm()
    {
        return 'finance-form-settings';
    }

    /**
     * Process any module settings
     */
    public function settings()
    {
        $this->module->settings['cachePeriod'] = Sanitize::getInt('cachePeriod', 300);

        // Return an array of the processed settings.
        return $this->module->settings;
    }

    public function validate()
    {
        if ($this->getDuration() == 0)
            throw new \InvalidArgumentException(__('Please enter a duration'));
    }

    /**
     * Add Media
     */
    public function add()
    {
        $this->setDuration(Sanitize::getInt('duration', $this->getDuration()));
        $this->setOption('name', Sanitize::getString('name'));
        $this->setOption('yql', Sanitize::getString('yql'));
        $this->setOption('item', Sanitize::getString('item'));
        $this->setOption('resultIdentifier', Sanitize::getString('resultIdentifier'));
        $this->setOption('effect', Sanitize::getString('effect'));
        $this->setOption('speed', Sanitize::getInt('speed'));
        $this->setOption('backgroundColor', Sanitize::getString('backgroundColor'));
        $this->setOption('noRecordsMessage', Sanitize::getString('noRecordsMessage'));
        $this->setOption('dateFormat', Sanitize::getString('dateFormat'));
        $this->setOption('overrideTemplate', Sanitize::getCheckbox('overrideTemplate'));
        $this->setOption('updateInterval', Sanitize::getInt('updateInterval', 60));
        $this->setOption('templateId', Sanitize::getString('templateId'));

        $this->setRawNode('template', Sanitize::getParam('ta_text', null));
        $this->setRawNode('styleSheet', Sanitize::getParam('ta_css', null));

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Edit Media
     */
    public function edit()
    {
        $this->setDuration(Sanitize::getInt('duration', $this->getDuration()));
        $this->setOption('name', Sanitize::getString('name'));
        $this->setOption('yql', Sanitize::getString('yql'));
        $this->setOption('item', Sanitize::getString('item'));
        $this->setOption('resultIdentifier', Sanitize::getString('resultIdentifier'));
        $this->setOption('effect', Sanitize::getString('effect'));
        $this->setOption('speed', Sanitize::getInt('speed'));
        $this->setOption('backgroundColor', Sanitize::getString('backgroundColor'));
        $this->setOption('noRecordsMessage', Sanitize::getString('noRecordsMessage'));
        $this->setOption('dateFormat', Sanitize::getString('dateFormat'));
        $this->setOption('overrideTemplate', Sanitize::getCheckbox('overrideTemplate'));
        $this->setOption('updateInterval', Sanitize::getInt('updateInterval', 60));
        $this->setOption('templateId', Sanitize::getString('templateId'));

        $this->setRawNode('template', Sanitize::getParam('ta_text', null));
        $this->setRawNode('styleSheet', Sanitize::getParam('ta_css', null));

        // Save the widget
        $this->validate();
        $this->saveWidget();

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Get YQL Data
     * @return array|bool an array of results according to the key specified by result identifier. false if an invalid value is returned.
     */
    protected function getYql()
    {
        // Construct the YQL
        // process items
        $yql = $this->getOption('yql');
        $items = $this->getOption('item');

        Log::debug('Finance module with YQL = . Looking for %s in response', $yql, $items);

        if ($yql == '' || $items == '') {
            Log::error('Missing YQL/Items for Finance Module with WidgetId %d', $this->getWidgetId());
            return false;
        }

        if (strstr($items, ','))
            $items = explode(',', $items);
        else
            $items = [$items];

        // quote each item
        $items = array_map(function ($element) {
            return '\'' . trim($element) . '\'';
        }, $items);

        $yql = str_replace('[Item]', implode(',', $items), $yql);

        // Fire off a request for the data
        $key = md5($yql);

        if (!Cache::has($key) || Cache::get($key) == '') {

            Log::debug('Querying API for ' . $yql);

            if (!$data = $this->request($yql)) {
                return false;
            }

            // Cache it
            Cache::put($key, $data, $this->getSetting('cachePeriod', 300));

        } else {
            Log::debug('Served from Cache');
            $data = Cache::get($key);
        }

        Log::debug('Finance data returned: %s', var_export($data, true));

        // Pull out the results according to the resultIdentifier
        // If the element to return is an array and we aren't, then box.
        $results = $data[$this->getOption('resultIdentifier')];

        if (array_key_exists(0, $results))
            return $results;
        else
            return [$results];
    }

    /**
     * Request from Yahoo API
     * @param $yql
     * @return array|bool
     */
    private function request($yql)
    {
        // Encode the YQL and make the request
        $url = 'https://query.yahooapis.com/v1/public/yql?q=' . urlencode($yql) . '&format=json&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys';
        //$url = 'https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yahoo.finance.quote%20where%20symbol%20in%20(%22TEC.PA%22)&format=json&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys&callback=';

        $client = new Client();

        try {
            $response = $client->get($url, Config::getGuzzelProxy());

            if ($response->getStatusCode() == 200) {
                return json_decode($response->getBody(), true)['query']['results'];
            }
            else {
                Log::info('Invalid response from Yahoo %d. %s', $response->getStatusCode(), $response->getBody());
                return false;
            }
        }
        catch (RequestException $e) {
            Log::error('Unable to reach Yahoo API: %s', $e->getMessage());
            return false;
        }
    }

    /**
     * Run through the data and substitute into the template
     * @param $data
     * @param $source
     * @return mixed
     */
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

                $time = Date::getLocalDate($data['time'], $timeSplit[1]);

                Log::debug('Time: ' . $time);

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
     * Get Tab
     */
    public function getTab($tab)
    {
        if (!$data = $this->getYql())
            throw new NotFoundException(__('No data returned, please check error log.'));

        return ['results' => $data[0]];
    }

    /**
     * Get Resource
     * @param int $displayId
     * @return mixed
     */
    public function getResource($displayId = 0)
    {
        $data = [];
        $isPreview = (Sanitize::getCheckbox('preview') == 1);

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        // Information from the Module
        $duration = $this->getDuration();

        // Generate a JSON string of items.
        if (!$items = $this->getYql($displayId, $isPreview)) {
            return '';
        }

        // Run through each item and substitute with the template
        $template = $this->getRawNode('template');
        $renderedItems = [];

        foreach ($items as $item) {
            $renderedItems[] = $this->makeSubstitutions($item, $template);
        }

        $marqueeEffect = (stripos($this->getOption('effect'), 'marquee') !== false);

        $options = array(
            'type' => $this->getModuleType(),
            'fx' => $this->getOption('effect', 'none'),
            'speed' => $this->getOption('speed', (($marqueeEffect) ? 1 : 500)),
            'duration' => $duration,
            'durationIsPerItem' => ($this->getOption('durationIsPerItem', 0) == 1),
            'numItems' => count($renderedItems),
            'itemsPerPage' => 1,
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'previewWidth' => Sanitize::getDouble('width', 0),
            'previewHeight' => Sanitize::getDouble('height', 0),
            'scaleOverride' => Sanitize::getDouble('scale_override', 0)
        );

        // Replace the control meta with our data from twitter
        $data['controlMeta'] = '<!-- NUMITEMS=' . count($items) . ' -->' . PHP_EOL . '<!-- DURATION=' . ($this->getOption('durationIsPerItem', 0) == 0 ? $duration : ($duration * count($items))) . ' -->';

        // Replace the head content
        $headContent = '';

        // Add the CSS if it isn't empty
        $css = $this->getRawNode('styleSheet', null);
        if ($css != '') {
            $headContent .= '<style type="text/css">' . $css . '</style>';
        }

        $backgroundColor = $this->getOption('backgroundColor');
        if ($backgroundColor != '') {
            $headContent .= '<style type="text/css">body, .page, .item { background-color: ' . $backgroundColor . ' }</style>';
        }

        // Add our fonts.css file
        $headContent .= '<link href="' . $this->getResourceUrl('fonts.css') . ' rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents(Theme::uri('css/client.css', true)) . '</style>';

        // Replace the Head Content with our generated javascript
        $data['head'] = $headContent;

        // Add some scripts to the JavaScript Content
        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';

        // Need the cycle plugin?
        if ($this->getOption('effect') != 'none')
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-cycle-2.1.6.min.js') . '"></script>';

        // Need the marquee plugin?
        if ($marqueeEffect)
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery.marquee.min.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-text-render.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($renderedItems) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboLayoutScaler(options); $("#content").xiboTextRender(options, items); ';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

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
}
