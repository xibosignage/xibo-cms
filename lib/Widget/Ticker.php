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
 */
namespace Xibo\Widget;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PicoFeed\Config\Config;
use PicoFeed\Logging\Logger;
use PicoFeed\Parser\Item;
use PicoFeed\PicoFeedException;
use PicoFeed\Reader\Reader;
use Respect\Validation\Validator as v;
use Stash\Invalidation;
use Xibo\Controller\Library;
use Xibo\Entity\DataSetColumn;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Helper\Environment;


class Ticker extends ModuleWidget
{
    /**
     * Install Files
     */
    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/moment.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery.marquee.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-cycle-2.1.6.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-text-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-image-render.js')->save();
    }

    /**
     * @return string
     */
    public function layoutDesignerJavaScript()
    {
        // We use the same javascript as the data set view designer
        return 'datasetview-designer-javascript';
    }

    /**
     * DataSets
     * @return array[DataSet]
     */
    public function dataSets()
    {
        return $this->dataSetFactory->query();
    }

    /**
     * Get Data Set Columns
     * @return array[DataSetColumn]
     */
    public function dataSetColumns()
    {
        if ($this->getOption('dataSetId') == 0)
            throw new \InvalidArgumentException(__('DataSet not selected'));

       return $this->dataSetColumnFactory->getByDataSetId($this->getOption('dataSetId'));
    }

    /**
     * Get the Order Clause
     * @return mixed
     */
    public function getOrderClause()
    {
        return json_decode($this->getOption('orderClauses', "[]"), true);
    }

    /**
     * Get the Filter Clause
     * @return mixed
     */
    public function getFilterClause()
    {
        return json_decode($this->getOption('filterClauses', "[]"), true);
    }

    /**
     * Get Extra content for the form
     * @return array
     */
    public function getExtra()
    {
        if ($this->getOption('sourceId') == 2) {
            return [
                'templates' => $this->templatesAvailable(),
                'orderClause' => $this->getOrderClause(),
                'filterClause' => $this->getFilterClause(),
                'columns' => $this->dataSetColumns(),
                'dataSet' => ($this->getOption('dataSetId', 0) != 0) ? $this->dataSetFactory->getById($this->getOption('dataSetId')) : null
            ];
        } else {
            return [
                'templates' => $this->templatesAvailable(),
            ];
        }
    }

    public function validate()
    {
        // Must have a duration
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new \InvalidArgumentException(__('Please enter a duration'));

        $sourceId = $this->getOption('sourceId');

        if ($sourceId == 1) {
            // Feed
            // Validate the URL
            if (!v::url()->notEmpty()->validate(urldecode($this->getOption('uri'))))
                throw new \InvalidArgumentException(__('Please enter a Link for this Ticker'));

        } else if ($sourceId == 2) {
            // DataSet
            // Validate Data Set Selected
            if ($this->getOption('dataSetId') == 0)
                throw new \InvalidArgumentException(__('Please select a DataSet'));

            // Check we have permission to use this DataSetId
            if (!$this->getUser()->checkViewable($this->dataSetFactory->getById($this->getOption('dataSetId'))))
                throw new \InvalidArgumentException(__('You do not have permission to use that dataset'));

            if ($this->widget->widgetId != 0) {
                // Some extra edit validation
                // Make sure we havent entered a silly value in the filter
                if (strstr($this->getOption('filter'), 'DESC'))
                    throw new \InvalidArgumentException(__('Cannot user ordering criteria in the Filter Clause'));

                if (!is_numeric($this->getOption('upperLimit')) || !is_numeric($this->getOption('lowerLimit')))
                    throw new \InvalidArgumentException(__('Limits must be numbers'));

                if ($this->getOption('upperLimit') < 0 || $this->getOption('lowerLimit') < 0)
                    throw new \InvalidArgumentException(__('Limits cannot be lower than 0'));

                // Check the bounds of the limits
                if ($this->getOption('upperLimit') < $this->getOption('lowerLimit'))
                    throw new \InvalidArgumentException(__('Upper limit must be higher than lower limit'));
            }

        } else {
            // Only supported two source types at the moment
            throw new \InvalidArgumentException(__('Unknown Source Type'));
        }

        if ($this->widget->widgetId != 0) {
            // Make sure we have a number in here
            if (!v::numeric()->validate($this->getOption('numItems', 0)))
                throw new \InvalidArgumentException(__('The value in Number of Items must be numeric.'));

            if (!v::intType()->min(0)->validate($this->getOption('updateInterval')))
                throw new \InvalidArgumentException(__('Update Interval must be greater than or equal to 0'));
        }
    }

    /**
     * Adds a Ticker Widget
     * @SWG\Post(
     *  path="/playlist/widget/ticker/{playlistId}",
     *  operationId="WidgetTickerAdd",
     *  tags={"widget"},
     *  summary="Add a ticker Widget",
     *  description="Add a new ticker Widget to the specified playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The playlist ID to add a Widget to",
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
     *      description="The Widget Duration",
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
     *      name="sourceId",
     *      in="formData",
     *      description="Add only - 1 for rss feed, 2 for dataset",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="uri",
     *      in="formData",
     *      description="For sourceId=1, the link for the rss feed",
     *      type="string",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="formData",
     *      description="For sourceId=2, Create ticker Widget using provided dataSetId of an existing dataSet",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="updateInterval",
     *      in="formData",
     *      description="EDIT Only - Update interval in minutes",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="effect",
     *      in="formData",
     *      description="Edit only - Effect that will be used to transitions between items, available options: fade, fadeout, scrollVert, scollHorz, flipVert, flipHorz, shuffle, tileSlide, tileBlind, marqueeUp, marqueeDown, marqueeRight, marqueeLeft",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="speed",
     *      in="formData",
     *      description="Edit only - The transition speed of the selected effect in milliseconds (1000 = normal) or the Marquee speed in a low to high scale (normal = 1)",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="copyright",
     *      in="formData",
     *      description="EDIT Only and SourceId=1 - Copyright information to display as the last item in this feed. can be styled with the #copyright CSS selector",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="numItems",
     *      in="formData",
     *      description="EDIT Only and SourceId=1 - The number of RSS items you want to display",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="takeItemsFrom",
     *      in="formData",
     *      description="EDIT Only and SourceId=1 - Take the items form the beginning or the end of the list, available options: start, end",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="durationIsPerItem",
     *      in="formData",
     *      description="A flag (0, 1), The duration specified is per item, otherwise it is per feed",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="itemsSideBySide",
     *      in="formData",
     *      description="A flag (0, 1), Should items be shown side by side",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="upperLimit",
     *      in="formData",
     *      description="EDIT Only, SourceId=2 - Upper low limit for this dataSet, 0 for nor limit",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="lowerLimit",
     *      in="formData",
     *      description="EDIT Only, SourceId=2 - Lower low limit for this dataSet, 0 for nor limit",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="itemsPerPage",
     *      in="formData",
     *      description="EDIT Only - When in single mode, how many items per page should be shown",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dateFormat",
     *      in="formData",
     *      description="EDIT Only - The date format to apply to all dates returned by the ticker",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="allowedAttributes",
     *      in="formData",
     *      description="EDIT Only and SourceId=1 - A comma separated list of attributes that should not be stripped from the feed",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="stripTags",
     *      in="formData",
     *      description="EDIT Only and SourceId=1 - A comma separated list of attributes that should be stripped from the feed",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="backgroundColor",
     *      in="formData",
     *      description="Edit only - A HEX color to use as the background color of this widget",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="disableDateSort",
     *      in="formData",
     *      description="EDIT Only, SourceId=1 - Should the date sort applied to the feed be disabled?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="textDirection",
     *      in="formData",
     *      description="EDIT Only, SourceId=1 - Which direction does the text in the feed use? Available options: ltr, rtl",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="noDataMessage",
     *      in="formData",
     *      description="EDIT Only - A message to display when no data is returned from the source",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="templateId",
     *      in="formData",
     *      description="EDIT Only, SourceId=1 - Template you'd like to apply, options available: title-only, prominent-title-with-desc-and-name-separator, media-rss-with-title, media-rss-wth-left-hand-text, media-rss-image-only",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="overrideTemplate",
     *      in="formData",
     *      description="EDIT Only, SourceId=1 - flag (0, 1) override template checkbox",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="template",
     *      in="formData",
     *      description="Template for each item, replaces [itemsTemplate] in main template, Pass only with overrideTemplate set to 1 or with sourceId=2 ",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="css",
     *      in="formData",
     *      description="Optional StyleSheet Pass only with overrideTemplate set to 1 or with sourceId=2 ",
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
     *      name="filter",
     *      in="formData",
     *      description="EDIT Only, SourceId=2 - SQL clause for filter this dataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ordering",
     *      in="formData",
     *      description="EDIT Only, SourceId=2- SQL clause for how this dataSet should be ordered",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="useOrderingClause",
     *      in="formData",
     *      description="EDIT Only, SourceId=2 - flag (0,1) Use advanced order clause - set to 1 if ordering is provided",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="useFilteringClause",
     *      in="formData",
     *      description="EDIT Only, SourceId=2 - flag (0,1) Use advanced filter clause - set to 1 if filter is provided",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="randomiseItems",
     *      in="formData",
     *      description="A flag (0, 1), whether to randomise the feed",
     *      type="integer",
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
        $this->setOption('xmds', true);
        $this->setOption('sourceId', $this->getSanitizer()->getInt('sourceId'));
        $this->setOption('uri', urlencode($this->getSanitizer()->getString('uri')));
        $this->setOption('durationIsPerItem', 1);
        $this->setOption('updateInterval', 120);
        $this->setOption('speed', 2);

        if ($this->getOption('sourceId') == 2)
            $this->setOption('dataSetId', $this->getSanitizer()->getInt('dataSetId', 0));

        // New tickers have template override set to 0 by add.
        // the edit form can then default to 1 when the element doesn't exist (for legacy)
        $this->setOption('overrideTemplate', 0);

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Edit Media
     */
    public function edit()
    {
        // Source is selected during add() and cannot be edited.
        // Other properties
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setOption('xmds', true);
        $this->setOption('uri', urlencode($this->getSanitizer()->getString('uri')));
        $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 120));
        $this->setOption('speed', $this->getSanitizer()->getInt('speed', 2));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('effect', $this->getSanitizer()->getString('effect'));
        $this->setOption('copyright', $this->getSanitizer()->getString('copyright'));
        $this->setOption('numItems', $this->getSanitizer()->getInt('numItems'));
        $this->setOption('takeItemsFrom', $this->getSanitizer()->getString('takeItemsFrom'));
        $this->setOption('durationIsPerItem', $this->getSanitizer()->getCheckbox('durationIsPerItem'));
        $this->setOption('randomiseItems', $this->getSanitizer()->getCheckbox('randomiseItems'));
        $this->setOption('itemsSideBySide', $this->getSanitizer()->getCheckbox('itemsSideBySide'));
        $this->setOption('upperLimit', $this->getSanitizer()->getInt('upperLimit', 0));
        $this->setOption('lowerLimit', $this->getSanitizer()->getInt('lowerLimit', 0));

        $this->setOption('itemsPerPage', $this->getSanitizer()->getInt('itemsPerPage'));
        $this->setOption('dateFormat', $this->getSanitizer()->getString('dateFormat'));
        $this->setOption('allowedAttributes', $this->getSanitizer()->getString('allowedAttributes'));
        $this->setOption('stripTags', $this->getSanitizer()->getString('stripTags'));
        $this->setOption('backgroundColor', $this->getSanitizer()->getString('backgroundColor'));
        $this->setOption('disableDateSort', $this->getSanitizer()->getCheckbox('disableDateSort'));
        $this->setOption('textDirection', $this->getSanitizer()->getString('textDirection'));
        $this->setOption('overrideTemplate', $this->getSanitizer()->getCheckbox('overrideTemplate'));
        $this->setOption('templateId', $this->getSanitizer()->getString('templateId'));
        $this->setRawNode('noDataMessage', $this->getSanitizer()->getParam('noDataMessage', ''));
        $this->setRawNode('javaScript', $this->getSanitizer()->getParam('javaScript', ''));

        // DataSet
        if ($this->getOption('sourceId') == 2) {
            // We are a data set, so get the custom filter controls
            $this->setOption('filter', $this->getSanitizer()->getParam('filter', null));
            $this->setOption('ordering', $this->getSanitizer()->getString('ordering'));
            $this->setOption('useOrderingClause', $this->getSanitizer()->getCheckbox('useOrderingClause'));
            $this->setOption('useFilteringClause', $this->getSanitizer()->getCheckbox('useFilteringClause'));

            // Order and Filter criteria
            $orderClauses = $this->getSanitizer()->getStringArray('orderClause');
            $orderClauseDirections = $this->getSanitizer()->getStringArray('orderClauseDirection');
            $orderClauseMapping = [];

            $i = -1;
            foreach ($orderClauses as $orderClause) {
                $i++;

                if ($orderClause == '')
                    continue;

                // Map the stop code received to the stop ref (if there is one)
                $orderClauseMapping[] = [
                    'orderClause' => $orderClause,
                    'orderClauseDirection' => isset($orderClauseDirections[$i]) ? $orderClauseDirections[$i] : '',
                ];
            }

            $this->setOption('orderClauses', json_encode($orderClauseMapping));

            $filterClauses = $this->getSanitizer()->getStringArray('filterClause');
            $filterClauseOperator = $this->getSanitizer()->getStringArray('filterClauseOperator');
            $filterClauseCriteria = $this->getSanitizer()->getStringArray('filterClauseCriteria');
            $filterClauseValue = $this->getSanitizer()->getStringArray('filterClauseValue');
            $filterClauseMapping = [];

            $i = -1;
            foreach ($filterClauses as $filterClause) {
                $i++;

                if ($filterClause == '')
                    continue;

                // Map the stop code received to the stop ref (if there is one)
                $filterClauseMapping[] = [
                    'filterClause' => $filterClause,
                    'filterClauseOperator' => isset($filterClauseOperator[$i]) ? $filterClauseOperator[$i] : '',
                    'filterClauseCriteria' => isset($filterClauseCriteria[$i]) ? $filterClauseCriteria[$i] : '',
                    'filterClauseValue' => isset($filterClauseValue[$i]) ? $filterClauseValue[$i] : '',
                ];
            }

            $this->setOption('filterClauses', json_encode($filterClauseMapping));

            // DataSet Tickers always have Templates provided.
            $this->setRawNode('template', $this->getSanitizer()->getParam('ta_text', $this->getSanitizer()->getParam('template', null)));
            $this->setRawNode('css', $this->getSanitizer()->getParam('ta_css', $this->getSanitizer()->getParam('css', null)));

        } else if ($this->getOption('overrideTemplate') == 1) {
            // Feed tickers should only use the template if they have override set.
            $this->setRawNode('template', $this->getSanitizer()->getParam('ta_text', $this->getSanitizer()->getParam('template', null)));
            $this->setRawNode('css', $this->getSanitizer()->getParam('ta_css', $this->getSanitizer()->getParam('css', null)));
        }
        
        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * @inheritdoc
     */
    public function hoverPreview()
    {
        $name = $this->getOption('name');
        $url = urldecode($this->getOption('uri'));
        $sourceId = $this->getOption('sourceId', 1);

        // Default Hover window contains a thumbnail, media type and duration
        $output = '<div class="thumbnail"><img alt="' . $this->module->name . ' thumbnail" src="' . $this->getConfig()->uri('img/forms/' . $this->getModuleType() . '.gif') . '"></div>';
        $output .= '<div class="info">';
        $output .= '    <ul>';
        $output .= '    <li>' . __('Type') . ': ' . $this->module->name . '</li>';
        $output .= '    <li>' . __('Name') . ': ' . $name . '</li>';

        if ($sourceId == 2) {
            // Get the DataSet name
            try {
                $dataSet = $this->dataSetFactory->getById($this->getOption('dataSetId'));

                $output .= '    <li>' . __('Source: DataSet named "%s".', $dataSet->dataSet) . '</li>';
            } catch (NotFoundException $notFoundException) {
                $this->getLog()->error('Layout Widget without a DataSet. widgetId: ' . $this->getWidgetId());
                $output .= '    <li>' . __('Warning: No DataSet found.') . '</li>';
            }
        }
        else
            $output .= '    <li>' . __('Source') . ': <a href="' . $url . '" target="_blank" title="' . __('Source') . '">' . $url . '</a></li>';


        $output .= '    <li>' . __('Duration') . ': ' . $this->getDuration() . ' ' . __('seconds') . '</li>';
        $output .= '    </ul>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Get Resource
     * @param int $displayId
     * @return mixed
     * @throws XiboException
     */
    public function getResource($displayId = 0)
    {
        // Load in the template
        $data = [];
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        // What is the data source for this ticker?
        $sourceId = $this->getOption('sourceId', 1);

        // Information from the Module
        $itemsSideBySide = $this->getOption('itemsSideBySide', 0);
        $duration = $this->getCalculatedDurationForGetResource();
        $durationIsPerItem = $this->getOption('durationIsPerItem', 1);
        $numItems = $this->getOption('numItems', 0);
        $takeItemsFrom = $this->getOption('takeItemsFrom', 'start');
        $itemsPerPage = $this->getOption('itemsPerPage', 0);

        // Text/CSS subsitution variables.
        $text = null;
        $css = null;

        // Get CSS and HTML template from the original template or from the input field
        if ($sourceId != 2 && $this->getOption('overrideTemplate') == 0) {
            // Feed tickers without override set.
            $template = $this->getTemplateById($this->getOption('templateId', 'title-only'));
            
            if (isset($template)) {
                $text = $template['template'];
                $css = $template['css'];
            } else {
                $text = $this->getRawNode('template', '');
                $css = $this->getRawNode('css', '');
            }
        } else {
            // DataSet tickers or feed tickers without overrides.
            $text = $this->getRawNode('template', '');
            $css = $this->getRawNode('css', '');
        }
        
        // Parse library references on the template
        $text = $this->parseLibraryReferences($isPreview, $text);

        // Parse library references on the CSS Node
        $css = $this->parseLibraryReferences($isPreview, $css);

        // Get the JavaScript node
        $javaScript = $this->parseLibraryReferences($isPreview, $this->getRawNode('javaScript', ''));

        // Handle older layouts that have a direction node but no effect node
        $oldDirection = $this->getOption('direction', 'none');

        if ($oldDirection == 'single')
            $oldDirection = 'noTransition';
        else if ($oldDirection != 'none')
            $oldDirection = 'marquee' . ucfirst($oldDirection);

        $effect = $this->getOption('effect', $oldDirection);

        $options = array(
            'type' => $this->getModuleType(),
            'fx' => $effect,
            'duration' => $duration,
            'durationIsPerItem' => (($durationIsPerItem == 0) ? false : true),
            'numItems' => $numItems,
            'takeItemsFrom' => $takeItemsFrom,
            'itemsPerPage' => $itemsPerPage,
            'randomiseItems' => $this->getOption('randomiseItems', 0),
            'speed' => $this->getOption('speed', 1000),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'previewWidth' => $this->getSanitizer()->getDouble('width', 0),
            'previewHeight' => $this->getSanitizer()->getDouble('height', 0),
            'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0)
        );

        // Generate a JSON string of substituted items.
        if ($sourceId == 2) {
            $items = $this->getDataSetItems($displayId, $isPreview, $text);
        } else {
            $items = $this->getRssItems($isPreview, $text);
        }

        // Return empty string if there are no items to show.
        if (count($items) == 0) {
            // Do we have a no-data message to display?
            $noDataMessage = $this->getRawNode('noDataMessage');

            if ($noDataMessage != '') {
                $items[] = $noDataMessage;
            } else {
                $this->getLog()->info('Request failed for dataSet id=%d. Widget=%d. Due to No Records Found', $this->getOption('dataSetId'), $this->getWidgetId());
                return '';
            }
        }

        // Work out how many pages we will be showing.
        $pages = $numItems;
        if ($numItems > count($items) || $numItems == 0)
            $pages = count($items);

        $pages = ($itemsPerPage > 0) ? ceil($pages / $itemsPerPage) : $pages;
        $totalDuration = ($durationIsPerItem == 0) ? $duration : ($duration * $pages);

        // Replace and Control Meta options
        $data['controlMeta'] = '<!-- NUMITEMS=' . $pages . ' -->' . PHP_EOL . '<!-- DURATION=' . $totalDuration . ' -->';   
        // Replace the head content
        $headContent = '';
        
        if ($itemsSideBySide == 1) {
            $headContent .= '<style type="text/css">';
            $headContent .= ' .item, .page { float: left; }';
            $headContent .= '</style>';
        }

        if ($this->getOption('textDirection') == 'rtl') {
            $headContent .= '<style type="text/css">';
            $headContent .= ' #content { direction: rtl; }';
            $headContent .= '</style>';
        }

        if ($this->getOption('backgroundColor') != '') {
            $headContent .= '<style type="text/css">';
            $headContent .= ' body { background-color: ' . $this->getOption('backgroundColor') . '; }';
            $headContent .= '</style>';
        }

        // Add the CSS if it isn't empty
        if ($css != '') {
            $headContent .= '<style type="text/css">' . $css . '</style>';
        }

        // Add our fonts.css file
        $headContent .= '<link href="' . (($isPreview) ? $this->getApp()->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';

        // Replace the Head Content with our generated javascript
        $data['head'] = $headContent;

        // Add some scripts to the JavaScript Content
        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';

        // Need the marquee plugin?
        if (stripos($effect, 'marquee') !== false)
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery.marquee.min.js') . '"></script>';

        // Need the cycle plugin?
        if ($effect != 'none')
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-cycle-2.1.6.min.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-text-render.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-image-render.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   var items = ' . json_encode($items) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("body").xiboLayoutScaler(options); $("#content").xiboTextRender(options, items); $("#content").find("img").xiboImageRender(options); ';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= $javaScript;
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

        return $this->renderTemplate($data);
    }

    /**
     * @param $isPreview
     * @param $text
     * @return array|mixed|null
     * @throws \Xibo\Exception\ConfigurationException
     */
    private function getRssItems($isPreview, $text)
    {
        // Make sure we have the cache location configured
        Library::ensureLibraryExists($this->getConfig()->GetSetting('LIBRARY_LOCATION'));

        // Create a key to use as a caching key for this item.
        // the rendered feed will be cached, so it is important the key covers all options.
        $feedUrl = urldecode($this->getOption('uri'));

        /** @var \Stash\Item $cache */
        $cache = $this->getPool()->getItem($this->makeCacheKey(md5($feedUrl)));
        $cache->setInvalidationMethod(Invalidation::SLEEP, 5000, 15);

        $this->getLog()->debug('Ticker with RSS source ' . $feedUrl . '. Cache key: ' . $cache->getKey());

        // Get the document out of the cache
        $document = $cache->get();

        // Check our cache to see if the key exists
        if ($cache->isMiss()) {
            // Invalid local cache, requery using picofeed.
            $this->getLog()->debug('Cache Miss, getting RSS items');

            // Lock this cache item (120 seconds)
            $cache->lock(120);

            try {
                // Create a Guzzle Client to get the Feed XML
                $client = new Client();
                $response = $client->get($feedUrl, $this->getConfig()->getGuzzleProxy([
                    'headers' => [
                        'Accept' => 'application/rss+xml, application/rdf+xml;q=0.8, application/atom+xml;q=0.6, application/xml;q=0.4, text/xml;q=0.4, text/html;q=0.2, text/*;q=0.1'
                    ],
                    'timeout' => 20 // wait no more than 20 seconds: https://github.com/xibosignage/xibo/issues/1401
                ]));

                // Pull out the content type
                $contentType = $response->getHeaderLine('Content-Type');

                $this->getLog()->debug('Feed returned content-type ' . $contentType);

                // https://github.com/xibosignage/xibo/issues/1401
                if (stripos($contentType, 'rss') === false && stripos($contentType, 'xml') === false && stripos($contentType, 'text') === false && stripos($contentType, 'html') === false) {
                    // The content type isn't compatible
                    $this->getLog()->error('Incompatible content type: ' . $contentType);
                    return false;
                }

                // Get the body, etc
                $result = explode('charset=', $contentType);
                $document['encoding'] = isset($result[1]) ? $result[1] : '';
                $document['xml'] = $response->getBody()->getContents();

                // Add this to the cache.
                $cache->set($document);
                $cache->expiresAfter($this->getOption('updateInterval', 360) * 60);

                // Save
                $this->getPool()->saveDeferred($cache);

                $this->getLog()->debug('Processed feed and added to the cache for ' . $this->getOption('updateInterval', 360) . ' minutes');

            } catch (RequestException $requestException) {
                // Log and return empty?
                $this->getLog()->error('Unable to get feed: ' . $requestException->getMessage());
                $this->getLog()->debug($requestException->getTraceAsString());

                return false;
            }
        }

        // Cache HIT or we've requested
        // Load the feed XML document into a feed parser
        $picoFeedLoggingEnabled = Environment::isDevMode();

        try {
            // Enable logging if we need to
            if ($picoFeedLoggingEnabled) {
                $this->getLog()->debug('Setting Picofeed Logger to Enabled.');
                Logger::enable();
            }

            // Allowable attributes
            $clientConfig = new Config();

            // need a sensible way to set this
            // https://github.com/fguillot/picoFeed/issues/196
            //if ($this->getOption('allowedAttributes') != null) {
                //$clientConfig->setFilterWhitelistedTags(explode(',', $this->getOption('allowedAttributes')));
            //}

            // Get the feed parser
            $reader = new Reader($clientConfig);
            $parser = $reader->getParser($feedUrl, $document['xml'], $document['encoding']);

            // Get a feed object
            $feed = $parser->execute();

            // Get all items
            $feedItems = $feed->getItems();

        } catch (PicoFeedException $picoFeedException) {
            // Output any PicoFeed logs
            if ($picoFeedLoggingEnabled) {
                $this->getLog()->debug('Outputting Picofeed Logs.');
                foreach (Logger::getMessages() as $message) {
                    $this->getLog()->debug($message);
                }
            }

            // Log and return empty?
            $this->getLog()->error('Unable to parse feed: ' . $picoFeedException->getMessage());
            $this->getLog()->debug($picoFeedException->getTraceAsString());
            return false;
        }

        // Output any PicoFeed logs
        if ($picoFeedLoggingEnabled) {
            foreach (Logger::getMessages() as $message) {
                $this->getLog()->debug($message);
            }
        }

        // Parse the text template
        $matches = '';
        preg_match_all('/\[.*?\]/', $text, $matches);

        // Disable date sorting?
        if ($this->getOption('disableDateSort') == 0 && $this->getOption('randomiseItems', 0) == 0) {
            // Sort the items array by date
            usort($feedItems, function($a, $b) {
                /* @var Item $a */
                /* @var Item $b */

                return ($a->getDate() < $b->getDate());
            });
        }

        // Date format for the feed items
        $dateFormat = $this->getOption('dateFormat', $this->getConfig()->GetSetting('DATE_FORMAT'));

        // Set an expiry time for the media
        $expires = $this->getDate()->parse()->addMinutes($this->getOption('updateInterval', 3600))->format('U');

        // Render the content now
        foreach ($feedItems as $item) {
            /* @var Item $item */

            // Substitute for all matches in the template
            $rowString = $text;

            // Run through all [] substitutes in $matches
            foreach ($matches[0] as $sub) {
                $replace = '';

                // Does our [] have a | - if so we need to do some special parsing
                if (strstr($sub, '|') !== false) {
                    // Use the provided name space to extract a tag
                    $attribute = NULL;
                    // Do we have more than 1 | - if we do then we are also interested in getting an attribute
                    if (substr_count($sub, '|') > 1)
                        list($tag, $namespace, $attribute) = explode('|', $sub);
                    else
                        list($tag, $namespace) = explode('|', $sub);

                    // Replace some things so that we know what we are looking at
                    $tag = str_replace('[', '', $tag);
                    $namespace = str_replace(']', '', $namespace);

                    if ($attribute !== null)
                        $attribute = str_replace(']', '', $attribute);

                    // What are we looking at
                    $this->getLog()->debug('Namespace: ' . $namespace . ', Tag: ' . $tag . ', Attribute: ' . $attribute);
                    //$this->getLog()->debug('Item content: %s', var_export($item, true));

                    // Are we an image place holder? [tag|image]
                    if ($namespace == 'image') {
                        // Try to get a link for the image
                        $link = null;

                        switch ($tag) {
                            case 'Link':
                                if (stripos($item->getEnclosureType(), 'image') > -1) {
                                    // Use the link to get the image
                                    $link = $item->getEnclosureUrl();

                                    if (empty($link)) {
                                        $this->getLog()->debug('No image found for Link|image tag using getEnclosureUrl');
                                    }
                                } else {
                                    $this->getLog()->debug('No image found for Link|image tag using getEnclosureType');
                                }
                                break;

                            default:
                                // Default behaviour just tries to get the content from the tag provided.
                                // it uses the attribute as a namespace if one has been provided
                                if ($attribute != null)
                                    $tags = $item->getTag($tag, $attribute);
                                else
                                    $tags = $item->getTag($tag);

                                if (count($tags) > 0 && !empty($tags[0]))
                                    $link = $tags[0];
                                else
                                    $this->getLog()->debug('Tag not found for [' . $tag . '] attribute [' . $attribute . ']');
                        }

                        $this->getLog()->debug('Resolved link: ' . $link);

                        // If we have managed to resolve a link, download it and replace the tag with the downloaded
                        // image url
                        if ($link != NULL) {
                            // Grab the profile image
                            $file = $this->mediaFactory->queueDownload('ticker_' . md5($this->getOption('url') . $link), $link, $expires);

                            $replace = '<img src="' . $this->getFileUrl($file, 'image') . '" ' . $attribute . ' />';
                        }
                    } else {
                        // Our namespace is not "image". Which means we are a normal text substitution using a namespace/attribute
                        if ($attribute != null)
                            $tags = $item->getTag($tag, $attribute);
                        else
                            $tags = $item->getTag($tag);

                        // If we find some tags then do the business with them
                        if ($tags != NULL && count($tags) > 0) {
                            $replace = $tags[0];
                        } else {
                            $this->getLog()->debug('Tag not found for ' . $tag . ' attribute ' . $attribute);
                        }
                    }
                } else {
                    // Use the pool of standard tags
                    switch ($sub) {
                        case '[Name]':
                            $replace = $this->getOption('name');
                            break;

                        case '[Title]':
                            $replace = $item->getTitle();
                            break;

                        case '[Description]':
                            // Try to get the description tag
                            if (!$desc = $item->getTag('description')) {
                                // use content with tags stripped
                                $replace = strip_tags($item->getContent());
                            } else {
                                // use description
                                $replace = $desc[0];
                            }
                            break;

                        case '[Content]':
                            $replace = $item->getContent();
                            break;

                        case '[Copyright]':
                            $replace = $item->getAuthor();
                            break;

                        case '[Date]':
                            $replace = $this->getDate()->getLocalDate($item->getDate()->format('U'), $dateFormat);
                            break;

                        case '[PermaLink]':
                            $replace = $item->getTag('permalink');
                            break;

                        case '[Link]':
                            $replace = $item->getUrl();
                            break;

                        case '[Image]':
                            if (stripos($item->getEnclosureType(), 'image') > -1) {
                                // Use the link to get the image
                                $link = $item->getEnclosureUrl();

                                if (!(empty($link))) {
                                    // Grab the image
                                    $file = $this->mediaFactory->queueDownload('ticker_' . md5($this->getOption('url') . $link), $link, $expires);

                                    $replace = '<img src="' . $this->getFileUrl($file, 'image') . '"/>';
                                } else {
                                    $this->getLog()->debug('No image found for image tag using getEnclosureUrl');
                                }
                            } else {
                                $this->getLog()->debug('No image found for image tag using getEnclosureType');
                            }
                            break;
                    }
                }

                if ($this->getOption('stripTags') != '') {
                    // Handle cache path for HTML serializer
                    $cachePath = $this->getConfig()->GetSetting('LIBRARY_LOCATION') . 'cache/HTMLPurifier';
                    if (!is_dir($cachePath))
                        mkdir($cachePath);

                    $config = \HTMLPurifier_Config::createDefault();
                    $config->set('Cache.SerializerPath', $cachePath);
                    $config->set('HTML.ForbiddenElements', explode(',', $this->getOption('stripTags')));
                    $purifier = new \HTMLPurifier($config);
                    $replace = $purifier->purify($replace);
                }

                // Substitute the replacement we have found (it might be '')
                $rowString = str_replace($sub, $replace, $rowString);
            }

            $items[] = $rowString;
        }

        // Process queued downloads
        $this->mediaFactory->processDownloads(function($media) {
            // Success
            $this->getLog()->debug((($media->isSaveRequired) ? 'Successfully downloaded ' : 'Download not required for ') . $media->mediaId);

            // Tag this layout with this file
            $this->assignMedia($media->mediaId);
        });

        // Copyright information?
        if ($this->getOption('copyright', '') != '') {
            $items[] = '<span id="copyright">' . $this->getOption('copyright') . '</span>';
        }

        return $items;
    }

    /**
     * @param $displayId
     * @param $isPreview
     * @param $text
     * @return array
     */
    private function getDataSetItems($displayId, $isPreview, $text)
    {
        // Extra fields for data sets
        $dataSetId = $this->getOption('dataSetId');
        $upperLimit = $this->getOption('upperLimit');
        $lowerLimit = $this->getOption('lowerLimit');

        // Ordering
        $ordering = '';

        if ($this->getOption('useOrderingClause', 1) == 1) {
            $ordering = $this->GetOption('ordering');
        } else {
            // Build an order string
            foreach (json_decode($this->getOption('orderClauses', '[]'), true) as $clause) {
                $ordering .= $clause['orderClause'] . ' ' . $clause['orderClauseDirection'] . ',';
            }

            $ordering = rtrim($ordering, ',');
        }

        // Filtering
        $filter = '';

        if ($this->getOption('useFilteringClause', 1) == 1) {
            $filter = $this->GetOption('filter');
        } else {
            // Build
            $i = 0;
            foreach (json_decode($this->getOption('filterClauses', '[]'), true) as $clause) {
                $i++;
                $criteria = '';

                switch ($clause['filterClauseCriteria']) {

                    case 'starts-with':
                        $criteria = 'LIKE \'' . $clause['filterClauseValue'] . '%\'';
                        break;

                    case 'ends-with':
                        $criteria = 'LIKE \'%' . $clause['filterClauseValue'] . '\'';
                        break;

                    case 'contains':
                        $criteria = 'LIKE \'%' . $clause['filterClauseValue'] . '%\'';
                        break;

                    case 'equals':
                        $criteria = '= \'' . $clause['filterClauseValue'] . '\'';
                        break;

                    case 'not-contains':
                        $criteria = 'NOT LIKE \'%' . $clause['filterClauseValue'] . '%\'';
                        break;

                    case 'not-starts-with':
                        $criteria = 'NOT LIKE \'' . $clause['filterClauseValue'] . '%\'';
                        break;

                    case 'not-ends-with':
                        $criteria = 'NOT LIKE \'%' . $clause['filterClauseValue'] . '\'';
                        break;

                    case 'not-equals':
                        $criteria = '<> \'' . $clause['filterClauseValue'] . '\'';
                        break;

                    case 'greater-than':
                        $criteria = '> \'' . $clause['filterClauseValue'] . '\'';
                        break;

                    case 'less-than':
                        $criteria = '< \'' . $clause['filterClauseValue'] . '\'';
                        break;

                    default:
                        continue;
                }

                if ($i > 1)
                    $filter .= ' ' . $clause['filterClauseOperator'] . ' ';

                $filter .= $clause['filterClause'] . ' ' . $criteria;
            }
        }

        $this->getLog()->notice('Then template for each row is: ' . $text);

        // Set an expiry time for the media
        $expires = time() + ($this->getOption('updateInterval', 3600) * 60);

        // Combine the column id's with the dataset data
        $matches = '';
        preg_match_all('/\[(.*?)\]/', $text, $matches);

        $columnIds = array();

        foreach ($matches[1] as $match) {
            // Get the column id's we are interested in
            $this->getLog()->notice('Matched column: ' . $match);

            $col = explode('|', $match);
            $columnIds[] = $col[1];
        }

        // Create a data set object, to get the results.
        try {
            $dataSet = $this->dataSetFactory->getById($dataSetId);

            // Get an array representing the id->heading mappings
            $mappings = [];
            foreach ($columnIds as $dataSetColumnId) {
                // Get the column definition this represents
                $column = $dataSet->getColumn($dataSetColumnId);
                /* @var DataSetColumn $column */

                $mappings[$column->heading] = [
                    'dataSetColumnId' => $dataSetColumnId,
                    'heading' => $column->heading,
                    'dataTypeId' => $column->dataTypeId
                ];
            }

            $this->getLog()->debug('Resolved column mappings: %s', json_encode($columnIds));

            $filter = [
                'filter' => $filter,
                'order' => $ordering,
                'displayId' => $displayId
            ];

            // limits?
            if ($lowerLimit != 0 || $upperLimit != 0) {
                // Start should be the lower limit
                // Size should be the distance between upper and lower
                $filter['start'] = $lowerLimit;
                $filter['size'] = $upperLimit - $lowerLimit;
            }

            // Set the timezone for SQL
            $dateNow = $this->getDate()->parse();
            if ($displayId != 0) {
                $display = $this->displayFactory->getById($displayId);
                $timeZone = $display->getSetting('displayTimeZone', '');
                $timeZone = ($timeZone == '') ? $this->getConfig()->GetSetting('defaultTimezone') : $timeZone;
                $dateNow->timezone($timeZone);
                $this->getLog()->debug('Display Timezone Resolved: %s. Time: %s.', $timeZone, $dateNow->toDateTimeString());
            }

            $this->getStore()->setTimeZone($this->getDate()->getLocalDate($dateNow, 'P'));

            // Get the data (complete table, filtered)
            $dataSetResults = $dataSet->getData($filter);

            if (count($dataSetResults) <= 0)
                throw new NotFoundException(__('Empty Result Set with filter criteria.'));

            $items = array();

            foreach ($dataSetResults as $row) {
                // For each row, substitute into our template
                $rowString = $text;

                foreach ($matches[1] as $sub) {
                    // Pick the appropriate column out
                    $subs = explode('|', $sub);

                    // The column header
                    $header = $subs[0];
                    $replace = $row[$header];

                    // If the value is empty, then move on
                    if ($replace != '') {
                        // Check in the columns array to see if this is a special one
                        if ($mappings[$header]['dataTypeId'] == 4) {
                            // External Image
                            // Download the image, alter the replace to wrap in an image tag
                            $file = $this->mediaFactory->queueDownload('ticker_dataset_' . md5($dataSetId . $mappings[$header]['dataSetColumnId'] . $replace), str_replace(' ', '%20', htmlspecialchars_decode($replace)), $expires);

                            $replace = '<img src="' . $this->getFileUrl($file, 'image') . '"/>';

                        } else if ($mappings[$header]['dataTypeId'] == 5) {
                            // Library Image
                            // The content is the ID of the image
                            try {
                                if ($replace !== 0) {
                                    $file = $this->mediaFactory->getById($replace);

                                    // Tag this layout with this file
                                    $this->assignMedia($file->mediaId);

                                    $replace = '<img src="' . $this->getFileUrl($file, 'image') . '" />';
                                } else {
                                    $replace = '';
                                }
                            }
                            catch (NotFoundException $e) {
                                $this->getLog()->error('Library Image [%s] not found in DataSetId %d.', $replace, $dataSetId);
                                $replace = '';
                            }
                        }
                    }

                    $rowString = str_replace('[' . $sub . ']', $replace, $rowString);
                }

                $items[] = $rowString;
            }

            // Process queued downloads
            $this->mediaFactory->processDownloads(function($media) {
                // Success
                $this->getLog()->debug('Successfully downloaded ' . $media->mediaId);

                // Tag this layout with this file
                $this->assignMedia($media->mediaId);
            });

            return $items;
        }
        catch (NotFoundException $e) {
            $this->getLog()->debug('getDataSetItems failed for id=%d. Widget=%d. Due to %s - this might be OK if we have a no-data message', $dataSetId, $this->getWidgetId(), $e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());
            return [];
        }
    }

    public function isValid()
    {
        // Can't be sure because the client does the rendering
        return 1;
    }

    /** @inheritdoc */
    public function getModifiedDate($displayId)
    {
        $widgetModifiedDt = $this->widget->modifiedDt;
        if ($this->getOption('sourceId', 1) == 2) {

            $dataSetId = $this->getOption('dataSetId');
            $dataSet = $this->dataSetFactory->getById($dataSetId);

            // Set the timestamp
            $widgetModifiedDt = ($dataSet->lastDataEdit > $widgetModifiedDt) ? $dataSet->lastDataEdit : $widgetModifiedDt;

            // Remote dataSets are kept "active" by required files
            if ($dataSet->isRemote) {
                // Touch this dataSet
                $dataSetCache = $this->getPool()->getItem('/dataset/accessed/' . $dataSet->dataSetId);
                $dataSetCache->set('true');
                $dataSetCache->expiresAfter($this->getSetting('REQUIRED_FILES_LOOKAHEAD') * 1.5);
                $this->getPool()->saveDeferred($dataSetCache);
            }
        }

        return $this->getDate()->parse($widgetModifiedDt, 'U');
    }

    /** @inheritdoc */
    public function getCacheDuration()
    {
        return $this->getOption('updateInterval', 120) * 60;
    }

    /** @inheritdoc */
    public function getCacheKey($displayId)
    {
        if ($displayId === 0 || $this->getOption('sourceId', 1) == 2) {
            // DataSets might use Display
            return $this->getWidgetId() . '_' . $displayId;
        } else {
            // Tickers are non-display specific
            return $this->getWidgetId() . (($displayId === 0) ? '_0' : '');
        }
    }

    /** @inheritdoc */
    public function getLockKey()
    {
        if ($this->getOption('sourceId', 1) == 2) {
            // Lock to the dataSetId, because our dataSet might have external images which are downloaded.
            return $this->getOption('dataSetId');
        } else {
            // Tickers are locked to the feed
            return md5(urldecode($this->getOption('uri')));
        }
    }
}
