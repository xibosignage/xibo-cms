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
    const DEFAULT_COLORS = '#7293CB, #E1974C, #84BA5B, #D35E60, #808585, #9067A7, #AB6857, #CCC210, #396AB1, #DA7C30, #3E9651, #CC2529, #535154, #6B4C9A, #922428, #948B3D';
    const SERIES_IDENTIFIER_SEPARATOR = ': ';
    
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
     * Install all Javascript-Files provided by Graph
     */
    public function installFiles()
    {
        $sourcePath = PROJECT_ROOT . '/modules/vendor/rgraph/';
        $dir = opendir($sourcePath);
        while ($dir && ($file = readdir($dir)) !== false) {
            if (substr($file, -3) == '.js') {
                $this->mediaFactory->createModuleSystemFile($sourcePath . $file)->save();
            }
        }
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
     */
    public function getResource($displayId = 0)
    {
        $data = [];
        $graphOptions = [];
        $containerId = 'graph-' . $displayId;

        $this->getLog()->debug('Render graph for widgetId: ' . $this->getWidgetId() . ' and displayId: ' . $displayId);

        // Replace the View Port Width if we are in Preview-Mode
        $data['viewPortWidth'] = ($this->getSanitizer()->getCheckbox('preview') == 1) ? $this->region->width : '[[ViewPortWidth]]';

        // Options XIBO needs for rendering - see JavaScript at the end of this function
        $options = array(
            'type' => $this->getModuleType(),
            'speed' => $this->getOption('speed', 500),
            'useDuration' => $this->getUseDuration(),
            'duration' => $this->getDuration(),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'previewWidth' => $this->getSanitizer()->getDouble('width', 0),
            'previewHeight' => $this->getSanitizer()->getDouble('height', 0),
            'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0),
        );

        // Head Content contains all needed scrips from Graph
        $data['head']  = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.common.core.js') . '"></script>';
        $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.common.dynamic.js') . '"></script>';
        $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.common.effects.js') . '"></script>';

        // Options for the rendering.
        // May be overridden by the various chart types
        // TODO: In future releases this options may be configured by the user
        $graphOptions['shadowBlur'] = '5';
        $graphOptions['shadowOffsetX'] = '10';
        $graphOptions['shadowOffsetY'] = '10';
        $graphOptions['shadowColor'] = '#aaa';
        
        // Processing dependent on the Graph Type
        switch ($this->getOption('graphType')) {
            case 'donut_chart':
                // Fall through on purpose
                $graphOptions['variant'] = 'donut';
            case 'pie_chart':
                $jsObject = 'RGraph.Pie';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.pie.js') . '"></script>';

                $graphOptions['exploded'] = '10';

                // Get the graph data
                $graphData = $this->getDataSetContentForRGraph();
                break;

            case 'bar_chart':
                $jsObject = 'RGraph.Bar';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.bar.js') . '"></script>';
                // Get the graph data
                $graphData = $this->getDataSetContentForRGraph(true);
                break;

            case 'horizontal_bar_chart':
                $jsObject = 'RGraph.HBar';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.hbar.js') . '"></script>';
                // Get the graph data
                $graphData = $this->getDataSetContentForRGraph(true);
                break;

            case 'waterfall_chart':
                $jsObject = 'RGraph.Waterfall';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.waterfall.js') . '"></script>';
                // Get the graph data
                $graphData = $this->getDataSetContentForRGraph();
                break;

            case 'vertical_progress':
                $jsObject = 'RGraph.VProgress';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.vprogress.js') . '"></script>';
                // Get the graph data
                $graphData = $this->getDataSetContentForRGraph();
                $graphOptions['min'] = '0';
                $graphOptions['max'] = '100';
                break;

            case 'horizontal_progress':
                $jsObject = 'RGraph.HProgress';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.hprogress.js') . '"></script>';
                // Get the graph data
                $graphData = $this->getDataSetContentForRGraph();
                $graphOptions['min'] = '0';
                $graphOptions['max'] = '100';
                break;

            case 'radar_chart':
                $jsObject = 'RGraph.Radar';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.radar.js') . '"></script>';
                // Get the graph data
                $graphData = $this->getDataSetContentForRGraph();
                break;

            case 'line_chart':
            default:
                $jsObject = 'RGraph.Line';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.line.js') . '"></script>';
                // Get the graph data
                $graphData = $this->getDataSetContentForRGraph();
                break;
        }

        $data['head'] .= '
            <style type="text/css">
                .graphLegend { 
                    position:absolute;
                    display:inline-block;
                    z-index:9999;
                    text-align:left;
                    border: 1px solid #ddd; 
                    box-shadow: 1px 1px 2px #ccc;
                    padding:0.5em 0.8em;
                    line-height:1.8em; 
                } 
                .graphLegend div { 
                    font-weight:bold; 
                } 
                .legendWrapper { 
                    width:100%;
                    top:0;
                    left:0;
                    text-align:center; 
                }
            </style>';

        // Background for the Graph and the legend
        $backgroundColor = $this->getOption('backgroundColor');
        $backgroundColor = ($backgroundColor == '') ? '' : 'background-color: ' . $backgroundColor . ';';
        
        // Preparing the Legend
        $legend = '';
        $javaScriptLegend = '';
        if (((int)$this->getOption('showLegend') > 0) && !empty($graphData->legend)) {
            $legendStyle = '';
            
            // Horizontal alignment
            if ($this->getOption('legendRight') == 1) {
                $legendStyle .= 'right:' . $this->getOption('legendX') . 'px;';
            } else if ($this->getOption('legendCenter') == 0) {
                $legendStyle .= 'left:' . $this->getOption('legendX') . 'px;';
            }
            
            // Vertical alignment
            if ($this->getOption('legendBottom') == 1) {
                $legendStyle .= 'bottom:' . $this->getOption('legendY') . 'px;';
            } else {
                $legendStyle .= 'top:' . $this->getOption('legendY') . 'px;';
            }
            $legend = '<div id="' . $containerId . '_legend" class="graphLegend" style="' . $legendStyle . ';' . $backgroundColor . '"></div>';
            
            if ($this->getOption('legendCenter') == 1) {
                $legend = '<div class="legendWrapper">' . $legend . '</div>';
            }
            
            $javaScriptLegend = '
                  if (typeof data.legend == "object") {
                      for (var i = 0; i < data.legend.length; i++) {
                          $("#' . $containerId . '_legend").append("<div style=\'color:" + graphOptions.colors[i%graphOptions.colors.length] + ";\'>" + data.legend[i] + "</div>");
                      }
                  }';
        }

        // Body content
        $data['body'] = '
            <div id="' . $containerId . '" style="' . $backgroundColor . '">
                <canvas id="' . $containerId . '_graph" width="' . $this->region->width . '" height="' . $this->region->height . '" style="border: 1px solid #ddd; box-shadow: 1px 1px 2px #ccc">[No Canvas support]</canvas>
            ' . $legend . '
            </div>
        ';

        if (!empty($graphData->data)) {
            // After body content - mostly XIBO-Stuff for scaling and so on
            $javaScriptContent  = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';
            $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';

            // Add all Chart Javascript
            $javaScriptContent .= '<script>
              $(document).ready(function() {
                  var options = ' . json_encode($options) . '
                  $("#' . $containerId . '").xiboLayoutScaler(options);

                  var graphOptions = ' . json_encode((object)$graphOptions) . ';
                  graphOptions.colors = ["' . str_replace(',', '","', $this->getOption('defaultColors', self::DEFAULT_COLORS)) . '"];

                  var data = ' . json_encode($graphData) . ';
                  ' . $javaScriptLegend . '
                  graphOptions.xaxisLabels = data.labels;
                  graphOptions.yaxisLabels = data.labels;
                  graphOptions.labels = data.labels;
                  graphOptions.title = "' . $this->getOption('name') . '";

                  new ' . $jsObject . '({
                      id: "' . $containerId . '_graph",
                      data: data.data,
                      options: graphOptions
                  }).draw();
              });</script>';

            // Replace the After body Content
            $data['body'] .= $javaScriptContent;
        }

        return $this->renderTemplate($data);
    }

    /**
     * Load all possible Columns and data from the selected DataSet
     * @param $grouped bool Should the results be grouped
     * @return Object { data: [], labels: [], series: [], legend: [] }
     */
    protected function getDataSetContentForRGraph($grouped = false)
    {
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
        $graphData->data = [];
        $graphData->labels = [];
        $graphData->legend = [];
        
        try {
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
            $graphData->labels = array_keys($data);

            // Set our legend to be the y-axis names
            // (we might override this when we come to fill out the data)
            $graphData->legend = $yAxis;

            // Each key should give us a "value" (perhaps an array) to add to the data array
            // we should consider each y-axis when we do this
            // Line chart: [ y1:[x1, x2, x3], y2:[x1, x2, x3] ]
            // Bar chart: [ x1:[y1, y2], x2: [y1, y2], x3: [y1, y2] ]
            $axisData = [];

            if (!$grouped) {
                // Reset the legend if we have a series identifier (because we will have a legend item per series)
                if ($seriesIdentifier !== null) {
                    $graphData->legend = [];
                }

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
                            // Output the keys as the legend
                            $graphData->legend[] = $key . self::SERIES_IDENTIFIER_SEPARATOR . $axis;

                            // Values
                            $axisData[] = array_values($value);
                        }
                    } else {
                        $axisData[] = array_values($axisDataTemp);
                    }
                }
            } else {
                $alternateLegend = [];

                foreach ($data as $key => $rows) {
                    // This happens for each x-axis
                    // for every y axis we've configured, go through the rows for this x-axis and work out
                    // what the value should be.
                    $axisDataTemp = [];
                    foreach ($yAxis as $axis) {
                        // We should keep a SUM for each yAxis
                        foreach ($rows as $row) {
                            if ($seriesIdentifier !== null) {
                                // We want a SUM for each series identifier within this axis
                                foreach ($seriesIdentifier as $identifier) {
                                    // Keep track of the axis we're on
                                    $axisDataTemp[$axis . $row[$identifier]] = (isset($axisDataTemp[$axis . $row[$identifier]])) ? $axisDataTemp[$axis . $row[$identifier]] + $row[$axis] : $row[$axis];

                                    // Make use of this logic to maintain an alternative legend
                                    if (!in_array($row[$identifier], $alternateLegend))
                                        $alternateLegend[] = $row[$identifier];
                                }
                            } else {
                                $axisDataTemp[$axis] = (isset($axisDataTemp[$axis])) ? $axisDataTemp[$axis] + $row[$axis] : $row[$axis];
                            }
                        }
                    }

                    $this->getLog()->debug('Axis Data Temp is: ' . json_encode($axisDataTemp, JSON_PRETTY_PRINT));

                    $axisData[] = array_values($axisDataTemp);
                }

                // Override the legend if necessary
                if (count($alternateLegend) > 0) {
                    $graphData->legend = $alternateLegend;
                }
            }

            $graphData->data = (count($axisData) == 1) ? $axisData[0] : $axisData;

            $this->getLog()->debug(json_encode($graphData, JSON_PRETTY_PRINT));


        } catch (NotFoundException $e) {
            // In case there is a DataSet to be displayed what does not exists (deleted or so)
            $graphData->data[0][] = 0;
            $graphData->legend[] = 'Unknown DataSet';
            $graphData->labels[] = '';
        }

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
        return Date::now();
    }
}

