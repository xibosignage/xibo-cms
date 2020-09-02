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

use Xibo\Exception\ConfigurationException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\ModuleFactory;

/**
 * Class Stocks
 * @package Xibo\Widget
 */
class Stocks extends AlphaVantageBase
{
    public $codeSchemaVersion = 1;

    /**
     * Install or Update this module
     * @param ModuleFactory $moduleFactory
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Stocks';
            $module->type = 'stocks';
            $module->class = 'Xibo\Widget\Stocks';
            $module->description = 'A module for showing Stock quotes';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 30;
            $module->settings = [];
            $module->installName = 'stocks';

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
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-finance-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-image-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/bootstrap.min.css')->save();
    }


    /**
     * Javascript functions for the layout designer
     */
    public function layoutDesignerJavaScript()
    {
        return 'stocks-designer-javascript';
    }

    /**
     * Form for updating the module settings
     */
    public function settingsForm()
    {
        return 'stocks-form-settings';
    }

    /**
     * Process any module settings
     * @throws InvalidArgumentException
     */
    public function settings()
    {
        $apiKey = $this->getSanitizer()->getString('apiKey');
        $cachePeriod = $this->getSanitizer()->getInt('cachePeriod', 14400);

        if ($this->module->enabled != 0) {
            if ($apiKey == '')
                throw new InvalidArgumentException(__('Missing API Key'), 'apiKey');

            if ($cachePeriod < 3600)
                throw new InvalidArgumentException(__('Cache Period must be 3600 or greater for this Module'), 'cachePeriod');
        }

        $this->module->settings['apiKey'] = $apiKey;
        $this->module->settings['cachePeriod'] = $cachePeriod;

        // Return an array of the processed settings.
        return $this->module->settings;
    }

    /**
     * Edit Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?stocks",
     *  operationId="WidgetStocksEdit",
     *  tags={"widget"},
     *  summary="Edit a Stocks Widget",
     *  description="Edit a new Stocks Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
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
     *      name="items",
     *      in="formData",
     *      description="A comma separated list of Stock Ticker Symbols, e.g. GOOGL,NVDA,AMZN. For the best results enter no more than 5 items.",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="effect",
     *      in="formData",
     *      description="Effect that will be used to transitions between items, available options: fade, fadeout, scrollVert, scollHorz, flipVert, flipHorz, shuffle, tileSlide, tileBlind",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="speed",
     *      in="formData",
     *      description="The transition speed of the selected effect in milliseconds (1000 = normal)",
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
     *      description="Use pre-configured templates, available options: stocks1, stocks2",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="durationIsPerPage",
     *      in="formData",
     *      description="A flag (0, 1), The duration specified is per page, otherwise the widget duration is divided between the number of pages/items",
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
     *      name="maxItemsPerPage",
     *      in="formData",
     *      description="This is the intended number of items on each page",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="mainTemplate",
     *      in="formData",
     *      description="Main template, Pass only with overrideTemplate set to 1",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="itemtemplate",
     *      in="formData",
     *      description="Template for each item, replaces [itemsTemplate] in main template, Pass only with overrideTemplate set to 1 ",
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
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws \Xibo\Exception\XiboException
     */
    public function edit()
    {
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('enableStat', $this->getSanitizer()->getString('enableStat'));
        $this->setOption('items', $this->getSanitizer()->getString('items'));
        $this->setOption('effect', $this->getSanitizer()->getString('effect'));
        $this->setOption('speed', $this->getSanitizer()->getInt('speed'));
        $this->setOption('backgroundColor', $this->getSanitizer()->getString('backgroundColor'));
        $this->setOption('noRecordsMessage', $this->getSanitizer()->getString('noRecordsMessage'));
        $this->setOption('dateFormat', $this->getSanitizer()->getString('dateFormat'));
        $this->setOption('overrideTemplate', $this->getSanitizer()->getCheckbox('overrideTemplate'));
        $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 60));
        $this->setOption('templateId', $this->getSanitizer()->getString('templateId'));
        $this->setOption('durationIsPerPage', $this->getSanitizer()->getCheckbox('durationIsPerPage'));
        $this->setRawNode('javaScript', $this->getSanitizer()->getParam('javaScript', ''));

        if ($this->getOption('overrideTemplate') == 1) {
            $this->setOption('widgetOriginalWidth', $this->getSanitizer()->getInt('widgetOriginalWidth'));
            $this->setOption('widgetOriginalHeight', $this->getSanitizer()->getInt('widgetOriginalHeight'));
            $this->setOption('maxItemsPerPage', $this->getSanitizer()->getInt('maxItemsPerPage', 4));
            $this->setRawNode('mainTemplate', $this->getSanitizer()->getParam('mainTemplate', $this->getSanitizer()->getParam('mainTemplate', null)));
            $this->setRawNode('itemTemplate', $this->getSanitizer()->getParam('itemTemplate', $this->getSanitizer()->getParam('itemTemplate', null)));
            $this->setRawNode('styleSheet', $this->getSanitizer()->getParam('styleSheet', $this->getSanitizer()->getParam('styleSheet', null)));
        }

        // Save the widget
        $this->isValid();
        $this->saveWidget();
    }

    /**
     * Get Stock Results
     *  PLEASE NOTE: This method does not cache results directly as the AlphaVantageBase class handles caching individual
     *  requests.
     * @return array|bool an array of results according to the key specified by result identifier. false if an invalid value is returned.
     * @throws ConfigurationException
     */
    protected function getResults()
    {
        // Construct the YQL
        // process items
        $items = $this->getOption('items');

        if ($items == '') {
            $this->getLog()->error('Missing Items for Stocks Module with WidgetId ' . $this->getWidgetId());
            return false;
        }

        $data = [];

        // Parse items out into an array
        $items = array_map('trim', explode(',', $items));

        try {
            foreach ($items as $symbol) {

                // Does this symbol have any additional data
                $parsedSymbol = explode('|', $symbol);

                $symbol = $parsedSymbol[0];
                $name = (isset($parsedSymbol[1]) ? $parsedSymbol[1] : $symbol);
                $currency = (isset($parsedSymbol[2]) ? $parsedSymbol[2] : '');

                $result = $this->getStockQuote($symbol);

                $this->getLog()->debug('Results are: ' . var_export($result, true));

                $parsedResult = [];

                foreach ($result['Time Series (Daily)'] as $series) {

                    $parsedResult = [
                        'Name' => $name,
                        'Symbol' => $symbol,
                        'time' => $result['Meta Data']['3. Last Refreshed'],
                        'LastTradePriceOnly' => round($series['4. close'], 4),
                        'RawLastTradePriceOnly' => $series['4. close'],
                        'YesterdayTradePriceOnly' => round($series['1. open'], 4),
                        'RawYesterdayTradePriceOnly' => $series['1. open'],
                        'TimeZone' => $result['Meta Data']['5. Time Zone'],
                        'Currency' => $currency
                    ];

                    $parsedResult['Change'] = round($parsedResult['RawLastTradePriceOnly'] - $parsedResult['RawYesterdayTradePriceOnly'], 4);

                    break;
                }

                // Parse the result and add it to our data array
                $data[] = $parsedResult;
            }
        } catch (ConfigurationException $configurationException) {
            throw $configurationException;
        } catch (XiboException $requestException) {
            $this->getLog()->error('Problem getting stock information. E = ' . $requestException->getMessage());
            $this->getLog()->debug($requestException->getTraceAsString());

            return false;
        }

        $this->getLog()->debug('Parsed Results are: ' . var_export($data, true));

        return $data;
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
            $replacement = 'NULL';
            
            $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);
            
            // Match that in the array
            if ( isset($data[$replace]) ){
                // If the tag exists on the data variables use that var
                $replacement = $data[$replace];
            } else {
                // Custom tags
                
                // Replace the time tag
                if (stripos($replace, 'time|') > -1) {
                    $timeSplit = explode('|', $replace);

                    $time = $this->getDate()->parse($data['time'], 'Y-m-d H:i:s')->format($timeSplit[1]);

                    $replacement = $time;

                } else if (stripos($replace, 'NameTrimmed|') > -1) {
                    $nameSplit = explode('|', $replace);
                    $name = $data['Name'];
                    
                    // Remove the last word until the string is inside the pretended Serializable
                    while (strlen($name) > $nameSplit[1]) {
                        $name = substr($name, 0, strrpos($name, " "));
                    }

                    $replacement = strtoupper($name);

                } else {
                    
                    // Replace the other tags
                    switch ($replace) {
                        case 'ChangePercentage':
                            // Protect against null values
                            if(($data['Change'] == null || $data['LastTradePriceOnly'] == null)){
                                $replacement = "0";
                            } else {
                                // Calculate the percentage dividing the change by the ( previous value minus the change )
                                $percentage = $data['Change'] / ( $data['LastTradePriceOnly'] - $data['Change'] );
                                
                                // Convert the value to percentage and round it
                                $replacement = round($percentage*100, 2);
                            }    
                            
                            break;
                            
                        case 'SymbolTrimmed':
                            
                            $replacement = explode('.', $data['Symbol'])[0];
                            
                            break;
                            
                        case 'ChangeStyle':
                            // Default value as no change
                            $replacement = 'value-equal';
                            
                            // Protect against null values
                            if(($data['Change'] != null && $data['LastTradePriceOnly'] != null)){
                    
                                if ( $data['Change'] > 0 ) {
                                    $replacement = 'value-up';
                                } else if ( $data['Change'] < 0 ){
                                    $replacement = 'value-down';
                                }
                            }
                            
                            break;
                            
                        case 'ChangeIcon':
                        
                            // Default value as no change
                            $replacement = 'right-arrow';
                            
                            // Protect against null values
                            if(($data['Change'] != null && $data['LastTradePriceOnly'] != null)){
                    
                                if ( $data['Change'] > 0 ) {
                                    $replacement = 'up-arrow';
                                } else if ( $data['Change'] < 0 ){
                                    $replacement = 'down-arrow';
                                }
                            }
                            
                            break;

                        case 'CurrencyUpper':
                            // Currency in uppercase
                            $replacement = strtoupper($data['Currency']);

                            break;
                            
                        default:
                            $replacement = null;
                            
                            break;
                    }    
                }
            }
            
            // Replace the variable on the source string
            $source = str_replace($sub, $replacement, $source);
        }

        return $source;
    }

    /**
     * Get Tab
     */
    public function getTab($tab)
    {
        if (!$data = $this->getResults())
            throw new NotFoundException(__('No data returned, please check error log.'));

        return ['results' => $data[0]];
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {        
        $data = [];
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        // Information from the Module        
        $duration = $this->getCalculatedDurationForGetResource();
        $durationIsPerItem = $this->getOption('durationIsPerItem', 1);

        // Generate a JSON string of items.
        if (!$items = $this->getResults()) {
            return '';
        }
        
        if( $this->getOption('overrideTemplate') == 0 ) {
            
            $template = $this->getTemplateById($this->getOption('templateId'));
            
            if (isset($template)) {
                $mainTemplate = $template['main'];
                $itemTemplate = $template['item'];
                $styleSheet = $template['css'];
                $widgetOriginalWidth = $template['widgetOriginalWidth'];
                $widgetOriginalHeight = $template['widgetOriginalHeight'];
                $maxItemsPerPage = $template['maxItemsPerPage'];
            }
            
        } else {
            
            $mainTemplate = $this->getRawNode('mainTemplate');
            $itemTemplate = $this->getRawNode('itemTemplate');
            $styleSheet = $this->getRawNode('styleSheet', '');
            $widgetOriginalWidth = $this->getSanitizer()->int($this->getOption('widgetOriginalWidth'));
            $widgetOriginalHeight = $this->getSanitizer()->int($this->getOption('widgetOriginalHeight'));
            $maxItemsPerPage = $this->getSanitizer()->int($this->getOption('maxItemsPerPage'));
        }

        // Run through each item and substitute with the template
        $mainTemplate = $this->parseLibraryReferences($isPreview, $mainTemplate);
        $itemTemplate = $this->parseLibraryReferences($isPreview, $itemTemplate);

        // Parse translations
        $mainTemplate = $this->parseTranslations($mainTemplate);
        $itemTemplate = $this->parseTranslations($itemTemplate);
        
        $renderedItems = [];
        
        foreach ($items as $item) {
            $renderedItems[] = $this->makeSubstitutions($item, $itemTemplate);
        }        

        $options = array(
            'type' => $this->getModuleType(),
            'fx' => $this->getOption('effect', 'none'),
            'speed' => $this->getOption('speed', 500),
            'duration' => $duration,
            'durationIsPerPage' => ($this->getOption('durationIsPerPage', 0) == 1),
            'numItems' => count($renderedItems),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'widgetDesignWidth' => $widgetOriginalWidth,
            'widgetDesignHeight'=> $widgetOriginalHeight,
            'maxItemsPerPage' => $maxItemsPerPage
        );

        $itemsPerPage = $options['maxItemsPerPage'];
        $pages = count($renderedItems);
        $pages = ($itemsPerPage > 0) ? ceil($pages / $itemsPerPage) : $pages;
        $totalDuration = ($durationIsPerItem == 0) ? $duration : ($duration * $pages);
        
        // Replace and Control Meta options
        $data['controlMeta'] = '<!-- NUMITEMS=' . $pages . ' -->' . PHP_EOL . '<!-- DURATION=' . $totalDuration . ' -->';

        // Get the JavaScript node
        $javaScript = $this->parseLibraryReferences($isPreview, $this->getRawNode('javaScript', ''));

        // Replace the head content
        $headContent = '';

        // Add our fonts.css file
        $headContent .= '<link href="' . (($isPreview) ? $this->getApp()->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">
        <link href="' . $this->getResourceUrl('vendor/bootstrap.min.css')  . '" rel="stylesheet" media="screen">';
        
        $backgroundColor = $this->getOption('backgroundColor');
        if ($backgroundColor != '') {
            $headContent .= '<style type="text/css"> body { background-color: ' . $backgroundColor . ' }</style>';
        } else {
          $headContent .= '<style type="text/css"> body { background-color: transparent }</style>';
        }
        
        // Add the CSS if it isn't empty, and replace the wallpaper
        $css = $this->makeSubstitutions([], $styleSheet, '');

        if ($css != '') {
            $headContent .= '<style type="text/css">' . $this->parseLibraryReferences($isPreview, $css) . '</style>';
        }
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

        // Replace the Head Content with our generated javascript
        $data['head'] = $headContent;

        // Add some scripts to the JavaScript Content
        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-cycle-2.1.6.min.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-finance-render.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-image-render.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($renderedItems) . ';';
        $javaScriptContent .= '   var body = ' . json_encode($mainTemplate) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboLayoutScaler(options); $("#content").xiboFinanceRender(options, items, body); $("#content").find("img").xiboImageRender(options); ';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= $javaScript;
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

        return $this->renderTemplate($data);
    }

    /** @inheritdoc */
    public function isValid()
    {
        if ($this->getOption('overrideTemplate') == 0 && ( $this->getOption('templateId') == '' || $this->getOption('templateId') == null))
            throw new InvalidArgumentException(__('Please choose a template'), 'templateId');

        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new InvalidArgumentException(__('Please enter a duration'), 'duration');

        // Validate for the items field
        if ($this->getOption('items') == '')
            throw new InvalidArgumentException(__('Please provide a comma separated list of symbols in the items field.'), 'items');

        return self::$STATUS_VALID;
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        $cachePeriod = $this->getSetting('cachePeriod', 3600);
        $updateInterval = $this->getSetting('updateInterval', 60) * 60;

        return max($cachePeriod, $updateInterval);
    }
}
