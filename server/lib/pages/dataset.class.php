<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2011-2013 Daniel Garner
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

    public function displayPage()
    {
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('dataset_form_add_url', 'index.php?p=dataset&q=AddDataSetForm');
        Theme::Set('form_meta', '<input type="hidden" name="p" value="dataset"><input type="hidden" name="q" value="DataSetGrid">');
        Theme::Set('pager', ResponseManager::Pager($id));

        // Render the Theme and output
        Theme::Render('dataset_page');
    }

    public function DataSetGrid()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $rows = array();

        foreach ($this->user->DataSetList() as $dataSet)
        {
            // Add some additional info
            $dataSet['owner'] = $user->getNameFromID($dataSet['ownerid']);
            $dataSet['groups'] = $this->GroupsForDataSet($dataSet['datasetid']);
            $dataSet['buttons'] = array();

            if ($dataSet['edit']) {
                
                // View Data
                $dataSet['buttons'][] = array(
                        'id' => 'dataset_button_viewdata',
                        'url' => 'index.php?p=dataset&q=DataSetDataForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . $dataSet['dataset'],
                        'text' => __('View Data')
                    );

                // View Columns
                $dataSet['buttons'][] = array(
                        'id' => 'dataset_button_viewcolumns',
                        'url' => 'index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . $dataSet['dataset'],
                        'text' => __('View Columns')
                    );

                // Edit DataSet
                $dataSet['buttons'][] = array(
                        'id' => 'dataset_button_edit',
                        'url' => 'index.php?p=dataset&q=EditDataSetForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . $dataSet['dataset'],
                        'text' => __('Edit')
                    );               
            }

            if ($dataSet['del']) {

                // Delete DataSet
                $dataSet['buttons'][] = array(
                        'id' => 'dataset_button_delete',
                        'url' => 'index.php?p=dataset&q=DeleteDataSetForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . $dataSet['dataset'],
                        'text' => __('Delete')
                    ); 
            }
                
            if ($dataSet['modifyPermissions']) {

                // Edit Permissions
                $dataSet['buttons'][] = array(
                        'id' => 'dataset_button_delete',
                        'url' => 'index.php?p=dataset&q=PermissionsForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . $dataSet['dataset'],
                        'text' => __('Permissions')
                    ); 
            }

            $rows[] = $dataSet;
        }

        Theme::Set('table_rows', $rows);
        
        $output = Theme::RenderReturn('dataset_page_grid');

        $response->SetGridResponse($output);
        $response->Respond();
    }

    public function AddDataSetForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        // Set some information about the form
        Theme::Set('form_id', 'AddDataSetForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=AddDataSet');

        $form = Theme::RenderReturn('dataset_form_add');

        $response->SetFormRequestResponse($form, __('Add DataSet'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DataSet', 'Add') . '")');
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

        $dataSetId = Kit::GetParam('datasetid', _GET, _INT);

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        // Get the information we already know
        $SQL = sprintf("SELECT DataSet, Description FROM dataset WHERE DataSetID = %d", $dataSetId);

        if (!$row = $db->GetSingleRow($SQL))
            trigger_error(__('Unable to get DataSet information'));

        // Set some information about the form
        Theme::Set('form_id', 'EditDataSetForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=EditDataSet');
        Theme::Set('form_meta', '<input type="hidden" name="datasetid" value="' . $dataSetId . '" />');

        Theme::Set('dataset', Kit::ValidateParam($row['DataSet'], _STRING));
        Theme::Set('description', Kit::ValidateParam($row['Description'], _STRING));

        $form = Theme::RenderReturn('dataset_form_edit');

        $response->SetFormRequestResponse($form, __('Edit DataSet'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DataSet', 'Edit') . '")');
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
        
        $dataSetId = Kit::GetParam('datasetid', _GET, _INT);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->del)
            trigger_error(__('Access Denied'));

        // Set some information about the form
        Theme::Set('form_id', 'DataSetDeleteForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=DeleteDataSet');
        Theme::Set('form_meta', '<input type="hidden" name="datasetid" value="' . $dataSetId . '" />');

        $form = Theme::RenderReturn('dataset_form_delete');

        $response->SetFormRequestResponse($form, __('Delete this DataSet?'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DataSet', 'Delete') . '")');
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

        $SQL  = "";
        $SQL .= "SELECT DataSetColumnID, Heading, DataTypeID, ListContent, ColumnOrder ";
        $SQL .= "  FROM datasetcolumn ";
        $SQL .= sprintf(" WHERE DataSetID = %d ", $dataSetId);
        $SQL .= "ORDER BY ColumnOrder ";

        // Load results into an array
        $dataSetColumns = $db->GetArray($SQL);

        if (!is_array($dataSetColumns)) 
        {
            trigger_error($db->error());
            trigger_error(__('Error getting list of dataSetColumns'), E_USER_ERROR);
        }

        $rows = array();

        foreach ($dataSetColumns as $row) {

            $row['heading'] = Kit::ValidateParam($row['Heading'], _STRING);
            $row['listcontent'] = Kit::ValidateParam($row['ListContent'], _STRING);
            $row['columnorder'] = Kit::ValidateParam($row['ColumnOrder'], _INT);

            // Edit        
            $row['buttons'][] = array(
                    'id' => 'dataset_button_edit',
                    'url' => 'index.php?p=dataset&q=EditDataSetColumnForm&datasetid=' . $dataSetId . '&datasetcolumnid=' . $row['DataSetColumnID'] . '&dataset=' . $dataSet,
                    'text' => __('Edit')
                );

            if ($auth->del) {
                // Delete
                $row['buttons'][] = array(
                        'id' => 'dataset_button_delete',
                        'url' => 'index.php?p=dataset&q=DeleteDataSetColumnForm&datasetid=' . $dataSetId . '&datasetcolumnid=' . $row['DataSetColumnID'] . '&dataset=' . $dataSet,
                        'text' => __('Delete')
                    );
            }

            $rows[] = $row;
        }

        Theme::Set('table_rows', $rows);
        
        $form = Theme::RenderReturn('dataset_form_column_grid');
        
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

        // Set some information about the form
        Theme::Set('form_id', 'DataSetColumnAddForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=AddDataSetColumn');
        Theme::Set('form_meta', '<input type="hidden" name="dataset" value="' . $dataSet . '" /><input type="hidden" name="datasetid" value="' . $dataSetId . '" />');

        $form = Theme::RenderReturn('dataset_form_column_add');

        $response->SetFormRequestResponse($form, __('Add Column'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'AddColumn') . '")');
        $response->AddButton(__('Cancel'), 'XiboFormRender("index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');
        $response->AddButton(__('Save'), '$("#DataSetColumnAddForm").submit()');
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

        // Set some information about the form
        Theme::Set('form_id', 'DataSetColumnEditForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=EditDataSetColumn');
        Theme::Set('form_meta', '<input type="hidden" name="dataset" value="' . $dataSet . '" /><input type="hidden" name="datasetid" value="' . $dataSetId . '" /><input type="hidden" name="datasetcolumnid" value="' . $dataSetColumnId . '" />');

        // Get some information about this data set column
        $SQL = sprintf("SELECT Heading, ListContent, ColumnOrder FROM datasetcolumn WHERE DataSetColumnID = %d", $dataSetColumnId);
        
        if (!$row = $db->GetSingleRow($SQL))
            trigger_error(__('Unabled to get Data Column information'), E_USER_ERROR);

        Theme::Set('heading',  Kit::ValidateParam($row['Heading'], _WORD));
        Theme::Set('listcontent',  Kit::ValidateParam($row['ListContent'], _STRING));
        Theme::Set('columnorder',  Kit::ValidateParam($row['ColumnOrder'], _INT));

        $form = Theme::RenderReturn('dataset_form_column_edit');

        $response->SetFormRequestResponse($form, __('Edit Column'), '350px', '200px');
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

         // Set some information about the form
        Theme::Set('form_id', 'DataSetColumnDeleteForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=DeleteDataSetColumn');
        Theme::Set('form_meta', '<input type="hidden" name="dataset" value="' . $dataSet . '" /><input type="hidden" name="datasetid" value="' . $dataSetId . '" /><input type="hidden" name="datasetcolumnid" value="' . $dataSetColumnId . '" />');

        $form = Theme::RenderReturn('dataset_form_column_delete');

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

        // Set some information about the form
        Theme::Set('form_id', 'DataSetPermissionsForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=Permissions');
        Theme::Set('form_meta', '<input type="hidden" name="datasetid" value="' . $dataSetId . '" />');

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

        $checkboxes = array();

        while ($row = $db->get_assoc_row($results))
        {
            $groupId = $row['GroupID'];
            $rowClass = ($row['IsUserSpecific'] == 0) ? 'strong_text' : '';

            $checkbox = array(
                    'id' => $groupId,
                    'name' => Kit::ValidateParam($row['Group'], _STRING),
                    'class' => $rowClass,
                    'value_view' => $groupId . '_view',
                    'value_view_checked' => (($row['View'] == 1) ? 'checked' : ''),
                    'value_edit' => $groupId . '_edit',
                    'value_edit_checked' => (($row['Edit'] == 1) ? 'checked' : ''),
                    'value_del' => $groupId . '_del',
                    'value_del_checked' => (($row['Del'] == 1) ? 'checked' : ''),
                );

            $checkboxes[] = $checkbox;
        }

        Theme::Set('form_rows', $checkboxes);

        $form = Theme::RenderReturn('dataset_form_permissions');

        $response->SetFormRequestResponse($form, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'Permissions') . '")');
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
                if (!$security->Link($dataSetId, $lastGroupId, $view, $edit, $del))
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
