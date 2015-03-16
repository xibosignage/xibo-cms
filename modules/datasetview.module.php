<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2011-2014 Daniel Garner
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
class datasetview extends Module
{
    // Custom Media information
    protected $maxFileSize;
    protected $maxFileSizeBytes;

    public function __construct(database $db, user $user, $mediaid = '', $layoutid = '', $regionid = '', $lkid = '')
    {
        // Must set the type of the class
        $this->type= 'datasetview';

        // Must call the parent class
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }

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
     * @return
     */
    public function AddForm()
    {
        $this->response = new ResponseManager();
        $db =& $this->db;
        $user =& $this->user;

        // Would like to get the regions width / height
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');
        
        $formFields = array();
        $formFields[] = FormManager::AddCombo(
                    'datasetid', 
                    __('DataSet'), 
                    NULL,
                    $user->DataSetList(),
                    'datasetid',
                    'dataset',
                    __('Please select the DataSet to use as a source of data for this view.'), 
                    'd');

        $formFields[] = FormManager::AddNumber('duration', __('Duration'), NULL, 
            __('The duration in seconds this counter should be displayed'), 'd', 'required');

        Theme::Set('form_fields', $formFields);
        
        $this->response->SetFormRequestResponse(NULL, __('Add DataSet View'), '350px', '275px');

        // Cancel button
        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }
        
        $this->response->AddButton(__('Save'), '$("#ModuleForm").submit()');

        return $this->response;
    }

    /**
     * Return the Edit Form as HTML
     * @return
     */
    public function EditForm()
    {
        $this->response = new ResponseManager();
        $db =& $this->db;
        $user =& $this->user;

        // Would like to get the regions width / height
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;

        // Permissions
        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = true;
            return $this->response;
        }

        $dataSetId = $this->GetOption('datasetid');

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=EditMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" /><input type="hidden" name="datasetid" value="' . $dataSetId . '"><input type="hidden" id="mediaid" name="mediaid" value="' . $mediaid . '">');

        // We want 2 tabs
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'));
        $tabs[] = FormManager::AddTab('advanced', __('Advanced'));
        Theme::Set('form_tabs', $tabs);

        $formFields = array();
        
        $formFields[] = FormManager::AddNumber('duration', __('Duration'), $this->duration, 
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
            $notColumns = $db->GetArray(sprintf("SELECT DataSetColumnID, Heading FROM datasetcolumn WHERE DataSetID = %d AND DataSetColumnID NOT IN (%s)", $dataSetId, $columns));

            // These columns need to be in order
            $columnIds = explode(',', $columns);
            $headings = array();

            foreach($columnIds as $col)
            {
                $heading = $db->GetSingleRow(sprintf('SELECT DataSetColumnID, Heading FROM datasetcolumn WHERE DataSetColumnID = %d', $col), 'Heading', _STRING);
                $headings[] = $heading;
            }

            $columns = $headings;
        }
        else
        {
            $columns = array();
            $notColumns = $db->GetArray(sprintf("SELECT DataSetColumnID, Heading FROM datasetcolumn WHERE DataSetID = %d ", $dataSetId));
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

        // Get the embedded HTML out of RAW
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());
        $rawNodes = $rawXml->getElementsByTagName('styleSheet');

        if ($rawNodes->length != 0)
            $rawNode = $rawNodes->item(0);
        
        $formFields[] = FormManager::AddMultiText('styleSheet', NULL, (($rawNodes->length == 0) ? $this->DefaultStyleSheet() : $rawNode->nodeValue), 
            __('Enter a style sheet for the table'), 's', 10);

        Theme::Set('form_fields_advanced', $formFields);

        $this->response->SetFormRequestResponse(NULL, 'Edit DataSet View for DataSet', '650px', '575px');

        // Cancel button
        if ($this->showRegionOptions)
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        else
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');

        $this->response->AddButton(__('Save'), 'DataSetViewSubmit()');
        $this->response->callBack = 'datasetview_callback';

        return $this->response;
    }

    /**
     * Add Media to the Database
     * @return
     */
    public function AddMedia()
    {
        $this->response = new ResponseManager();
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;

        //Other properties
        $dataSetId = Kit::GetParam('datasetid', _POST, _INT, 0);
        $duration = Kit::GetParam('duration', _POST, _INT, 0, false);

        // validation
        if ($dataSetId == 0)
        {
            $this->response->SetError(__('Please select a DataSet'));
            $this->response->keepOpen = true;
            return $this->response;
        }

        // Check we have permission to use this DataSetId
        if (!$this->user->DataSetAuth($dataSetId))
        {
            $this->response->keepOpen = true;
            return $this->response->SetError(__('You do not have permission to use that dataset'));
        }

        if ($duration == 0)
        {
            $this->response->SetError(__('You must enter a duration.'));
            $this->response->keepOpen = true;
            return $this->response;
        }

        // Required Attributes
        $this->mediaid = md5(uniqid());
        $this->duration = $duration;

        // Any Options
        $this->SetOption('datasetid', $dataSetId);

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        // Link
        Kit::ClassLoader('dataset');
        $dataSet = new DataSet($this->db);
        $dataSet->LinkLayout($dataSetId, $this->layoutid, $this->regionid, $this->mediaid);

        //Set this as the session information
        setSession('content', 'type', 'datasetview');

        if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = "index.php?p=module&mod=datasetview&q=Exec&method=EditForm&layoutid=$this->layoutid&regionid=$regionid&mediaid=$this->mediaid";
        }

        return $this->response;
    }

    /**
     * Edit Media in the Database
     * @return
     */
    public function EditMedia()
    {
        $this->response = new ResponseManager();
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;

        // Other properties
        $dataSetId = Kit::GetParam('datasetid', _POST, _INT, 0);

        if (!$this->auth->edit)
        {
            $this->response->SetError('You do not have permission to edit this assignment.');
            $this->response->keepOpen = false;
            return $this->response;
        }

        // If we have permission to change it, then get the value from the form
        if ($this->auth->modifyPermissions)
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0, false);

        if ($this->duration == 0)
        {
            $this->response->SetError(__('You must enter a duration.'));
            $this->response->keepOpen = true;
            return $this->response;
        }

        $columns = Kit::GetParam('DataSetColumnId', _GET, _ARRAY, array());
        $upperLimit = Kit::GetParam('upperLimit', _POST, _INT);
        $lowerLimit = Kit::GetParam('lowerLimit', _POST, _INT);
        $filter = Kit::GetParam('filter', _POST, _STRINGSPECIAL);
        $ordering = Kit::GetParam('ordering', _POST, _STRING);
        $showHeadings = Kit::GetParam('showHeadings', _POST, _CHECKBOX);
        $styleSheet = Kit::GetParam('styleSheet', _POST, _HTMLSTRING);
        $updateInterval = Kit::GetParam('updateInterval', _POST, _INT);
        $rowsPerPage = Kit::GetParam('rowsPerPage', _POST, _INT);

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
        $this->SetOption('upperLimit', $upperLimit);
        $this->SetOption('lowerLimit', $lowerLimit);
        $this->SetOption('filter', $filter);
        $this->SetOption('ordering', $ordering);
        $this->SetOption('showHeadings', $showHeadings);
        $this->SetOption('duration', $this->duration);
        $this->SetOption('updateInterval', $updateInterval);
        $this->SetOption('rowsPerPage', $rowsPerPage);
        $this->SetRaw('<styleSheet><![CDATA[' . $styleSheet . ']]></styleSheet>');

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        //Set this as the session information
        setSession('content', 'type', 'datasetview');

        if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = "index.php?p=timeline&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
        }

        return $this->response;
    }

    public function DeleteMedia()
    {
        $dataSetId = $this->GetOption('datasetid');

        Debug::LogEntry('audit', sprintf('Deleting Media with DataSetId %d', $dataSetId), 'datasetview', 'DeleteMedia');

        Kit::ClassLoader('dataset');
        $dataSet = new DataSet($this->db);
        $dataSet->UnlinkLayout($dataSetId, $this->layoutid, $this->regionid, $this->mediaid);

        return parent::DeleteMedia();
    }

    public function GetResource($displayId = 0)
    {
        // Load in the template
        if ($this->layoutSchemaVersion == 1)
            $template = file_get_contents('modules/preview/Html4TransitionalTemplate.html');
        else
            $template = file_get_contents('modules/preview/HtmlTemplate.html');

        $isPreview = (Kit::GetParam('preview', _REQUEST, _WORD, 'false') == 'true');

        // Replace the View Port Width?
        if ($isPreview)
            $template = str_replace('[[ViewPortWidth]]', $this->width, $template);

        // Get the embedded HTML out of RAW
        $styleSheet = $this->GetRawNode('styleSheet', $this->DefaultStyleSheet());

        $options = array(
            'type' => $this->type,
            'duration' => $this->duration,
            'originalWidth' => $this->width,
            'originalHeight' => $this->height,
            'rowsPerPage' => $this->GetOption('rowsPerPage'),
            'previewWidth' => Kit::GetParam('width', _GET, _DOUBLE, 0),
            'previewHeight' => Kit::GetParam('height', _GET, _DOUBLE, 0),
            'scaleOverride' => Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
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

    public function DataSetTableHtml($displayId = 0, $isPreview = true)
    {
        $db =& $this->db;

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
        $layout = new Layout();
        $expires = time() + ($this->GetOption('updateInterval', 3600) * 60);
            
        // Create a data set view object, to get the results.
        $dataSet = new DataSet($db);
        if (!$dataSetResults = $dataSet->DataSetResults($dataSetId, $columnIds, $filter, $ordering, $lowerLimit, $upperLimit, $displayId)) {
            return;
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
                    $layout->AddLk($this->layoutid, 'module', $file['mediaId']);

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
    
    public function IsValid() {
        // DataSet rendering will be valid
        return 1;
    }
}
?>