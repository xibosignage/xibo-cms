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
use Xibo\Helper\ApplicationState;
use Xibo\Helper\Theme;

class datasetview extends Module
{
    /**
     * Install Modules Files
     */
    public function InstallFiles()
    {
        $media = new Media();
        $media->addModuleFile('modules/preview/vendor/jquery-1.11.1.min.js');
        $media->addModuleFile('modules/preview/vendor/jquery-cycle-2.1.6.min.js');
        $media->addModuleFile('modules/preview/xibo-layout-scaler.js');
        $media->addModuleFile('modules/preview/xibo-dataset-render.js');
    }

    /**
     * Return the Add Form as HTML
     */
    public function AddForm()
    {
        $response = $this->getState();

        // Configure form
        $this->configureForm('AddMedia');

        $formFields = array();
        $formFields[] = FormManager::AddCombo(
            'datasetid',
            __('DataSet'),
            NULL,
            $this->getUser()->DataSetList(),
            'datasetid',
            'dataset',
            __('Please select the DataSet to use as a source of data for this view.'),
            'd');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), NULL, 
            __('The duration in seconds this counter should be displayed'), 'd', 'required');

        Theme::Set('form_fields', $formFields);
        
        $response->SetFormRequestResponse(NULL, __('Add DataSet View'), '350px', '275px');
        $this->configureFormButtons($response);

        return $response;
    }

    /**
     * Return the Edit Form as HTML
     */
    public function EditForm()
    {
        $response = $this->getState();
        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this widget.'));

        // Configure the form
        $this->configureForm('EditMedia');

        // We want 2 tabs
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'));
        $tabs[] = FormManager::AddTab('advanced', __('Advanced'));
        Theme::Set('form_tabs', $tabs);

        $formFields = array();
        $formFields[] = FormManager::AddHidden('dataSetId', $this->GetOption('datasetid'));

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->getDuration(),
            __('The duration in seconds this item should be displayed'), 'd', 'required', '', ($this->auth->modifyPermissions));

        $formFields[] = FormManager::AddText('ordering', __('Order'), $this->GetOption('ordering'),
            __('Please enter a SQL clause for how this dataset should be ordered'), 'o');

        $formFields[] = FormManager::AddText('filter', __('Filter'), $this->GetOption('filter'), 
            __('Please enter a SQL clause to filter this DataSet.'), 'f');

        $formFields[] = FormManager::AddCheckbox('showHeadings', __('Show the table headings?'), 
            $this->GetOption('showHeadings'), __('Should the Table headings be shown?'), 
            'h');

        // Handle the columns
        $columns = $this->GetOption('columns');

        if ($columns != '')
        {
            // Query for more info about the selected and available columns
            $notColumns = \Xibo\Storage\PDOConnect::select(sprintf("SELECT DataSetColumnID, Heading FROM datasetcolumn WHERE DataSetID = %d AND DataSetColumnID NOT IN (%s)", $this->GetOption('datasetid'), $columns), array());

            // These columns need to be in order
            $columnIds = explode(',', $columns);
            $headings = array();

            foreach($columnIds as $col)
            {
                $heading = \Xibo\Storage\PDOConnect::select(sprintf('SELECT DataSetColumnID, Heading FROM datasetcolumn WHERE DataSetColumnID = %d', $col), array());
                $headings[] = $heading[0]['Heading'];
            }

            $columns = $headings;
        }
        else
        {
            $columns = array();
            $notColumns = \Xibo\Storage\PDOConnect::select(sprintf("SELECT DataSetColumnID, Heading FROM datasetcolumn WHERE DataSetID = %d ", $this->GetOption('datasetid')), array());
        }

        // Build the two lists
        $columnsSelected = '<ul id="columnsIn" class="connectedSortable">';
        $columnsNotSelected = '<ul id="columnsOut" class="connectedSortable">';

        foreach($columns as $col)
            $columnsSelected .= '<li id="DataSetColumnId_' . $col['DataSetColumnID'] . '" class="li-sortable">' . $col['Heading'] . '</li>';

        $columnsSelected .= '</ul>';

        foreach($notColumns as $notCol)
            $columnsNotSelected .= '<li id="DataSetColumnId_' . $notCol['DataSetColumnID'] . '" class="li-sortable">' . $notCol['Heading'] . '</li>';

        $columnsNotSelected .= '</ul>';

        Theme::Set('columns_selected_list', $columnsSelected);
        Theme::Set('columns_available_list', $columnsNotSelected);

        // Add the columns in as a RAW message
        $formFields[] = FormManager::AddRaw(Theme::RenderReturn('media_form_datasetview_edit'));

        Theme::Set('form_fields_general', $formFields);
        
        // Advanced Tab
        $formFields = array();
        $formFields[] = FormManager::AddNumber('lowerLimit', __('Lower Row Limit'), $this->GetOption('lowerLimit'), 
            __('Please enter the Lower Row Limit for this DataSet (enter 0 for no limit)'), 'l');

        $formFields[] = FormManager::AddNumber('upperLimit', __('Upper Row Limit'), $this->GetOption('upperLimit'), 
            __('Please enter the Upper Row Limit for this DataSet (enter 0 for no limit)'), 'u');

        $formFields[] = FormManager::AddNumber('updateInterval', __('Update Interval (mins)'), $this->GetOption('updateInterval', 5), 
            __('Please enter the update interval in minutes. This should be kept as high as possible. For example, if the data will only change once per day this could be set to 60.'),
            'n', 'required');

        $formFields[] = FormManager::AddNumber('rowsPerPage', __('Rows per page'), $this->GetOption('rowsPerPage'), 
            __('Please enter the number of rows per page. 0 for no pages.'), 'u');

        $formFields[] = FormManager::AddMultiText('styleSheet', NULL, $this->getRawNode('styleSheet', $this->DefaultStyleSheet()),
            __('Enter a style sheet for the table'), 's', 10);

        Theme::Set('form_fields_advanced', $formFields);

        $response->SetFormRequestResponse(NULL, 'Edit DataSet View for DataSet', '650px', '575px');

        $this->configureFormButtons($response);
        $response->callBack = 'datasetview_callback';


        return $response;
    }

    /**
     * Add Media to the Database
     * @return
     */
    public function AddMedia()
    {
        $response = $this->getState();

        // Other properties
        $dataSetId = \Kit::GetParam('datasetid', _POST, _INT, 0);
        $duration = \Kit::GetParam('duration', _POST, _INT, 0, false);

        // validation
        if ($dataSetId == 0)
            throw new InvalidArgumentException(__('Please select a DataSet'));

        // Check we have permission to use this DataSetId
        if (!$this->getUser()->DataSetAuth($dataSetId))
            throw new InvalidArgumentException(__('You do not have permission to use that dataset'));

        if ($duration == 0)
            throw new InvalidArgumentException(__('You must enter a duration.'));


        // Any Options
        $this->setDuration($duration);
        $this->SetOption('datasetid', $dataSetId);

        // Save the widget
        $this->saveWidget();

        // Load an edit form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();

        // Link
        // TODO: repair this link in some way. They can't be linked to layouts anymore before one widget might end up
        // in more than one layout, due to the playlist it belongs to
        //$dataSet = new DataSet();
        //$dataSet->LinkLayout($dataSetId, $this->layoutid, $this->regionid, $this->mediaid);

        return $response;
    }

    /**
     * Edit Media in the Database
     */
    public function EditMedia()
    {
        $response = $this->getState();

        if (!$this->auth->edit)
            throw new Exception(__('You do not have permission to edit this media.'));

        $columns = \Kit::GetParam('DataSetColumnId', _GET, _ARRAY, array());
        $upperLimit = \Xibo\Helper\Sanitize::getInt('upperLimit');
        $lowerLimit = \Xibo\Helper\Sanitize::getInt('lowerLimit');
        $filter = \Kit::GetParam('filter', _POST, _STRINGSPECIAL);
        $ordering = \Xibo\Helper\Sanitize::getString('ordering');
        $showHeadings = \Xibo\Helper\Sanitize::getCheckbox('showHeadings');
        $styleSheet = \Kit::GetParam('styleSheet', _POST, _HTMLSTRING);
        $updateInterval = \Xibo\Helper\Sanitize::getInt('updateInterval');
        $rowsPerPage = \Xibo\Helper\Sanitize::getInt('rowsPerPage');

        if (count($columns) == 0)
            $this->SetOption('columns', '');
        else
            $this->SetOption('columns', implode(',', $columns));

        // Validate some content
        if (!is_numeric($upperLimit) || !is_numeric($lowerLimit))
            trigger_error(__('Limits must be numbers'), E_USER_ERROR);

        if ($upperLimit < 0 || $lowerLimit < 0)
            trigger_error(__('Limits cannot be lower than 0'), E_USER_ERROR);

        // Check the bounds of the limits
        if ($upperLimit < $lowerLimit)
            trigger_error(__('Upper limit must be higher than lower limit'), E_USER_ERROR);

        if ($updateInterval < 0)
            trigger_error(__('Update Interval must be greater than or equal to 0'), E_USER_ERROR);

        // Make sure we havent entered a silly value in the filter
        if (strstr($filter, 'DESC'))
            trigger_error(__('Cannot user ordering criteria in the Filter Clause'), E_USER_ERROR);

        // Store the values on the XLF
        $this->setDuration(Kit::GetParam('duration', _POST, _INT, $this->getDuration(), false));
        $this->SetOption('upperLimit', $upperLimit);
        $this->SetOption('lowerLimit', $lowerLimit);
        $this->SetOption('filter', $filter);
        $this->SetOption('ordering', $ordering);
        $this->SetOption('showHeadings', $showHeadings);
        $this->SetOption('duration', $this->duration);
        $this->SetOption('updateInterval', $updateInterval);
        $this->SetOption('rowsPerPage', $rowsPerPage);
        $this->setRawNode('styleSheet', $styleSheet);

        // Save the widget
        $this->saveWidget();

        // Load an edit form
        $response->loadForm = true;
        $response->loadFormUri = $this->getTimelineLink();

        return $response;
    }

    /**
     * Delete Media
     * @throws Exception
     */
    public function DeleteMedia()
    {
        // TODO: repair this link in some way. They can't be linked to layouts anymore before one widget might end up
        // in more than one layout, due to the playlist it belongs to
        // $dataSet = new DataSet($this->db);
        // $dataSet->UnlinkLayout($this->GetOption('datasetid'), $this->layoutid, $this->regionid, $this->mediaid);

        parent::DeleteMedia();
    }

    /**
     * GetResource
     * Return the rendered resource to be used by the client (or a preview)
     * for displaying this content.
     * @param integer $displayId If this comes from a real client, this will be the display id.
     * @return mixed
     */
    public function GetResource($displayId = 0)
    {
        $template = file_get_contents('modules/preview/HtmlTemplate.html');

        $isPreview = (\Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');

        // Replace the View Port Width?
        if ($isPreview)
            $template = str_replace('[[ViewPortWidth]]', $this->region->width, $template);

        // Get the embedded HTML out of RAW
        $styleSheet = $this->GetRawNode('styleSheet', $this->DefaultStyleSheet());

        $options = array(
            'type' => $this->getModuleType(),
            'duration' => $this->getDuration(),
            'originalWidth' => $this->region->width,
            'originalHeight' => $this->region->height,
            'rowsPerPage' => $this->GetOption('rowsPerPage'),
            'previewWidth' => \Kit::GetParam('width', _GET, _DOUBLE, 0),
            'previewHeight' => \Kit::GetParam('height', _GET, _DOUBLE, 0),
            'scaleOverride' => \Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
        );

        // Add our fonts.css file
        $headContent = '<link href="' . (($isPreview) ? 'modules/preview/' : '') . 'fonts.css" rel="stylesheet" media="screen">';
        $headContent .= '<style type="text/css">' . file_get_contents(Theme::ItemPath('css/client.css')) . '</style>';
        $headContent .= '<style type="text/css">' . $styleSheet . '</style>';

        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $headContent, $template);

        $template = str_replace('<!--[[[BODYCONTENT]]]-->', $this->DataSetTableHtml($displayId, $isPreview), $template);

        // Build some JS nodes
        $javaScriptContent  = '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-1.11.1.min.js"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/vendor/' : '') . 'jquery-cycle-2.1.6.min.js"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-layout-scaler.js"></script>';
        $javaScriptContent .= '<script type="text/javascript" src="' . (($isPreview) ? 'modules/preview/' : '') . 'xibo-dataset-render.js"></script>';

        $javaScriptContent .= '<script type="text/javascript">';
        $javaScriptContent .= '   var options = ' . json_encode($options) . ';';
        $javaScriptContent .= '   $(document).ready(function() { ';
        $javaScriptContent .= '       $("#DataSetTableContainer").dataSetRender(options); $("body").xiboLayoutScaler(options);';
        $javaScriptContent .= '   }); ';
        $javaScriptContent .= '</script>';

        // Replace the Head Content with our generated javascript
        $template = str_replace('<!--[[[JAVASCRIPTCONTENT]]]-->', $javaScriptContent, $template);

        return $template;
    }

    public function DefaultStyleSheet()
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
    public function DataSetTableHtml($displayId = 0, $isPreview = true)
    {
        // Show a preview of the data set table output.
        $dataSetId = $this->GetOption('datasetid');
        $upperLimit = $this->GetOption('upperLimit');
        $lowerLimit = $this->GetOption('lowerLimit');
        $filter = $this->GetOption('filter');
        $ordering = $this->GetOption('ordering');
        $columnIds = $this->GetOption('columns');
        $showHeadings = $this->GetOption('showHeadings');
        $rowsPerPage = $this->GetOption('rowsPerPage');

        if ($columnIds == '')
            return 'No columns';

        // Set an expiry time for the media
        $media = new Media();
        $expires = time() + ($this->GetOption('updateInterval', 3600) * 60);
            
        // Create a data set view object, to get the results.
        $dataSet = new DataSet();
        if (!$dataSetResults = $dataSet->DataSetResults($dataSetId, $columnIds, $filter, $ordering, $lowerLimit, $upperLimit, $displayId)) {
            return '';
        }

        $rowCount = 1;
        $rowCountThisPage = 1;
        $totalRows = count($dataSetResults['Rows']);

        if ($rowsPerPage > 0)
            $totalPages = $totalRows / $rowsPerPage;
        else
            $totalPages = 1;
        
        $table = '<div id="DataSetTableContainer" totalRows="' . $totalRows . '" totalPages="' . $totalPages . '">';

        foreach($dataSetResults['Rows'] as $row)
        {
            if (($rowsPerPage > 0 && $rowCountThisPage >= $rowsPerPage) || $rowCount == 1) {

                // Reset the row count on this page
                $rowCountThisPage = 0;

                if ($rowCount > 1) {
                    $table .= '</tbody>';
                    $table .= '</table>';
                }

                // Output the table header
                $table .= '<table class="DataSetTable">';

                if ($showHeadings == 1)
                {
                    $table .= '<thead>';
                    $table .= ' <tr class="HeaderRow">';

                    foreach($dataSetResults['Columns'] as $col)
                        $table .= '<th class="DataSetColumnHeaderCell">' . $col['Text'] . '</th>';

                    $table .= ' </tr>';
                    $table .= '</thead>';
                }

                $table .= '<tbody>';
            }

            $table .= '<tr class="DataSetRow DataSetRow' . (($rowCount % 2) ? 'Odd' : 'Even') . '" id="row_' . $rowCount . '">';

            // Output each cell for these results
            for($i = 0; $i < count($dataSetResults['Columns']); $i++) {

                // Pull out the cell for this row / column
                $replace = $row[$i];

                // What if this column is an image column type?
                if ($dataSetResults['Columns'][$i]['DataTypeID'] == 4) {
                    // Download the image, alter the replace to wrap in an image tag
                    $file = $media->addModuleFileFromUrl(str_replace(' ', '%20', htmlspecialchars_decode($replace)), 'datasetview_' . md5($dataSetId . $dataSetResults['Columns'][$i]['DataSetColumnID'] . $replace), $expires);

                    // Tag this layout with this file
                    $this->assignMedia($file['mediaId']);

                    $replace = ($isPreview) ? '<img src="index.php?p=module&mod=image&q=Exec&method=GetResource&mediaid=' . $file['mediaId'] . '" />' : '<img src="' . $file['storedAs'] . '" />';
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
