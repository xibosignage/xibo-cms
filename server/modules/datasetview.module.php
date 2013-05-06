<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2011 Daniel Garner
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
     * Sets the Layout and Region Information
     *  it will then fill in any blanks it has about this media if it can
     * @return
     * @param $layoutid Object
     * @param $regionid Object
     * @param $mediaid Object
     */
    public function SetRegionInformation($layoutid, $regionid)
    {
        $db =& $this->db;
        $this->layoutid = $layoutid;
        $this->regionid = $regionid;
        $mediaid = $this->mediaid;
        $this->existingMedia = false;

        if ($this->regionSpecific == 1) return;

        return true;
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
        Theme::Set('updateInterval', $this->GetOption('updateInterval', 0));
        Theme::Set('upperLimit', $this->GetOption('upperLimit'));
        Theme::Set('lowerLimit', $this->GetOption('lowerLimit'));
        Theme::Set('filter', $this->GetOption('filter'));
        Theme::Set('ordering', $this->GetOption('ordering'));
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

        $this->response->SetFormRequestResponse($form, __('Edit DataSet View'), '650px', '575px');

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

        $columns = Kit::GetParam('DataSetColumnId', _POST, _ARRAY, array());
        $upperLimit = Kit::GetParam('upperLimit', _POST, _INT);
        $lowerLimit = Kit::GetParam('lowerLimit', _POST, _INT);
        $filter = Kit::GetParam('filter', _POST, _STRING);
        $ordering = Kit::GetParam('ordering', _POST, _STRING);
        $showHeadings = Kit::GetParam('showHeadings', _POST, _CHECKBOX);
        $styleSheet = Kit::GetParam('styleSheet', _POST, _STRING);
        $updateInterval = Kit::GetParam('updateInterval', _POST, _STRING);

        if (count($columns) == 0)
            $this->SetOption('columns', '');
        else
            $this->SetOption('columns', implode(',', $columns));

        $this->SetOption('upperLimit', $upperLimit);
        $this->SetOption('lowerLimit', $lowerLimit);
        $this->SetOption('filter', $filter);
        $this->SetOption('ordering', $ordering);
        $this->SetOption('showHeadings', $showHeadings);
        $this->SetOption('duration', $this->duration);
        $this->SetOption('updateInterval', $updateInterval);
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

    public function Preview($width, $height)
    {
        if ($this->previewEnabled == 0)
            return parent::Preview ($width, $height);
        
        // Show a preview of the data set table output.
        return $this->DataSetTableHtml();
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

        $styleSheet = '<style type="text/css">' . $styleSheet . '</style>';

        // Load the HtmlTemplate
        $template = file_get_contents('modules/preview/HtmlTemplateForGetResource.html');

        $template = str_replace('<!--[[[HEADCONTENT]]]-->', $styleSheet, $template);
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

        if ($columnIds == '')
            return 'No columns';
            
        // Create a data set view object, to get the results.
        Kit::ClassLoader('dataset');
        $dataSet = new DataSet($db);
        $dataSetResults = $dataSet->DataSetResults($dataSetId, $columnIds, $filter, $ordering, $lowerLimit, $upperLimit, $displayId);

        $table  = '<table class="DataSetTable">';

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

        $rowCount = 1;

        foreach($dataSetResults['Rows'] as $row)
        {
            $table .= '<tr class="DataSetRow" id="row_' . $rowCount . '">';

            for($i = 0; $i < count($dataSetResults['Columns']); $i++)
                $table .= '<td class="DataSetColumn" id="column_' . ($i + 1) . '"><span class="DataSetCellSpan" id="span_' . $rowCount . '_' . ($i + 1) . '">' . $row[$i] . '</span></td>';

            $table .= '</tr>';

            $rowCount++;
        }

        $table .= '</tbody>';
        $table .= '</table>';

        return $table;
    }
}
?>