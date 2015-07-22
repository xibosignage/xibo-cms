<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
namespace Xibo\Controller;

use Xibo\Entity\DataSetColumn;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\DataSetFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Help;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Theme;


class DataSet extends Base
{
    /**
     * View Route
     */
    public function displayPage()
    {
        $this->getState()->template = 'dataset-page';
    }

    /**
     * Search Data
     * @throws \Xibo\Exception\NotFoundException
     */
    public function grid()
    {
        $user = $this->getUser();

        $dataSets = DataSetFactory::query();

        foreach ($dataSets as $dataSet) {
            /* @var \Xibo\Entity\DataSet $dataSet */

            if ($this->isApi())
                break;

            $dataSet->buttons = [];

            if ($user->checkEditable($dataSet)) {

                // View Data
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_viewdata',
                    'class' => 'XiboRedirectButton',
                    'url' => $this->urlFor('dataSet.view.data', ['id' => $dataSet->dataSetId]),
                    'text' => __('View Data')
                );

                // View Columns
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_viewcolumns',
                    'url' => $this->urlFor('dataSet.column.view', ['id' => $dataSet->dataSetId]),
                    'link' => true,
                    'text' => __('View Columns')
                );

                // Edit DataSet
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_edit',
                    'url' => $this->urlFor('dataSet.edit.form', ['id' => $dataSet->dataSetId]),
                    'text' => __('Edit')
                );

                // Import DataSet
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_import',
                    'url' => $this->urlFor('dataSet.import.form', ['id' => $dataSet->dataSetId]),
                    'text' => __('Import CSV')
                );
            }

            if ($user->checkDeleteable($dataSet)) {
                // Delete DataSet
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_delete',
                    'url' => $this->urlFor('dataSet.delete.form', ['id' => $dataSet->dataSetId]),
                    'text' => __('Delete')
                );
            }

            if ($user->checkPermissionsModifyable($dataSet)) {
                // Edit Permissions
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_permissions',
                    'url' => $this->urlFor('user.permissions.form', ['entity' => 'DataSet', 'id' => $dataSet->dataSetId]),
                    'text' => __('Permissions')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = DataSetFactory::countLast();
        $this->getState()->setData($dataSets);
    }

    /**
     * Add DataSet Form
     */
    public function addForm()
    {
        $this->getState()->template = 'dataset-form-add';
        $this->getState()->setData([
            'help' => Help::Link('DataSet', 'Add')
        ]);
    }

    /**
     * Add dataSet
     */
    public function add()
    {
        $dataSet = new \Xibo\Entity\DataSet();
        $dataSet->dataSet = Sanitize::getString('dataSet');
        $dataSet->description = Sanitize::getString('description');
        $dataSet->userId = $this->getUser()->userId;

        // Also add one column
        $dataSetColumn = new DataSetColumn();
        $dataSetColumn->columnOrder = 1;
        $dataSetColumn->heading = 'Col1';
        $dataSetColumn->dataSetColumnTypeId = 1;

        // Add Column
        $dataSet->assignColumn($dataSetColumn);

        // Save
        $dataSet->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $dataSet->dataSet),
            'id' => $dataSet->dataSetId,
            'data' => [$dataSet]
        ]);
    }

    /**
     * Edit DataSet Form
     * @param int $dataSetId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function editForm($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Set the form
        $this->getState()->template = 'dataset-form-edit';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'help' => Help::Link('DataSet', 'Edit')
        ]);
    }

    /**
     * Edit DataSet
     * @param int $dataSetId
     */
    public function edit($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $dataSet->dataSet = Sanitize::getString('dataSet');
        $dataSet->description = Sanitize::getString('description');
        $dataSet->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $dataSet->dataSet),
            'id' => $dataSet->dataSetId,
            'data' => [$dataSet]
        ]);
    }

    /**
     * DataSet Delete
     * * @param int $dataSetId
     */
    public function deleteForm($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkDeleteable($dataSet))
            throw new AccessDeniedException();

        // Set the form
        $this->getState()->template = 'dataset-form-delete';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'help' => Help::Link('DataSet', 'Delete')
        ]);
    }

    /**
     * DataSet Delete
     * @param int $dataSetId
     */
    public function delete($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkDeleteable($dataSet))
            throw new AccessDeniedException();

        // Is there existing data?
        if (Sanitize::getCheckbox('deleteData') == 0 && $dataSet->hasData())
            throw new \InvalidArgumentException(__('There is data assigned to this data set, cannot delete.'));

        // Otherwise delete
        $dataSet->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $dataSet->dataSet)
        ]);
    }

    /**
     * Import Form
     * @param $dataSetId
     */
    public function importForm($dataSetId)
    {
        global $session;

        $response = $this->getState();

        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $dataSet = \Xibo\Helper\Sanitize::getString('dataset');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
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
        $formFields[] = Form::AddCheckbox('overwrite', __('Overwrite existing data?'),
            NULL,
            __('Erase all content in this DataSet and overwrite it with the new content in this import.'),
            'o');

        $formFields[] = Form::AddCheckbox('ignorefirstrow', __('Ignore first row?'),
            NULL,
            __('Ignore the first row? Useful if the CSV has headings.'),
            'i');

        // Enumerate over the columns in the DataSet and offer a column mapping for each one (from the file)
        $SQL = "";
        $SQL .= "SELECT DataSetColumnID, Heading ";
        $SQL .= "  FROM datasetcolumn ";
        $SQL .= sprintf(" WHERE DataSetID = %d ", $dataSetId);
        $SQL .= "   AND DataSetColumnTypeID = 1 ";
        $SQL .= "ORDER BY ColumnOrder ";

        // Load results into an array
        $dataSetColumns = $db->GetArray($SQL);

        if (!is_array($dataSetColumns)) {
            trigger_error($db->error());
            trigger_error(__('Error getting list of dataSetColumns'), E_USER_ERROR);
        }

        $i = 0;

        foreach ($dataSetColumns as $row) {
            $i++;

            $formFields[] = Form::AddNumber('csvImport_' . \Xibo\Helper\Sanitize::int($row['DataSetColumnID']),
                \Xibo\Helper\Sanitize::string($row['Heading']), $i, NULL, 'c');
        }

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('CSV Import'), '350px', '200px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('DataSet', 'ImportCsv') . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Import'), '$("#DataSetImportCsvForm").submit()');

    }

    /**
     * @param int $dataSetId
     */
    public function import($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $response = $this->getState();
        $dataSetId = \Xibo\Helper\Sanitize::getInt('datasetid');
        $overwrite = \Xibo\Helper\Sanitize::getCheckbox('overwrite');
        $ignorefirstrow = \Xibo\Helper\Sanitize::getCheckbox('ignorefirstrow');

        $auth = $this->getUser()->DataSetAuth($dataSetId, true);
        if (!$auth->edit)
            trigger_error(__('Access Denied'), E_USER_ERROR);

        // File data
        $tmpName = \Xibo\Helper\Sanitize::getString('hidFileID');

        if ($tmpName == '')
            trigger_error(__('Please ensure you have picked a file and it has finished uploading'), E_USER_ERROR);

        // File name and extension (original name)
        $fileName = \Xibo\Helper\Sanitize::getString('txtFileName');
        $fileName = basename($fileName);
        $ext = strtolower(substr(strrchr($fileName, "."), 1));

        // Check it is a CSV file
        if ($ext != 'csv')
            trigger_error(__('Files with a CSV extension only.'), E_USER_ERROR);

        // File upload directory.. get this from the settings object
        $csvFileLocation = Config::GetSetting('LIBRARY_LOCATION') . 'temp/' . $tmpName;

        // Enumerate over the columns in the DataSet and offer a column mapping for each one (from the file)
        $SQL = "";
        $SQL .= "SELECT DataSetColumnID ";
        $SQL .= "  FROM datasetcolumn ";
        $SQL .= sprintf(" WHERE DataSetID = %d ", $dataSetId);
        $SQL .= "   AND DataSetColumnTypeID = 1 ";
        $SQL .= "ORDER BY ColumnOrder ";

        // Load results into an array
        $dataSetColumns = $db->GetArray($SQL);

        if (!is_array($dataSetColumns)) {
            trigger_error($db->error());
            trigger_error(__('Error getting list of dataSetColumns'), E_USER_ERROR);
        }

        $spreadSheetMapping = array();

        foreach ($dataSetColumns as $row) {

            $dataSetColumnId = \Xibo\Helper\Sanitize::int($row['DataSetColumnID']);
            $spreadSheetColumn = \Kit::GetParam('csvImport_' . $dataSetColumnId, _POST, _INT);

            // If it has been left blank, then skip
            if ($spreadSheetColumn != 0)
                $spreadSheetMapping[($spreadSheetColumn - 1)] = $dataSetColumnId;
        }

        $dataSetObject = new DataSetData($db);

        if (!$dataSetObject->ImportCsv($dataSetId, $csvFileLocation, $spreadSheetMapping, ($overwrite == 1), ($ignorefirstrow == 1)))
            trigger_error($dataSetObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('CSV File Imported'));

    }
}

?>
