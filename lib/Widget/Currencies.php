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

use Xibo\Exception\NotFoundException;
use Xibo\Factory\ModuleFactory;

/**
 * Class Currencies
 * @package Xibo\Widget
 */
class Currencies extends YahooBase
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
            $module->name = 'Currencies';
            $module->type = 'currencies';
            $module->class = 'Xibo\Widget\Currencies';
            $module->description = 'Yahoo Currencies';
            $module->imageUri = 'forms/library.gif';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 30;
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
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/xibo-finance-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/xibo-image-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/vendor/bootstrap.min.css')->save();
    }

    /**
     * Form for updating the module settings
     */
    public function settingsForm()
    {
        return 'currencies-form-settings';
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
        if($this->getOption('overrideTemplate') == 0 && ( $this->getOption('templateId') == '' || $this->getOption('templateId') == null) )
            throw new \InvalidArgumentException(__('Please choose a template'));
            
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new \InvalidArgumentException(__('Please enter a duration'));
    }

    /**
     * Adds a Currencies Widget
     * @SWG\Post(
     *  path="/playlist/widget/currencies/{playlistId}",
     *  operationId="WidgetCurrenciesAdd",
     *  tags={"widget"},
     *  summary="Add a Currencies Widget",
     *  description="Add a new Currencies Widget to the specified playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The playlist ID to add a Currencies widget",
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
     *      name="base",
     *      in="formData",
     *      description="The base currency",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="items",
     *      in="formData",
     *      description="Items wanted",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="reverseConversion",
     *      in="formData",
     *      description="(0, 1) Select 1 if you'd like your base currency to be used as the comparison currency you've entered",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="effect",
     *      in="formData",
     *      description="Effect that will be used to transitions between items, available options: fade, fadeout, scrollVert, scollHorz, flipVert, flipHorz, shuffle, tileSlide, tileBlind ",
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
     *      name="durationIsPerPage",
     *      in="formData",
     *      description="A flag (0, 1), The duration specified is per page/item, otherwise the widget duration is divided between the number of pages/items",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="templateId",
     *      in="formData",
     *      description="Use pre-configured templates, available options: currencies1, currencies2",
     *      type="string",
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
     *      name="maxItemsPerPage",
     *      in="formData",
     *      description="This is the intended number of items on each page",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="mainTemplate",
     *      in="formData",
     *      description="Main template, Pass only with overrideTemplate set to 1 ",
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
        $this->setOption('base', $this->getSanitizer()->getString('base', ''));
        $this->setOption('items', $this->getSanitizer()->getString('items'));
        $this->setOption('effect', $this->getSanitizer()->getString('effect'));
        $this->setOption('speed', $this->getSanitizer()->getInt('speed'));
        $this->setOption('backgroundColor', $this->getSanitizer()->getString('backgroundColor'));
        $this->setOption('noRecordsMessage', $this->getSanitizer()->getString('noRecordsMessage'));
        $this->setOption('dateFormat', $this->getSanitizer()->getString('dateFormat'));
        $this->setOption('reverseConversion', $this->getSanitizer()->getCheckbox('reverseConversion'));
        $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 60));
        $this->setOption('templateId', $this->getSanitizer()->getString('templateId'));
        $this->setOption('durationIsPerPage', $this->getSanitizer()->getCheckbox('durationIsPerPage'));
        $this->setRawNode('javaScript', $this->getSanitizer()->getParam('javaScript', ''));
        $this->setOption('overrideTemplate', $this->getSanitizer()->getCheckbox('overrideTemplate'));
        
        if( $this->getOption('overrideTemplate') == 1 ){
            $this->setRawNode('mainTemplate', $this->getSanitizer()->getParam('mainTemplate', $this->getSanitizer()->getParam('mainTemplate', null)));
            $this->setRawNode('itemTemplate', $this->getSanitizer()->getParam('itemTemplate', $this->getSanitizer()->getParam('itemTemplate', null)));
            $this->setRawNode('styleSheet', $this->getSanitizer()->getParam('styleSheet', $this->getSanitizer()->getParam('styleSheet', null)));
            $this->setOption('widgetOriginalWidth', $this->getSanitizer()->getInt('widgetOriginalWidth'));
            $this->setOption('widgetOriginalHeight', $this->getSanitizer()->getInt('widgetOriginalHeight'));
            $this->setOption('maxItemsPerPage', $this->getSanitizer()->getInt('maxItemsPerPage', 4));
        }
    }

    /**
     * Get YQL Data
     * @return array|bool an array of results according to the key specified by result identifier. false if an invalid value is returned.
     */
    protected function getYql()
    {
        
        $reverseConversion = ($this->getOption('reverseConversion', 0) == 1);

        $items = $this->getOption('items');
        $base = $this->getOption('base');
        
        // Get current item template
        if( $this->getOption('overrideTemplate') == 0 ) {
            $template = $this->getTemplateById($this->getOption('templateId'));
            
            if (isset($template)) {
                $itemTemplate = $template['item'];
            }    
        } else {
            $itemTemplate = $this->getRawNode('itemTemplate');
        }
        
        // Use the template to see if we need the xchange or quotes table 
        $useVariation = stripos($itemTemplate, '[ChangePercentage]') > -1;
        if($useVariation){
            $resultIdentifier = "quote";
            $yql = "select * from yahoo.finance.quotes where symbol in ([Item])";
        } else {
            $resultIdentifier = "rate";
            $yql = "select * from yahoo.finance.xchange where pair in ([Item])";
        }
    
        $this->getLog()->debug('Finance module with YQL = . Looking for %s in response', $yql);

        if ($yql == '' || $items == '' || $base == '' ) {
            $this->getLog()->error('Missing YQL/Items for Finance Module with WidgetId %d', $this->getWidgetId());
            return false;
        }

        if (strstr($items, ','))
            $items = explode(',', $items);
        else
            $items = [$items];
            
        // quote each item
        $itemsJoined = array();
        
        foreach ($items as $key => $item) {
            
            // Remove the multiplier if there's one
            $item = explode('|', $item)[0];
            
            $baseItemPair = ( $reverseConversion ) ? ( trim($item) . trim($base) ) : ( trim($base) . trim($item) );
            
            array_push(
                $itemsJoined, 
                ( $useVariation ) ? ('\'' . $baseItemPair . '=X' . '\'') : ('\'' . $baseItemPair . '\'')
            );
        }

        $yql = str_replace('[Item]', implode(',', $itemsJoined), $yql);

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
     * @param $base 
     * @return mixed
     */
    private function makeSubstitutions($data, $source, $baseCurrency)
    {
        // Replace all matches.
        $matches = '';
        preg_match_all('/\[.*?\]/', $source, $matches);
        
        // Get the currencies' items
        $items = $this->getOption('items');
        
        if (strstr($items, ','))
            $items = explode(',', $items);
        else
            $items = [$items];
        
        $reverseConversion = ($this->getOption('reverseConversion', 0) == 1);

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

                    $time = $this->getDate()->getLocalDate($data['time'], $timeSplit[1]);

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
                        case 'NameShort':
                            // Replace the name to have just the second currency (or the first if the currency is reversed)
                            $replaceBase = ( $reverseConversion ) ? ('/' . $baseCurrency) : ($baseCurrency . '/');
                            
                            if (isset($data['Name']))
                                $replacement = trim(str_replace($replaceBase,'',$data['Name']));
                                    
                            break;
                            
                        case 'Multiplier':
                            
                            // Initialize replacement with empty string 
                            $replacement = '';
                            
                            // Get the current currency name/code
                            $pairName = ( $reverseConversion ) ? ('/' . $baseCurrency) : ($baseCurrency . '/');
                            
                            if (isset($data['Name']))
                                $currencyName = trim(str_replace($pairName,'',$data['Name']));
                            
                            // Search for the item that relates to the actual currency
                            foreach ($items as $item) {
                                
                                // Get the item name
                                $itemName = trim(explode('|', $item)[0]);
                                
                                // Compare the item name with the actual currency and test if the inputed value has a multiplier flag
                                if( sizeof(explode('|', $item)) > 1 && strcmp($itemName, $currencyName) == 0 ){
                                    
                                    // Get the multiplier
                                    $replacement = explode('|', $item)[1];
                                }
                            }
                            
                            break;
                            
                        case 'CurrencyFlag':
                            $replaceBase = ( $reverseConversion ) ? ('/' . $baseCurrency) : ($baseCurrency . '/');
                            $currencyCode = str_replace($replaceBase,'',$data['Name']);
                            
                            if (!file_exists(PROJECT_ROOT . '/web/modules/currencies/currency-flags/' . $currencyCode . '.svg')) 
                                $currencyCode = 'default';
                            
                            $file = $this->mediaFactory->createModuleFile('currency_' . $currencyCode, PROJECT_ROOT . '/web/modules/currencies/currency-flags/' . $currencyCode . '.svg');
                            $file->alwaysCopy = true;
                            $file->storedAs = 'currency_' . $currencyCode . '.svg';
                            $file->save();
                            
                            // Tag this layout with this file
                            $this->assignMedia($file->mediaId);
                            
                            $replacement = ($isPreview) ? $this->getResourceUrl('currencies/currency-flags/' . $currencyCode . '.svg') : $file->storedAs;
                            
                            break;
                            
                        case 'ChangePercentage':
                            // Protect against null values
                            if(($data['Change'] == null || $data['LastTradePriceOnly'] == null)){
                                $replacement = "NULL";
                            } else {
                                // Calculate the percentage dividing the change by the ( previous value minus the change )
                                $percentage = $data['Change'] / ( $data['LastTradePriceOnly'] - $data['Change'] );
                                
                                // Convert the value to percentage and round it
                                $replacement = round($percentage*100, 2);
                            }    
                            
                            break;
                            
                        case 'LastTradePriceOnlyValue':
                        case 'BidValue':
                        case 'AskValue':
                            
                            // Get the converted currency name
                            $currencyName = ( $reverseConversion ) ? ('/' . $baseCurrency) : ($baseCurrency . '/');
                            
                            if (isset($data['Name']))
                                $currencyName = trim(str_replace($currencyName, '', $data['Name']));
                            
                            // Get the field's name and set the replacement as the default value from the API
                            $fieldName = str_replace('Value', '', $replace);
                            $replacement = $data[$fieldName];
                                
                            // Search for the item that relates to the actual currency
                            foreach ($items as $item) {
                                
                                // Get the item name
                                $itemName = trim(explode('|', $item)[0]);
                                
                                // Compare the item name with the actual currency and test if the inputed value has a multiplier flag
                                if( sizeof(explode('|', $item)) > 1 && strcmp($itemName, $currencyName) == 0 ){
                                    // Get the multiplier
                                    $multiplier = explode('|', $item)[1];
                                    
                                    // Set the replacement to be the API value times the multiplier
                                    $replacement = $data[$fieldName] * (float)$multiplier;
                                }
                            }        
                            
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
                            $replacement = 'NULL';
                            
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
        $durationIsPerItem = $this->getOption('durationIsPerItem', 1);

        // Generate a JSON string of items.
        if (!$items = $this->getYql()) {
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
        
        $renderedItems = [];
        
        $base = $this->getOption('base');

        foreach ($items as $item) {
            $renderedItems[] = $this->makeSubstitutions($item, $itemTemplate, $base);
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
            'previewWidth' => $this->getSanitizer()->getDouble('width', 0),
            'previewHeight' => $this->getSanitizer()->getDouble('height', 0),
            'widgetDesignWidth' => $widgetOriginalWidth,
            'widgetDesignHeight'=> $widgetOriginalHeight,
            'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0), 
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

        // Update and save widget if we've changed our assignments.
        if ($this->hasMediaChanged())
            $this->widget->save(['saveWidgetOptions' => false, 'notifyDisplays' => true, 'audit' => false]);

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
