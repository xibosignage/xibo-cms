<?php
/*
 * Graph Xibo Module
 * Copyright (C) 2018 Lukas Zurschmiede
 *
 * This Xibo-Module is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * This Xibo-Module is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with This Xibo-Module.  If not, see <http://www.gnu.org/licenses/>.
 * 
 */

/**
 * This module depends on RGraph <https://www.rgraph.net/>
 * For later releases see <https://www.rgraph.net/demos/index.html#canvas>
 *  - Options for the different chart types
 *  - Effects for the different chart types
 *  - Combine multiple graphs: Line and Line, Line and Bar, Pie and Donut and Donut, ...
 */
namespace Xibo\Widget;

use Jenssegers\Date\Date;
use Respect\Validation\Validator as v;
use Xibo\Entity\DataSet;
use Xibo\Entity\DataSetColumn;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\ModuleFactory;

class Graph extends ModuleWidget
{
    const SERIES_IDENTIFIER_SEPARATOR = ': ';
    private static $defaultColors = ['#7293CB', '#E1974C', '#84BA5B', '#D35E60', '#808585', '#9067A7', '#AB6857', '#CCC210',
        '#396AB1', '#DA7C30', '#3E9651', '#CC2529', '#535154', '#6B4C9A', '#922428', '#948B3D'];

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
            $module->name = 'Graph';
            $module->type = 'graph';
            $module->class = 'Xibo\Widget\Graph';
            $module->description = 'Graphical data visualization';
            $module->imageUri = 'forms/library.gif';
            $module->enabled = 1;
            $module->previewEnabled = 1;
            $module->assignable = 1;
            $module->regionSpecific = 1;
            $module->renderAs = 'html';
            $module->schemaVersion = $this->codeSchemaVersion;
            $module->defaultDuration = 240;
            $module->settings = [];

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
        return 'graph-form-settings';
    }
    
    /**
     * @return string
     */
    public function layoutDesignerJavaScript()
    {
        return 'graph-designer-javascript';
    }

    /**
     * Process any module settings
     * @return array An array of the processed settings.
     * @override
     */
    public function settings()
    {
        $this->module->settings['defaultColors'] = $this->getSanitizer()->getString('defaultColors', self::DEFAULT_COLORS);
        return $this->module->settings;
    }

    /**
     * Used by the TWIG template to show a list of available dataSets
     * @return DataSet[]
     */
    public function dataSets()
    {
        return $this->dataSetFactory->query();
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
     * Get Extra content for the form
     * @return array
     * @throws NotFoundException
     */
    public function getExtra()
    {
        return [
            'config' => $this->getColumnConfig(),
            'columns' => $this->dataSetColumns(),
            'dataSet' => ($this->getOption('dataSetId', 0) != 0) ? $this->dataSetFactory->getById($this->getOption('dataSetId')) : null
        ];
    }

    /**
     * Validates the settings
     * @override
     * @throws InvalidArgumentException
     */
    public function validate()
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

                case '':
                    throw new InvalidArgumentException(__('Please select a graph type'), 'graphType');
            }
        }
    }

    /**
     * Adds a Widget
     * @override
     * @throws XiboException
     */
    public function add()
    {
        $this->setOption('dataSetId', $this->getSanitizer()->getInt('dataSetId', 0));

        // Check we have permission to use this DataSetId
        if (!$this->getUser()->checkViewable($this->dataSetFactory->getById($this->getOption('dataSetId'))))
            throw new InvalidArgumentException(__('You do not have permission to use that DataSet'), 'dataSetId');

        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setUseDuration(0);
        $this->setDuration($this->getModule()->defaultDuration);

        $this->validate();
        $this->saveWidget();
    }

    /**
     * Edit the Widget
     * @override
     * @throws InvalidArgumentException
     */
    public function edit()
    {
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));

        $this->setOption('graphType', $this->getSanitizer()->getString('graphType'));

        $this->setOption('backgroundColor', $this->getSanitizer()->getString('backgroundColor'));
        $this->setOption('showLegend', $this->getSanitizer()->getCheckbox('showLegend', 0));
        $this->setOption('legendCenter', $this->getSanitizer()->getCheckbox('legendCenter', 0));
        $this->setOption('legendX', $this->getSanitizer()->getInt('legendX', 0));
        $this->setOption('legendY', $this->getSanitizer()->getInt('legendY', 0));
        $this->setOption('legendRight', $this->getSanitizer()->getCheckbox('legendRight', 0));
        $this->setOption('legendBottom', $this->getSanitizer()->getCheckbox('legendBottom', 0));

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


        $this->validate();
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
            'originalHeight' => $this->region->height,
            'previewWidth' => $this->getSanitizer()->getDouble('width', 0),
            'previewHeight' => $this->getSanitizer()->getDouble('height', 0),
            'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0),
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
        $chartData = $this->getChartConfig();
        $chartData->type = $this->getOption('graphType');

        if ($this->getOption('showLegend') !== '1') {
            $chartData->options = new \stdClass();
            $chartData->options->legend = new \stdClass();
            $chartData->options->legend->display = false;
        }

        $this->getLog()->debug(json_encode($chartData, JSON_PRETTY_PRINT));

        // Add all Chart Javascript
        $javaScriptContent .= '<script>
            $(document).ready(function() {                
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
     * @return Object { data: { datasets: [], labels: [] } }
     * @throws NotFoundException
     */
    protected function getChartConfig()
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

        $orderBy = $this->getOption('orderBy', null);

        // Set some query options
        $queryOptions = [];
        if (!empty($orderBy)) {
            $queryOptions['orderBy'] = $orderBy;
        }

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
                        $temp['borderColor'] = self::$defaultColors[count($axisData)];
                    } else {
                        $temp['backgroundColor'] = self::$defaultColors[count($axisData)];
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
                    $temp['borderColor'] = self::$defaultColors[count($axisData)];
                } else if ($chartType === 'pie' || $chartType === 'doughnut') {
                    for ($i = 0; $i < count($axisDataTemp); $i++) {
                        $temp['backgroundColor'][] = self::$defaultColors[$i];
                    }
                } else {
                    $temp['backgroundColor'] = self::$defaultColors[count($axisData)];
                }

                $axisData[] = $temp;
            }
        }

        $graphData->data->datasets = $axisData;

        return $graphData;
    }
    
    /** @inheritdoc */
    public function isValid()
    {
        // We can be sure because every WebPlayer should render this graph corectly
        return 1;
    }

    /**
     * @inheritdoc
     * @override
     */
    public function getModifiedDate($displayId)
    {
        // TODO: this is only here for testing
        return Date::now();

        $widgetModifiedDt = null;

        $dataSetId = $this->getOption('dataSetId');
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        // Set the timestamp
        $widgetModifiedDt = $dataSet->lastDataEdit;

        // Remote dataSets are kept "active" by required files
        if ($dataSet->isRemote) {
            // Touch this dataSet
            $dataSetCache = $this->getPool()->getItem('/dataset/accessed/' . $dataSet->dataSetId);
            $dataSetCache->set('true');
            $dataSetCache->expiresAfter($this->getSetting('REQUIRED_FILES_LOOKAHEAD') * 1.5);
            $this->getPool()->saveDeferred($dataSetCache);
        }

        return $widgetModifiedDt;
    }
}

