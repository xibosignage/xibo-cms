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
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/vendor/bootstrap.min.css')->save();
    }

    /**
     * Loads templates for this module
     */
    private function loadTemplates()
    {
        $this->module->settings['templates'] = [];

        // Scan the folder for template files
        foreach (glob(PROJECT_ROOT . '/modules/currencies/*.template.json') as $template) {
            // Read the contents, json_decode and add to the array
            $this->module->settings['templates'][] = json_decode(file_get_contents($template), true);
        }
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
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new \InvalidArgumentException(__('Please enter a duration'));
    }

    /**
     * Add Media
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
        $this->setOption('overrideTemplate', $this->getSanitizer()->getCheckbox('overrideTemplate'));
        $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 60));
        $this->setOption('templateId', $this->getSanitizer()->getString('templateId'));
        $this->setOption('durationIsPerPage', $this->getSanitizer()->getCheckbox('durationIsPerPage'));
        $this->setOption('widgetOriginalWidth', $this->getSanitizer()->getInt('widgetOriginalWidth'));
        $this->setOption('widgetOriginalHeight', $this->getSanitizer()->getInt('widgetOriginalHeight'));
        $this->setOption('maxItemsPerPage', $this->getSanitizer()->getInt('maxItemsPerPage', 4));
        $this->setRawNode('mainTemplate', $this->getSanitizer()->getParam('mainTemplate', $this->getSanitizer()->getParam('mainTemplate', null)));
        $this->setRawNode('itemTemplate', $this->getSanitizer()->getParam('itemTemplate', $this->getSanitizer()->getParam('itemTemplate', null)));
        $this->setRawNode('styleSheet', $this->getSanitizer()->getParam('styleSheet', $this->getSanitizer()->getParam('styleSheet', null)));
        $this->setRawNode('javaScript', $this->getSanitizer()->getParam('javaScript', ''));
    }

    /**
     * Get YQL Data
     * @return array|bool an array of results according to the key specified by result identifier. false if an invalid value is returned.
     */
    protected function getYql()
    {
        $items = $this->getOption('items');
        $base = $this->getOption('base');
        
        // Use the template to see if we need the xchange or quotes table 
        $useVariation = stripos($this->getRawNode('itemTemplate'), '[ChangePercentage]') > -1;
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
            array_push(
                $itemsJoined, 
                ( $useVariation ) ? ('\'' . trim($base) . trim($item) . '=X' . '\'') : ('\'' . trim($base) . trim($item) . '\'')
            );
        }

        $yql = str_replace('[Item]', implode(',', $itemsJoined), $yql);

        // Fire off a request for the data
        $cache = $this->getPool()->getItem('finance/' . md5($yql));

        $data = $cache->get();

        if ($cache->isMiss()) {

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
                            // Replace the name to have just the second currency
                            if (isset($data['Name']))
                                $replacement = str_replace(($baseCurrency . '/'),'',$data['Name']);
                                
                            break;
                            
                        case 'CurrencyFlag':
                            $currencyCode = str_replace(($baseCurrency . '/'),'',$data['Name']);
                            
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

        // Run through each item and substitute with the template
        $mainTemplate = $this->parseLibraryReferences($isPreview, $this->getRawNode('mainTemplate'));
        $itemTemplate = $this->parseLibraryReferences($isPreview, $this->getRawNode('itemTemplate'));
        
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
            'widgetDesignWidth' => $this->getSanitizer()->int($this->getOption('widgetOriginalWidth')),
            'widgetDesignHeight'=> $this->getSanitizer()->int($this->getOption('widgetOriginalHeight')),
            'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0), 
            'maxItemsPerPage' => $this->getSanitizer()->int($this->getOption('maxItemsPerPage'))
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
            $headContent .= '<style type="text/css"> .container-main, .page, .item { background-color: ' . $backgroundColor . ' }</style>';
        }
        
        // Add the CSS if it isn't empty, and replace the wallpaper
        $css = $this->makeSubstitutions([], $this->getRawNode('styleSheet', null), '');

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

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($renderedItems) . ';';
        $javaScriptContent .= '   var body = ' . json_encode($mainTemplate) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboLayoutScaler(options); $("#content").xiboFinanceRender(options, items, body); ';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= $javaScript;
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

        // Update and save widget if we've changed our assignments.
        if ($this->hasMediaChanged())
            $this->widget->save(['saveWidgetOptions' => false, 'notifyDisplays' => true]);

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
