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
defined('XIBO') or die('Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.');

class datasetDAO
{
    private $db;
    private $user;

    function __construct(database $db, user $user)
    {
        $this->db =& $db;
        $this->user =& $user;

        Kit::ClassLoader('dataset');
        Kit::ClassLoader('datasetcolumn');
        Kit::ClassLoader('datasetdata');
    }

    function on_page_load()
    {
            return "";
    }

    function echo_page_heading()
    {
            echo __("Layouts");
            return true;
    }

    function displayPage()
    {
        require('template/pages/dataset_view.php');
    }

    public function DataSetFilter()
    {
        $id = uniqid();

        $xiboGrid = <<<HTML
        <div class="XiboGrid" id="$id">
                <div class="XiboFilter">
                        <form onsubmit="return false">
				<input type="hidden" name="p" value="dataset">
				<input type="hidden" name="q" value="DataSetGrid">
                        </form>
                </div>
                <div class="XiboData">

                </div>
        </div>
HTML;
        echo $xiboGrid;
    }

    public function DataSetGrid()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $msgEdit = __('Edit');
        $msgDelete = __('Delete');
        $msgPermissions = __('Permissions');

        $output = <<<END
        <div class="info_table">
        <table style="width:100%">
            <thead>
                <tr>
                <th>Name</th>
                <th>Description</th>
                <th>Owner</th>
                <th>$msgPermissions</th>
                <th>Action</th>
                </tr>
            </thead>
            <tbody>
END;

        foreach($this->user->DataSetList() as $dataSet)
        {
            $auth = $user->DataSetAuth($dataSet['datasetid'], true);
            $owner = $user->getNameFromID($dataSet['ownerid']);
            $groups = $this->GroupsForDataSet($dataSet['datasetid']);

            $output .= '<tr>';
            $output .= '    <td>' . $dataSet['dataset'] . '</td>';
            $output .= '    <td>' . $dataSet['description'] . '</td>';
            $output .= '    <td>' . $owner . '</td>';
            $output .= '    <td>' . $groups . '</td>';
            $output .= '    <td>';

            if ($auth->edit)
            {
                $output .= '<button class="XiboFormButton" href="index.php?p=dataset&q=DataSetDataForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . $dataSet['dataset'] . '"><span>' . __('View Data') . '</span></button>';
                $output .= '<button class="XiboFormButton" href="index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . $dataSet['dataset'] . '"><span>' . __('View Columns') . '</span></button>';
                $output .= '<button class="XiboFormButton" href="index.php?p=dataset&q=EditDataSetForm&datasetid=' . $dataSet['datasetid'] . '"><span>' . $msgEdit . '</span></button>';
            }

            if ($auth->del)
                $output .= '<button class="XiboFormButton" href="index.php?p=dataset&q=DeleteDataSetForm&datasetid=' . $dataSet['datasetid'] . '"><span>' . $msgDelete . '</span></button>';

            if ($auth->modifyPermissions)
                $output .= '<button class="XiboFormButton" href="index.php?p=dataset&q=PermissionsForm&datasetid=' . $dataSet['datasetid'] . '"><span>' . $msgPermissions . '</span></button>';

            $output .= '    </td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table></div>';
        $response->SetGridResponse($output);
        $response->Respond();
    }

    public function AddDataSetForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $helpManager = new HelpManager($db, $user);

        $msgName = __('Name');
        $msgDesc = __('Description');

        $form = <<<END
        <form id="AddDataSetForm" class="XiboForm" method="post" action="index.php?p=dataset&q=AddDataSet">
            <table>
                <tr>
                    <td><label for="dataset" accesskey="n">$msgName<span class="required">*</span></label></td>
                    <td><input name="dataset" class="required" type="text" id="dataset" tabindex="1" /></td>
                </tr>
                <tr>
                    <td><label for="description" accesskey="d">$msgDesc</label></td>
                    <td><input name="description" type="text" id="description" tabindex="2" /></td>
                </tr>
            </table>
        </form>
END;


        $response->SetFormRequestResponse($form, __('Add DataSet'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'Add') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Add'), '$("#AddDataSetForm").submit()');
        $response->Respond();
    }

    /**
     * Add a dataset
     */
    public function AddDataSet()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $dataSet = Kit::GetParam('dataset', _POST, _STRING);
        $description = Kit::GetParam('description', _POST, _STRING);

        $dataSetObject = new DataSet($db);
        if (!$dataSetId = $dataSetObject->Add($dataSet, $description, $this->user->userid))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        // Also add one column
        $dataSetColumn = new DataSetColumn($db);
        $dataSetColumn->Add($dataSetId, 'Col1', 1, null, 1);
            
        $response->SetFormSubmitResponse(__('DataSet Added'));
        $response->Respond();
    }

    public function EditDataSetForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $helpManager = new HelpManager($db, $user);

        $dataSetId = Kit::GetParam('datasetid', _GET, _INT);

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        // Get the information we already know
        $SQL = sprintf("SELECT DataSet, Description FROM dataset WHERE DataSetID = %d", $dataSetId);

        if (!$row = $db->GetSingleRow($SQL))
            trigger_error(__('Unable to get DataSet information'));

        $dataSet = $row['DataSet'];
        $description = $row['Description'];

        $msgName = __('Name');
        $msgDesc = __('Description');

        $form = <<<END
        <form id="EditDataSetForm" class="XiboForm" method="post" action="index.php?p=dataset&q=EditDataSet">
            <input type="hidden" name="datasetid" value="$dataSetId" />
            <table>
                <tr>
                    <td><label for="dataset" accesskey="n">$msgName<span class="required">*</span></label></td>
                    <td><input name="dataset" class="required" type="text" id="dataset" tabindex="1" value="$dataSet" /></td>
                </tr>
                <tr>
                    <td><label for="description" accesskey="d">$msgDesc</label></td>
                    <td><input name="description" type="text" id="description" tabindex="2" value="$description" /></td>
                </tr>
            </table>
        </form>
END;


        $response->SetFormRequestResponse($form, __('Edit DataSet'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'Add') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Edit'), '$("#EditDataSetForm").submit()');
        $response->Respond();
    }

    public function EditDataSet()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $dataSetId = Kit::GetParam('datasetid', _POST, _INT);

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $dataSet = Kit::GetParam('dataset', _POST, _STRING);
        $description = Kit::GetParam('description', _POST, _STRING);

        $dataSetObject = new DataSet($db);
        if (!$dataSetObject->Edit($dataSetId, $dataSet, $description))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('DataSet Edited'));
        $response->Respond();
    }

    /**
     * Return the Delete Form as HTML
     * @return
     */
    public function DeleteDataSetForm()
    {
        $db =& $this->db;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $this->user);

        $dataSetId = Kit::GetParam('datasetid', _GET, _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->del)
            trigger_error(__('Access Denied'));

        // Translate messages
        $msgDelete		= __('Are you sure you want to delete this DataSet?');
        $msgYes			= __('Yes');
        $msgNo			= __('No');

        //we can delete
        $form = <<<END
        <form id="DataSetDeleteForm" class="XiboForm" method="post" action="index.php?p=dataset&q=DeleteDataSet">
            <input type="hidden" name="datasetid" value="$dataSetId">
            <p>$msgDelete</p>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Delete this DataSet?'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'Delete') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Delete'), '$("#DataSetDeleteForm").submit()');
        $response->Respond();
    }

    public function DeleteDataSet()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $dataSetId = Kit::GetParam('datasetid', _POST, _INT);

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->del)
            trigger_error(__('Access Denied'));

        $dataSetObject = new DataSet($db);
        if (!$dataSetObject->Delete($dataSetId))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('DataSet Deleted'));
        $response->Respond();
    }

    public function DataSetColumnsForm()
    {
        $db =& $this->db;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $this->user);

        $dataSetId = Kit::GetParam('datasetid', _GET, _INT);
        $dataSet = Kit::GetParam('dataset', _GET, _STRING);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $msgEdit = __('Edit');
        $msgDelete = __('Delete');

        $form = <<<END
        <div class="info_table">
        <table style="width:100%">
            <thead>
                <tr>
                <th>Heading</th>
                <th>Data Type</th>
                <th>List Content</th>
                <th>Column Order</th>
                <th>Action</th>
                </tr>
            </thead>
            <tbody>
END;

        $SQL  = "";
        $SQL .= "SELECT DataSetColumnID, Heading, DataTypeID, ListContent, ColumnOrder ";
        $SQL .= "  FROM datasetcolumn ";
        $SQL .= sprintf(" WHERE DataSetID = %d ", $dataSetId);
        $SQL .= "ORDER BY ColumnOrder ";

        if (!$results = $db->query($SQL))
            trigger_error(__('Unable to get columns for DataSet'));

        while ($row = $db->get_assoc_row($results))
        {
            $form .= '<tr>';
            $form .= '  <td>' . $row['Heading'] . '</td>';
            $form .= '  <td>String</td>';
            $form .= '  <td>' . $row['ListContent'] . '</td>';
            $form .= '  <td>' . $row['ColumnOrder'] . '</td>';
            $form .= '  <td>';
            $form .= '      <button class="XiboFormButton" href="index.php?p=dataset&q=EditDataSetColumnForm&datasetid=' . $dataSetId . '&datasetcolumnid=' . $row['DataSetColumnID'] . '&dataset=' . $dataSet . '"><span>' . $msgEdit . '</span></button>';

            if ($auth->del)
                $form .= '      <button class="XiboFormButton" href="index.php?p=dataset&q=DeleteDataSetColumnForm&datasetid=' . $dataSetId . '&datasetcolumnid=' . $row['DataSetColumnID'] . '&dataset=' . $dataSet . '"><span>' . $msgDelete . '</span></button>';

            $form .= '  </td>';
            $form .= '</tr>';
        }

        $form .= '</tbody></table></div>';
        
        $response->SetFormRequestResponse($form, sprintf(__('Columns for %s'), $dataSet), '550px', '400px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'ViewColumns') . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->AddButton(__('Add Column'), 'XiboFormRender("index.php?p=dataset&q=AddDataSetColumnForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');
        $response->Respond();
    }

    public function AddDataSetColumnForm()
    {
        $db =& $this->db;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $this->user);

        $dataSetId = Kit::GetParam('datasetid', _GET, _INT);
        $dataSet = Kit::GetParam('dataset', _GET, _STRING);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $msgHeading = __('Heading');
        $msgListContent = __('List Content');
        $msgColumnOrder = __('Column Order');

        $form = <<<END
        <form id="DataSetColumnEditForm" class="XiboForm" method="post" action="index.php?p=dataset&q=AddDataSetColumn">
            <input type="hidden" name="dataset" value="$dataSet" />
            <input type="hidden" name="datasetid" value="$dataSetId" />
            <table>
                <tr>
                    <td><label for="heading" accesskey="h">$msgHeading<span class="required">*</span></label></td>
                    <td><input name="heading" class="required" type="text" id="heading" tabindex="1" /></td>
                </tr>
                <tr>
                    <td><label for="listcontent" accesskey="l">$msgListContent</label></td>
                    <td><input name="listcontent" type="text" id="listcontent" tabindex="2" /></td>
                </tr>
                <tr>
                    <td><label for="columnorder" accesskey="c">$msgColumnOrder</label></td>
                    <td><input name="columnorder" type="text" id="columnorder" tabindex="3" /></td>
                </tr>
            </table>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Add Column'), '450px', '400px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'EditColumn') . '")');
        $response->AddButton(__('Cancel'), 'XiboFormRender("index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');
        $response->AddButton(__('Save'), '$("#DataSetColumnEditForm").submit()');
        $response->Respond();
    }

    public function AddDataSetColumn()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $dataSetId = Kit::GetParam('datasetid', _POST, _INT);
        $dataSet = Kit::GetParam('dataset', _POST, _STRING);

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $heading = Kit::GetParam('heading', _POST, _WORD);
        $listContent = Kit::GetParam('listcontent', _POST, _STRING);
        $columnOrder = Kit::GetParam('columnorder', _POST, _INT);

        $dataSetObject = new DataSetColumn($db);
        if (!$dataSetObject->Add($dataSetId, $heading, 1, $listContent))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Column Edited'));
        $response->hideMessage = true;
        $response->loadForm = true;
        $response->loadFormUri = 'index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet;
        $response->Respond();
    }

    public function EditDataSetColumnForm()
    {
        $db =& $this->db;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $this->user);

        $dataSetId = Kit::GetParam('datasetid', _GET, _INT);
        $dataSetColumnId = Kit::GetParam('datasetcolumnid', _GET, _INT);
        $dataSet = Kit::GetParam('dataset', _GET, _STRING);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        // Get some information about this data set column
        $SQL = sprintf("SELECT Heading, ListContent, ColumnOrder FROM datasetcolumn WHERE DataSetColumnID = %d", $dataSetColumnId);
        
        if (!$row = $db->GetSingleRow($SQL))
            trigger_error(__('Unabled to get Data Column information'), E_USER_ERROR);

        $heading = Kit::ValidateParam($row['Heading'], _WORD);
        $listContent = Kit::ValidateParam($row['ListContent'], _STRING);
        $columnOrder = Kit::ValidateParam($row['ColumnOrder'], _INT);

        $msgHeading = __('Heading');
        $msgListContent = __('List Content');
        $msgColumnOrder = __('Column Order');

        $form = <<<END
        <form id="DataSetColumnEditForm" class="XiboForm" method="post" action="index.php?p=dataset&q=EditDataSetColumn">
            <input type="hidden" name="dataset" value="$dataSet" />
            <input type="hidden" name="datasetid" value="$dataSetId" />
            <input type="hidden" name="datasetcolumnid" value="$dataSetColumnId" />
            <table>
                <tr>
                    <td><label for="heading" accesskey="h">$msgHeading<span class="required">*</span></label></td>
                    <td><input name="heading" class="required" type="text" id="heading" tabindex="1" value="$heading" /></td>
                </tr>
                <tr>
                    <td><label for="listcontent" accesskey="l">$msgListContent</label></td>
                    <td><input name="listcontent" type="text" id="listcontent" tabindex="2" value="$listContent" /></td>
                </tr>
                <tr>
                    <td><label for="columnorder" accesskey="c">$msgColumnOrder</label></td>
                    <td><input name="columnorder" type="text" id="columnorder" tabindex="3" value="$columnOrder" /></td>
                </tr>
            </table>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Edit Column'), '450px', '400px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'EditColumn') . '")');
        $response->AddButton(__('Cancel'), 'XiboFormRender("index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');
        $response->AddButton(__('Save'), '$("#DataSetColumnEditForm").submit()');
        $response->Respond();
    }

    public function EditDataSetColumn()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $dataSetId = Kit::GetParam('datasetid', _POST, _INT);
        $dataSet = Kit::GetParam('dataset', _POST, _STRING);

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));
            
        $dataSetColumnId = Kit::GetParam('datasetcolumnid', _POST, _INT);
        $heading = Kit::GetParam('heading', _POST, _WORD);
        $listContent = Kit::GetParam('listcontent', _POST, _STRING);
        $columnOrder = Kit::GetParam('columnorder', _POST, _INT);

        $dataSetObject = new DataSetColumn($db);
        if (!$dataSetObject->Edit($dataSetColumnId, $heading, 1, $listContent, $columnOrder))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Column Edited'));
        $response->hideMessage = true;
        $response->loadForm = true;
        $response->loadFormUri = 'index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet;
        $response->Respond();
    }

    public function DeleteDataSetColumnForm()
    {
        $db =& $this->db;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $this->user);

        $dataSetId = Kit::GetParam('datasetid', _GET, _INT);
        $dataSet = Kit::GetParam('dataset', _GET, _STRING);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $dataSetColumnId = Kit::GetParam('datasetcolumnid', _GET, _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->del)
            trigger_error(__('Access Denied'));

        // Translate messages
        $msgDelete		= __('Are you sure you want to delete this Column?');
        $msgYes			= __('Yes');
        $msgNo			= __('No');

        //we can delete
        $form = <<<END
        <form id="DataSetColumnDeleteForm" class="XiboForm" method="post" action="index.php?p=dataset&q=DeleteDataSetColumn">
            <input type="hidden" name="datasetid" value="$dataSetId">
            <input type="hidden" name="dataset" value="$dataSet">
            <input type="hidden" name="datasetcolumnid" value="$dataSetColumnId">
            <p>$msgDelete</p>
        </form>
END;

        $response->SetFormRequestResponse($form, __('Delete this Column?'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'DeleteColumn') . '")');
        $response->AddButton(__('Cancel'), 'XiboFormRender("index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');
        $response->AddButton(__('Delete'), '$("#DataSetColumnDeleteForm").submit()');
        $response->Respond();
    }

    public function DeleteDataSetColumn()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $dataSetId = Kit::GetParam('datasetid', _POST, _INT);
        $dataSet = Kit::GetParam('dataset', _POST, _STRING);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $dataSetColumnId = Kit::GetParam('datasetcolumnid', _POST, _INT);

        $dataSetObject = new DataSetColumn($db);
        if (!$dataSetObject->Delete($dataSetColumnId))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Column Deleted'));
        $response->hideMessage = true;
        $response->loadForm = true;
        $response->loadFormUri = 'index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet;
        $response->Respond();
    }

    public function DataSetDataForm()
    {
        $db =& $this->db;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $this->user);

        $dataSetId = Kit::GetParam('datasetid', _GET, _INT);
        $dataSet = Kit::GetParam('dataset', _GET, _STRING);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        // Get the max number of rows
        $SQL  = "";
        $SQL .= "SELECT MAX(RowNumber) AS RowNumber, COUNT(DISTINCT datasetcolumn.DataSetColumnID) AS ColNumber ";
        $SQL .= "  FROM datasetdata ";
        $SQL .= "   RIGHT OUTER JOIN datasetcolumn ";
        $SQL .= "   ON datasetcolumn.DataSetColumnID = datasetdata.DataSetColumnID ";
        $SQL .= sprintf("WHERE datasetcolumn.DataSetID = %d ", $dataSetId);

        Debug::LogEntry($db, 'audit', $SQL, 'dataset', 'DataSetDataForm');

        if (!$maxResult = $db->GetSingleRow($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to find the number of data points'), E_USER_ERROR);
        }

        $maxRows = $maxResult['RowNumber'];
        $maxCols = $maxResult['ColNumber'];

        // Get some information about the columns in this dataset
        $SQL  = "SELECT Heading, DataSetColumnID, ListContent, ColumnOrder FROM datasetcolumn WHERE DataSetID = %d ";
        $SQL .= "ORDER BY ColumnOrder ";
        
        if (!$results = $db->query(sprintf($SQL, $dataSetId)))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to find the column headings'), E_USER_ERROR);
        }

        $columnDefinition = array();
        
        $form  = '<div class="info_table">';
        $form .= '<table style="width:100%">';
        $form .= '   <tr>';
        $form .= '      <th>' . __('Row Number') . '</th>';

	while ($row = $db->get_assoc_row($results))
        {
            $columnDefinition[] = $row;
            $heading = $row['Heading'];

            $form .= ' <th>' . $heading . '</th>';
        }

        $form .= '</tr>';

        // Loop through the max rows
        for ($row = 1; $row <= $maxRows + 2; $row++)
        {
            $form .= '<tr>';
            $form .= '  <td>' . $row . '</td>';

            // $row is the current row
            for ($col = 0; $col < $maxCols; $col++)
            {
                $dataSetColumnId = $columnDefinition[$col]['DataSetColumnID'];
                $listContent = $columnDefinition[$col]['ListContent'];
                $columnOrder = $columnDefinition[$col]['ColumnOrder'];

                // Value for this Col/Row
                $value = '';

                if ($row <= $maxRows)
                {
                    // This is intended to be a blank row
                    $SQL  = "";
                    $SQL .= "SELECT Value ";
                    $SQL .= "  FROM datasetdata ";
                    $SQL .= "WHERE datasetdata.RowNumber = %d ";
                    $SQL .= "   AND datasetdata.DataSetColumnID = %d ";
                    $SQL = sprintf($SQL, $row, $dataSetColumnId);

                    Debug::LogEntry($db, 'audit', $SQL, 'dataset');

                    if (!$results = $db->query($SQL))
                    {
                        trigger_error($db->error());
                        trigger_error(__('Can not get the data row/column'), E_USER_ERROR);
                    }

                    if ($db->num_rows($results) == 0)
                    {
                        $value = '';
                    }
                    else
                    {
                        $valueRow = $db->get_assoc_row($results);
                        $value = $valueRow['Value'];
                    }
                }
                
                // Do we need a select list?
                if ($listContent != '')
                {
                    $listItems = explode(',', $listContent);
                    $selected = ($value == '') ? ' selected' : '';
                    $select = '<select name="value">';
                    $select.= '     <option value="" ' . $selected . '></option>';

                    for ($i=0; $i < count($listItems); $i++)
                    {
                        $selected = ($listItems[$i] == $value) ? ' selected' : '';

                        $select .= '<option value="' . $listItems[$i] . '" ' . $selected . '>' . $listItems[$i] . '</option>';
                    }

                    $select .= '</select>';
                }
                else
                {
                    $select = '<input type="text" name="value" value="' . $value . '">';
                }

                $action = ($value == '') ? 'AddDataSetData' : 'EditDataSetData';
                $fieldId = uniqid();
                
                $form .= <<<END
                <td>
                    <form id="$fieldId" class="XiboDataSetDataForm" action="index.php?p=dataset&q=$action">
                        <input type="hidden" name="fieldid" value="$fieldId">
                        <input type="hidden" name="datasetid" value="$dataSetId">
                        <input type="hidden" name="datasetcolumnid" value="$dataSetColumnId">
                        <input type="hidden" name="rownumber" value="$row">
                        $select
                    </form>
                </td>
END;

                
            } //cols loop

            $form .= '</tr>';
        } //rows loop

        $form .= '</table></div>';
        
        $response->SetFormRequestResponse($form, $dataSet, '750px', '600px', 'dataSetData');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'Data') . '")');
        $response->AddButton(__('Add Rows'), 'XiboFormRender("index.php?p=dataset&q=DataSetDataForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');
        $response->AddButton(__('Done'), 'XiboDialogClose()');
        $response->Respond();
    }

    public function AddDataSetData()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $response->uniqueReference = Kit::GetParam('fieldid', _POST, _WORD);
        $dataSetId = Kit::GetParam('datasetid', _POST, _INT);
        $dataSetColumnId = Kit::GetParam('datasetcolumnid', _POST, _INT);
        $rowNumber = Kit::GetParam('rownumber', _POST, _INT);
        $value = Kit::GetParam('value', _POST, _STRING);

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $dataSetObject = new DataSetData($db);
        if (!$dataSetObject->Add($dataSetColumnId, $rowNumber, $value))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Data Added'));
        $response->loadFormUri = 'index.php?p=dataset&q=EditDataSetData';
        $response->hideMessage = true;
        $response->keepOpen = true;
        $response->Respond();
    }

    public function EditDataSetData()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $response->uniqueReference = Kit::GetParam('fieldid', _POST, _WORD);
        $dataSetId = Kit::GetParam('datasetid', _POST, _INT);
        $dataSetColumnId = Kit::GetParam('datasetcolumnid', _POST, _INT);
        $rowNumber = Kit::GetParam('rownumber', _POST, _INT);
        $value = Kit::GetParam('value', _POST, _STRING);

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        if ($value == '')
        {
            $dataSetObject = new DataSetData($db);
            if (!$dataSetObject->Delete($dataSetColumnId, $rowNumber))
                trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

            $response->SetFormSubmitResponse(__('Data Deleted'));
            $response->loadFormUri = 'index.php?p=dataset&q=AddDataSetData';
        }
        else
        {
            $dataSetObject = new DataSetData($db);
            if (!$dataSetObject->Edit($dataSetColumnId, $rowNumber, $value))
                trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

            $response->SetFormSubmitResponse(__('Data Edited'));
            $response->loadFormUri = 'index.php?p=dataset&q=EditDataSetData';
        }

        $response->hideMessage = true;
        $response->keepOpen = true;
        $response->Respond();
    }

    /**
     * Get a list of group names for a layout
     * @param <type> $layoutId
     * @return <type>
     */
    private function GroupsForDataSet($dataSetId)
    {
        $db =& $this->db;

        $SQL = '';
        $SQL .= 'SELECT `group`.Group ';
        $SQL .= '  FROM `group` ';
        $SQL .= '   INNER JOIN lkdatasetgroup ';
        $SQL .= '   ON `group`.GroupID = lkdatasetgroup.GroupID ';
        $SQL .= ' WHERE lkdatasetgroup.DataSetID = %d ';

        $SQL = sprintf($SQL, $dataSetId);

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get group information for dataset'), E_USER_ERROR);
        }

        $groups = '';

        while ($row = $db->get_assoc_row($results))
        {
            $groups .= $row['Group'] . ', ';
        }

        $groups = trim($groups);
        $groups = trim($groups, ',');

        return $groups;
    }

    public function PermissionsForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $helpManager = new HelpManager($db, $user);

        $dataSetId = Kit::GetParam('datasetid', _GET, _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);

        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this dataset'), E_USER_ERROR);

        // Form content
        $form = '<form id="DataSetPermissionsForm" class="XiboForm" method="post" action="index.php?p=dataset&q=Permissions">';
	$form .= '<input type="hidden" name="datasetid" value="' . $dataSetId . '" />';
        $form .= '<div class="dialog_table">';
	$form .= '  <table style="width:100%">';
        $form .= '      <tr>';
        $form .= '          <th>' . __('Group') . '</th>';
        $form .= '          <th>' . __('View') . '</th>';
        $form .= '          <th>' . __('Edit') . '</th>';
        $form .= '          <th>' . __('Delete') . '</th>';
        $form .= '      </tr>';

        // List of all Groups with a view/edit/delete checkbox
        $SQL = '';
        $SQL .= 'SELECT `group`.GroupID, `group`.`Group`, View, Edit, Del, `group`.IsUserSpecific ';
        $SQL .= '  FROM `group` ';
        $SQL .= '   LEFT OUTER JOIN lkdatasetgroup ';
        $SQL .= '   ON lkdatasetgroup.GroupID = group.GroupID ';
        $SQL .= '       AND lkdatasetgroup.DataSetID = %d ';
        $SQL .= ' WHERE `group`.GroupID <> %d ';
        $SQL .= 'ORDER BY `group`.IsEveryone DESC, `group`.IsUserSpecific, `group`.`Group` ';

        $SQL = sprintf($SQL, $dataSetId, $user->getGroupFromId($user->userid, true));

        if (!$results = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to get permissions for this dataset'), E_USER_ERROR);
        }

        while($row = $db->get_assoc_row($results))
        {
            $groupId = $row['GroupID'];
            $group = ($row['IsUserSpecific'] == 0) ? '<strong>' . $row['Group'] . '</strong>' : $row['Group'];

            $form .= '<tr>';
            $form .= ' <td>' . $group . '</td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_view" ' . (($row['View'] == 1) ? 'checked' : '') . '></td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_edit" ' . (($row['Edit'] == 1) ? 'checked' : '') . '></td>';
            $form .= ' <td><input type="checkbox" name="groupids[]" value="' . $groupId . '_del" ' . (($row['Del'] == 1) ? 'checked' : '') . '></td>';
            $form .= '</tr>';
        }

        $form .= '</table>';
        $form .= '</div>';
        $form .= '</form>';

        $response->SetFormRequestResponse($form, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('Layout', 'Permissions') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DataSetPermissionsForm").submit()');
        $response->Respond();
    }

    public function Permissions()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        Kit::ClassLoader('datasetgroupsecurity');

        $dataSetId = Kit::GetParam('datasetid', _POST, _INT);
        $groupIds = Kit::GetParam('groupids', _POST, _ARRAY);

        $auth = $this->user->DataSetAuth($dataSetId, true);

        if (!$auth->modifyPermissions)
            trigger_error(__('You do not have permissions to edit this dataset'), E_USER_ERROR);

        // Unlink all
        $security = new DataSetGroupSecurity($db);
        if (!$security->UnlinkAll($dataSetId))
            trigger_error(__('Unable to set permissions'));

        // Some assignments for the loop
        $lastGroupId = 0;
        $first = true;
        $view = 0;
        $edit = 0;
        $del = 0;

        // List of groupIds with view, edit and del assignments
        foreach($groupIds as $groupPermission)
        {
            $groupPermission = explode('_', $groupPermission);
            $groupId = $groupPermission[0];

            if ($first)
            {
                // First time through
                $first = false;
                $lastGroupId = $groupId;
            }

            if ($groupId != $lastGroupId)
            {
                // The groupId has changed, so we need to write the current settings to the db.
                // Link new permissions
                if (!$security->Link($dataSetId, $groupId, $view, $edit, $del))
                    trigger_error(__('Unable to set permissions'));

                // Reset
                $lastGroupId = $groupId;
                $view = 0;
                $edit = 0;
                $del = 0;
            }

            switch ($groupPermission[1])
            {
                case 'view':
                    $view = 1;
                    break;

                case 'edit':
                    $edit = 1;
                    break;

                case 'del':
                    $del = 1;
                    break;
            }
        }

        // Need to do the last one
        if (!$first)
        {
            if (!$security->Link($dataSetId, $lastGroupId, $view, $edit, $del))
                    trigger_error(__('Unable to set permissions'));
        }

        $response->SetFormSubmitResponse(__('Permissions Changed'));
        $response->Respond();
    }
}
?>
