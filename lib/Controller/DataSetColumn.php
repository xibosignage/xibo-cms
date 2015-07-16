<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetColumn.php)
 */


namespace Xibo\Controller;


class DataSetColumn extends Base
{
    public function displayPage()
    {
        $this->getState()->template = 'dataset-column-page';
    }

    public function grid()
    {

        $response = $this->getState();
        $helpManager = new Help($db, $this->user);

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $SQL = "";
        $SQL .= "SELECT DataSetColumnID, Heading, datatype.DataType, datasetcolumntype.DataSetColumnType, ListContent, ColumnOrder ";
        $SQL .= "  FROM datasetcolumn ";
        $SQL .= "   INNER JOIN `datatype` ";
        $SQL .= "   ON datatype.DataTypeID = datasetcolumn.DataTypeID ";
        $SQL .= "   INNER JOIN `datasetcolumntype` ";
        $SQL .= "   ON datasetcolumntype.DataSetColumnTypeID = datasetcolumn.DataSetColumnTypeID ";
        $SQL .= sprintf(" WHERE DataSetID = %d ", $dataSetId);
        $SQL .= "ORDER BY ColumnOrder ";


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

    }

    public function AddDataSetColumnForm()
    {

        $response = $this->getState();
        $helpManager = new Help($db, $this->user);

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        // Set some information about the form
        Theme::Set('form_id', 'DataSetColumnAddForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=AddDataSetColumn');
        Theme::Set('form_meta', '<input type="hidden" name="dataset" value="' . $dataSet . '" /><input type="hidden" name="datasetid" value="' . $dataSetId . '" />');

        $formFields = array();
        $formFields[] = Form::AddText('heading', __('Heading'), NULL, __('The heading for this Column'), 'h', 'required');
        $formFields[] = Form::AddCombo(
            'datasetcolumntypeid',
            __('Column Type'),
            NULL,
            $db->GetArray('SELECT datasetcolumntypeid, datasetcolumntype FROM datasetcolumntype'),
            'datasetcolumntypeid',
            'datasetcolumntype',
            __('Whether this column is a value or a formula'),
            't');
        $formFields[] = Form::AddCombo(
            'datatypeid',
            __('Data Type'),
            NULL,
            $db->GetArray('SELECT datatypeid, datatype FROM datatype'),
            'datatypeid',
            'datatype',
            __('The DataType of the Intended Data'),
            'd');
        $formFields[] = Form::AddText('listcontent', __('List Content'), NULL, __('A comma separated list of items to present in a combo box'), 'l', '');
        $formFields[] = Form::AddNumber('columnorder', __('Column Order'), NULL, __('The order this column should be displayed in when entering data'), 'o', '');
        $formFields[] = Form::AddText('formula', __('Formula'), NULL, __('A formula to use as a calculation for formula column types'), 'f', '');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Add Column'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'AddColumn') . '")');
        $response->AddButton(__('Cancel'), 'XiboFormRender("index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');
        $response->AddButton(__('Save'), '$("#DataSetColumnAddForm").submit()');

    }

    public function AddDataSetColumn()
    {



        $user = $this->getUser();
        $response = $this->getState();

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $heading = \Xibo\Helper\Sanitize::getString('heading');
        $listContent = \Xibo\Helper\Sanitize::getString('listcontent');
        $columnOrder = \Xibo\Helper\Sanitize::getInt('columnorder');
        $dataTypeId = \Xibo\Helper\Sanitize::getInt('datatypeid');
        $dataSetColumnTypeId = \Xibo\Helper\Sanitize::getInt('datasetcolumntypeid');
        $formula = \Xibo\Helper\Sanitize::getString('formula');

        $dataSetObject = new DataSetColumn($db);
        if (!$dataSetObject->Add($dataSetId, $heading, $dataTypeId, $listContent, $columnOrder, $dataSetColumnTypeId, $formula))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Column Added'));
        $response->hideMessage = true;
        $response->loadForm = true;
        $response->loadFormUri = 'index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet;

    }

    public function EditDataSetColumnForm()
    {

        $response = $this->getState();
        $helpManager = new Help($db, $this->user);

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSetColumnId = \Xibo\Helper\Sanitize::getInt('datasetcolumnid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
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
        $formFields[] = Form::AddText('heading', __('Heading'), \Xibo\Helper\Sanitize::string($row['Heading']),
            __('The heading for this Column'), 'h', 'required');

        $formFields[] = Form::AddCombo(
            'datasetcolumntypeid',
            __('Column Type'),
            \Xibo\Helper\Sanitize::int($row['DataSetColumnTypeID']),
            $db->GetArray('SELECT datasetcolumntypeid, datasetcolumntype FROM datasetcolumntype'),
            'datasetcolumntypeid',
            'datasetcolumntype',
            __('Whether this column is a value or a formula'),
            't');

        $formFields[] = Form::AddCombo(
            'datatypeid',
            __('Data Type'),
            \Xibo\Helper\Sanitize::int($row['DataTypeID']),
            $db->GetArray('SELECT datatypeid, datatype FROM datatype'),
            'datatypeid',
            'datatype',
            __('The DataType of the Intended Data'),
            'd');

        $formFields[] = Form::AddText('listcontent', __('List Content'), \Xibo\Helper\Sanitize::string($row['ListContent']),
            __('A comma separated list of items to present in a combo box'), 'l', '');

        $formFields[] = Form::AddNumber('columnorder', __('Column Order'), \Xibo\Helper\Sanitize::int($row['ColumnOrder']),
            __('The order this column should be displayed in when entering data'), 'o', '');

        $formFields[] = Form::AddText('formula', __('Formula'), \Xibo\Helper\Sanitize::string($row['Formula']),
            __('A formula to use as a calculation for formula column types'), 'f', '');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Edit Column'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'EditColumn') . '")');
        $response->AddButton(__('Cancel'), 'XiboFormRender("index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');
        $response->AddButton(__('Save'), '$("#DataSetColumnEditForm").submit()');

    }

    public function EditDataSetColumn()
    {



        $user = $this->getUser();
        $response = $this->getState();

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $dataSetColumnId = \Xibo\Helper\Sanitize::getInt('datasetcolumnid');
        $heading = \Xibo\Helper\Sanitize::getString('heading');
        $listContent = \Xibo\Helper\Sanitize::getString('listcontent');
        $columnOrder = \Xibo\Helper\Sanitize::getInt('columnorder');
        $dataTypeId = \Xibo\Helper\Sanitize::getInt('datatypeid');
        $dataSetColumnTypeId = \Xibo\Helper\Sanitize::getInt('datasetcolumntypeid');
        $formula = \Xibo\Helper\Sanitize::getString('formula');

        $dataSetObject = new DataSetColumn($db);
        if (!$dataSetObject->Edit($dataSetColumnId, $heading, $dataTypeId, $listContent, $columnOrder, $dataSetColumnTypeId, $formula))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Column Edited'));
        $response->hideMessage = true;
        $response->loadForm = true;
        $response->loadFormUri = 'index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet;

    }

    public function DeleteDataSetColumnForm()
    {

        $response = $this->getState();
        $helpManager = new Help($db, $this->user);

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $dataSetColumnId = \Xibo\Helper\Sanitize::getInt('datasetcolumnid');

        // Set some information about the form
        Theme::Set('form_id', 'DataSetColumnDeleteForm');
        Theme::Set('form_action', 'index.php?p=dataset&q=DeleteDataSetColumn');
        Theme::Set('form_meta', '<input type="hidden" name="dataset" value="' . $dataSet . '" /><input type="hidden" name="datasetid" value="' . $dataSetId . '" /><input type="hidden" name="datasetcolumnid" value="' . $dataSetColumnId . '" />');

        Theme::Set('form_fields', array(Form::AddMessage(__('Are you sure you want to delete?'))));

        $response->SetFormRequestResponse(NULL, __('Delete this Column?'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . $helpManager->Link('DataSet', 'DeleteColumn') . '")');
        $response->AddButton(__('Cancel'), 'XiboFormRender("index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet . '")');
        $response->AddButton(__('Delete'), '$("#DataSetColumnDeleteForm").submit()');

    }

    public function DeleteDataSetColumn()
    {



        $user = $this->getUser();
        $response = $this->getState();

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        $dataSetColumnId = \Xibo\Helper\Sanitize::getInt('datasetcolumnid');

        $dataSetObject = new DataSetColumn($db);
        if (!$dataSetObject->Delete($dataSetColumnId))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('Column Deleted'));
        $response->hideMessage = true;
        $response->loadForm = true;
        $response->loadFormUri = 'index.php?p=dataset&q=DataSetColumnsForm&datasetid=' . $dataSetId . '&dataset=' . $dataSet;

    }
}