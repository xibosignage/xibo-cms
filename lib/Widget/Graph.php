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
 */
namespace Xibo\Widget;

use Respect\Validation\Validator as v;
use Xibo\Entity\DataSet;
use Xibo\Entity\DataSetColumn;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\ModuleFactory;

class Graph extends ModuleWidget
{
	const DEFAULT_COLORS = '#7293CB, #E1974C, #84BA5B, #D35E60, #808585, #9067A7, #AB6857, #CCC210, #396AB1, #DA7C30, #3E9651, #CC2529, #535154, #6B4C9A, #922428, #948B3D';
	
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
	public function dataSets() {
		$result = $this->dataSetFactory->query();
		array_unshift($result, []);
		return $result;
	}

    /**
     * Get the currently selected DataSetIds
     * @return int[]
     */
	public function dataSetIds()
    {
        $ids = unserialize($this->getOption('dataSetIds'));
        $labels = unserialize($this->getOption('dataSetLabels'));
        $result = [];
        foreach ($ids as $k => $v) {
            $result[] = ['selected' => $v, 'label' => $labels[$k]];
        }
        return $result;
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

        $ids = $this->getSanitizer()->getIntArray('dataSetIds');
        $labels = $this->getSanitizer()->getStringArray('dataSetLabels');
        foreach ($ids as $k => $v) {
            if (empty($v)) {
                unset($ids[$k]);
                unset($labels[$k]);
            }
        }
        $this->setOption('dataSetIds', serialize($ids));
        $this->setOption('dataSetLabels', serialize($labels));
        $this->setOption('maxdata', $this->getSanitizer()->getInt('maxdata', 180));

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
	public function preview($width, $height, $scaleOverride = 0)
    {
		return $this->previewAsClient($width, $height, $scaleOverride);
	}

	/**
	 * @inheritdoc
	 */
	public function getResource($displayId = 0)
    {
		$data = [];
		$containerId = 'graph-' . $displayId;
		$dataSetIds = unserialize($this->getOption('dataSetIds'));
		$dataLabels = unserialize($this->getOption('dataSetLabels'));
		$maxData = $this->getOption('maxdata', 180);
		$graphData = $this->getDataSetContentForRGraph($dataSetIds, $dataLabels, $maxData);

		// Replace the View Port Width if we are in Preview-Mode
		$data['viewPortWidth'] = ($this->getSanitizer()->getCheckbox('preview') == 1) ? $this->region->width : '[[ViewPortWidth]]';

		// Options XIBO needs for rendering - see JavaScript at the end of this function
		$options = array(
			'type' => $this->getModuleType(),
			'fx' => $this->getOption('effect', 'noAnim'),
			'speed' => $this->getOption('speed', 500),
			'useDuration' => $this->getUseDuration(),
			'duration' => $this->getDuration(),
			'originalWidth' => $this->region->width,
			'originalHeight' => $this->region->height,
			'previewWidth' => $this->getSanitizer()->getDouble('width', 0),
			'previewHeight' => $this->getSanitizer()->getDouble('height', 0),
			'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0),
		);
		
		// Use SVG or Canvas?
		$svg = $this->getOption('rendering') == 'render_with_svg' ? '.svg' : '';

		// Head Content contains all needed scrips from Graph
		$data['head'] = '';
		if ($this->getOption('rendering') == 'render_with_svg') {
			$data['head']  = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.SVG.common.core.js') . '"></script>';
			$data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.SVG.common.ajax.js') . '"></script>';
			$data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.SVG.common.fx.js') . '"></script>';
		} else {
			$data['head']  = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.common.core.js') . '"></script>';
			$data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.common.dynamic.js') . '"></script>';
			$data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph.common.effects.js') . '"></script>';
		}

		// Processing dependent on the Graph Type
		switch ($this->getOption('graphType')) {
			case 'pie_chart':
				$jsObject = 'RGraph.Pie';
				$data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph' . $svg . '.pie.js') . '"></script>';
				$graphData->data = $graphData->data[0];
				break;

			case 'bar_chart':
				$jsObject = 'RGraph.Bar';
				$data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph' . $svg . '.bar.js') . '"></script>';
				$this->swapLabelAndLegend($graphData);
				break;

			case 'horizontal_bar_chart':
				$jsObject = 'RGraph.HBar';
				$data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph' . $svg . '.hbar.js') . '"></script>';
				$this->swapLabelAndLegend($graphData);
				break;

			case 'waterfall_chart':
				$jsObject = 'RGraph.Waterfall';
				$data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph' . $svg . '.waterfall.js') . '"></script>';
				$graphData->data = $graphData->data[0];
				break;

			case 'circular_progress':
				$jsObject = 'RGraph.SemiCircularProgress';
				$data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph' . $svg . '.semicircularprogress.js') . '"></script>';
				$this->swapLabelAndLegend($graphData);
				break;

			case 'vertical_progress':
				$jsObject = 'RGraph.Bar';
				$data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph' . $svg . '.bar.js') . '"></script>';
				$this->swapLabelAndLegend($graphData);
				break;

			case 'horizontal_progress':
				$jsObject = 'RGraph.HBar';
				$data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph' . $svg . '.hbar.js') . '"></script>';
				$this->swapLabelAndLegend($graphData);
				break;

			case 'radar_chart':
				$jsObject = 'RGraph.Radar';
				$data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph' . $svg . '.radar.js') . '"></script>';
				break;

			case 'scatter_chart':
				$jsObject = 'RGraph.Scatter';
				$data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph' . $svg . '.scatter.js') . '"></script>';
				break;

			case 'line_chart':
			default:
				$jsObject = 'RGraph.Line';
				$data['head'] .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/rgraph/RGraph' . $svg . '.line.js') . '"></script>';
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
		if ($this->getOption('showLegend') == 1) {
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
		}

		// Body content
		$data['body'] = '
		    <div id="' . $containerId . '" style="' . $backgroundColor . '">
			    <canvas id="' . $containerId . '_graph" width="' . $this->region->width . '" height="' . $this->region->height . '" style="border: 1px solid #ddd; box-shadow: 1px 1px 2px #ccc">[No Canvas support]</canvas>
			' . $legend . '
		    </div>
		';

		// After body content - mostly XIBO-Stuff for scaling and so on
		$javaScriptContent  = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';
		$javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';

		// Add all Chart Javascript
		$javaScriptContent .= '<script>
			$(document).ready(function() {
				var options = ' . json_encode($options) . '
				$("#' . $containerId . '").xiboLayoutScaler(options);

				var graphOptions = ' . json_encode($this->getSanitizer()->getParam($jsObject, (object) array())) . ';
				graphOptions.colors = ["' . str_replace(',', '","', $this->getOption('defaultColors', self::DEFAULT_COLORS)) . '"];

				var data = ' . json_encode( $graphData ) . ';';

		if ((int)$this->getOption('showLegend') > 0) {
			$javaScriptContent .= '
				if (typeof data.legend == "object") {
					for (var i = 0; i < data.legend.length; i++) {
						$("#' . $containerId . '_legend").append("<div style=\'color:" + graphOptions.colors[i%graphOptions.colors.length] + ";\'>" + data.legend[i] + "</div>");
					}
				}';
		}

		$javaScriptContent .= '
				graphOptions.xaxisLabels = data.labels;
				graphOptions.yaxisLabels = data.labels;
				graphOptions.labels = data.labels;
				graphOptions.title = "' . $this->getOption('name') . '";

				var line = new ' . $jsObject . '({
					id: "' . $containerId . '_graph",
					data: data.data,
					options: graphOptions
				}).draw();
			});</script>';

		// Replace the After body Content
		$data['body'] .= $javaScriptContent;

		return $this->renderTemplate($data);
	}

	/**
	 * Load all possible Columns and data from the selected DataSet
	 * 
	 * @param array $dataSetIds The IDs of the Datasets to visualize
	 * @param array $labelCols The Column where the Labels for the X-Axis are saved in
     * @param int $maxData Maximum Datapoints to read
	 * @return Object { data: [], labels: [], legend: [] }
	 */
	protected function getDataSetContentForRGraph($dataSetIds, $labelCols, $maxData)
    {
        $maxData = abs($maxData);
		$graphData = (object)[];
		$graphData->data = [];
		$graphData->labels = [];
		$graphData->legend = [];

		try {
			// Get all Headers to show as different Data-Streams
			$columns = [];
			$maxColumns = 0;
			foreach ($dataSetIds as $k => $id) {
				if (empty(trim($id))) continue;
				$dataSet = $this->dataSetFactory->getById($id);

				/* @var DataSetColumn $column */
				foreach ($dataSet->getColumn() as $column) {
					// DataSetColumn->dataTypeId "2" (Number) and "3" (Date) can be processed
					if (($column == $labelCols[$k]) || (($column->dataTypeId != 2) && ($column->dataTypeId != 3)) ) {
						continue;
					}
					$graphData->legend[] = $dataSet->dataSet . ': ' . $column->heading;
					$columns[$dataSet->dataSetId][] = $column->heading;
				}
				$maxColumns = max([$maxColumns, count($columns[$dataSet->dataSetId])]);
			}

			foreach ($dataSetIds as $k => $id) {
				if (empty(trim($id))) continue;
				$dataSet = $this->dataSetFactory->getById($id);
				$columnOffset = count($graphData->data);

				// Get the total number of data to limit to the last $maxData entries
				$data = $dataSet->getData([], ['requireTotal' => true]);
				$filter = [
				  'size' => $maxData,
				  'start' => max(0, $dataSet->countLast() - $maxData)
				];
        
				// Add Labels and Data
				foreach ($dataSet->getData($filter, ['requireTotal' => true]) as $c => $row) {
					if (!array_key_exists($c, $graphData->labels)) {
						$graphData->labels[$c] = '' . (!empty($labelCols[$k]) ? $row[$labelCols[$k]] : $c);
					}
					
					// Add all Data
					foreach ($columns[$dataSet->dataSetId] as $k => $column) {
						$graphData->data[$columnOffset + $k][] = $row[$column];
					}
				}
			}
		} catch (NotFoundException $e) {
			// In case there is a datset to be displayed what does not exists (deleted or so)
			$graphData->data[0][] = 0;
			$graphData->legend[] = 'Unknown Dataset';
			$graphData->labels[] = '';
		}
		return $graphData;
	}
	
	/**
	 * Some Charts need the Labels and Legend Texts to be swapped
	 *
	 * @param Object $graphData
	 */
	protected function swapLabelAndLegend($graphData)
    {
		$labels = $graphData->labels;
		$legend = $graphData->legend;
		$graphData->labels = $legend;
		$graphData->legend = $labels;
	}
	
	/**
	 * @inheritdoc
	 */
	public function IsValid()
    {
		// Can't be sure because the client does the rendering
		return 2;
	}
}

