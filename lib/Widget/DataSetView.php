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
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;

class DataSetView extends ModuleWidget
{
    /**
     * Install Modules Files
     */
    public function installFiles()
    {
        MediaFactory::createModuleSystemFile('modules/vendor/jquery-1.11.1.min.js')->save();
        MediaFactory::createModuleSystemFile('modules/vendor/jquery-cycle-2.1.6.min.js')->save();
        MediaFactory::createModuleSystemFile('modules/xibo-layout-scaler.js')->save();
        MediaFactory::createModuleSystemFile('modules/xibo-dataset-render.js')->save();
    }

    /**
     * DataSets
     * @return array[DataSet]
     */
    public function dataSets()
    {
        return DataSetFactory::query();
    }

    /**
     * Get Data Set Columns
     * @return array[DataSetColumn]
     */
    public function dataSetColumnsSelected()
    {
        if ($this->getOption('dataSetId') == 0)
            throw new \InvalidArgumentException(__('DataSet not selected'));

        $columns = DataSetColumnFactory::getByDataSetId($this->getOption('dataSetId'));
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

        $columns = DataSetColumnFactory::getByDataSetId($this->getOption('dataSetId'));

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
     * validate
     */
    public function validate()
    {
        // Must have a duration
        if ($this->getDuration() == 0)
            throw new \InvalidArgumentException(__('Please enter a duration'));

        // Validate Data Set Selected
        if ($this->getOption('dataSetId') == 0)
            throw new \InvalidArgumentException(__('Please select a DataSet'));

        // Check we have permission to use this DataSetId
        if (!$this->getUser()->checkViewable(DataSetFactory::getById($this->getOption('dataSetId'))))
            throw new \InvalidArgumentException(__('You do not have permission to use that dataset'));

        if ($this->getWidgetId() != 0) {

            if (!is_numeric($this->getOption('upperLimit')) || !is_numeric($this->getOption('lowerLimit')))
                throw new \InvalidArgumentException(__('Limits must be numbers'));

            if ($this->getOption('upperLimit') < 0 || $this->getOption('lowerLimit') < 0)
                throw new \InvalidArgumentException(__('Limits cannot be lower than 0'));

            // Check the bounds of the limits
            if ($this->getOption('upperLimit') < $this->getOption('lowerLimit'))
                throw new \InvalidArgumentException(__('Upper limit must be higher than lower limit'));

            if (!v::int()->notEmpty()->min(0)->validate($this->getOption('updateInterval')))
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
        $this->setOption('name', Sanitize::getString('name'));
        $this->setDuration(Sanitize::getInt('duration', $this->getDuration()));
        $this->setOption('dataSetId', Sanitize::getInt('dataSetId'));

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
        $columns = Sanitize::getIntArray('dataSetColumnId');
        if (count($columns) == 0)
            $this->SetOption('columns', '');
        else
            $this->SetOption('columns', implode(',', $columns));

        // Other properties
        $this->setOption('name', Sanitize::getString('name', $this->getOption('name')));
        $this->setDuration(Sanitize::getInt('duration', $this->getDuration()));
        $this->setOption('updateInterval', Sanitize::getInt('updateInterval', 120));
        $this->setOption('name', Sanitize::getString('name'));
        $this->setOption('rowsPerPage', Sanitize::getInt('rowsPerPage'));
        $this->setOption('showHeadings', Sanitize::getCheckbox('showHeadings'));
        $this->setOption('upperLimit', Sanitize::getInt('upperLimit', 0));
        $this->setOption('lowerLimit', Sanitize::getInt('lowerLimit', 0));
        $this->setOption('filter', Sanitize::getString('filter'));
        $this->setOption('ordering', Sanitize::getString('ordering'));

        // Style Sheet
        $this->setRawNode('styleSheet', Sanitize::getParam('styleSheet', null));

        // Save the widget
        $this->validate();
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
        $isPreview = (Sanitize::getCheckbox('preview') == 1);

        // Clear all linked media.
        $this->clearMedia();

        // Replace the View Port Width?
        $data['viewPortWidth'] = ($isPreview) ? $this->region->width : '[[ViewPortWidth]]';

        // Get the embedded HTML out of RAW
        $styleSheet = $this->GetRawNode('styleSheet', $this->defaultStyleSheet());

        $options = array(
            'type' => $this->getModuleType(),
            'duration' => $this->getDuration(),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'rowsPerPage' => $this->GetOption('rowsPerPage'),
            'previewWidth' => Sanitize::getDouble('width', 0),
            'previewHeight' => Sanitize::getDouble('height', 0),
            'scaleOverride' => Sanitize::getDouble('scale_override', 0)
        );

        // Add our fonts.css file
        $headContent = '<link href="' . $this->getResourceUrl('fonts.css') . ' rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents(Theme::uri('css/client.css', true)) . '</style>';
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
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $data['javaScript'] = $javaScriptContent;

        // Update and save widget if we've changed our assignments.
        if ($this->hasMediaChanged())
            $this->widget->save(['saveWidgetOptions' => false]);

        return $this->renderTemplate($data);
    }

    public function defaultStyleSheet()
    {
        $styleSheet = <<<END
table.DataSetTable {

}

tr.HeaderRow {

}

tr#row_1 {

}

td#column_1 {

}

td.DataSetColumn {

}

tr.DataSetRow {

}

tr.DataSetRowOdd {

}

tr.DataSetRowEven {

}

th.DataSetColumnHeaderCell {

}

span#1_1 {

}

span.DataSetColumnSpan {

}
END;
        return $styleSheet;
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
        $dataSetId = $this->GetOption('dataSetId');
        $upperLimit = $this->GetOption('upperLimit');
        $lowerLimit = $this->GetOption('lowerLimit');
        $filter = $this->GetOption('filter');
        $ordering = $this->GetOption('ordering');
        $columnIds = $this->GetOption('columns');
        $showHeadings = $this->GetOption('showHeadings');
        $rowsPerPage = $this->GetOption('rowsPerPage');

        if ($columnIds == '')
            return __('No columns');

        // Array of columnIds we want
        $columnIds = explode(',', $columnIds);

        // Set an expiry time for the media
        $expires = time() + ($this->GetOption('updateInterval', 3600) * 60);

        // Create a data set object, to get the results.
        try {
            $dataSet = DataSetFactory::getById($dataSetId);

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

            Log::debug('Resolved column mappings: %s', json_encode($columnIds));

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

            // Get the data (complete table, filtered)
            $dataSetResults = $dataSet->getData($filter);

            if (count($dataSetResults) <= 0)
                throw new NotFoundException(__('Empty Result Set with filter criteria.'));

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

                    // What if this column is an image column type?
                    if ($mapping['dataTypeId'] == 4) {
                        // Grab the profile image
                        $file = MediaFactory::createModuleFile(str_replace(' ', '%20', htmlspecialchars_decode($replace)), 'datasetview_' . md5($dataSetId . $mapping['dataSetColumnId'] . $replace));
                        $file->isRemote = true;
                        $file->expires = $expires;
                        $file->save();

                        // Tag this layout with this file
                        $this->assignMedia($file->mediaId);

                        $url = $this->getApp()->urlFor('library.download', ['id' => $file->mediaId, 'type' => 'image']);
                        $replace = ($isPreview) ? '<img src="' . $url . '?preview=1" />' : '<img src="' . $file->storedAs . '" />';

                    } else if ($mapping['dataTypeId'] == 5) {
                        // Library Image
                        // The content is the ID of the image
                        $file = MediaFactory::getById($replace);

                        // Tag this layout with this file
                        $this->assignMedia($file->mediaId);

                        $url = $this->getApp()->urlFor('library.download', ['id' => $file->mediaId, 'type' => 'image']);
                        $replace = ($isPreview) ? '<img src="' . $url . '?preview=1" />' : '<img src="' . $file->storedAs . '" />';
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
            Log::error('Request failed for dataSet id=%d. Widget=%d. Due to %s', $dataSetId, $this->getWidgetId(), $e->getMessage());
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
