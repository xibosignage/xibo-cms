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

        // Layout list
        $dataSets = $user->DataSetList();
        $dataSetList = Kit::SelectList('datasetid', $dataSets, 'datasetid', 'dataset');

        $form = <<<FORM
        <form id="ModuleForm" class="XiboTextForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=AddMedia">
            <input type="hidden" name="layoutid" value="$layoutid">
            <input type="hidden" id="iRegionId" name="regionid" value="$regionid">
            <input type="hidden" name="showRegionOptions" value="$this->showRegionOptions" />
            <table>
                <tr>
                    <td><label for="dataset" title="The DataSet for this View">DataSet<span class="required">*</span></label></td>
                    <td>$dataSetList</td>
                </tr>
                <tr>
                    <td><label for="duration" title="The duration in seconds this DataSet View should be displayed">Duration<span class="required">*</span></label></td>
                    <td><input id="duration" name="duration" type="text"></td>
                </tr>
            </table>
        </form>
FORM;

        $this->response->SetFormRequestResponse($form, __('Add DataSet View'), '350px', '275px');

        // Cancel button
        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=layout&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
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

        $msgUpperLimit = __('Upper Limit');
        $msgLowerLimit = __('Lower Limit');
        $msgDuration = __('Duration');
        
        $dataSetId = $this->GetOption('datasetid');
        $upperLimit = $this->GetOption('upperLimit');
        $lowerLimit = $this->GetOption('lowerLimit');
        $columns = $this->GetOption('columns');

        if ($columns != '')
        {
            // Query for more info about the selected and available columns
            $notColumns = $db->GetArray(sprintf("SELECT DataSetColumnID, Heading FROM datasetcolumn WHERE DataSetID = %d AND DataSetColumnID NOT IN (%s)", $dataSetId, $columns));
            $columns = $db->GetArray(sprintf("SELECT DataSetColumnID, Heading FROM datasetcolumn WHERE DataSetID = %d AND DataSetColumnID IN (%s)", $dataSetId, $columns));
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

        $columnsList = '<div class="connectedlist"><h3>Columns Selected</h3>' . $columnsSelected . '</div><div class="connectedlist"><h3>Columns Available</h3>' . $columnsNotSelected . '</div>';


        $durationFieldEnabled = ($this->auth->modifyPermissions) ? '' : ' readonly';

        $form = <<<FORM
        <form id="ModuleForm" method="post" action="index.php?p=module&mod=$this->type&q=Exec&method=EditMedia">
            <input type="hidden" name="layoutid" value="$layoutid">
            <input type="hidden" name="datasetid" value="$dataSetId">
            <input type="hidden" id="iRegionId" name="regionid" value="$regionid">
            <input type="hidden" id="mediaid" name="mediaid" value="$mediaid">
            <input type="hidden" name="showRegionOptions" value="$this->showRegionOptions" />
            <table>
                <tr>
                    <td><label for="duration">$msgDuration<span class="required">*</span></label></td>
                    <td><input id="duration" name="duration" type="text" value="$this->duration" $durationFieldEnabled></td>
                </tr>
                <tr>
                    <td><label for="upperLimit">$msgUpperLimit<span class="required">*</span></label></td>
                    <td><input class="numeric" id="upperLimit" name="upperLimit" type="text"></td>
                    <td><label for="lowerLimit">$msgLowerLimit<span class="required">*</span></label></td>
                    <td><input class="numeric" id="lowerLimit" name="lowerLimit" type="text"></td>
                </tr>
                <tr>
                    <td colspan="4">$columnsList<td>
                </tr>
            </table>
        </form>
FORM;

        $this->response->SetFormRequestResponse($form, __('Edit DataSet View'), '650px', '575px');

        // Cancel button
        if ($this->showRegionOptions)
        {
            $this->response->AddButton(__('Cancel'), 'XiboSwapDialog("index.php?p=layout&layoutid=' . $layoutid . '&regionid=' . $regionid . '&q=RegionOptions")');
        }
        else
        {
            $this->response->AddButton(__('Cancel'), 'XiboDialogClose()');
        }

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
        $db =& $this->db;

        $layoutid = $this->layoutid;
        $regionid = $this->regionid;
        $mediaid = $this->mediaid;

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
            $this->response->loadFormUri = "index.php?p=module&mod=datasetview&q=Exec&method=EditForm&layoutid=$this->layoutid&regionid=$regionid&mediaid=$mediaid";
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

        //Other properties
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

        if (count($columns) == 0)
            $this->SetOption('columns', '');
        else
            $this->SetOption('columns', implode(',', $columns));

        // Should have built the media object entirely by this time
        // This saves the Media Object to the Region
        $this->UpdateRegion();

        //Set this as the session information
        setSession('content', 'type', 'datasetview');

	if ($this->showRegionOptions)
        {
            // We want to load a new form
            $this->response->loadForm = true;
            $this->response->loadFormUri = "index.php?p=layout&layoutid=$layoutid&regionid=$regionid&q=RegionOptions";
        }

        return $this->response;
    }
}
?>