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
use Xibo\Exception\XiboException;
use Xibo\Factory\ModuleFactory;

/**
 * Class Finance
 *
 * This module is deprecated - the Yahoo YQL API has been removed.
 *
 * @package Xibo\Widget
 */
class Finance extends ModuleWidget
{
    public $codeSchemaVersion = 1;

    /**
     * Install or Update this module
     * @param ModuleFactory $moduleFactory
     * @throws XiboException
     */
    public function installOrUpdate($moduleFactory)
    {
        throw new XiboException(__('Sorry, this module is deprecated and cannot be installed'));
    }

    /**
     * Install Files
     */
    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-text-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-image-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
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
        $this->module->settings['cachePeriod'] = $this->getSanitizer()->getInt('cachePeriod', 300);

        // Return an array of the processed settings.
        return $this->module->settings;
    }

    public function validate()
    {        
        // If overrideTemplate is false we have to define a template Id 
        if($this->getOption('overrideTemplate') == 0 && ( $this->getOption('templateId') == '' || $this->getOption('templateId') == null) )
            throw new \InvalidArgumentException(__('Please choose a template'));
                
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new \InvalidArgumentException(__('Please enter a duration'));
    }

    /**
     * Adds a Finance Widget
     * @SWG\Post(
     *  path="/playlist/widget/finance/{playlistId}",
     *  operationId="WidgetFinanceAdd",
     *  tags={"widget"},
     *  summary="Add a Finance Widget",
     *  description="Add a new Finance Widget to the specified playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The playlist ID to add a Finance widget",
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
     *      name="item",
     *      in="formData",
     *      description="Items wanted, can be comma separated (example EURUSD, GBPUSD), pass only with overrideTemplate set to 1",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="effect",
     *      in="formData",
     *      description="Effect that will be used to transitions between items, available options: fade, fadeout, scrollVert, scollHorz, flipVert, flipHorz, shuffle, tileSlide, tileBlind, marqueeUp, marqueeDown, marqueeRight, marqueeLeft",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="speed",
     *      in="formData",
     *      description="The transition speed of the selected effect in milliseconds (1000 = normal) or the Marquee speed in a low to high scale (normal = 1)",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="backgroundColor",
     *      in="formData",
     *      description="A HEX color to use as the background color of this widget",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="noRecordsMessage",
     *      in="formData",
     *      description="A message to display when there are no records returned by the search query",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dateFormat",
     *      in="formData",
     *      description="The format to apply to all dates returned by he widget",
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
     *      name="templateId",
     *      in="formData",
     *      description="Use pre-configured templates, available options: currency-simple, stock-simple",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="durationIsPerItem",
     *      in="formData",
     *      description="A flag (0, 1), The duration specified is per item, otherwise the widget duration is divided between the number of items",
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
     *      name="yql",
     *      in="formData",
     *      description="The YQL query to use for data, pass only with overrideTemplate set to 1",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="resultIdentifier",
     *      in="formData",
     *      description="The name of the result identifier returned by the YQL, pass only with overrideTemplate set to 1",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="template",
     *      in="formData",
     *      description="Main template, Pass only with overrideTemplate set to 1 ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="styleSheet",
     *      in="formData",
     *      description="Optional StyleSheet Pass only with overrideTemplate set to 1 ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="javaScript",
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
        $this->setCommonOptions();

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Edit Media
     */
    public function edit()
    {
        $this->setCommonOptions();

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    public function setCommonOptions()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('item', $this->getSanitizer()->getString('item'));
        $this->setOption('effect', $this->getSanitizer()->getString('effect'));
        $this->setOption('speed', $this->getSanitizer()->getInt('speed'));
        $this->setOption('backgroundColor', $this->getSanitizer()->getString('backgroundColor'));
        $this->setOption('noRecordsMessage', $this->getSanitizer()->getString('noRecordsMessage'));
        $this->setOption('dateFormat', $this->getSanitizer()->getString('dateFormat'));
        $this->setOption('overrideTemplate', $this->getSanitizer()->getCheckbox('overrideTemplate'));
        $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 60));
        $this->setOption('templateId', $this->getSanitizer()->getString('templateId'));
        $this->setOption('durationIsPerItem', $this->getSanitizer()->getCheckbox('durationIsPerItem'));
        $this->setRawNode('javaScript', $this->getSanitizer()->getParam('javaScript', ''));
        
        if ($this->getOption('overrideTemplate') == 1){
            $this->setRawNode('template', $this->getSanitizer()->getParam('ta_text', $this->getSanitizer()->getParam('template', null)));
            $this->setRawNode('styleSheet', $this->getSanitizer()->getParam('ta_css', $this->getSanitizer()->getParam('styleSheet', null)));
            $this->setOption('yql', $this->getSanitizer()->getParam('yql', null));
            $this->setOption('resultIdentifier', $this->getSanitizer()->getString('resultIdentifier'));
        }
        
    }

    /**
     * Get YQL Data
     * @return array|bool an array of results according to the key specified by result identifier. false if an invalid value is returned.
     */
    protected function getYql()
    {
        // Construct the YQL
        // process items
        
        if( $this->getOption('overrideTemplate') == 0 ) {
            
            // Get YQL and result identifier from the default templates
            
            $tmplt = $this->getTemplateById($this->getOption('templateId'));
            
            if (isset($tmplt)) {
                $yql = $tmplt['yql'];
                $resultIdentifier = $tmplt['resultIdentifier'];
            }
            
        } else {
            // Get YQL and result identifier from the override input fields
            
            $yql = $this->getOption('yql');
            $resultIdentifier = $this->getOption('resultIdentifier');
        }
        
        $items = $this->getOption('item');

        $this->getLog()->debug('Finance module with YQL = . Looking for %s in response', $yql, $items);

        if ($yql == '' || $items == '') {
            $this->getLog()->error('Missing YQL/Items for Finance Module with WidgetId %d', $this->getWidgetId());
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
        $cache = $this->getPool()->getItem($this->makeCacheKey(md5($yql)));

        $data = $cache->get();

        if ($cache->isMiss()) {

            $cache->lock();

            $this->getLog()->debug('Querying API for ' . $yql);

            if (!$data = $this->request($yql)) {
                return false;
            }

            // Cache it
            $cache->set($data);
            $cache->expiresAfter($this->getSetting('cachePeriod', 300));
            $this->getPool()->saveDeferred($cache);

        }

        $this->getLog()->debug('Finance data returned: %s', var_export($data, true));

        // Pull out the results according to the resultIdentifier
        // If the element to return is an array and we aren't, then box.
        $results = $data[$resultIdentifier];

        if (array_key_exists(0, $results))
            return $results;
        else
            return [$results];
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

                $time = $this->getDate()->getLocalDate($data['time'], $timeSplit[1]);

                $this->getLog()->debug('Time: ' . $time);

                // Pull time out of the array
                $source = str_replace($sub, $time, $source);
            } else {
                // Match that in the array
                if (isset($data[$replace]))
                    $source = str_replace($sub, $data[$replace], $source);
                else
                    $source = str_replace($sub, '', $source);
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
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        // Information from the Module
        $duration = $this->getCalculatedDurationForGetResource();

        // Generate a JSON string of items.
        if (!$items = $this->getYql()) {
            return '';
        }
        
        if( $this->getOption('overrideTemplate') == 0 ) {
            
            // Get CSS and HTML from the default templates
            
            $tmplt = $this->getTemplateById($this->getOption('templateId'));
            
            if (isset($tmplt)) {
                $template = $tmplt['template'];
                $css = $tmplt['css'];
            }
            
        } else {
            // Get CSS and HTML from the override input fields
            
            // Run through each item and substitute with the template
            $template = $this->parseLibraryReferences($isPreview, $this->getRawNode('template'));    
            
            // Get stylesheet
            $css = $this->getRawNode('styleSheet', null);
        }
        
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
            'previewWidth' => $this->getSanitizer()->getDouble('width', 0),
            'previewHeight' => $this->getSanitizer()->getDouble('height', 0),
            'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0)
        );

        // Replace the control meta with our data from twitter
        $data['controlMeta'] = '<!-- NUMITEMS=' . count($items) . ' -->' . PHP_EOL . '<!-- DURATION=' . ($this->getOption('durationIsPerItem', 0) == 0 ? $duration : ($duration * count($items))) . ' -->';

        // Get the JavaScript node
        $javaScript = $this->parseLibraryReferences($isPreview, $this->getRawNode('javaScript', ''));

        // Replace the head content
        $headContent = '';

        // Add the CSS if it isn't empty
        if ($css != '') {
            $headContent .= '<style type="text/css">' . $this->parseLibraryReferences($isPreview, $css ) . '</style>';
        }

        $backgroundColor = $this->getOption('backgroundColor');
        if ($backgroundColor != '') {
            $headContent .= '<style type="text/css">body, .page, .item { background-color: ' . $backgroundColor . ' }</style>';
        }

        // Add our fonts.css file
        $headContent .= '<link href="' . (($isPreview) ? $this->getApp()->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

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
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-image-render.js') . '"></script>';
        

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($renderedItems) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboLayoutScaler(options); $("#content").xiboTextRender(options, items); $("#content").find("img").xiboImageRender(options); ';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= $javaScript;
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

    /**
     * Request from Yahoo API
     * @param $yql
     * @return array|bool
     */
    protected function request($yql)
    {
        // Encode the YQL and make the request
        $url = 'https://query.yahooapis.com/v1/public/yql?q=' . urlencode($yql) . '&format=json&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys';
        //$url = 'https://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20yahoo.finance.quote%20where%20symbol%20in%20(%22TEC.PA%22)&format=json&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys&callback=';

        $client = new Client();

        try {
            $response = $client->get($url, $this->getConfig()->getGuzzleProxy());

            if ($response->getStatusCode() == 200) {
                return json_decode($response->getBody(), true)['query']['results'];
            }
            else {
                $this->getLog()->info('Invalid response from Yahoo %d. %s', $response->getStatusCode(), $response->getBody());
                return false;
            }
        }
        catch (RequestException $e) {
            $this->getLog()->error('Unable to reach Yahoo API: %s', $e->getMessage());
            return false;
        }
    }
}
