<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2018 Lukas Zurschmiede
 * Copyright (C) 2018 Xibo Signage Ltd
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
use Xibo\Factory\ModuleFactory;

class Chart extends ModuleWidget
{
    public $codeSchemaVersion = 1;

    /**
     * Graph constructor.
     * @override
     */
    public function init()
    {
        v::with('Xibo\\Validation\\Rules\\');
    }
    
    /**
     * Install or Update this module
     * @param ModuleFactory $moduleFactory
     * @Override
     */
    public function installOrUpdate($moduleFactory)
    {
        // Install
        if ($this->module == null) {
            $module = $moduleFactory->createEmpty();
            $module->name = 'Chart';
            $module->type = 'chart';
            $module->class = 'Xibo\Widget\Chart';
            $module->description = 'Graphical data visualization';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 240;
            $module->settings = [];
            $module->installName = 'chart';

            $this->setModule($module);
            $this->installModule();
        }

        // Check we are all installed
        $this->installFiles();
    }

    /**
     * @inheritdoc
     * @override
     */
    public function installFiles()
    {
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/moment.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/Chart.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
    }

    /**
     * Form for updating the module settings
     * @return string Name of the Settings-Form
     * @override
     */
    public function settingsForm()
    {
        return 'chart-form-settings';
    }
    
    /**
     * @return string
     */
    public function layoutDesignerJavaScript()
    {
        return 'chart-designer-javascript';
    }

    /**
     * Process any module settings
     * @return array An array of the processed settings.
     * @override
     */
    public function settings()
    {
        $this->module->settings['defaultColors'] = explode(',', $this->getSanitizer()->getString('defaultColors'));
        return $this->module->settings;
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

    /** @var DataSetColumn[] */
    private $columnsCache = null;

    /**
     * Get DataSet Columns
     * Used by the TWIG template to show a list of available dataSetColumns
     * @return DataSetColumn[]
     */
    public function dataSetColumns()
    {
        if ($this->columnsCache === null) {
            $this->columnsCache = $this->dataSetColumnFactory->getByDataSetId($this->getOption('dataSetId'));
        }

        return $this->columnsCache;
    }

    /**
     * Get the currently configured chart columns
     * @return mixed
     */
    public function getColumnConfig()
    {
        return json_decode($this->getOption('config', "[]"), true);
    }

    /**
     * Get the column configuration for a particular type of column
     * @param string $type The Type of Column you want
     * @return null|string|string[]
     */
    private function getColumnType($type)
    {
        $columnType = [];

        foreach ($this->getColumnConfig() as $config) {
            if ($config['columnType'] == $type) {
                foreach ($this->dataSetColumns() as $dataSetColumn) {
                    if ($dataSetColumn->dataSetColumnId == (int)$config['dataSetColumnId']) {
                        $columnType[] = $dataSetColumn->heading;
                    }
                }
            }
        }

        $found = count($columnType);
        if ($found <= 0) {
            return null;
        } else if ($found === 1) {
            return $columnType[0];
        } else {
            return $columnType;
        }
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
     * Get the configured colours
     * @return array
     */
    public function getColors()
    {
        return json_decode($this->getOption('seriesColors', "[]"), true);
    }

    /**
     * Get Extra content for the form
     * @return array
     * @throws NotFoundException
     */
    public function getExtra()
    {
        return [
            'config' => $this->getColumnConfig(),
            'seriesColors' => $this->getColors(),
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
            return 'chart-form-edit-step1';
        } else {
            return 'chart-form-edit';
        }
    }

    /**
     * Edit the Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?chart",
     *  operationId="widgetChartEdit",
     *  tags={"widget"},
     *  summary="Edit a Chart Widget",
     *  description="Edit Chart Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
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
     *      description="Required for step 1. Create Chart Widget using provided dataSetId of an existing dataSet",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="graphType",
     *      in="formData",
     *      description="Chart Type",
     *      type="string",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="columnType",
     *      in="formData",
     *      description="Array of Column Types (x-axis, y-axis, series-identifier) to assign",
     *      type="array",
     *      required=false,
     *      @SWG\Items(type="integer")
     *   ),
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
     *      description="The Chart Duration",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="useDuration",
     *      in="formData",
     *      description="Edit Only - (0, 1) Select 1 only if you will provide duration parameter as well",
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
     *      name="backgroundColor",
     *      in="formData",
     *      description="Background Color",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="fontColor",
     *      in="formData",
     *      description="Font Color",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="fontSize",
     *      in="formData",
     *      description="Font Size",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="showLegend",
     *      in="formData",
     *      description="Should the Legend be Shown",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="legendPosition",
     *      in="formData",
     *      description="Where should the Legend be Shown (top, left, right, bottom)",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="startYAtZero",
     *      in="formData",
     *      description="Start the Y-Axis at 0",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="title",
     *      in="formData",
     *      description="Chart title",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="x-axis-label",
     *      in="formData",
     *      description="Chart x-axis label",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="y-axis-label",
     *      in="formData",
     *      description="Chart y-axis label",
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
        // Do we have a step provided?
        $step = $this->getSanitizer()->getInt('step', 2);

        if ($step == 1 || !$this->hasDataSet()) {
            $dataSetId = $this->getSanitizer()->getInt('dataSetId');

            // Do we already have a DataSet?
            if($this->hasDataSet() && $dataSetId != $this->getOption('dataSetId')) {
                // Reset the fields that are dependent on the dataSetId

                $this->setOption('config', '[]');
                $this->setOption('filterClauses', '[]');
                $this->setOption('orderClauses', '[]');
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

            // Check we have permission to use this DataSetId
            if (!$this->getUser()->checkViewable($this->dataSetFactory->getById($this->getOption('dataSetId'))))
                throw new InvalidArgumentException(__('You do not have permission to use that DataSet'), 'dataSetId');

            $this->setOption('name', $this->getSanitizer()->getString('name'));
            $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
            $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
            $this->setOption('enableStat', $this->getSanitizer()->getString('enableStat'));

            $this->setOption('graphType', $this->getSanitizer()->getString('graphType'));
            $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 120));
            $this->setOption('backgroundColor', $this->getSanitizer()->getString('backgroundColor'));
            $this->setOption('fontColor', $this->getSanitizer()->getString('fontColor'));
            $this->setOption('fontSize', $this->getSanitizer()->getInt('fontSize'));
            $this->setOption('showLegend', $this->getSanitizer()->getCheckbox('showLegend', 0));
            $this->setOption('legendPosition', $this->getSanitizer()->getString('legendPosition'));
            $this->setOption('startYAtZero', $this->getSanitizer()->getCheckbox('startYAtZero', 0));
            $this->setOption('title', $this->getSanitizer()->getString('title'));
            $this->setOption('x-axis-label', $this->getSanitizer()->getString('x-axis-label'));
            $this->setOption('y-axis-label', $this->getSanitizer()->getString('y-axis-label'));

            // Handle the config
            $columnTypes = $this->getSanitizer()->getStringArray('columnType');
            $dataSetColumnIds = $this->getSanitizer()->getStringArray('dataSetColumnId');
            $config = [];

            $i = -1;
            foreach ($columnTypes as $columnType) {
                $i++;

                if ($columnType == '')
                    continue;

                // Store this column configuration
                $config[] = [
                    'columnType' => $columnType,
                    'dataSetColumnId' => isset($dataSetColumnIds[$i]) ? $dataSetColumnIds[$i] : ''
                ];
            }

            $this->setOption('config', json_encode($config));

            // Handle colours
            $seriesColors = $this->getSanitizer()->getStringArray('seriesColor');
            $this->setOption('seriesColors', json_encode(array_filter($seriesColors)));

            // Order and Filter criteria
            $this->setOption('useOrderingClause', $this->getSanitizer()->getCheckbox('useOrderingClause'));
            $this->setOption('useFilteringClause', $this->getSanitizer()->getCheckbox('useFilteringClause'));
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


            $this->isValid();
        }

        $this->saveWidget();
    }

    /**
     * @inheritdoc
     * @override
     */
    public function getResource($displayId = 0)
    {
        $containerId = 'graph-' . $displayId;

        $this->getLog()->debug('Render graph for widgetId: ' . $this->getWidgetId() . ' and displayId: ' . $displayId);

        // Replace the View Port Width if we are in Preview-Mode
        $data['viewPortWidth'] = ($this->getSanitizer()->getCheckbox('preview') == 1) ? $this->region->width : '[[ViewPortWidth]]';

        // Options XIBO needs for rendering - see JavaScript at the end of this function
        $options = array(
            'type' => $this->getModuleType(),
            'useDuration' => $this->getUseDuration(),
            'duration' => $this->getDuration(),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height
        );

        // Background for the Graph and the legend
        $backgroundColor = $this->getOption('backgroundColor');
        $backgroundColor = ($backgroundColor == '') ? '' : 'background-color: ' . $backgroundColor . ';';

        // Body content
        $data['body'] = '
            <div id="' . $containerId . '" style="' . $backgroundColor . '">
                <canvas id="' . $containerId . '_graph" width="' . $this->region->width . '" height="' . $this->region->height . '">
                    [No Canvas support]
                </canvas>
            </div>
        ';

        // After body content - mostly XIBO-Stuff for scaling and so on
        $javaScriptContent  = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/moment.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/Chart.min.js') . '"></script>';

        // Get data
        $chartData = $this->getChartData($displayId);
        $chartData->type = $this->getOption('graphType');

        // Do we have any chart options to take into consideration?
        $chartData->options = $this->getChartOptions();

        $this->getLog()->debug(json_encode($chartData, JSON_PRETTY_PRINT));

        // Add all Chart Javascript
        $javaScriptContent .= '<script>
            $(document).ready(function() {
                Chart.defaults.global.defaultFontSize = ' . $this->getOption('fontSize', 24)  . ';
                Chart.defaults.global.defaultFontColor = "' . $this->getOption('fontColor', '#000000')  . '";
                var ctx = document.getElementById("' . $containerId . '_graph");
                var chart = new Chart(ctx, ' . json_encode($chartData) . ');
                
                // Scale the Layout after rendering
                $("#' . $containerId . '").xiboLayoutScaler(' . json_encode($options) . ');
            });</script>
        ';

        // Replace the After body Content
        $data['body'] .= $javaScriptContent;

        return $this->renderTemplate($data);
    }

    /**
     * Load all possible Columns and data from the selected DataSet
     * @param int $displayId
     * @return Object { data: { datasets: [], labels: [] } }
     * @throws NotFoundException
     */
    protected function getChartData($displayId)
    {
        $chartType = $this->getOption('graphType');
        $xAxis = $this->getColumnType('x-axis');
        $yAxis = $this->getColumnType('y-axis');
        $seriesIdentifier = $this->getColumnType('series-identifier');

        if (!is_array($yAxis))
            $yAxis = [$yAxis];

        // Do we have a series identifier?
        // if we do, then we should treat those as a separate X axis (for the purposes of R.Graph's data requirements)
        // we might have more than 1 :o
        if ($seriesIdentifier !== null && !is_array($seriesIdentifier))
            $seriesIdentifier = [$seriesIdentifier];

        // Set some query options
        $queryOptions = $this->getQueryOptions($displayId);

        // Create an object we json_encode into the HTML for this RGraph
        $graphData = new \stdClass();
        $graphData->data = new \stdClass();
        $graphData->data->datasets = [];
        $graphData->data->labels = [];

        // Get the DataSet
        $dataSet = $this->dataSetFactory->getById($this->getOption('dataSetId'));

        // Pull out the Data
        $results = $dataSet->getData($queryOptions, ['requireTotal' => false]);

        // transform the query results into the data array expected by r-graph
        $data = [];

        // Build an array keyed by the x-axis value configured
        foreach ($results as $row) {
            $data[$row[$xAxis]][] = $row;
        }

        $this->getLog()->debug(json_encode($data, JSON_PRETTY_PRINT));

        // Set our labels to be our x-axis keys
        $graphData->data->labels = array_keys($data);

        // Calculate a DataSet for each x-axis that has been provided, taking care to enumerate through any
        // series identifiers that have also been provided.
        // we aggregate out duplicate keys
        $axisData = [];

        // Process each Y-Axis, adding a new point per X
        foreach ($yAxis as $axis) {
            $axisDataTemp = [];

            foreach ($data as $key => $rows) {
                foreach ($rows as $row) {
                    // If we have a series identifier, do this one time for each
                    if ($seriesIdentifier !== null) {
                        foreach ($seriesIdentifier as $identifier) {
                            // Keep track of the axis we're on
                            $axisDataTemp[$row[$identifier]][$key] = (isset($axisDataTemp[$row[$identifier]][$key])) ? $axisDataTemp[$row[$identifier]][$key] + $row[$axis] : $row[$axis];
                        }
                    } else {
                        // Keep track of the axis we're on
                        $axisDataTemp[$key] = (isset($axisDataTemp[$key])) ? $axisDataTemp[$key] + $row[$axis] : $row[$axis];
                    }
                }
            }

            $this->getLog()->debug('Axis Data Temp is: ' . json_encode($axisDataTemp, JSON_PRETTY_PRINT));

            // We should have an array of data points for the first Y-axis on each X-Axis
            // e.g. for y1 [x1, x2, x3]
            if ($seriesIdentifier !== null) {
                // Split by series
                foreach ($axisDataTemp as $key => $value) {
                    // Values
                    $temp = [
                        'label' => $key,
                        'data' => array_values($value)
                    ];

                    // Special handling for colors
                    if ($chartType === 'line' || $chartType === 'radar') {
                        $temp['borderColor'] = $this->getColorPallet()[count($axisData)];
                    } else {
                        $temp['backgroundColor'] = $this->getColorPallet()[count($axisData)];
                    }

                    $axisData[] = $temp;
                }
            } else {
                // No series identifiers
                $temp = [
                    'label' => $axis,
                    'data' => array_values($axisDataTemp)
                ];

                // We could consider having a "colour per bar"
                if ($chartType === 'line' || $chartType === 'radar') {
                    $temp['borderColor'] = $this->getColorPallet()[count($axisData)];
                } else if ($chartType === 'pie' || $chartType === 'doughnut') {
                    for ($i = 0; $i < count($axisDataTemp); $i++) {
                        $temp['backgroundColor'][] = $this->getColorPallet()[$i];
                    }
                } else {
                    $temp['backgroundColor'] = $this->getColorPallet()[count($axisData)];
                }

                $axisData[] = $temp;
            }
        }

        $graphData->data->datasets = $axisData;

        return $graphData;
    }

    /**
     * Get Chart Options
     * @return array
     */
    protected function getChartOptions()
    {
        $chartType = $this->getOption('graphType');
        $options = [];

        if ($this->getOption('showLegend') !== '1') {
            $options['legend'] = [];
            $options['legend']['display'] = false;
        } else {
            $options['legend']['position'] = $this->getOption('legendPosition', 'top');
        }

        if ($this->getOption('title') != '') {
            $options['title'] = [
                'display' => true,
                'text' => $this->getOption('title')
            ];
        }

        // Axis are only applicable for charts with a cartesan axis
        if ($chartType !== 'pie' && $chartType !== 'doughnut' && $chartType !== 'radar') {

            // Options effecting the Y-Axis
            $yAxis = [];
            $xAxis = [];

            if ($this->getOption('x-axis-label') != '') {
                $xAxis['scaleLabel'] = [
                    'display' => true,
                    'labelString' => $this->getOption('x-axis-label')
                ];
            }

            if ($this->getOption('y-axis-label') != '') {
                $yAxis['scaleLabel'] = [
                    'display' => true,
                    'labelString' => $this->getOption('y-axis-label')
                ];
            }

            if ($this->getOption('startYAtZero') == '1') {
                if ($chartType === 'horizontalBar') {
                    $xAxis['ticks']['beginAtZero'] = true;
                } else {
                    $yAxis['ticks']['beginAtZero'] = true;
                }
            }

            $options['scales']['yAxes'][] = $yAxis;
            $options['scales']['xAxes'][] = $xAxis;
        }

        return $options;
    }

    /**
     * Get the Query Options
     * @param $displayId
     * @return array
     */
    private function getQueryOptions($displayId)
    {
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

        return [
            'filter' => $filter,
            'order' => $ordering,
            'displayId' => $displayId
        ];
    }

    private $colorPallet = null;

    /**
     * Get Color Pallet
     * @return array|null
     */
    private function getColorPallet()
    {
        if ($this->colorPallet === null) {
            // Get the combination of the widget, module and default color array.
            $this->colorPallet = [
                '#7293CB', '#E1974C', '#84BA5B', '#D35E60', '#808585', '#9067A7', '#AB6857', '#CCC210',
                '#396AB1', '#DA7C30', '#3E9651', '#CC2529', '#535154', '#6B4C9A', '#922428', '#948B3D'
            ];

            $widgetColors = $this->getColors();
            $moduleColors = $this->getSetting('defaultColors', []);

            if (count($widgetColors) > 0)
                $this->colorPallet = array_merge($widgetColors, $this->colorPallet);

            if (count($moduleColors) > 0)
                $this->colorPallet = array_merge($moduleColors, $this->colorPallet);

            $this->getLog()->debug('Colour palette is ' . var_export($this->colorPallet, true));
        }

        return $this->colorPallet;
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
            throw new InvalidArgumentException(__('You must enter a duration.'), 'duration');

        if ($this->getWidgetId() != 0) {
            // We must always have an X
            if ($this->getColumnType('x-axis') === null)
                throw new InvalidArgumentException(__('Please make sure you select an X-Axis'), 'config');

            // We must always have a Y
            if ($this->getColumnType('y-axis') === null)
                throw new InvalidArgumentException(__('Please make sure you select an Y-Axis'), 'config');

            // TODO: validate the contents of the config object to ensure we have all we need (an X and Y for example)
            switch ($this->getOption('graphType')) {

                case 'pie':
                case 'doughnut':
                    // Make sure we have an X and a Y and nothing else
                    if ($this->getColumnType('series-identifier') !== null)
                        throw new InvalidArgumentException(__('This type of chart does not support a Series Identifier'), 'config');

                    break;

                case '':
                    throw new InvalidArgumentException(__('Please select a graph type'), 'graphType');
            }
        }

        // depends on whether we have a dataSet yet or not.
        return ($this->hasDataSet()) ? self::$STATUS_VALID : self::$STATUS_INVALID;
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

    /**
     * @inheritdoc
     * @override
     */
    public function getModifiedDate($displayId)
    {
        // Either the Widget Modified Date or the DataSet Edit Date, whichever happens sooner
        $widgetModifiedDt = $this->widget->modifiedDt;

        $dataSetId = $this->getOption('dataSetId');
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        // Set the timestamp
        $widgetModifiedDt = ($dataSet->lastDataEdit > $widgetModifiedDt) ? $dataSet->lastDataEdit : $widgetModifiedDt;

        // Remote dataSets are kept "active" by required files
        $dataSet->setActive();

        return $this->getDate()->parse($widgetModifiedDt, 'U');
    }
}

