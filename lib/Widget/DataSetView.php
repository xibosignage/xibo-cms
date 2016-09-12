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

use InvalidArgumentException;
use Respect\Validation\Validator as v;
use Xibo\Entity\DataSetColumn;
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
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/vendor/jquery-1.11.1.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/vendor/jquery-cycle-2.1.6.min.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/xibo-layout-scaler.js')->save();
        $this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/web/modules/xibo-dataset-render.js')->save();
    }

    /**
     * @return string
     */
    public function layoutDesignerJavaScript()
    {
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

        foreach ($columns as $column) {
            /* @var DataSetColumn $column */
            if (in_array($column->dataSetColumnId, $colIds))
                $columnsSelected[] = $column;
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
     * Loads templates for this module
     */
    private function loadTemplates()
    {
        $this->module->settings['templates'] = [];

        // Scan the folder for template files
        foreach (glob(PROJECT_ROOT . '/modules/datasetview/*.template.json') as $template) {
            // Read the contents, json_decode and add to the array
            $this->module->settings['templates'][] = json_decode(file_get_contents($template), true);
        }

        $this->getLog()->debug(count($this->module->settings['templates']));
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
        return [
            'templates' => $this->templatesAvailable(),
            'orderClause' => $this->getOrderClause(),
            'filterClause' => $this->getFilterClause(),
            'columns' => $this->dataSetColumns(),
            'dataSet' => ($this->getOption('dataSetId', 0) != 0) ? $this->dataSetFactory->getById($this->getOption('dataSetId')) : null
        ];
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
     * validate
     */
    public function validate()
    {
        // Must have a duration
        if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
            throw new \InvalidArgumentException(__('Please enter a duration'));

        // Validate Data Set Selected
        if ($this->getOption('dataSetId') == 0)
            throw new \InvalidArgumentException(__('Please select a DataSet'));

        // Check we have permission to use this DataSetId
        if (!$this->getUser()->checkViewable($this->dataSetFactory->getById($this->getOption('dataSetId'))))
            throw new \InvalidArgumentException(__('You do not have permission to use that dataset'));

        if ($this->getWidgetId() != 0) {

            if (!is_numeric($this->getOption('upperLimit')) || !is_numeric($this->getOption('lowerLimit')))
                throw new \InvalidArgumentException(__('Limits must be numbers'));

            if ($this->getOption('upperLimit') < 0 || $this->getOption('lowerLimit') < 0)
                throw new \InvalidArgumentException(__('Limits cannot be lower than 0'));

            // Check the bounds of the limits
            if ($this->getOption('upperLimit') < $this->getOption('lowerLimit'))
                throw new \InvalidArgumentException(__('Upper limit must be higher than lower limit'));

            if (!v::int()->min(0)->validate($this->getOption('updateInterval')))
                throw new InvalidArgumentException(__('Update Interval must be greater than or equal to 0'));

            // Make sure we haven't entered a silly value in the filter
            if (strstr($this->getOption('filter'), 'DESC'))
                throw new InvalidArgumentException(__('Cannot user ordering criteria in the Filter Clause'));
        }
    }

    /**
     * Add Media to the Database
     */
    public function add()
    {
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setUseDuration(0);
        $this->setDuration($this->getModule()->defaultDuration);
        $this->setOption('dataSetId', $this->getSanitizer()->getInt('dataSetId'));

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Edit Media in the Database
     */
    public function edit()
    {
        // Columns
        $columns = $this->getSanitizer()->getIntArray('dataSetColumnId');
        if (count($columns) == 0)
            $this->setOption('columns', '');
        else
            $this->setOption('columns', implode(',', $columns));

        // Other properties
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setUseDuration($this->getSanitizer()->getCheckbox('useDuration'));
        $this->setDuration($this->getSanitizer()->getInt('duration', $this->getDuration()));
        $this->setOption('updateInterval', $this->getSanitizer()->getInt('updateInterval', 120));
        $this->setOption('name', $this->getSanitizer()->getString('name'));
        $this->setOption('rowsPerPage', $this->getSanitizer()->getInt('rowsPerPage'));
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
        $this->setRawNode('javaScript', $this->getSanitizer()->getParam('javaScript', ''));

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

        // Style Sheet
        $this->setRawNode('styleSheet', $this->getSanitizer()->getParam('styleSheet', null));

        // Save the widget
        $this->validate();
        $this->saveWidget();
    }

    /**
     * @inheritdoc
     */
    public function hoverPreview()
    {
        // Default Hover window contains a thumbnail, media type and duration
        $output = '<div class="well">';
        $output .= '<div class="preview-module-image"><img alt="' . __($this->module->name) . ' thumbnail" src="' . $this->getConfig()->uri('img/' . $this->module->imageUri) . '" /></div>';
        $output .= '<div class="info">';
        $output .= '    <ul>';
        $output .= '    <li>' . __('Type') . ': ' . $this->module->name . '</li>';
        $output .= '    <li>' . __('Name') . ': ' . $this->getName() . '</li>';

        // Get the DataSet name
        $dataSet = $this->dataSetFactory->getById($this->getOption('dataSetId'));

        $output .= '    <li>' . __('Source: DataSet named "%s".', $dataSet->dataSet) . '</li>';

        if ($this->getUseDuration() == 1)
            $output .= '    <li>' . __('Duration') . ': ' . $this->widget->duration . ' ' . __('seconds') . '</li>';
        $output .= '    </ul>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
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

        // Clear all linked media.
        $this->clearMedia();

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        // Get the embedded HTML out of RAW
        $styleSheet = $this->parseLibraryReferences($isPreview, $this->getRawNode('styleSheet', ''));

        // Get the JavaScript node
        $javaScript = $this->parseLibraryReferences($isPreview, $this->getRawNode('javaScript', ''));

        $options = array(
            'type' => $this->getModuleType(),
            'duration' => $this->getCalculatedDurationForGetResource(),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'rowsPerPage' => $this->getOption('rowsPerPage'),
            'previewWidth' => $this->getSanitizer()->getDouble('width', 0),
            'previewHeight' => $this->getSanitizer()->getDouble('height', 0),
            'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 0)
        );

        // Add our fonts.css file
        $headContent = '<link href="' . (($isPreview) ? $this->getApp()->urlFor('library.font.css') : 'fonts.css') . '" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents($this->getConfig()->uri('css/client.css', true)) . '</style>';
        $headContent .= '<style type="text/css">' . $styleSheet . '</style>';

        $data['head'] = $headContent;
        $data['body'] = $this->dataSetTableHtml($displayId, $isPreview);

        // Build some JS nodes
        $javaScriptContent = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-cycle-2.1.6.min.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-dataset-render.js') . '"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("#DataSetTableContainer").dataSetRender(options); $("body").xiboLayoutScaler(options);';
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

    /**
     * Get the Data Set Table
     * @param int $displayId
     * @param bool $isPreview
     * @return string
     */
    public function dataSetTableHtml($displayId = 0, $isPreview = true)
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
                        continue;
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
                $timeZone = ($timeZone == '') ? $this->getConfig()->GetSetting('defaultTimezone') : $timeZone;
                $dateNow->timezone($timeZone);
                $this->getLog()->debug('Display Timezone Resolved: %s. Time: %s.', $timeZone, $dateNow->toDateTimeString());
            }

            $this->getStore()->setTimeZone($this->getDate()->getLocalDate($dateNow, 'P'));

            // Get the data (complete table, filtered)
            $dataSetResults = $dataSet->getData($filter);

            if (count($dataSetResults) <= 0) {

                if ($this->getRawNode('noDataMessage') == '')
                    throw new NotFoundException(__('Empty Result Set with filter criteria.'));
                else
                    return $this->getRawNode('noDataMessage');
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

                    // If the value is empty, then move on
                    if ($replace == '')
                        continue;

                    // What if this column is an image column type?
                    if ($mapping['dataTypeId'] == 4) {

                        // Grab the external image
                        $file = $this->mediaFactory->createModuleFile('datasetview_' . md5($dataSetId . $mapping['dataSetColumnId'] . $replace), str_replace(' ', '%20', htmlspecialchars_decode($replace)));
                        $file->isRemote = true;
                        $file->expires = $expires;
                        $file->save();

                        // Tag this layout with this file
                        $this->assignMedia($file->mediaId);

                        $replace = ($isPreview)
                            ? '<img src="' . $this->getApp()->urlFor('library.download', ['id' => $file->mediaId, 'type' => 'image']) . '?preview=1" />'
                            : '<img src="' . $file->storedAs . '" />';

                    } else if ($mapping['dataTypeId'] == 5) {

                        // Library Image
                        // The content is the ID of the image
                        try {
                            $file = $this->mediaFactory->getById($replace);
                        }
                        catch (NotFoundException $e) {
                            $this->getLog()->error('Library Image [%s] not found in DataSetId %d.', $replace, $dataSetId);
                            continue;
                        }

                        // Tag this layout with this file
                        $this->assignMedia($file->mediaId);

                        $replace = ($isPreview)
                            ? '<img src="' . $this->getApp()->urlFor('library.download', ['id' => $file->mediaId, 'type' => 'image']) . '?preview=1" />'
                            : '<img src="' . $file->storedAs . '" />';
                    }

                    $table .= '<td class="DataSetColumn" id="column_' . ($i + 1) . '"><span class="DataSetCellSpan" id="span_' . $rowCount . '_' . ($i + 1) . '">' . $replace . '</span></td>';
                }

                $table .= '</tr>';

                $rowCount++;
                $rowCountThisPage++;
            }

            $table .= '</tbody>';
            $table .= '</table>';
            $table .= '</div>';

            return $table;
        }
        catch (NotFoundException $e) {
            $this->getLog()->error('Request failed for dataSet id=%d. Widget=%d. Due to %s', $dataSetId, $this->getWidgetId(), $e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());
            return '';
        }
    }

    /**
     * Is Valid
     * @return int
     */
    public function IsValid()
    {
        // DataSet rendering will be valid
        return 1;
    }
}
