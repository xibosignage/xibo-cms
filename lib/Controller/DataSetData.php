<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetData.php)
 */


namespace Xibo\Controller;


class DataSetData extends Base
{


    public function displayDataEntry()
    {
        Theme::Set('id', 'DataEntryGrid');
        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        Theme::Set('form_meta', '<input type="hidden" name="p" value="dataset"><input type="hidden" name="q" value="DataSetDataForm"><input type="hidden" name="datasetid" value="' . $dataSetId . '"><input type="hidden" name="dataset" value="' . $dataSet . '">');

        // Call to render the template
        Theme::Set('header_text', $dataSet);
        Theme::Set('form_fields', array());
        $this->getState()->html .= Theme::RenderReturn('grid_render');
        $this->getState()->template = 'dataset-dataentry-page';
    }



    public function DataSetDataForm()
    {

        $response = $this->getState();

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'), E_USER_ERROR);

        // Get the max number of rows
        $SQL = "";
        $SQL .= "SELECT MAX(RowNumber) AS RowNumber, COUNT(DISTINCT datasetcolumn.DataSetColumnID) AS ColNumber ";
        $SQL .= "  FROM datasetdata ";
        $SQL .= "   RIGHT OUTER JOIN datasetcolumn ";
        $SQL .= "   ON datasetcolumn.DataSetColumnID = datasetdata.DataSetColumnID ";
        $SQL .= sprintf("WHERE datasetcolumn.DataSetID = %d  AND datasetcolumn.DataSetColumnTypeID = 1 ", $dataSetId);

        Log::notice($SQL, 'dataset', 'DataSetDataForm');

        if (!$maxResult = $db->GetSingleRow($SQL)) {
            trigger_error($db->error());
            trigger_error(__('Unable to find the number of data points'), E_USER_ERROR);
        }

        $maxRows = $maxResult['RowNumber'];
        $maxCols = $maxResult['ColNumber'];

        // Get some information about the columns in this dataset
        $SQL = "SELECT Heading, DataSetColumnID, ListContent, ColumnOrder, DataTypeID FROM datasetcolumn WHERE DataSetID = %d  AND DataSetColumnTypeID = 1 ";
        $SQL .= "ORDER BY ColumnOrder ";

        if (!$results = $db->query(sprintf($SQL, $dataSetId))) {
            trigger_error($db->error());
            trigger_error(__('Unable to find the column headings'), E_USER_ERROR);
        }

        $columnDefinition = array();

        $form = '<table class="table table-bordered">';
        $form .= '   <tr>';
        $form .= '      <th>#</th>';

        while ($row = $db->get_assoc_row($results)) {
            $columnDefinition[] = $row;
            $heading = $row['Heading'];

            $form .= ' <th>' . $heading . '</th>';
        }

        $form .= '</tr>';

        // Loop through the max rows
        for ($row = 1; $row <= $maxRows + 2; $row++) {
            $form .= '<tr>';
            $form .= '  <td>' . $row . '</td>';

            // $row is the current row
            for ($col = 0; $col < $maxCols; $col++) {
                $dataSetColumnId = $columnDefinition[$col]['DataSetColumnID'];
                $listContent = $columnDefinition[$col]['ListContent'];
                $columnOrder = $columnDefinition[$col]['ColumnOrder'];
                $dataTypeId = $columnDefinition[$col]['DataTypeID'];

                // Value for this Col/Row
                $value = '';

                if ($row <= $maxRows) {
                    // This is intended to be a blank row
                    $SQL = "";
                    $SQL .= "SELECT Value ";
                    $SQL .= "  FROM datasetdata ";
                    $SQL .= "WHERE datasetdata.RowNumber = %d ";
                    $SQL .= "   AND datasetdata.DataSetColumnID = %d ";
                    $SQL = sprintf($SQL, $row, $dataSetColumnId);

                    Log::notice($SQL, 'dataset');

                    if (!$results = $db->query($SQL)) {
                        trigger_error($db->error());
                        trigger_error(__('Can not get the data row/column'), E_USER_ERROR);
                    }

                    if ($db->num_rows($results) == 0) {
                        $value = '';
                    } else {
                        $valueRow = $db->get_assoc_row($results);
                        $value = $valueRow['Value'];
                    }
                }

                // Do we need a select list?
                if ($listContent != '') {
                    $listItems = explode(',', $listContent);
                    $selected = ($value == '') ? ' selected' : '';
                    $select = '<select class="form-control" name="value">';
                    $select .= '     <option value="" ' . $selected . '></option>';

                    for ($i = 0; $i < count($listItems); $i++) {
                        $selected = ($listItems[$i] == $value) ? ' selected' : '';

                        $select .= '<option value="' . $listItems[$i] . '" ' . $selected . '>' . $listItems[$i] . '</option>';
                    }

                    $select .= '</select>';
                } else {
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

    }

    public function AddDataSetData()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $response->uniqueReference = \Xibo\Helper\Sanitize::getString('fieldid');
        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSetColumnId = \Xibo\Helper\Sanitize::getInt('datasetcolumnid');
        $rowNumber = \Xibo\Helper\Sanitize::getInt('rownumber');
        $value = \Xibo\Helper\Sanitize::getString('value');

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

    }

    public function EditDataSetData()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $response->uniqueReference = \Xibo\Helper\Sanitize::getString('fieldid');
        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSetColumnId = \Xibo\Helper\Sanitize::getInt('datasetcolumnid');
        $rowNumber = \Xibo\Helper\Sanitize::getInt('rownumber');
        $value = \Xibo\Helper\Sanitize::getString('value');

        $auth = $user->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'));

        if ($value == '') {
            $dataSetObject = new DataSetData($db);
            if (!$dataSetObject->Delete($dataSetColumnId, $rowNumber))
                trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

            $response->SetFormSubmitResponse(__('Data Deleted'));
            $response->loadFormUri = 'index.php?p=dataset&q=AddDataSetData';
        } else {
            $dataSetObject = new DataSetData($db);
            if (!$dataSetObject->Edit($dataSetColumnId, $rowNumber, $value))
                trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

            $response->SetFormSubmitResponse(__('Data Edited'));
            $response->loadFormUri = 'index.php?p=dataset&q=EditDataSetData';
        }

        $response->hideMessage = true;
        $response->keepOpen = true;

    }
}