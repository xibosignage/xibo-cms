<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2011-2015 Daniel Garner
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
 * Class DataSetView
 * @package Xibo\Widget
 */
class DataSetView extends ModuleWidget
{
    /**
     * Install Modules Files
     */
    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-cycle-2.1.6.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-dataset-render.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-image-render.js')->save();
    }

    /** @inheritdoc */
    public function layoutDesignerJavaScript()
    {
        return 'datasetview-designer-javascript';
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
     * Get DataSet Columns
     * @return array
     */
    public function dataSetColumns()
    {
        return $this->dataSetColumnFactory->getByDataSetId($this->getOption('dataSetId'));
    }

    /**
     * Get Data Set Columns
     * @return array[DataSetColumn]
     */
    public function dataSetColumnsSelected()
    {
        if ($this->getOption('dataSetId') == 0)
            throw new \InvalidArgumentException(__('DataSet not selected'));

        $columns = $this->dataSetColumnFactory->getByDataSetId($this->getOption('dataSetId'));
        $columnsSelected = [];
        $colIds = explode(',', $this->getOption('columns'));
        
        // Cycle elements of the ordered columns Ids array $colIds
        foreach ($colIds as $colId) {
            // Cycle data set columns $columns
            foreach ($columns as $column) {
                // See if the element on the odered list is the column
                if ($column->dataSetColumnId == $colId) {
                    $columnsSelected[] = $column;    
                }
            }
        }
        
        return $columnsSelected;
    }

    /**
     * Get Data Set Columns
     * @return array[DataSetColumn]
     */
    public function dataSetColumnsNotSelected()
    {
        if ($this->getOption('dataSetId') == 0)
            throw new \InvalidArgumentException(__('DataSet not selected'));

        $columns = $this->dataSetColumnFactory->getByDataSetId($this->getOption('dataSetId'));

        $columnsNotSelected = [];
        $colIds = explode(',', $this->getOption('columns'));

        foreach ($columns as $column) {
            /* @var DataSetColumn $column */
            if (!in_array($column->dataSetColumnId, $colIds))
                $columnsNotSelected[] = $column;
        }

        return $columnsNotSelected;
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

    /** @inheritdoc */
    public function getExtra()
    {
        return [
            'templates' => $this->templatesAvailable(),
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
            return 'datasetview-form-edit-step1';
        } else {
            return 'datasetview-form-edit';
        }
    }

    /**
     * * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?dataSetView",
     *  operationId="widgetDataSetViewEdit",
     *  tags={"widget"},
     *  summary="Edit a dataSetView Widget",
     *  description="Edit an existing dataSetView Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
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
     *      name="dataSetId",
     *      in="formData",
     *      description="For Step 1. Create dataSetView Widget using provided dataSetId of an existing dataSet",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="dataSetColumnId",
     *      in="formData",
     *      description="Array of dataSetColumn IDs to assign",
     *      type="array",
     *      required=false,
     *      @SWG\Items(type="integer")
     *   ),
     *  @SWG\Parameter(
     *      name="duration",
     *      in="formData",
     *      description="The dataSetView Duration",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="useDuration",
     *      in="formData",
     *      description="Select 1 only if you will provide duration parameter as well",
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
     *      name="updateInterval",
     *      in="formData",
     *      description="Update interval in minutes",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="rowsPerPage",
     *      in="formData",
     *      description="Number of rows per page, 0 for no pages",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="showHeadings",
     *      in="formData",
     *      description="Should the table show Heading? (0,1)",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="upperLimit",
     *      in="formData",
     *      description="Upper low limit for this dataSet, 0 for no limit",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="lowerLimit",
     *      in="formData",
     *      description="Lower low limit for this dataSet, 0 for no limit",
     *      type="integer",
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
     *      name="templateId",
     *      in="formData",
     *      description="Template you'd like to apply, options available: empty, light-green",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="overrideTemplate",
     *      in="formData",
     *      description="flag (0, 1) override template checkbox",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="useOrderingClause",
     *      in="formData",
     *      description="flag (0,1) Use advanced order clause - set to 1 if ordering is provided",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="useFilteringClause",
     *      in="formData",
     *      description="flag (0,1) Use advanced filter clause - set to 1 if filter is provided",
     *      type="integer",
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
     *  @SWG\Response(
     *      response=204,
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

            // Read in the dataSetId, validate and store it
            $dataSetId = $this->getSanitizer()->getInt('dataSetId');

            // Do we already have a DataSet?
            if ($this->hasDataSet() && $dataSetId != $this->getOption('dataSetId')) {
                // Reset the fields that are dependent on the dataSetId
                $this->setOption('columns', '');
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

            // Columns
            $columns = $this->getSanitizer()->getIntArray('dataSetColumnId');

            if (count($columns) == 0) {
                $this->setOption('columns', '');
            } else {
                $this->setOption('columns', implode(',', $columns));
            }

            // Other properties
            $this->setOption('name', $this->getSanitizer()->getString('name'));
            $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
            $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
            $this->setOption('enableStat', $this->getSanitizer()->getString('enableStat'));
            $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 120));
            $this->setOption('rowsPerPage', $this->getSanitizer()->getInt('rowsPerPage'));
            $this->setOption('durationIsPerPage', $this->getSanitizer()->getCheckbox('durationIsPerPage'));
            $this->setOption('showHeadings', $this->getSanitizer()->getCheckbox('showHeadings'));
            $this->setOption('upperLimit', $this->getSanitizer()->getInt('upperLimit', 0));
            $this->setOption('lowerLimit', $this->getSanitizer()->getInt('lowerLimit', 0));
            $this->setOption('filter', $this->getSanitizer()->getParam('filter', null));
            $this->setOption('ordering', $this->getSanitizer()->getString('ordering'));
            $this->setOption('templateId', $this->getSanitizer()->getString('templateId'));
            $this->setOption('overrideTemplate', $this->getSanitizer()->getCheckbox('overrideTemplate'));
            $this->setOption('useOrderingClause', $this->getSanitizer()->getCheckbox('useOrderingClause'));
            $this->setOption('useFilteringClause', $this->getSanitizer()->getCheckbox('useFilteringClause'));
            $this->setRawNode('noDataMessage', $this->getSanitizer()->getParam('noDataMessage', ''));
            $this->setOption('noDataMessage_advanced', $this->getSanitizer()->getCheckbox('noDataMessage_advanced'));
            $this->setRawNode('javaScript', $this->getSanitizer()->getParam('javaScript', ''));

            $this->setOption('backgroundColor', $this->getSanitizer()->getString('backgroundColor'));
            $this->setOption('borderColor', $this->getSanitizer()->getString('borderColor'));
            $this->setOption('fontColor', $this->getSanitizer()->getString('fontColor'));
            $this->setOption('fontFamily', $this->getSanitizer()->getString('fontFamily'));
            $this->setOption('fontSize', $this->getSanitizer()->getInt('fontSize'));

            if ($this->getOption('overrideTemplate') == 1) {
                $this->setRawNode('styleSheet', $this->getSanitizer()->getParam('styleSheet', null));
            }

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

            // Validate
            $this->isValid();
        }

        // Save the widget
        $this->saveWidget();
    }

    /**
     * GetResource
     * Return the rendered resource to be used by the client (or a preview)
     * for displaying this content.
     * @param integer $displayId If this comes from a real client, this will be the display id.
     * @return mixed
     */
    public function getResource($displayId = 0)
    {
        // Load in the template
        $data = [];
        $isPreview = ($this->getSanitizer()->getCheckbox('preview') == 1);

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';
    
        // Get CSS from the original template or from the input field
        $styleSheet = '';
        if ($this->getOption('overrideTemplate', 1) == 0) {
            
            $template = $this->getTemplateById($this->getOption('templateId'));
            
            if (isset($template))
                $styleSheet = $template['css'];
                    
        } else {
            $styleSheet = $this->getRawNode('styleSheet', '');
        }
        
        // Get the embedded HTML out of RAW
        $styleSheet = $this->parseLibraryReferences($isPreview, $styleSheet);

        // If we have some options then add them to the end of the style sheet
        if ($this->getOption('backgroundColor') != '') {
            $styleSheet .= 'table.DataSetTable { background-color: ' . $this->getOption('backgroundColor') . '; }';
        }
        if ($this->getOption('borderColor') != '') {
            $styleSheet .= 'table.DataSetTable, table.DataSetTable tr, table.DataSetTable th, table.DataSetTable td { border: 1px solid ' . $this->getOption('borderColor') . '; }';
        }
        if ($this->getOption('fontColor') != '') {
            $styleSheet .= 'table.DataSetTable { color: ' . $this->getOption('fontColor') . '; }';
        }
        if ($this->getOption('fontFamily') != '') {
            $styleSheet .= 'table.DataSetTable { font-family: ' . $this->getOption('fontFamily') . '; }';
        }
        if ($this->getOption('fontSize') != '') {
            $styleSheet .= 'table.DataSetTable { font-size: ' . $this->getOption('fontSize') . 'px; }';
        }

        // Table display CSS fix
        $styleSheet .= 'table.DataSetTable.cycle-slide { display: table !important; }';

        // Get the JavaScript node
        $javaScript = $this->parseLibraryReferences($isPreview, $this->getRawNode('javaScript', ''));

        $duration = $this->getCalculatedDurationForGetResource();
        $durationIsPerItem = $this->getOption('durationIsPerPage', 1);
        $rowsPerPage = $this->getOption('rowsPerPage', 0);

        $options = array(
            'type' => $this->getModuleType(),
            'duration' => $duration,
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'rowsPerPage' => $rowsPerPage,
            'durationIsPerItem' => (($durationIsPerItem == 0) ? false : true)
        );

        // Generate the table
        $table = $this->dataSetTableHtml($displayId, $isPreview);

        // Work out how many pages we will be showing.
        $pages = ceil($table['countPages']);
        $totalDuration = ($durationIsPerItem == 0) ? $duration : ($duration * $pages);

        // Replace and Control Meta options
        $data['controlMeta'] = '<!-- NUMITEMS=' . $pages . ' -->' . PHP_EOL . '<!-- DURATION=' . $totalDuration . ' -->';

        // Add our fonts.css file
        $headContent = '<link href="' . (($isPreview) ? $this->getApp()->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';
        $headContent .= '<style type="text/css">' . $styleSheet . '</style>';

        // If we are going to cycle between pages, make sure we hide all of the tables initially.
        if ($rowsPerPage > 0) {
            $headContent .= '<style type="text/css">table.DataSetTable {visibility:hidden;}</style>';
        }

        $data['head'] = $headContent;
        $data['body'] = $table['html'];

        // Build some JS nodes
        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-cycle-2.1.6.min.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-dataset-render.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-image-render.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("#DataSetTableContainer").dataSetRender(options); $("body").xiboLayoutScaler(options); $("#DataSetTableContainer").find("img").xiboImageRender(options); ';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= $javaScript;
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

        return $this->renderTemplate($data);
    }

    /**
     * Get the Data Set Table
     * @param int $displayId
     * @param bool $isPreview
     * @return array
     */
    private function dataSetTableHtml($displayId = 0, $isPreview = true)
    {
        // Show a preview of the data set table output.
        $dataSetId = $this->getOption('dataSetId');
        $upperLimit = $this->getOption('upperLimit');
        $lowerLimit = $this->getOption('lowerLimit');
        $columnIds = $this->getOption('columns');
        $showHeadings = $this->getOption('showHeadings');
        $rowsPerPage = $this->getOption('rowsPerPage');

        if ($columnIds == '')
            return __('No columns');

        // Ordering
        $ordering = '';

        if ($this->getOption('useOrderingClause', 1) == 1) {
            $ordering = $this->getOption('ordering');
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
            $filter = $this->getOption('filter');
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

        // Array of columnIds we want
        $columnIds = explode(',', $columnIds);

        // Set an expiry time for the media
        $expires = time() + ($this->getOption('updateInterval', 3600) * 60);

        // Create a data set object, to get the results.
        try {
            $dataSet = $this->dataSetFactory->getById($dataSetId);

            // Get an array representing the id->heading mappings
            $mappings = [];
            foreach ($columnIds as $dataSetColumnId) {
                // Get the column definition this represents
                $column = $dataSet->getColumn($dataSetColumnId);
                /* @var DataSetColumn $column */

                $mappings[] = [
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
                $timeZone = ($timeZone == '') ? $this->getConfig()->getSetting('defaultTimezone') : $timeZone;
                $dateNow->timezone($timeZone);
                $this->getLog()->debug('Display Timezone Resolved: %s. Time: %s.', $timeZone, $dateNow->toDateTimeString());
            }

            $this->getStore()->setTimeZone($this->getDate()->getLocalDate($dateNow, 'P'));

            // Get the data (complete table, filtered)
            $dataSetResults = $dataSet->getData($filter);

            if (count($dataSetResults) <= 0) {
                if ($this->getRawNode('noDataMessage') == '') {
                    throw new NotFoundException(__('Empty Result Set with filter criteria.'));
                } else {
                    return [
                        'html' => $this->getRawNode('noDataMessage'),
                        'countPages' => 1
                    ];
                }
            }

            $rowCount = 1;
            $rowCountThisPage = 1;
            $totalRows = count($dataSetResults);

            if ($rowsPerPage > 0)
                $totalPages = $totalRows / $rowsPerPage;
            else
                $totalPages = 1;

            $table = '<div id="DataSetTableContainer" totalRows="' . $totalRows . '" totalPages="' . $totalPages . '">';

            // Parse each result and
            foreach ($dataSetResults as $row) {
                if (($rowsPerPage > 0 && $rowCountThisPage >= $rowsPerPage) || $rowCount == 1) {

                    // Reset the row count on this page
                    $rowCountThisPage = 0;

                    if ($rowCount > 1) {
                        $table .= '</tbody>';
                        $table .= '</table>';
                    }

                    // Output the table header
                    $table .= '<table class="DataSetTable">';

                    if ($showHeadings == 1) {
                        $table .= '<thead>';
                        $table .= ' <tr class="HeaderRow">';

                        foreach ($mappings as $mapping)
                            $table .= '<th class="DataSetColumnHeaderCell">' . $mapping['heading'] . '</th>';

                        $table .= ' </tr>';
                        $table .= '</thead>';
                    }

                    $table .= '<tbody>';
                }

                $table .= '<tr class="DataSetRow DataSetRow' . (($rowCount % 2) ? 'Odd' : 'Even') . '" id="row_' . $rowCount . '">';

                // Output each cell for these results
                $i = 0;
                foreach ($mappings as $mapping) {
                    $i++;

                    // Pull out the cell for this row / column
                    $replace = $row[$mapping['heading']];

                    // If the value is empty, then move on (don't do so for 0)
                    if ($replace === '') {
                        // We don't do anything there, we just output an empty column.
                        $replace = '';

                    } else if ($mapping['dataTypeId'] == 4) {
                        // What if this column is an image column type?

                        // Grab the external image
                        $file = $this->mediaFactory->queueDownload('datasetview_' . md5($dataSetId . $mapping['dataSetColumnId'] . $replace), str_replace(' ', '%20', htmlspecialchars_decode($replace)), $expires);

                        $replace = ($isPreview)
                            ? '<img src="' . $this->getApp()->urlFor('library.download', ['id' => $file->mediaId, 'type' => 'image']) . '?preview=1" />'
                            : '<img src="' . $file->storedAs . '" />';

                    } else if ($mapping['dataTypeId'] == 5) {

                        // Library Image
                        // The content is the ID of the image
                        try {
                            $file = $this->mediaFactory->getById($replace);

                            // Already in the library - assign this mediaId to the Layout immediately.
                            $this->assignMedia($file->mediaId);
                        }
                        catch (NotFoundException $e) {
                            $this->getLog()->error('Library Image [%s] not found in DataSetId %d.', $replace, $dataSetId);
                            continue;
                        }

                        $replace = ($isPreview)
                            ? '<img src="' . $this->getApp()->urlFor('library.download', ['id' => $file->mediaId, 'type' => 'image']) . '?preview=1" />'
                            : '<img src="' . $file->storedAs . '" />';
                    }

                    $table .= '<td class="DataSetColumn DataSetColumn_' . $i . '" id="column_' . ($i + 1) . '"><span class="DataSetCellSpan DataSetCellSpan_' . $rowCount . '_' . $i .'" id="span_' . $rowCount . '_' . ($i + 1) . '">' . $replace . '</span></td>';
                }

                // Process queued downloads
                $this->mediaFactory->processDownloads(function($media) {
                    // Success
                    $this->getLog()->debug('Successfully downloaded ' . $media->mediaId);

                    // Tag this layout with this file
                    $this->assignMedia($media->mediaId);
                });

                $table .= '</tr>';

                $rowCount++;
                $rowCountThisPage++;
            }

            $table .= '</tbody>';
            $table .= '</table>';
            $table .= '</div>';

            return [
                'html' => $table,
                'countRows' => $totalRows,
                'countPages' => $totalPages
            ];
        }
        catch (NotFoundException $e) {
            $this->getLog()->info('Request failed for dataSet id=%d. Widget=%d. Due to %s', $dataSetId, $this->getWidgetId(), $e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());
            return '';
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

    /** @inheritdoc */
    public function isValid()
    {
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new InvalidArgumentException(__('Please enter a duration'), 'duration');

        if (!is_numeric($this->getOption('upperLimit')) || !is_numeric($this->getOption('lowerLimit')))
            throw new InvalidArgumentException(__('Limits must be numbers'), 'limit');

        if ($this->getOption('upperLimit') < 0 || $this->getOption('lowerLimit') < 0)
            throw new InvalidArgumentException(__('Limits cannot be lower than 0'), 'limit');

        // Check the bounds of the limits
        if ($this->getOption('upperLimit') != 0 && $this->getOption('upperLimit') < $this->getOption('lowerLimit'))
            throw new InvalidArgumentException(__('Upper limit must be higher than lower limit'), 'limit');

        if ($this->getOption('updateInterval') !== null && !v::intType()->min(0)->validate($this->getOption('updateInterval', 0)))
            throw new InvalidArgumentException(__('Update Interval must be greater than or equal to 0'), 'updateInterval');

        // Make sure we haven't entered a silly value in the filter
        if (strstr($this->getOption('filter'), 'DESC'))
            throw new InvalidArgumentException(__('Cannot user ordering criteria in the Filter Clause'), 'filter');

        return ($this->hasDataSet()) ? self::$STATUS_VALID : self::$STATUS_INVALID;
    }

    /** @inheritdoc */
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
        // DataSetViews are display specific
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
