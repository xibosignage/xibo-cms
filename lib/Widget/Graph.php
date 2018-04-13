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

use Respect\Validation\Validator as v;
use Xibo\Entity\DataSet;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
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
    
    /**
     * Returns a List of all Columns which can be used to plot
     * @param int $dataSetId the dataset to get all columns from this can be used for the values
     * @return array
     * @throws NotFoundException
     */
    public function selectedValueColumns($dataSetId)
    {
        $data = $this->valueColumns($dataSetId, []);
        $selected = explode(',', $this->getOption('columns', ''));
        return array_intersect($data, $selected);
    }
    
    /**
     * Returns a List of all Columns which can be used to plot
     * @param int $dataSetId the dataset to get all columns from this can be used for the values
     * @param array $selected List of all selected columns which should not be in the resulting list
     * @return array
     * @throws NotFoundException
     */
    public function valueColumns($dataSetId, $selected)
    {
        $data = [];
        $dataSet = $this->dataSetFactory->getById($dataSetId);
        
        // @var DataSetColumn $column
        foreach ($dataSet->getColumn() as $column) {
            // Only selected columns and DataSetColumn->dataTypeId "2" (Number) and "3" (Date) can be processed
            if (($column->dataTypeId == 2) || ($column->dataTypeId == 3)) {
                array_push($data, $column->heading);
            }
        }
        return array_diff($data, $selected);
    }
    
    /**
     * Returns a List of all Columns which can be used for the Labels
     * @return array
     * @throws NotFoundException
     */
    public function labelColumns($dataSetId)
    {
        $data = [];
        $dataSet = $this->dataSetFactory->getById($dataSetId);
        
        // @var DataSetColumn $column
        foreach ($dataSet->getColumn() as $column) {
            // Only selected columns and DataSetColumn->dataTypeId "1" (String), "2" (Number) and "3" (Date) can be processed
            if (($column->dataTypeId == 1) || ($column->dataTypeId == 2) || ($column->dataTypeId == 3)) {
                array_push($data, $column->heading);
            }
        }
        array_unshift($data, '');
        return $data;
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
    }

    /**
     * Adds a Widget
     * @override
     * @throws InvalidArgumentException
     */
    public function add()
    {
        $this->setCommonOptions();
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
        $this->setCommonOptions();
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Set common options from Request Params
     */
    private function setCommonOptions()
    {
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setOption('rendering', $this->getSanitizer()->getString('rendering'));
        $this->setOption('graphType', $this->getSanitizer()->getString('graphType'));
        $this->setOption('backgroundColor', $this->getSanitizer()->getString('backgroundColor'));
        $this->setOption('labelColumn', $this->getSanitizer()->getString('labelColumn', ''));
        $this->setOption('seriesColumn', $this->getSanitizer()->getString('seriesColumn', ''));
        $this->setOption('dataSetId', $this->getSanitizer()->getInt('dataSetId', 0));
        $this->setOption('columns', implode(',', $this->getSanitizer()->getStringArray('columns', '')));

        $this->setOption('showLegend', $this->getSanitizer()->getCheckbox('showLegend', 0));
        $this->setOption('legendCenter', $this->getSanitizer()->getCheckbox('legendCenter', 0));
        $this->setOption('legendX', $this->getSanitizer()->getInt('legendX', 0));
        $this->setOption('legendY', $this->getSanitizer()->getInt('legendY', 0));
        $this->setOption('legendRight', $this->getSanitizer()->getCheckbox('legendRight', 0));
        $this->setOption('legendBottom', $this->getSanitizer()->getCheckbox('legendBottom', 0));
    }

    /**
     * @inheritdoc
     */
    public function getResource($displayId = 0)
    {
        $data = [];
        $graphOptions = [];
        $jsObject = '';
        $containerId = 'graph-' . $displayId;
        $labelColumn = $this->getOption('labelColumn');
        $seriesColumn = $this->getOption('seriesColumn');
        $dataSetId = $this->getOption('dataSetId');
        $selected = explode(',', $this->getOption('columns', ''));
        $graphData = $this->getDataSetContentForRGraph($dataSetId, $selected, $labelColumn, $seriesColumn, "");

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
        $data['head'] = '';
        $data['head']  = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.common.core.js') . '"></script>';
        $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.common.dynamic.js') . '"></script>';
        $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.common.effects.js') . '"></script>';

        // Options for the rendering.
        // May be overridden by the various chart types
        // In future releases this options may be configured by the user
        $graphOptions['shadowBlur'] = '5';
        $graphOptions['shadowOffsetX'] = '10';
        $graphOptions['shadowOffsetY'] = '10';
        $graphOptions['shadowColor'] = '#aaa';
        
        // Processing dependent on the Graph Type
        switch ($this->getOption('graphType')) {
            case 'donut_chart':
                $graphOptions['variant'] = 'donut';
                
            case 'pie_chart':
                $jsObject = 'RGraph.Pie';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.pie.js') . '"></script>';
                $this->regroupData($graphData->data);
                $this->summarizeData($graphData->data);
                
                $graphOptions['exploded'] = '10';
                break;

            case 'bar_chart':
                $jsObject = 'RGraph.Bar';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.bar.js') . '"></script>';
                $this->regroupData($graphData->data);
                break;

            case 'horizontal_bar_chart':
                $jsObject = 'RGraph.HBar';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.hbar.js') . '"></script>';
                $this->regroupData($graphData->data);
                break;

            case 'waterfall_chart':
                $jsObject = 'RGraph.Waterfall';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.waterfall.js') . '"></script>';
                $this->regroupData($graphData->data);
                $this->summarizeData($graphData->data);
                break;

            case 'vertical_progress':
                $jsObject = 'RGraph.Bar';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.bar.js') . '"></script>';
                $this->regroupData($graphData->data);
                break;

            case 'horizontal_progress':
                $jsObject = 'RGraph.HBar';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.hbar.js') . '"></script>';
                $this->regroupData($graphData->data);
                break;

            case 'radar_chart':
                $jsObject = 'RGraph.Radar';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.radar.js') . '"></script>';
                break;

            case 'line_chart':
            default:
                $jsObject = 'RGraph.Line';
                $data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.line.js') . '"></script>';
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
     * 
     * @param int $dataSetId The ID of the Dataset to visualize
     * @param array $columns The Columns where the values are in
     * @param string $labelCol The Column where the Labels for the X-Axis are saved in
     * @param string $seriesCol The Column where to group the data by
     * @param string $orderBy Order by this column - may be null
     * @return Object { data: [], labels: [], legend: [] }
     */
    protected function getDataSetContentForRGraph($dataSetId, $columns = [], $labelCol = "", $seriesCol = "", $orderBy = "")
    {
        $graphData = (object)[];
        $graphData->data = [];
        $graphData->labels = [];
        $graphData->series = [];
        $graphData->legend = [];
        
        try {
            $dataSet = $this->dataSetFactory->getById($dataSetId);
            $data = $dataSet->getData(empty($orderBy) ? [] : ['order' => $orderBy], ['requireTotal' => true]);
            
            $graphData->series = $this->extractUniqueValues($data, $seriesCol);
            $graphData->legend = $this->prepareLegend($dataSet, $columns, $graphData->series, $labelCol);
            $graphData->labels = $this->extractUniqueValues($data, $labelCol);
            $graphData->data = $this->prepareData($data, $columns, $labelCol, $seriesCol);
        } catch (NotFoundException $e) {
            // In case there is a datset to be displayed what does not exists (deleted or so)
            $graphData->data[0][] = 0;
            $graphData->legend[] = 'Unknown Dataset';
            $graphData->labels[] = '';
        }
        return $graphData;
    }
    
    /**
     * Extracts all Columns to be shown and returns them for the legend
     * 
     * @param \Xibo\Entity\DataSet &$dataSet Reference to the DataSet to get the Column-Names from
     * @param array &$columns Reference to all columns to show
     * @param array $series Series identifier to append to the Legend
     * @param string $labelCol Name of the Column which holds the labels and therefore should not be displayed
     * @return array of column names for the legend
     * @throws NotFoundException
     */
    protected function prepareLegend(\Xibo\Entity\DataSet &$dataSet, array &$columns, $series = [], $labelCol = '')
    {
        $result = [];
        if (empty($series)) {
            $series[] = NULL;
        }
        foreach ($series as $identifier) {
            // @var DataSetColumn $column
            foreach ($dataSet->getColumn() as $column) {
                // Only selected columns and DataSetColumn->dataTypeId "2" (Number) and "3" (Date) can be processed
                if ((empty($columns) || in_array($column->heading, $columns))
                    && (($column->dataTypeId == 2) || ($column->dataTypeId == 3))
                    && ($column->heading != $labelCol)) {
                    // Prepand the series identifier if at least one is present
                    if ($identifier == NULL) {
                        array_push($result, $column->heading);
                    } else {
                        array_push($result, $identifier . self::SERIES_IDENTIFIER_SEPARATOR . $column->heading);
                    }
                } else if (in_array($column->heading, $columns)) {
                  unset($columns[array_search($column->heading, $columns)]);
                }
            }
        }
        return $result;
    }
    
    /**
     * Returns a list of all unique values on the given column
     * 
     * @param array $data Reference to all data from the DataSet
     * @param string $column Name of the Column which holds the values
     * @return array List of all values only once
     */
    protected function extractUniqueValues(&$data, $column = '')
    {
        $result = [];
        if (!empty($column)) {
            foreach ($data as $k => $row) {
                if (array_key_exists($column, $row)) {
                    array_push($result, $row[$column]);
                }
            }
        }
        reset($data);
        return array_values(array_unique($result));
    }

    /**
     * Returns a list of all data from the requested columns
     * 
     * This creates a summarized list of data, grouped by the value in the label column
     *   LBL | A | B |
     *  -----+---+---+
     *   aa  | a | b |
     *   aa  | c | d |
     *   bb  | e | f |
     *   cc  | g | h |
     * 
     * Create an array like [ [a+c, e, g], [b+d, f, h] ]
     * This is: Each Column has one block; each LBL has one number in each block
     * 
     * 
     * If there is also a series defined, the grouping will be like this:
     *   LBL | SER | A | B |
     *  -----+-----+---+---+
     *   aa  | AA  | a | b |
     *   aa  | BB  | c | d |
     *   bb  | AA  | e | f |
     *   bb  | BB  | g | h |
     *   cc  | AA  | i | j |
     *   cc  | AA  | k | l |
     *             
     * Create an array like [ [a, e, i+k], [b, f, j+l], [c, g], [d, h] ]
     * This is: Each Column+SER has one block; each LBL+SER has one number in each block
     * 
     * @param array &$data Reference to all data from the DataSet
     * @param array &$columns Reference to the list of all columns to be shown
     * @param string $labelCol Name of the Column which holds the labels
     * @param string $seriesCol Name of Column where the series identifier is in
     * @return array List of all data grouped by columns
     */
    protected function prepareData(&$data, &$columns, $labelCol = '', $seriesCol = '')
    {
        $result = [];
        $keys = [];
        foreach ($data as $k => $row) {
            // Get the current key to identify the position in the values list.
            // If there is a series identifier, this is appended to the main key to seperate the series
            $series = '';
            if (!empty($seriesCol) && array_key_exists($seriesCol, $row)) {
                $series = self::SERIES_IDENTIFIER_SEPARATOR . $row[$seriesCol];
            }
            
            // Getting the position to add the current column-values in the resulting list
            // If there is label, try to find out if it was already processed to summarize the previous values
            $key = empty($labelCol) ? $k : $row[$labelCol];
            if (!array_key_exists($series, $keys)) {
                $keys[$series] = [];
            }
            if (in_array($key, $keys[$series])) {
                $pos = array_search($key, $keys[$series]);
            } else {
                $pos = count($keys[$series]);
                array_push($keys[$series], $key);
            }
            
            // Go through each column and add or append the value from the current row to the correct index
            foreach ($columns as $c => $column) {
                $col = $column . $series;
                if (!array_key_exists($col, $result)) {
                    $result[$col] = [];
                }
                
                // Fill up with ZERO
                if (count($result[$col]) < $pos) {
                    $result[$col] = array_merge($result[$col], array_fill(0, $pos, 0));
                }
                
                // If there is already a number on the current index, summarize the values; otherwise add the value
                if (array_key_exists($pos, $result[$col])) {
                    $result[$col][$pos] = $result[$col][$pos] + floatval($row[$column]);
                } else {
                    $result[$col][$pos] = floatval($row[$column]);
                }
            }
        }
        reset($data);
        return array_values($result);
    }
    
    /**
     * This method regroups the data based on the index of the values in each row into a row based on the position of the value in the row.
     * Example: $data = [ [ 1, 2, 3 ], [ 4, 5, 6 ] ]
     * Result:  $data = [ [ 1, 4 ], [ 2, 5 ], [ 3, 6 ] ]
     * This is needed for Bar-Charts and others which needs this kind of data representation
     * 
     * @param array &$data Reference to the data list to regroup
     */
    protected function regroupData(array &$data)
    {
        if (count($data) <= 0) {
            return;
        }
        
        $copy = $data;
        if (!is_array($data[0])) {
            $copy = [ $data ];
        }
        $data = [];
        
        foreach ($copy as $k => $row) {
            foreach ($row as $col => $val) {
                if (!array_key_exists($col, $data)) {
                    $data[$col] = [];
                }
                $data[$col][$k] = $val;
            }
        }
    }
    
    /**
     * This method summarizes all values on each index (and subindex) and returns a flat list
     * Example: $data = [ [ 1, 4 ], [ 2, 5 ], [ 3, 6 ] ]
     * Result:  $data = [ 1+2+3, 4+5+6 ]
     * This is needed for example for the Pie-/Donut-Chart, ...
     * 
     * @param array &$data Reference to the data list to summarize
     */
    protected function summarizeData(array &$data)
    {
        if ((count($data) <= 0) || !is_array($data[0])) {
            return;
        }
        $result = [];
        foreach ($data as $k => $row) {
            foreach ($row as $col => $val) {
                if (!array_key_exists($col, $result)) {
                    $result[$col] = 0;
                }
                $result[$col] += $val;
            }
        }
        $data = $result;
    }
    
    /**
     * @inheritdoc
     */
    public function IsValid()
    {
        // We can be sure because every WebPlayer should render this graph corectly
        return 1;
    }
}

