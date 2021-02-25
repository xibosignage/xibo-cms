<?php
/**
 * Copyright (C) 2018 Xibo Signage Ltd
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
 */
namespace Xibo\Widget;

use Respect\Validation\Validator as v;
use Xibo\Entity\DataSetColumn;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;

/**
 * Class DataSetTicker
 * @package Xibo\Widget
 */
class DataSetTicker extends ModuleWidget
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

    /** @inheritdoc */
    public function layoutDesignerJavaScript()
    {
        // We use the same javascript as the data set view designer
        return 'datasetticker-designer-javascript';
    }

    /**
     * Get DataSet object, used by TWIG template.
     *
     * @return array
     * @throws NotFoundException
     */
    public function getDataSet()
    {
        if ($this->getOption('dataSetId') != 0) {
            return [$this->dataSetFactory->getById($this->getOption('dataSetId'))];
        } else {
            return null;
        }
    }

    /**
     * Get Data Set Columns
     * @return \Xibo\Entity\DataSetColumn[]
     * @throws \Xibo\Exception\InvalidArgumentException
     */
    public function dataSetColumns()
    {
        if ($this->getOption('dataSetId') == 0)
            throw new InvalidArgumentException(__('DataSet not selected'), 'dataSetId');

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
     * @throws \Xibo\Exception\XiboException
     */
    public function getExtra()
    {
        return [
            'orderClause' => $this->getOrderClause(),
            'filterClause' => $this->getFilterClause(),
            'columns' => $this->dataSetColumns(),
            'dataSet' => ($this->getOption('dataSetId', 0) != 0) ? $this->dataSetFactory->getById($this->getOption('dataSetId')) : null
        ];
    }

    /** @inheritdoc @override */
    public function editForm()
    {
        // Do we have a step provided?
        $step = $this->getSanitizer()->getInt('step', 2);

        if ($step == 1 || !$this->hasDataSet()) {
            return 'datasetticker-form-edit-step1';
        } else {
            return 'datasetticker-form-edit';
        }
    }

    /**
     * Does this module have a DataSet yet?
     * @return bool
     */
    private function hasDataSet()
    {
        return (v::notEmpty()->validate($this->getOption('dataSetId')));
    }

    /**
     * Edit
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?dataSetTicker",
     *  operationId="WidgetDataSetTickerEdit",
     *  tags={"widget"},
     *  summary="Edit a dataSet ticker Widget",
     *  description="Edit a dataSet ticker Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The WidgetId to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="step",
     *      in="formData",
     *      description="The Step Number being edited",
     *      type="integer",
     *      required=false
     *  ),
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
     *      name="enableStat",
     *      in="formData",
     *      description="The option (On, Off, Inherit) to enable the collection of Widget Proof of Play statistics",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="formData",
     *      description="Required for Step 1. Create ticker Widget using provided dataSetId of an existing dataSet",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="updateInterval",
     *      in="formData",
     *      description="Update interval in minutes",
     *      type="integer",
     *      required=false
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
     *      description="Upper low limit for this dataSet, 0 for nor limit",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="lowerLimit",
     *      in="formData",
     *      description="Lower low limit for this dataSet, 0 for nor limit",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="itemsPerPage",
     *      in="formData",
     *      description="When in single mode, how many items per page should be shown",
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
     *      name="noDataMessage",
     *      in="formData",
     *      description="A message to display when no data is returned from the source",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="noDataMessage_advanced",
     *      in="formData",
     *      description="A flag (0, 1), Should text area by presented as a visual editor?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="template",
     *      in="formData",
     *      description="Template for each item",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ta_text_advanced",
     *      in="formData",
     *      description="A flag (0, 1), Should text area by presented as a visual editor?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="css",
     *      in="formData",
     *      description="Optional StyleSheet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="javaScript",
     *      in="formData",
     *      description="Optional JavaScript",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="filter",
     *      in="formData",
     *      description="SQL clause for filter this dataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ordering",
     *      in="formData",
     *      description="SQL clause for how this dataSet should be ordered",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="useOrderingClause",
     *      in="formData",
     *      description="Use advanced order clause - set to 1 if ordering is provided",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="useFilteringClause",
     *      in="formData",
     *      description="Use advanced filter clause - set to 1 if filter is provided",
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
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     *
     * @inheritdoc
     */
    public function edit()
    {
        // Do we have a step provided?
        $step = $this->getSanitizer()->getInt('step', 2);

        if ($step == 1) {

            $dataSetId = $this->getSanitizer()->getInt('dataSetId');

            // Do we already have a DataSet?
            if($this->hasDataSet() && $dataSetId != $this->getOption('dataSetId')) {
                // Reset the fields that are dependent on the dataSetId
                //$this->setOption('columns', '');
            }

            $this->setOption('dataSetId', $dataSetId);

            // Validate Data Set Selected
            if ($dataSetId == 0) {
                throw new InvalidArgumentException(__('Please select a DataSet'), 'dataSetId');
            }

            // Check we have permission to use this DataSetId
            if (!$this->getUser()->checkViewable($this->dataSetFactory->getById($this->getOption('dataSetId')))) {
                throw new InvalidArgumentException(__('You do not have permission to use that dataset'), 'dataSetId');
            }

        } else {

            $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
            $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
            $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 120));
            $this->setOption('speed', $this->getSanitizer()->getInt('speed', 2));
            $this->setOption('name', $this->getSanitizer()->getString('name'));
            $this->setOption('effect', $this->getSanitizer()->getString('effect'));
            $this->setOption('durationIsPerItem', $this->getSanitizer()->getCheckbox('durationIsPerItem'));
            $this->setOption('enableStat', $this->getSanitizer()->getString('enableStat'));
            $this->setOption('itemsSideBySide', $this->getSanitizer()->getCheckbox('itemsSideBySide'));
            $this->setOption('upperLimit', $this->getSanitizer()->getInt('upperLimit', 0));
            $this->setOption('lowerLimit', $this->getSanitizer()->getInt('lowerLimit', 0));
            $this->setOption('numItems', $this->getSanitizer()->getInt('numItems'));
            $this->setOption('randomiseItems', $this->getSanitizer()->getCheckbox('randomiseItems'));
            $this->setOption('itemsPerPage', $this->getSanitizer()->getInt('itemsPerPage'));
            $this->setOption('backgroundColor', $this->getSanitizer()->getString('backgroundColor'));
            $this->setRawNode('noDataMessage', $this->getSanitizer()->getParam('noDataMessage', ''));
            $this->setOption('noDataMessage_advanced', $this->getSanitizer()->getCheckbox('noDataMessage_advanced'));
            $this->setRawNode('javaScript', $this->getSanitizer()->getParam('javaScript', ''));
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
            $this->setOption('ta_text_advanced', $this->getSanitizer()->getCheckbox('ta_text_advanced'));
            $this->setRawNode('css', $this->getSanitizer()->getParam('ta_css', $this->getSanitizer()->getParam('css', null)));

            $this->isValid();
        }

        // Save the widget
        $this->saveWidget();
    }

    /** @inheritdoc */
    public function getResource($displayId = 0)
    {
        // Load in the template
        $data = [];
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        // Information from the Module
        $itemsSideBySide = $this->getOption('itemsSideBySide', 0);
        $duration = $this->getCalculatedDurationForGetResource();
        $durationIsPerItem = $this->getOption('durationIsPerItem', 1);
        $takeItemsFrom = $this->getOption('takeItemsFrom', 'start');
        $itemsPerPage = $this->getOption('itemsPerPage', 0);
        $numItems = $this->getOption('numItems', 0);

        // Text/CSS subsitution variables.
        // DataSet tickers or feed tickers without overrides.
        $text = $this->getRawNode('template', '');
        $css = $this->getRawNode('css', '');
        
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
            'takeItemsFrom' => $takeItemsFrom,
            'itemsPerPage' => $itemsPerPage,
            'numItems' => $numItems,
            'randomiseItems' => $this->getOption('randomiseItems', 0),
            'speed' => $this->getOption('speed', 1000),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height
        );

        // Generate a JSON string of substituted items.
        $items = $this->getDataSetItems($displayId, $text);

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
     * @param $displayId
     * @param $text
     * @return array
     */
    private function getDataSetItems($displayId, $text)
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
                        continue 2;
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

            $this->getLog()->debug('Resolved column mappings: ' . json_encode($columnIds));

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
                $timeZone = ($timeZone == '') ? $this->getConfig()->getSetting('defaultTimezone') : $timeZone;
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
            $this->getLog()->debug('getDataSetItems failed for id=' . $dataSetId . '. Widget=' . $this->getWidgetId() . '. Due to ' . $e->getMessage() . ' - this might be OK if we have a no-data message');
            $this->getLog()->debug($e->getTraceAsString());
            return [];
        }
    }

    /** @inheritdoc */
    public function isValid()
    {
        // Must have a duration
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new InvalidArgumentException(__('Please enter a duration'), 'duration');

        // Validate Data Set Selected
        if ($this->getOption('dataSetId') == 0)
            throw new InvalidArgumentException(__('Please select a DataSet'), 'dataSetId');

        // Check we have permission to use this DataSetId
        // FIXME: Call to a member function checkViewable() on null
        //if (!$this->getUser()->checkViewable($this->dataSetFactory->getById($this->getOption('dataSetId'))))
            //throw new InvalidArgumentException(__('You do not have permission to use that dataset'), 'dataSetId');

        if ($this->widget->widgetId != 0) {
            // Some extra edit validation
            // Make sure we havent entered a silly value in the filter
            if (strstr($this->getOption('filter'), 'DESC'))
                throw new InvalidArgumentException(__('Cannot user ordering criteria in the Filter Clause'), 'filter');

            if (!is_numeric($this->getOption('upperLimit')) || !is_numeric($this->getOption('lowerLimit')))
                throw new InvalidArgumentException(__('Limits must be numbers'), 'limits');

            if ($this->getOption('upperLimit') < 0 || $this->getOption('lowerLimit') < 0)
                throw new InvalidArgumentException(__('Limits cannot be lower than 0'), 'limits');

            // Check the bounds of the limits
            if ($this->getOption('upperLimit') != 0 && $this->getOption('upperLimit') < $this->getOption('lowerLimit'))
                throw new InvalidArgumentException(__('Upper limit must be higher than lower limit'), 'limits');
        }

        // Make sure we have a number in here
        if ($this->getOption('updateInterval') !== null && !v::intType()->min(0)->validate($this->getOption('updateInterval', 0)))
            throw new InvalidArgumentException(__('Update Interval must be greater than or equal to 0'), 'updateInterval');

        return self::$STATUS_VALID;
    }

    /**
     * @inheritdoc
     * @throws \Xibo\Exception\XiboException
     */
    public function getModifiedDate($displayId)
    {
        $widgetModifiedDt = $this->widget->modifiedDt;

        $dataSetId = $this->getOption('dataSetId');
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        // Set the timestamp
        $widgetModifiedDt = ($dataSet->lastDataEdit > $widgetModifiedDt) ? $dataSet->lastDataEdit : $widgetModifiedDt;

        // Remote dataSets are kept "active" by required files
        $dataSet->setActive();

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
        return $this->getWidgetId() . '_' . $displayId;
    }

    /** @inheritdoc */
    public function isCacheDisplaySpecific()
    {
        return true;
    }

    /** @inheritdoc */
    public function getLockKey()
    {
        // Lock to the dataSetId, because our dataSet might have external images which are downloaded.
        return $this->getOption('dataSetId');
    }
}
