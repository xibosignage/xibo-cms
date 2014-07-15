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
        $this->displayType = __('DataSet View');

        // Must call the parent class
        parent::__construct($db, $user, $mediaid, $layoutid, $regionid, $lkid);
    }

    /**
     * Return the Add Form as HTML
     * @return
     */
    public function AddForm()
    {
        $db =& $this->db;
        $user =& $this->user;

        // Would like to get the regions width / height
        $layoutid = $this->layoutid;
        $regionid = $this->regionid;

        Theme::Set('form_id', 'ModuleForm');
        Theme::Set('form_action', 'index.php?p=module&mod=' . $this->type . '&q=Exec&method=AddMedia');
        Theme::Set('form_meta', '<input type="hidden" name="layoutid" value="' . $layoutid . '"><input type="hidden" id="iRegionId" name="regionid" value="' . $regionid . '"><input type="hidden" name="showRegionOptions" value="' . $this->showRegionOptions . '" />');

        // Data set list
        Theme::Set('dataset_field_list', $user->DataSetList());
        
        $form = Theme::RenderReturn('media_form_datasetview_add');

        
        $this->response->SetFormRequestResponse($form, __('Add DataSet View'), '350px', '275px');

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

        Theme::Set('dataSetId', $dataSetId);
        $dataSetName = $db->GetSingleValue(sprintf('SELECT dataset FROM `dataset` WHERE DataSetID = %d', $dataSetId), 'dataset', _STRING);
        Theme::Set('dataSetName', $dataSetName);
        Theme::Set('updateInterval', $this->GetOption('updateInterval', 0));
        Theme::Set('upperLimit', $this->GetOption('upperLimit'));
        Theme::Set('lowerLimit', $this->GetOption('lowerLimit'));
        Theme::Set('filter', $this->GetOption('filter'));
        Theme::Set('ordering', $this->GetOption('ordering'));
        Theme::Set('rowsPerPage', $this->GetOption('rowsPerPage'));
        Theme::Set('showHeadings', $this->GetOption('showHeadings'));
        Theme::Set('showHeadingsChecked', ($this->GetOption('showHeadings') == 1) ? ' checked' : '');
        Theme::Set('duration', $this->duration);

        $columns = $this->GetOption('columns');
        Theme::Set('columns', $columns);

        // Get the embedded HTML out of RAW
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());
        $rawNodes = $rawXml->getElementsByTagName('styleSheet');

        if ($rawNodes->length == 0)
        {
            Theme::Set('styleSheet', $this->DefaultStyleSheet());
        }
        else
        {
            $rawNode = $rawNodes->item(0);
            Theme::Set('styleSheet', $rawNode->nodeValue);
        }

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
        Theme::Set('durationFieldEnabled', (($this->auth->modifyPermissions) ? '' : ' readonly'));

        // Render the Theme
        $form = Theme::RenderReturn('media_form_datasetview_edit');

        $this->response->SetFormRequestResponse($form, sprintf(__('Edit DataSet View for DataSet %s'), $dataSetName), '650px', '575px');

        // Cancel button
        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=timeline&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

        $this->response->AddButton(__('Save'), 'DataSetViewSubmit()');
        $this->response->callBack = 'datasetview_callback';
        $this->response->dialogClass = 'modal-big';

        return $this->response;
    }

    /**
     * Add Media to the Database
     * @return
     */
    public function AddMedia()
    {
        $db =& $this->db;

        $layoutid = $this->layoutid;
        $regionid = $this->regionid;

        //Other properties
        $dataSetId = Kit::GetParam('datasetid', _POST, _INT, 0);
        $duration = Kit::GetParam('duration', _POST, _INT, 0);

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
        $dataSet = new DataSet($db);
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
        $db =& $this->db;

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
            $this->duration = Kit::GetParam('duration', _POST, _INT, 0);

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

    public function DeleteMedia() {

        $dataSetId = $this->GetOption('datasetid');

        Debug::LogEntry('audit', sprintf('Deleting Media with DataSetId %d', $dataSetId), 'datasetview', 'DeleteMedia');

        Kit::ClassLoader('dataset');
        $dataSet = new DataSet($this->db);
        $dataSet->UnlinkLayout($dataSetId, $this->layoutid, $this->regionid, $this->mediaid);

        return parent::DeleteMedia();
    }

    public function Preview($width, $height)
    {
        if ($this->previewEnabled == 0)
            return parent::Preview ($width, $height);
        
        $layoutId = $this->layoutid;
        $regionId = $this->regionid;

        $mediaId = $this->mediaid;
        $lkId = $this->lkid;
        $mediaType = $this->type;
        $mediaDuration = $this->duration;

        $widthPx    = $width.'px';
        $heightPx   = $height.'px';

        return '<iframe scrolling="no" src="index.php?p=module&mod=' . $mediaType . '&q=Exec&method=GetResource&preview=true&raw=true&scale_override=1&layoutid=' . $layoutId . '&regionid=' . $regionId . '&mediaid=' . $mediaId . '&lkid=' . $lkId . '&width=' . $width . '&height=' . $height . '" width="' . $widthPx . '" height="' . $heightPx . '" style="border:0;"></iframe>';
    }

    public function GetResource($displayId = 0)
    {
        $db =& $this->db;

        // Get the embedded HTML out of RAW
        $rawXml = new DOMDocument();
        $rawXml->loadXML($this->GetRaw());
        $rawNodes = $rawXml->getElementsByTagName('styleSheet');

        if ($rawNodes->length == 0)
        {
            $styleSheet = $this->DefaultStyleSheet();
        }
        else
        {
            $rawNode = $rawNodes->item(0);
            $styleSheet = $rawNode->nodeValue;
        }

        $options = array(
            'duration' => $this->duration,
            'originalWidth' => $this->width,
            'originalHeight' => $this->height,
            'rowsPerPage' => $this->GetOption('rowsPerPage'),
            'previewWidth' => Kit::GetParam('width', _GET, _DOUBLE, 0),
            'previewHeight' => Kit::GetParam('height', _GET, _DOUBLE, 0),
            'scaleOverride' => Kit::GetParam('scale_override', _GET, _DOUBLE, 0)
        );

        $headContent  = '<style type="text/css">' . $styleSheet . '</style>';
        $headContent .= '<script type="text/javascript">';
        $headContent .= '   function init() { ';
        $headContent .= '       $("#DataSetTableContainer").dataSetRender(options);';
        $headContent .= '   } ';
        $headContent .= '   var options = ' . json_encode($options) . ';';
        $headContent .= '</script>';

        // Load the HtmlTemplate
        $template = file_get_contents('modules/preview/HtmlTemplateForGetResource.html');

        // Preview?
        if (isset($_GET['preview']))
            $template = str_replace('[[ViewPortWidth]]', $this->width . 'px', $template);

        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $headContent, $template);
        $template = str_replace('<!--[[[BODYCONTENT]]]-->', $this->DataSetTableHtml($displayId), $template);

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

    public function DataSetTableHtml($displayId = 0)
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
            
        // Create a data set view object, to get the results.
        Kit::ClassLoader('dataset');
        $dataSet = new DataSet($db);
        $dataSetResults = $dataSet->DataSetResults($dataSetId, $columnIds, $filter, $ordering, $lowerLimit, $upperLimit, $displayId);

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
                        $table .= '<th class="DataSetColumnHeaderCell">' . $col . '</th>';

                    $table .= ' </tr>';
                    $table .= '</thead>';
                }

                $table .= '<tbody>';
            }

            $table .= '<tr class="DataSetRow DataSetRow' . (($rowCount % 2) ? 'Odd' : 'Even') . '" id="row_' . $rowCount . '">';

            for($i = 0; $i < count($dataSetResults['Columns']); $i++)
                $table .= '<td class="DataSetColumn" id="column_' . ($i + 1) . '"><span class="DataSetCellSpan" id="span_' . $rowCount . '_' . ($i + 1) . '">' . $row[$i] . '</span></td>';

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