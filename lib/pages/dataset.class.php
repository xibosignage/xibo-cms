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
defined('XIBO') or die('Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.');

Kit::ClassLoader('dataset');
Kit::ClassLoader('datasetcolumn');
Kit::ClassLoader('datasetdata');

class datasetDAO extends baseDAO
{
    public function displayPage()
    {
        $subpage = Kit::GetParam('sp', _GET, _WORD, '');

        // Configure the theme
        $id = uniqid();
        
        // Different pages for data entry and admin    
        if ($subpage == 'DataEntry') {
            Theme::Set('id', 'DataEntryGrid');
            $dataSetId = Kit::GetParam('datasetid', _GET, _INT);
            $dataSet = Kit::GetParam('dataset', _GET, _STRING);
            
            Theme::Set('form_meta', '<input type="hidden" name="p" value="dataset"><input type="hidden" name="q" value="DataSetDataForm"><input type="hidden" name="datasetid" value="' . $dataSetId . '"><input type="hidden" name="dataset" value="' . $dataSet . '">');
            
            // Call to render the template
            Theme::Set('header_text', $dataSet);
            Theme::Set('form_fields', array());
            Theme::Render('grid_render');
        }
        else {
            $id = uniqid();
            Theme::Set('id', $id);
            Theme::Set('form_meta', '<input type="hidden" name="p" value="dataset"><input type="hidden" name="q" value="DataSetGrid">');
            Theme::Set('pager', ResponseManager::Pager($id));

            // Call to render the template
            Theme::Set('header_text', __('DataSets'));
            Theme::Set('form_fields', array());
            Theme::Render('grid_render');
        }
    }

    function actionMenu() {

        if (Kit::GetParam('sp', _GET, _WORD, 'view') == 'view') {
            return array(
                array('title' => __('Add DataSet'),
                    'class' => 'XiboFormButton',
                    'selected' => false,
                    'link' => 'index.php?p=dataset&q=AddDataSetForm',
                    'help' => __('Add a new DataSet'),
                    'onclick' => ''
                    )
            );
        }
        else if (Kit::GetParam('sp', _GET, _WORD, 'view') == 'DataEntry') {
            return array(
                array('title' => __('More Rows'),
                    'class' => '',
                    'selected' => false,
                    'link' => '#',
                    'help' => __('Add more rows to the end of this DataSet'),
                    'onclick' => 'XiboGridRender(\'DataEntryGrid\')'
                    )
            );
        }
        else
            return NULL;
    }

    public function DataSetGrid()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $cols = array(
                array('name' => 'dataset', 'title' => __('Name')),
                array('name' => 'description', 'title' => __('Description')),
                array('name' => 'owner', 'title' => __('Owner')),
                array('name' => 'groups', 'title' => __('Permissions'))
            );
        Theme::Set('table_cols', $cols);

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
                        'class' => 'XiboRedirectButton',
                        'url' => 'index.php?p=dataset&sp=DataEntry&datasetid=' . $dataSet['datasetid'] . '&dataset=' . urlencode($dataSet['dataset']),
                        'text' => __('View Data')
                    );

                // View Columns
                $dataSet['buttons'][] = array(
                        'id' => 'dataset_button_viewcolumns',
                        'url' => 'index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . urlencode($dataSet['dataset']),
                        'text' => __('View Columns')
                    );

                // Edit DataSet
                $dataSet['buttons'][] = array(
                        'id' => 'dataset_button_edit',
                        'url' => 'index.php?p=dataset&q=EditDataSetForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . urlencode($dataSet['dataset']),
                        'text' => __('Edit')
                    );

                // Edit DataSet
                $dataSet['buttons'][] = array(
                        'id' => 'dataset_button_import',
                        'url' => 'index.php?p=dataset&q=ImportCsvForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . urlencode($dataSet['dataset']),
                        'text' => __('Import CSV')
                    );            
            }

            if ($dataSet['del']) {

                // Delete DataSet
                $dataSet['buttons'][] = array(
                        'id' => 'dataset_button_delete',
                        'url' => 'index.php?p=dataset&q=DeleteDataSetForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . urlencode($dataSet['dataset']),
                        'text' => __('Delete')
                    ); 
            }
                
            if ($dataSet['modifyPermissions']) {

                // Edit Permissions
                $dataSet['buttons'][] = array(
                        'id' => 'dataset_button_delete',
                        'url' => 'index.php?p=dataset&q=PermissionsForm&datasetid=' . $dataSet['datasetid'] . '&dataset=' . urlencode($dataSet['dataset']),
                        'text' => __('Permissions')
                    ); 
            }

            $rows[] = $dataSet;
        }

        Theme::Set('table_rows', $rows);
        
        $output = Theme::RenderReturn('table_render');

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

        $formFields = array();
        $formFields[] = FormManager::AddText('dataset', __('Name'), NULL, 
            __('A name for this DataSet'), 'n', 'required');

        $formFields[] = FormManager::AddText('description', __('Description'), NULL, 
            __('An optional description'), 'd', 'maxlength="250"');
        
        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Add DataSet'), '350px', '275px');
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
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
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

        $formFields = array();
        $formFields[] = FormManager::AddText('dataset', __('Name'), Kit::ValidateParam($row['DataSet'], _STRING), 
            __('A name for this DataSet'), 'n', 'required');

        $formFields[] = FormManager::AddText('description', __('Description'), Kit::ValidateParam($row['Description'], _STRING), 
            __('An optional description'), 'd', 'maxlength="250"');
        
        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Edit DataSet'), '350px', '275px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DataSet', 'Edit') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#EditDataSetForm").submit()');
        $response->Respond();
    }

    public function EditDataSet()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
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

        $formFields = array();
        $formFields[] = FormManager::AddMessage(__('Are you sure you want to delete?'));
        $formFields[] = FormManager::AddCheckbox('deleteData', __('Delete any associated data?'), NULL, 
            __('Please tick the box if you would like to delete all the Data contained in this DataSet'), 'c');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Delete this DataSet?'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DataSet', 'Delete') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Delete'), '$("#DataSetDeleteForm").submit()');
        $response->Respond();
    }

    public function DeleteDataSet()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $dataSetId = Kit::GetParam('datasetid', _POST, _INT);

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->del)
            trigger_error(__('Access Denied'));

        $dataSetObject = new DataSet($db);

        if ($dataSetObject->hasData($dataSetId) && Kit::GetParam('deleteData', _POST, _CHECKBOX) == 0)
            trigger_error(__('There is data assigned to this data set, cannot delete.'), E_USER_ERROR);

        // Proceed with the delete
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
        $SQL .= "SELECT DataSetColumnID, Heading, datatype.DataType, datasetcolumntype.DataSetColumnType, ListContent, ColumnOrder ";
        $SQL .= "  FROM datasetcolumn ";
        $SQL .= "   INNER JOIN `datatype` ";
        $SQL .= "   ON datatype.DataTypeID = datasetcolumn.DataTypeID ";
        $SQL .= "   INNER JOIN `datasetcolumntype` ";
        $SQL .= "   ON datasetcolumntype.DataSetColumnTypeID = datasetcolumn.DataSetColumnTypeID ";
        $SQL .= sprintf(" WHERE DataSetID = %d ", $dataSetId);
        $SQL .= "ORDER BY ColumnOrder ";

        Kit::ClassLoader('datasetcolumn');
        $dataSetColumnObject = new DataSetColumn($db);

        // Load results into an array
        if (!$dataSetColumns = $dataSetColumnObject->GetColumns($dataSetId))
            trigger_error($dataSetColumnObject->GetErrorMessage(), E_USER_ERROR);

        $rows = array();

        foreach ($dataSetColumns as $row) {

            $row['datatype'] = __($row['datatype']);
            $row['datasetcolumntype'] = __($row['datasetcolumntype']);

            // Edit        
            $row['buttons'][] = array(
                    'id' => 'dataset_button_edit',
                    'url' => 'index.php?p=dataset&q=EditDataSetColumnForm&datasetid=' . $dataSetId . '&datasetcolumnid=' . $row['datasetcolumnid'] . '&dataset=' . $dataSet,
                    'text' => __('Edit')
                );

            if ($auth->del) {
                // Delete
                $row['buttons'][] = array(
                        'id' => 'dataset_button_delete',
                        'url' => 'index.php?p=dataset&q=DeleteDataSetColumnForm&datasetid=' . $dataSetId . '&datasetcolumnid=' . $row['datasetcolumnid'] . '&dataset=' . $dataSet,
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
        
        $formFields = array();
        $formFields[] = FormManager::AddText('heading', __('Heading'), NULL, __('The heading for this Column'), 'h', 'required');
        $formFields[] = FormManager::AddCombo(
                    'datasetcolumntypeid', 
                    __('Column Type'), 
                    NULL,
                    $db->GetArray('SELECT datasetcolumntypeid, datasetcolumntype FROM datasetcolumntype'),
                    'datasetcolumntypeid',
                    'datasetcolumntype',
                    __('Whether this column is a value or a formula'), 
                    't');
        $formFields[] = FormManager::AddCombo(
                    'datatypeid', 
                    __('Data Type'), 
                    NULL,
                    $db->GetArray('SELECT datatypeid, datatype FROM datatype'),
                    'datatypeid',
                    'datatype',
                    __('The DataType of the Intended Data'), 
                    'd');
        $formFields[] = FormManager::AddText('listcontent', __('List Content'), NULL, __('A comma separated list of items to present in a combo box'), 'l', '');
        $formFields[] = FormManager::AddNumber('columnorder', __('Column Order'), NULL, __('The order this column should be displayed in when entering data'), 'o', '');
        $formFields[] = FormManager::AddText('formula', __('Formula'), NULL, __('A formula to use as a calculation for formula column types'), 'f', '');
        
        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Add Column'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'AddColumn') . '")');
        $response->AddButton(__('Cancel'), 'XiboFormRender("index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');
        $response->AddButton(__('Save'), '$("#DataSetColumnAddForm").submit()');
        $response->Respond();
    }

    public function AddDataSetColumn()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $dataSetId = Kit::GetParam('datasetid', _POST, _INT);
        $dataSet = Kit::GetParam('dataset', _POST, _STRING);

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $heading = Kit::GetParam('heading', _POST, _STRING);
        $listContent = Kit::GetParam('listcontent', _POST, _STRING);
        $columnOrder = Kit::GetParam('columnorder', _POST, _INT);
        $dataTypeId = Kit::GetParam('datatypeid', _POST, _INT);
        $dataSetColumnTypeId = Kit::GetParam('datasetcolumntypeid', _POST, _INT);
        $formula = Kit::GetParam('formula', _POST, _STRING);

        $dataSetObject = new DataSetColumn($db);
        if (!$dataSetObject->Add($dataSetId, $heading, $dataTypeId, $listContent, $columnOrder, $dataSetColumnTypeId, $formula))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Column Added'));
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
        $SQL = sprintf("SELECT Heading, ListContent, ColumnOrder, DataTypeID, DataSetColumnTypeID, Formula FROM datasetcolumn WHERE DataSetColumnID = %d", $dataSetColumnId);
        
        if (!$row = $db->GetSingleRow($SQL))
            trigger_error(__('Unabled to get Data Column information'), E_USER_ERROR);

        // Dropdown list for DataType and DataColumnType
        Theme::Set('datatype_field_list', $db->GetArray('SELECT datatypeid, datatype FROM datatype'));
        Theme::Set('datasetcolumntype_field_list', $db->GetArray('SELECT datasetcolumntypeid, datasetcolumntype FROM datasetcolumntype'));

        $formFields = array();
        $formFields[] = FormManager::AddText('heading', __('Heading'), Kit::ValidateParam($row['Heading'], _STRING), 
            __('The heading for this Column'), 'h', 'required');

        $formFields[] = FormManager::AddCombo(
                    'datasetcolumntypeid', 
                    __('Column Type'), 
                    Kit::ValidateParam($row['DataSetColumnTypeID'], _INT),
                    $db->GetArray('SELECT datasetcolumntypeid, datasetcolumntype FROM datasetcolumntype'),
                    'datasetcolumntypeid',
                    'datasetcolumntype',
                    __('Whether this column is a value or a formula'), 
                    't');

        $formFields[] = FormManager::AddCombo(
                    'datatypeid', 
                    __('Data Type'), 
                    Kit::ValidateParam($row['DataTypeID'], _INT),
                    $db->GetArray('SELECT datatypeid, datatype FROM datatype'),
                    'datatypeid',
                    'datatype',
                    __('The DataType of the Intended Data'), 
                    'd');

        $formFields[] = FormManager::AddText('listcontent', __('List Content'), Kit::ValidateParam($row['ListContent'], _STRING), 
            __('A comma separated list of items to present in a combo box'), 'l', '');

        $formFields[] = FormManager::AddNumber('columnorder', __('Column Order'), Kit::ValidateParam($row['ColumnOrder'], _INT), 
            __('The order this column should be displayed in when entering data'), 'o', '');

        $formFields[] = FormManager::AddText('formula', __('Formula'), Kit::ValidateParam($row['Formula'], _STRING), 
            __('A formula to use as a calculation for formula column types'), 'f', '');
        
        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Edit Column'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'EditColumn') . '")');
        $response->AddButton(__('Cancel'), 'XiboFormRender("index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');
        $response->AddButton(__('Save'), '$("#DataSetColumnEditForm").submit()');
        $response->Respond();
    }

    public function EditDataSetColumn()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $dataSetId = Kit::GetParam('datasetid', _POST, _INT);
        $dataSet = Kit::GetParam('dataset', _POST, _STRING);

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));
            
        $dataSetColumnId = Kit::GetParam('datasetcolumnid', _POST, _INT);
        $heading = Kit::GetParam('heading', _POST, _STRING);
        $listContent = Kit::GetParam('listcontent', _POST, _STRING);
        $columnOrder = Kit::GetParam('columnorder', _POST, _INT);
        $dataTypeId = Kit::GetParam('datatypeid', _POST, _INT);
        $dataSetColumnTypeId = Kit::GetParam('datasetcolumntypeid', _POST, _INT);
        $formula = Kit::GetParam('formula', _POST, _STRING);

        $dataSetObject = new DataSetColumn($db);
        if (!$dataSetObject->Edit($dataSetColumnId, $heading, $dataTypeId, $listContent, $columnOrder, $dataSetColumnTypeId, $formula))
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

        Theme::Set('form_fields', array(FormManager::AddMessage(__('Are you sure you want to delete?'))));

        $response->SetFormRequestResponse(NULL, __('Delete this Column?'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'DeleteColumn') . '")');
        $response->AddButton(__('Cancel'), 'XiboFormRender("index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');
        $response->AddButton(__('Delete'), '$("#DataSetColumnDeleteForm").submit()');
        $response->Respond();
    }

    public function DeleteDataSetColumn()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
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

        $dataSetId = Kit::GetParam('datasetid', _POST, _INT);
        $dataSet = Kit::GetParam('dataset', _POST, _STRING);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'), E_USER_ERROR);

        // Get the max number of rows
        $SQL  = "";
        $SQL .= "SELECT MAX(RowNumber) AS RowNumber, COUNT(DISTINCT datasetcolumn.DataSetColumnID) AS ColNumber ";
        $SQL .= "  FROM datasetdata ";
        $SQL .= "   RIGHT OUTER JOIN datasetcolumn ";
        $SQL .= "   ON datasetcolumn.DataSetColumnID = datasetdata.DataSetColumnID ";
        $SQL .= sprintf("WHERE datasetcolumn.DataSetID = %d  AND datasetcolumn.DataSetColumnTypeID = 1 ", $dataSetId);

        Debug::LogEntry('audit', $SQL, 'dataset', 'DataSetDataForm');

        if (!$maxResult = $db->GetSingleRow($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to find the number of data points'), E_USER_ERROR);
        }

        $maxRows = $maxResult['RowNumber'];
        $maxCols = $maxResult['ColNumber'];

        // Get some information about the columns in this dataset
        $SQL  = "SELECT Heading, DataSetColumnID, ListContent, ColumnOrder, DataTypeID FROM datasetcolumn WHERE DataSetID = %d  AND DataSetColumnTypeID = 1 ";
        $SQL .= "ORDER BY ColumnOrder ";
        
        if (!$results = $db->query(sprintf($SQL, $dataSetId)))
        {
            trigger_error($db->error());
            trigger_error(__('Unable to find the column headings'), E_USER_ERROR);
        }

        $columnDefinition = array();
        
        $form = '<table class="table table-bordered">';
        $form .= '   <tr>';
        $form .= '      <th>#</th>';

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
                $dataTypeId = $columnDefinition[$col]['DataTypeID'];

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

                    Debug::LogEntry('audit', $SQL, 'dataset');

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
                    $select = '<select class="form-control" name="value">';
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
                    // Numbers are always small
                    $size = ($dataTypeId == 2) ? ' class="form-control col-sm-3"' : '';

                    if ($dataTypeId == 1) {
                        // Strings should be based on something - not sure what.
                    }

                    $select = '<input type="text" class="form-control ' . $size . '" name="value" value="' . $value . '">';
                }

                $action = ($value == '') ? 'AddDataSetData' : 'EditDataSetData';
                $fieldId = uniqid();
                
                $form .= <<<END
                <td>
                    <form id="$fieldId" class="XiboDataSetDataForm form-inline" action="index.php?p=dataset&q=$action">
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

        $form .= '</table>';
        
        $response->SetGridResponse($form);
        $response->callBack = 'dataSetData';
        $response->Respond();
    }

    public function AddDataSetData()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $response->uniqueReference = Kit::GetParam('fieldid', _POST, _STRING);
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

        $response->uniqueReference = Kit::GetParam('fieldid', _POST, _STRING);
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
        Kit::ClassLoader('datasetgroupsecurity');
        $security = new DataSetGroupSecurity($this->db);

        if (!$results = $security->ListSecurity($dataSetId, $user->getGroupFromId($user->userid, true))) {
            trigger_error(__('Unable to get permissions for this dataset'), E_USER_ERROR);
        }

        $checkboxes = array();

        foreach ($results as $row) {
            $groupId = $row['groupid'];
            $rowClass = ($row['isuserspecific'] == 0) ? 'strong_text' : '';

            $checkbox = array(
                    'id' => $groupId,
                    'name' => Kit::ValidateParam($row['group'], _STRING),
                    'class' => $rowClass,
                    'value_view' => $groupId . '_view',
                    'value_view_checked' => (($row['view'] == 1) ? 'checked' : ''),
                    'value_edit' => $groupId . '_edit',
                    'value_edit_checked' => (($row['edit'] == 1) ? 'checked' : ''),
                    'value_del' => $groupId . '_del',
                    'value_del_checked' => (($row['del'] == 1) ? 'checked' : ''),
                );

            $checkboxes[] = $checkbox;
        }

        $formFields = array();
        $formFields[] = FormManager::AddPermissions('groupids[]', $checkboxes);
        
        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Permissions'), '350px', '500px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'Permissions') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#DataSetPermissionsForm").submit()');
        $response->Respond();
    }

    public function Permissions()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
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
                    trigger_error(__('Unable to set permissions'), E_USER_ERROR);

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
                trigger_error(__('Unable to set permissions'), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__('Permissions Changed'));
        $response->Respond();
    }

    public function ImportCsvForm() {
        global $session;
        $db =& $this->db;
        $response = new ResponseManager();
        
        $dataSetId = Kit::GetParam('datasetid', _GET, _INT);
        $dataSet = Kit::GetParam('dataset', _GET, _STRING);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'), E_USER_ERROR);

        // Set the Session / Security information
        $sessionId = session_id();
        $securityToken = CreateFormToken();

        $session->setSecurityToken($securityToken);

        // Find the max file size
        $maxFileSizeBytes = convertBytes(ini_get('upload_max_filesize'));

         // Set some information about the form
        Theme::Set('form_id', 'DataSetImportCsvForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=ImportCsv');
        Theme::Set('form_meta', '<input type="hidden" name="dataset" value="' . $dataSet . '" /><input type="hidden" name="datasetid" value="' . $dataSetId . '" /><input type="hidden" id="txtFileName" name="txtFileName" readonly="true" /><input type="hidden" name="hidFileID" id="hidFileID" value="" />');

        Theme::Set('form_upload_id', 'file_upload');
        Theme::Set('form_upload_action', 'index.php?p=content&q=FileUpload');
        Theme::Set('form_upload_meta', '<input type="hidden" id="PHPSESSID" value="' . $sessionId . '" /><input type="hidden" id="SecurityToken" value="' . $securityToken . '" /><input type="hidden" name="MAX_FILE_SIZE" value="' . $maxFileSizeBytes . '" />');

        Theme::Set('prepend', Theme::RenderReturn('form_file_upload_single'));

        $formFields = array();
        $formFields[] = FormManager::AddCheckbox('overwrite', __('Overwrite existing data?'), 
            NULL, 
            __('Erase all content in this DataSet and overwrite it with the new content in this import.'), 
            'o');

        $formFields[] = FormManager::AddCheckbox('ignorefirstrow', __('Ignore first row?'), 
            NULL, 
            __('Ignore the first row? Useful if the CSV has headings.'), 
            'i');
    
        // Enumerate over the columns in the DataSet and offer a column mapping for each one (from the file)
        $SQL  = "";
        $SQL .= "SELECT DataSetColumnID, Heading ";
        $SQL .= "  FROM datasetcolumn ";
        $SQL .= sprintf(" WHERE DataSetID = %d ", $dataSetId);
        $SQL .= "   AND DataSetColumnTypeID = 1 ";
        $SQL .= "ORDER BY ColumnOrder ";

        // Load results into an array
        $dataSetColumns = $db->GetArray($SQL);

        if (!is_array($dataSetColumns)) 
        {
            trigger_error($db->error());
            trigger_error(__('Error getting list of dataSetColumns'), E_USER_ERROR);
        }

        $i = 0;

        foreach ($dataSetColumns as $row) {
            $i++;

            $formFields[] = FormManager::AddNumber('csvImport_' . Kit::ValidateParam($row['DataSetColumnID'], _INT), 
                Kit::ValidateParam($row['Heading'], _STRING), $i, NULL, 'c');
        }

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('CSV Import'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('DataSet', 'ImportCsv') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Import'), '$("#DataSetImportCsvForm").submit()');
        $response->Respond();
    }

    public function ImportCsv() {

        $db =& $this->db;
        $response = new ResponseManager();
        $dataSetId = Kit::GetParam('datasetid', _POST, _INT);
        $overwrite = Kit::GetParam('overwrite', _POST, _CHECKBOX);
        $ignorefirstrow = Kit::GetParam('ignorefirstrow', _POST, _CHECKBOX);

        $auth = $this->user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'), E_USER_ERROR);

        // File data
        $tmpName = Kit::GetParam('hidFileID', _POST, _STRING);

        if ($tmpName == '')
            trigger_error(__('Please ensure you have picked a file and it has finished uploading'), E_USER_ERROR);

        // File name and extension (original name)
        $fileName = Kit::GetParam('txtFileName', _POST, _STRING);
        $fileName = basename($fileName);
        $ext = strtolower(substr(strrchr($fileName, "."), 1));

        // Check it is a CSV file
        if ($ext != 'csv')
            trigger_error(__('Files with a CSV extension only.'), E_USER_ERROR);

        // File upload directory.. get this from the settings object
        $csvFileLocation = Config::GetSetting('LIBRARY_LOCATION') . 'temp/' . $tmpName;

        // Enumerate over the columns in the DataSet and offer a column mapping for each one (from the file)
        $SQL  = "";
        $SQL .= "SELECT DataSetColumnID ";
        $SQL .= "  FROM datasetcolumn ";
        $SQL .= sprintf(" WHERE DataSetID = %d ", $dataSetId);
        $SQL .= "   AND DataSetColumnTypeID = 1 ";
        $SQL .= "ORDER BY ColumnOrder ";

        // Load results into an array
        $dataSetColumns = $db->GetArray($SQL);

        if (!is_array($dataSetColumns)) 
        {
            trigger_error($db->error());
            trigger_error(__('Error getting list of dataSetColumns'), E_USER_ERROR);
        }

        $spreadSheetMapping = array();

        foreach ($dataSetColumns as $row) {

            $dataSetColumnId = Kit::ValidateParam($row['DataSetColumnID'], _INT);
            $spreadSheetColumn = Kit::GetParam('csvImport_' . $dataSetColumnId, _POST, _INT);

            // If it has been left blank, then skip
            if ($spreadSheetColumn != 0)
                $spreadSheetMapping[($spreadSheetColumn - 1)] = $dataSetColumnId;
        }

        $dataSetObject = new DataSetData($db);

        if (!$dataSetObject->ImportCsv($dataSetId, $csvFileLocation, $spreadSheetMapping, ($overwrite == 1), ($ignorefirstrow == 1)))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('CSV File Imported'));
        $response->Respond();
    }
}
?>
