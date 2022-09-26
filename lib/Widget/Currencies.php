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
 * Template strings to be translated, that will be used to replace tags in the ||tag|| format
 * __('RATE')
 */
namespace Xibo\Widget;

use Carbon\Carbon;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\DuplicateEntityException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Currencies
 * @package Xibo\Widget
 */
class Currencies extends AlphaVantageBase
{
    public $codeSchemaVersion = 1;

    /**
     * @inheritDoc
     */
    public function installOrUpdate($moduleFactory)
    {
        if ($this->module == null) {
            // Install
            $module = $moduleFactory->createEmpty();
            $module->name = 'Currencies';
            $module->type = 'currencies';
            $module->class = 'Xibo\Widget\Currencies';
            $module->description = 'A module for showing Currency pairs and exchange rates';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 30;
            $module->settings = [];
            $module->installName = 'currencies';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /**
     * @inheritDoc
     */
    public function installFiles()
    {
        // Extends parent's method
        parent::installFiles();

        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-finance-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-image-render.js')->save();
    }


    /**
     * @inheritDoc
     */
    public function layoutDesignerJavaScript()
    {
        return 'currencies-designer-javascript';
    }

    /**
     * @inheritDoc
     */
    public function settingsForm()
    {
        return 'currencies-form-settings';
    }

    /**
     * @inheritDoc
     */
    public function settings(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $apiKey = $sanitizedParams->getString('apiKey');
        $cachePeriod = $sanitizedParams->getInt('cachePeriod', ['default' => 14400]);

        if ($this->module->enabled != 0) {
            if ($apiKey == '') {
                throw new InvalidArgumentException(__('Missing API Key'), 'apiKey');
            }

            if ($cachePeriod < 3600) {
                throw new InvalidArgumentException(__('Cache Period must be 3600 or greater for this Module'), 'cachePeriod');
            }
        }

        $this->module->settings['apiKey'] = $apiKey;
        $this->module->settings['cachePeriod'] = $cachePeriod;

        // Return an array of the processed settings.
        return $response;
    }

    /**
     * Edit Media
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?currencies",
     *  operationId="widgetCurrenciesEdit",
     *  tags={"widget"},
     *  summary="Edit a Currencies Widget",
     *  description="Edit a new Currencies Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
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
     *      name="base",
     *      in="formData",
     *      description="The base currency",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="items",
     *      in="formData",
     *      description="A comma separated list of Currency Acronyms/Abbreviations, e.g. GBP,USD,EUR. For the best results enter no more than 5 items.",
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
     *  @SWG\Parameter(
     *      name="alignH",
     *      in="formData",
     *      description="Horizontal alignment - left, center, bottom",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="alignV",
     *      in="formData",
     *      description="Vertical alignment - top, middle, bottom",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *   )
     * )
     *
     * @inheritDoc
     */
    public function edit(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $this->setDuration($sanitizedParams->getInt('duration', ['default' =>  $this->getDuration()]));
        $this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
        $this->setOption('name', $sanitizedParams->getString('name'));
        $this->setOption('base', $sanitizedParams->getString('base', ['default' => '']));
        $this->setOption('items', $sanitizedParams->getString('items'));
        $this->setOption('effect', $sanitizedParams->getString('effect'));
        $this->setOption('speed', $sanitizedParams->getInt('speed'));
        $this->setOption('backgroundColor', $sanitizedParams->getString('backgroundColor'));
        $this->setOption('noRecordsMessage', $sanitizedParams->getString('noRecordsMessage'));
        $this->setOption('dateFormat', $sanitizedParams->getString('dateFormat', ['defaultOnEmptyString' => true]));
        $this->setOption('reverseConversion', $sanitizedParams->getCheckbox('reverseConversion'));
        $this->setOption('updateInterval', $sanitizedParams->getInt('updateInterval', ['default' => 60]));
        $this->setOption('templateId', $sanitizedParams->getString('templateId'));
        $this->setOption('durationIsPerPage', $sanitizedParams->getCheckbox('durationIsPerPage'));
        $this->setRawNode('javaScript', $request->getParam('javaScript', ''));
        $this->setOption('overrideTemplate', $sanitizedParams->getCheckbox('overrideTemplate'));
        $this->setOption('enableStat', $sanitizedParams->getString('enableStat'));
        $this->setOption('alignH', $sanitizedParams->getString('alignH', ['default' => 'center']));
        $this->setOption('alignV', $sanitizedParams->getString('alignV', ['default' => 'middle']));

        if ($this->getOption('overrideTemplate') == 1) {
            $this->setRawNode('mainTemplate', $request->getParam('mainTemplate', $request->getParam('mainTemplate', null)));
            $this->setRawNode('itemTemplate', $request->getParam('itemTemplate', $request->getParam('itemTemplate', null)));
            $this->setRawNode('styleSheet', $request->getParam('styleSheet', $request->getParam('styleSheet', null)));
            $this->setOption('widgetOriginalWidth', $sanitizedParams->getInt('widgetOriginalWidth'));
            $this->setOption('widgetOriginalHeight', $sanitizedParams->getInt('widgetOriginalHeight'));
            $this->setOption('maxItemsPerPage', $sanitizedParams->getInt('maxItemsPerPage', ['default' => 4]));
        }

        // Save the widget
        $this->isValid();
        $this->saveWidget();

        return $response;
    }

    /**
     * Get FX Results
     *  PLEASE NOTE: This method does not cache results directly as the AlphaVantageBase class handles caching individual
     *  requests.
     *  This request uses a combination of AlphaVantage and Fixer.IO
     * @return array|bool an array of results. false if an invalid value is returned.
     * @throws ConfigurationException
     */
    protected function getResults()
    {
        // Does this require a reversed conversion?
        $reverseConversion = ($this->getOption('reverseConversion', 0) == 1);

        // What items/base currencies are we interested in?
        $items = $this->getOption('items');
        $base = $this->getOption('base');

        if ($items == '' || $base == '') {
            $this->getLog()->error('Missing Items for Currencies Module with WidgetId ' . $this->getWidgetId());
            return false;
        }

        // Parse items out into an array
        $items = array_map('trim', explode(',', $items));
        
        // Get current item template
        $itemTemplate = null;

        if ($this->getOption('overrideTemplate') == 0) {
            $template = $this->getTemplateById($this->getOption('templateId'));
            
            if (isset($template)) {
                $itemTemplate = $template['item'];
            }
        } else {
            $itemTemplate = $this->getRawNode('itemTemplate');
        }
        
        // Does the template require a percentage change calculation.
        $percentageChangeRequested = stripos($itemTemplate, '[ChangePercentage]') > -1;

        $data = [];

        // Each item we want is a call to the results API
        try {
            foreach ($items as $currency) {
                // Remove the multiplier if there's one (this is handled when we substitute the results into
                // the template)
                $currency = explode('|', $currency)[0];

                // Do we need to reverse the from/to currency for this comparison?
                if ($reverseConversion) {
                    $result = $this->getCurrencyExchangeRate($currency, $base);
                } else {
                    $result = $this->getCurrencyExchangeRate($base, $currency);
                }

                $this->getLog()->debug('Results are: ' . var_export($result, true));

                if (!array_key_exists('Meta Data', $result)) {
                    throw new InvalidArgumentException(__('Currency data invalid'), 'Meta Data');
                }

                if (!array_key_exists('Time Series FX (Daily)', $result)) {
                    throw new InvalidArgumentException(__('Currency data invalid'), 'Time Series FX (Daily)');
                }

                $parsedResult = [
                    'time' => $result['Meta Data']['5. Last Refreshed'],
                    'ToName' => $result['Meta Data']['3. To Symbol'],
                    'FromName' => $result['Meta Data']['2. From Symbol'],
                    'Bid' => round(array_values($result['Time Series FX (Daily)'])[0]['1. open'], 4),
                    'Ask' => round(array_values($result['Time Series FX (Daily)'])[0]['1. open'], 4),
                    'LastTradePriceOnly' => round(array_values($result['Time Series FX (Daily)'])[0]['1. open'], 4),
                    'RawLastTradePriceOnly' => array_values($result['Time Series FX (Daily)'])[0]['1. open'],
                    'TimeZone' => $result['Meta Data']['6. Time Zone'],
                ];

                // Set the name/currency to be the full name including the base currency
                $parsedResult['Name'] = $parsedResult['FromName'] . '/' . $parsedResult['ToName'];

                // work out the change when compared to the previous day
                if ($percentageChangeRequested) {
                    // We need to get the prior day for this pair only (reversed)
                    $priorDay = $reverseConversion
                        ? $this->getPriorDay($currency, $base)
                        : $this->getPriorDay($base, $currency);

                    $this->getLog()->debug('Percentage change requested, prior day is '
                        . var_export($priorDay['Time Series FX (Daily)'], true));

                    $priorDay = count($priorDay['Time Series FX (Daily)']) < 2
                        ? ['1. open' => 1]
                        : array_values($priorDay['Time Series FX (Daily)'])[1];

                    $parsedResult['YesterdayTradePriceOnly'] = $priorDay['1. open'];
                    $parsedResult['Change'] = $parsedResult['RawLastTradePriceOnly'] - $parsedResult['YesterdayTradePriceOnly'];
                } else {
                    $parsedResult['YesterdayTradePriceOnly'] = 0;
                    $parsedResult['Change'] = 0;
                }

                // Parse the result and add it to our data array
                $data[] = $parsedResult;
            }
        } catch (ConfigurationException $configurationException) {
            throw $configurationException;
        } catch (GeneralException $requestException) {
            $this->getLog()->error('Problem getting currency information. E = ' . $requestException->getMessage());
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
     * @param $baseCurrency
     * @return mixed
     * @throws ConfigurationException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws DuplicateEntityException
     */
    private function makeSubstitutions($data, $source, $baseCurrency)
    {
        // Replace all matches.
        $matches = '';
        preg_match_all('/\[.*?\]/', $source, $matches);
        
        // Get the currencies' items
        $items = $this->getOption('items');
        
        if (strstr($items, ',')) {
            $items = explode(',', $items);
        } else {
            $items = [$items];
        }

        $reverseConversion = ($this->getOption('reverseConversion', 0) == 1);

        // Substitute
        foreach ($matches[0] as $sub) {
            $replace = str_replace('[', '', str_replace(']', '', $sub));
            $replacement = 'NULL';

            // Match that in the array
            if (isset($data[$replace])) {
                // If the tag exists on the data variables use that var
                $replacement = $data[$replace];
            } else {
                // Custom tags
                
                // Replace the time tag
                if (stripos($replace, 'time|') > -1) {
                    $timeSplit = explode('|', $replace);

                    $time = Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $data['time'])->format($timeSplit[1]);

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
                            $replacement = ($reverseConversion) ? $data['FromName'] : $data['ToName'];
                            break;
                            
                        case 'Multiplier':
                            // Initialize replacement with empty string
                            $replacement = '';
                            
                            // Get the current currency name/code
                            $currencyName = ($reverseConversion) ? $data['FromName'] : $data['ToName'];
                            
                            // Search for the item that relates to the actual currency
                            foreach ($items as $item) {
                                // Get the item name
                                $itemName = trim(explode('|', $item)[0]);
                                
                                // Compare the item name with the actual currency and test if the inputed value has a multiplier flag
                                if (sizeof(explode('|', $item)) > 1 && strcmp($itemName, $currencyName) == 0) {
                                    // Get the multiplier
                                    $replacement = explode('|', $item)[1];
                                }
                            }
                            
                            break;
                            
                        case 'CurrencyFlag':
                            $currencyCode = ($reverseConversion) ? $data['FromName'] : $data['ToName'];
                            
                            if (!file_exists(PROJECT_ROOT . '/modules/currencies/currency-flags/' . $currencyCode . '.svg'))
                                $currencyCode = 'default';
                            
                            $file = $this->mediaFactory->createModuleFile('currency_' . $currencyCode, PROJECT_ROOT . '/modules/currencies/currency-flags/' . $currencyCode . '.svg');
                            $file->alwaysCopy = true;
                            $file->storedAs = 'currency_' . $currencyCode . '.svg';
                            $file->save();
                            
                            // Tag this layout with this file
                            $this->assignMedia($file->mediaId);
                            
                            $replacement = $this->getFileUrl($file);
                            
                            break;
                            
                        case 'LastTradePriceOnlyValue':
                        case 'BidValue':
                        case 'AskValue':
                            
                            // Get the converted currency name
                            $currencyName = ($reverseConversion) ? $data['FromName'] : $data['ToName'];
                            
                            // Get the field's name and set the replacement as the default value from the API
                            $fieldName = str_replace('Value', '', $replace);
                            $replacement = $data[$fieldName];
                                
                            // Search for the item that relates to the actual currency
                            foreach ($items as $item) {
                                
                                // Get the item name
                                $itemName = trim(explode('|', $item)[0]);
                                
                                // Compare the item name with the actual currency and test if the inputed value has a multiplier flag
                                if (sizeof(explode('|', $item)) > 1 && strcmp($itemName, $currencyName) == 0) {
                                    // Get the multiplier
                                    $multiplier = explode('|', $item)[1];
                                    
                                    // Set the replacement to be the API value times the multiplier
                                    $replacement = $data[$fieldName] * (float)$multiplier;
                                }
                            }
                            
                            break;

                        case 'ChangePercentage':
                            // Protect against null values
                            if (($data['Change'] === null || $data['LastTradePriceOnly'] === null)) {
                                $replacement = "NULL";
                            } else {
                                // Calculate the percentage dividing the change by the ( previous value minus the change)
                                $percentage = $data['Change'] / ( $data['LastTradePriceOnly'] - $data['Change'] );

                                // Convert the value to percentage and round it
                                $replacement = round($percentage*100, 2);
                            }

                            break;
                            
                        case 'ChangeStyle':
                            // Default value as no change
                            $replacement = 'value-equal';
                            
                            // Protect against null values
                            if (($data['Change'] != null && $data['LastTradePriceOnly'] != null)) {
                                if ($data['Change'] > 0) {
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
                            if (($data['Change'] != null && $data['LastTradePriceOnly'] != null)) {
                                if ($data['Change'] > 0) {
                                    $replacement = 'up-arrow';
                                } else if ($data['Change'] < 0) {
                                    $replacement = 'down-arrow';
                                }
                            }
                            
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
     * @inheritdoc
     * @param $tab
     * @return array
     * @throws ConfigurationException
     * @throws NotFoundException
     */
    public function getTab($tab)
    {
        if (!$data = $this->getResults()) {
            throw new NotFoundException(__('No data returned, please check error log.'));
        }

        return ['results' => $data[0]];
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        $data = [];

        // Set the null values for template variables.
        $mainTemplate = null;
        $itemTemplate = null;
        $styleSheet = null;
        $widgetOriginalWidth = null;
        $widgetOriginalHeight = null;
        $maxItemsPerPage = null;

        // Replace the View Port Width?
        $data['viewPortWidth'] = $this->isPreview() ? $this->region->width : '[[ViewPortWidth]]';

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
            $widgetOriginalWidth = intval($this->getOption('widgetOriginalWidth'));
            $widgetOriginalHeight = intval($this->getOption('widgetOriginalHeight'));
            $maxItemsPerPage = intval($this->getOption('maxItemsPerPage'));
        }
        
        // Run through each item and substitute with the template
        $mainTemplate = $this->parseLibraryReferences($this->isPreview(), $mainTemplate);
        $itemTemplate = $this->parseLibraryReferences($this->isPreview(), $itemTemplate);

        // Parse translations
        $mainTemplate = $this->parseTranslations($mainTemplate);
        $itemTemplate = $this->parseTranslations($itemTemplate);
        
        $renderedItems = [];
        
        $base = $this->getOption('base');

        foreach ($items as $item) {
            $renderedItems[] = $this->makeSubstitutions($item, $itemTemplate, $base);
        }        

        $options = [
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
            'maxItemsPerPage' => $maxItemsPerPage,
            'alignmentH' => $this->getOption('alignH'),
            'alignmentV' => $this->getOption('alignV')
        ];

        $itemsPerPage = $options['maxItemsPerPage'];
        $pages = count($renderedItems);
        $pages = ($itemsPerPage > 0) ? ceil($pages / $itemsPerPage) : $pages;
        $totalDuration = ($durationIsPerItem == 0) ? $duration : ($duration * $pages);
        
        // Replace and Control Meta options
        $data['controlMeta'] = '<!-- NUMITEMS=' . $pages . ' -->' . PHP_EOL . '<!-- DURATION=' . $totalDuration . ' -->';

        // Get the JavaScript node
        $javaScript = $this->parseLibraryReferences($this->isPreview(), $this->getRawNode('javaScript', ''));

        // Replace the head content
        $headContent = '';

        // Add our fonts.css file
        $headContent .= '<link href="' . ($this->isPreview() ? $this->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">';
        
        $backgroundColor = $this->getOption('backgroundColor');
        if ($backgroundColor != '') {
            $headContent .= '<style type="text/css"> body { background-color: ' . $backgroundColor . ' }</style>';
        } else {
          $headContent .= '<style type="text/css"> body { background-color: transparent }</style>';
        }
        
        // Add the CSS if it isn't empty, and replace the wallpaper
        $css = $this->makeSubstitutions([], $styleSheet, '');

        if ($css != '') {
            $headContent .= '<style type="text/css">' . $this->parseLibraryReferences($this->isPreview(), $css) . '</style>';
        }
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

        // Replace the Head Content with our generated javascript
        $data['head'] = $headContent;

        // Add some scripts to the JavaScript Content
        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery.min.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-cycle-2.1.6.min.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-finance-render.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-image-render.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript">var xiboICTargetId = ' . $this->getWidgetId() . ';</script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-interactive-control.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript">xiboIC.lockAllInteractions();</script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($renderedItems) . ';';
        $javaScriptContent .= '   var body = ' . json_encode($mainTemplate) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboLayoutScaler(options); $("#content").find("img").xiboImageRender(options); ';

        // Run based only if the element is visible or not
        $javaScriptContent .= '       var runOnVisible = function() { $("#content").xiboFinanceRender(options, items, body); }; ';
        $javaScriptContent .= '       (xiboIC.checkVisible()) ? runOnVisible() : xiboIC.addToQueue(runOnVisible); ';

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
        if ($this->getOption('overrideTemplate') == 0
            && ( $this->getOption('templateId') == '' || $this->getOption('templateId') == null)
        ) {
            throw new InvalidArgumentException(__('Please choose a template'), 'templateId');
        }

        if ($this->getUseDuration() == 1 && $this->getDuration() == 0) {
            throw new InvalidArgumentException(__('Please enter a duration'), 'duration');
        }

        // Validate for the items field
        if ($this->getOption('items') == '') {
            throw new InvalidArgumentException(__('Please provide a comma separated list of symbols in the items field.'),
                'items');
        }

        if ($this->getOption('base') == '') {
            throw new InvalidArgumentException(__('Please provide a symbols in the base field.'), 'base');
        }

        return self::$STATUS_VALID;
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        $cachePeriod = $this->getSetting('cachePeriod', 3600);
        $updateInterval = $this->getSetting('updateInterval', 60) * 60;

        return max($cachePeriod, $updateInterval);
    }

    /** @inheritDoc */
    public function hasTemplates()
    {
        return true;
    }

    /** @inheritDoc */
    public function hasHtmlEditor()
    {
        return true;
    }

    /** @inheritDoc */
    public function getHtmlWidgetOptions()
    {
        return ['mainTemplate', 'itemTemplate'];
    }
}
