<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2011-2017 Spring Signage Ltd
 * contributions by LukyLuke aka Lukas Zurschmiede - https://github.com/LukyLuke
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

use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Helper\DataSetUploadHandler;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class DataSet
 * @package Xibo\Controller
 */
class DataSet extends Base
{
    /** @var  DataSetFactory */
    private $dataSetFactory;

    /** @var  DataSetColumnFactory */
    private $dataSetColumnFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $dataSetFactory, $dataSetColumnFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
    }

    /**
     * @return SanitizerServiceInterface
     */
    public function getSanitizer()
    {
        return parent::getSanitizer();
    }

    /**
     * @return DataSetFactory
     */
    public function getDataSetFactory()
    {
        return $this->dataSetFactory;
    }

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
     *
     * @SWG\Get(
     *  path="/dataset",
     *  operationId="dataSetSearch",
     *  tags={"dataset"},
     *  summary="DataSet Search",
     *  description="Search this users DataSets",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="formData",
     *      description="Filter by DataSet Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dataSet",
     *      in="formData",
     *      description="Filter by DataSet Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="Filter by DataSet Code",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embed",
     *      in="formData",
     *      description="Embed related data such as columns",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/DataSet")
     *      )
     *  )
     * )
     */
    public function grid()
    {
        $user = $this->getUser();
        
        // Embed?
        $embed = ($this->getSanitizer()->getString('embed') != null) ? explode(',', $this->getSanitizer()->getString('embed')) : [];
        
        $filter = [
            'dataSetId' => $this->getSanitizer()->getInt('dataSetId'),
            'dataSet' => $this->getSanitizer()->getString('dataSet'),
            'code' => $this->getSanitizer()->getString('code'),
        ];

        $dataSets = $this->dataSetFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        foreach ($dataSets as $dataSet) {
            /* @var \Xibo\Entity\DataSet $dataSet */
            if (in_array('columns', $embed)) {
                $dataSet->load();
            }
            if ($this->isApi())
                break;

            $dataSet->includeProperty('buttons');
            $dataSet->buttons = [];

            // Load the dataSet to get the columns
            $dataSet->load();

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
                    'class' => 'XiboRedirectButton',
                    'text' => __('View Columns')
                );

                // Divider
                $dataSet->buttons[] = ['divider' => true];

                // Import DataSet
                if ($dataSet->isRemote !== 1) {
                    $dataSet->buttons[] = array(
                        'id' => 'dataset_button_import',
                        'class' => 'dataSetImportForm',
                        'url' => $this->urlFor('dataSet.import.form', ['id' => $dataSet->dataSetId]),
                        'text' => __('Import CSV')
                    );
                }

                // Copy
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_copy',
                    'url' => $this->urlFor('dataSet.copy.form', ['id' => $dataSet->dataSetId]),
                    'text' => __('Copy')
                );

                // Divider
                $dataSet->buttons[] = ['divider' => true];

                // Edit DataSet
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_edit',
                    'url' => $this->urlFor('dataSet.edit.form', ['id' => $dataSet->dataSetId]),
                    'text' => __('Edit')
                );
            }

            if ($user->checkDeleteable($dataSet) && $dataSet->isLookup == 0) {
                // Delete DataSet
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_delete',
                    'url' => $this->urlFor('dataSet.delete.form', ['id' => $dataSet->dataSetId]),
                    'text' => __('Delete')
                );
            }

            // Divider
            $dataSet->buttons[] = ['divider' => true];

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
        $this->getState()->recordsTotal = $this->dataSetFactory->countLast();
        $this->getState()->setData($dataSets);
    }

    /**
     * Add DataSet Form
     */
    public function addForm()
    {
        $this->getState()->template = 'dataset-form-add';
        $this->getState()->setData([
            'dataSets' => $this->dataSetFactory->query(),
            'help' => $this->getHelp()->link('DataSet', 'Add')
        ]);
    }

    /**
     * Add dataSet
     *
     * @SWG\Post(
     *  path="/dataset",
     *  operationId="dataSetAdd",
     *  tags={"dataset"},
     *  summary="Add DataSet",
     *  description="Add a DataSet",
     *  @SWG\Parameter(
     *      name="dataSet",
     *      in="formData",
     *      description="The DataSet Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description of this DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="A code for this DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DataSet"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @throws XiboException
     */
    public function add()
    {
        $dataSet = $this->dataSetFactory->createEmpty();
        $dataSet->dataSet = $this->getSanitizer()->getString('dataSet');
        $dataSet->description = $this->getSanitizer()->getString('description');
        $dataSet->code = $this->getSanitizer()->getString('code');
        $dataSet->isRemote = $this->getSanitizer()->getCheckbox('isRemote');
        $dataSet->userId = $this->getUser()->userId;

        // Fields for remote
        if ($dataSet->isRemote === 1) {
            $dataSet->method = $this->getSanitizer()->getString('method');
            $dataSet->uri = $this->getSanitizer()->getString('uri');
            $dataSet->postData = $this->getSanitizer()->getString('postData');
            $dataSet->authentication = $this->getSanitizer()->getString('authentication');
            $dataSet->username = $this->getSanitizer()->getString('username');
            $dataSet->password = $this->getSanitizer()->getString('password');
            $dataSet->refreshRate = $this->getSanitizer()->getInt('refreshRate');
            $dataSet->clearRate = $this->getSanitizer()->getInt('clearRate');
            $dataSet->runsAfter = $this->getSanitizer()->getInt('runsAfter');
            $dataSet->dataRoot = $this->getSanitizer()->getString('dataRoot');
            $dataSet->summarize = $this->getSanitizer()->getString('summarize');
            $dataSet->summarizeField = $this->getSanitizer()->getString('summarizeField');
        }

        // Also add one column
        $dataSetColumn = $this->dataSetColumnFactory->createEmpty();
        $dataSetColumn->columnOrder = 1;
        $dataSetColumn->heading = 'Col1';
        $dataSetColumn->dataSetColumnTypeId = 1;
        $dataSetColumn->dataTypeId = 1;

        // Add Column
        $dataSet->assignColumn($dataSetColumn);

        // Save
        $dataSet->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $dataSet->dataSet),
            'id' => $dataSet->dataSetId,
            'data' => $dataSet
        ]);
    }

    /**
     * Edit DataSet Form
     * @param int $dataSetId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function editForm($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Set the form
        $this->getState()->template = 'dataset-form-edit';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'dataSets' => $this->dataSetFactory->query(),
            'help' => $this->getHelp()->link('DataSet', 'Edit')
        ]);
    }

    /**
     * Edit DataSet
     * @param int $dataSetId
     *
     * @SWG\Put(
     *  path="/dataset/{dataSetId}",
     *  operationId="dataSetEdit",
     *  tags={"dataset"},
     *  summary="Edit DataSet",
     *  description="Edit a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSet",
     *      in="formData",
     *      description="The DataSet Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description of this DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="A code for this DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DataSet")
     *  )
     * )
     *
     * @throws XiboException
     */
    public function edit($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $dataSet->dataSet = $this->getSanitizer()->getString('dataSet');
        $dataSet->description = $this->getSanitizer()->getString('description');
        $dataSet->code = $this->getSanitizer()->getString('code');
        $dataSet->isRemote = $this->getSanitizer()->getCheckbox('isRemote');

        if ($dataSet->isRemote === 1) {
            $dataSet->method = $this->getSanitizer()->getString('method');
            $dataSet->uri = $this->getSanitizer()->getString('uri');
            $dataSet->postData = $this->getSanitizer()->getString('postData');
            $dataSet->authentication = $this->getSanitizer()->getString('authentication');
            $dataSet->username = $this->getSanitizer()->getString('username');
            $dataSet->password = $this->getSanitizer()->getString('password');
            $dataSet->refreshRate = $this->getSanitizer()->getInt('refreshRate');
            $dataSet->clearRate = $this->getSanitizer()->getInt('clearRate');
            $dataSet->runsAfter = $this->getSanitizer()->getInt('runsAfter');
            $dataSet->dataRoot = $this->getSanitizer()->getString('dataRoot');
            $dataSet->summarize = $this->getSanitizer()->getString('summarize');
            $dataSet->summarizeField = $this->getSanitizer()->getString('summarizeField');
        }

        $dataSet->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $dataSet->dataSet),
            'id' => $dataSet->dataSetId,
            'data' => $dataSet
        ]);
    }

    /**
     * DataSet Delete
     * @param int $dataSetId
     * @throws XiboException
     */
    public function deleteForm($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkDeleteable($dataSet))
            throw new AccessDeniedException();

        if ($dataSet->isLookup)
            throw new \InvalidArgumentException(__('Lookup Tables cannot be deleted'));

        // Set the form
        $this->getState()->template = 'dataset-form-delete';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'help' => $this->getHelp()->link('DataSet', 'Delete')
        ]);
    }

    /**
     * DataSet Delete
     * @param int $dataSetId
     *
     * @SWG\Delete(
     *  path="/dataset/{dataSetId}",
     *  operationId="dataSetDelete",
     *  tags={"dataset"},
     *  summary="Delete DataSet",
     *  description="Delete a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     */
    public function delete($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkDeleteable($dataSet))
            throw new AccessDeniedException();

        // Is there existing data?
        if ($this->getSanitizer()->getCheckbox('deleteData') == 0 && $dataSet->hasData())
            throw new InvalidArgumentException(__('There is data assigned to this data set, cannot delete.'), 'dataSetId');

        // Otherwise delete
        $dataSet->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $dataSet->dataSet)
        ]);
    }

    /**
     * Copy DataSet Form
     * @param int $dataSetId
     * @throws \Xibo\Exception\NotFoundException
     */
    public function copyForm($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Set the form
        $this->getState()->template = 'dataset-form-copy';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'help' => $this->getHelp()->link('DataSet', 'Edit')
        ]);
    }

    /**
     * Copy DataSet
     * @param int $dataSetId
     *
     * @SWG\Post(
     *  path="/dataset/copy/{dataSetId}",
     *  operationId="dataSetCopy",
     *  tags={"dataset"},
     *  summary="Copy DataSet",
     *  description="Copy a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSet",
     *      in="formData",
     *      description="The DataSet Name",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="description",
     *      in="formData",
     *      description="A description of this DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="formData",
     *      description="A code for this DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DataSet")
     *  )
     * )
     *
     * @throws XiboException
     */
    public function copy($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Load for the Copy
        $dataSet->load();
        $oldName = $dataSet->dataSet;

        // Clone and reset parameters
        $dataSet = clone $dataSet;
        $dataSet->dataSet = $this->getSanitizer()->getString('dataSet');
        $dataSet->description = $this->getSanitizer()->getString('description');
        $dataSet->code = $this->getSanitizer()->getString('code');
        $dataSet->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Copied %s as %s'), $oldName, $dataSet->dataSet),
            'id' => $dataSet->dataSetId,
            'data' => $dataSet
        ]);
    }

    /**
     * Import CSV
     * @param int $dataSetId
     *
     * @SWG\Post(
     *  path="/dataset/import/{dataSetId}",
     *  operationId="dataSetImport",
     *  tags={"dataset"},
     *  summary="Import CSV",
     *  description="Import a CSV into a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID to import into.",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="files",
     *      in="formData",
     *      description="The file",
     *      type="file",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="csvImport_{dataSetColumnId}",
     *      in="formData",
     *      description="You need to provide dataSetColumnId after csvImport_, to know your dataSet columns Ids, you will need to use the GET /dataset/{dataSetId}/column call first. The value of this parameter is the index of the column in your csv file, where the first column is 1",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="overwrite",
     *      in="formData",
     *      description="flag (0,1) Set to 1 to erase all content in the dataSet and overwrite it with new content in this import",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ignorefirstrow",
     *      in="formData",
     *      description="flag (0,1), Set to 1 to Ignore first row, useful if the CSV file has headings",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws XiboException
     * @throws \Exception
     */
    public function import($dataSetId)
    {
        $this->getLog()->debug('Import DataSet');

        $libraryFolder = $this->getConfig()->GetSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        Library::ensureLibraryExists($this->getConfig()->GetSetting('LIBRARY_LOCATION'));

        $options = array(
            'userId' => $this->getUser()->userId,
            'dataSetId' => $dataSetId,
            'controller' => $this,
            'upload_dir' => $libraryFolder . 'temp/',
            'download_via_php' => true,
            'script_url' => $this->urlFor('dataSet.import'),
            'upload_url' => $this->urlFor('dataSet.import'),
            'image_versions' => array(),
            'accept_file_types' => '/\.csv/i'
        );

        try {
            // Hand off to the Upload Handler provided by jquery-file-upload
            new DataSetUploadHandler($options);

        } catch (\Exception $e) {
            // We must not issue an error, the file upload return should have the error object already
            $this->getApp()->commit = false;
        }

        $this->setNoOutput(true);
    }

    /**
     * Import JSON
     * @param int $dataSetId
     * @throws \Exception
     *
     * @SWG\Post(
     *  path="/dataset/importjson/{dataSetId}",
     *  operationId="dataSetImportJson",
     *  tags={"dataset"},
     *  summary="Import JSON",
     *  description="Import JSON into a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID to import into.",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="data",
     *      in="body",
     *      schema="json",
     *      description="The row data, field name vs field data format. e.g. { uniqueKeys: [col1], rows: [{col1: value1}]}",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     */
    public function importJson($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $body = $this->getApp()->request()->getBody();

        if (empty($body))
            throw new \InvalidArgumentException(__('Missing JSON Body'));

        // Expect 2 parameters
        $data = json_decode($body, true);

        if (!isset($data['rows']) || !isset($data['uniqueKeys']))
            throw new \InvalidArgumentException(__('Malformed JSON body, rows and uniqueKeys are required'));

        $this->getLog()->debug('Import JSON into DataSet with ' . count($data['rows']) . ' and unique keys ' . json_encode($data['uniqueKeys']));

        // Should we truncate?
        if (isset($data['truncate']) && $data['truncate']) {
            $dataSet->deleteData();
        }

        // Get the columns for this dataset
        $columns = [];
        foreach ($dataSet->getColumn() as $column) {
            /* @var \Xibo\Entity\DataSetColumn $column */
            if ($column->dataSetColumnTypeId == 1) {
                $columns[$column->heading] = $column->dataTypeId;
            }
        }

        $takenSomeAction = false;

        // Parse and validate each data row we've been provided
        foreach ($data['rows'] as $row) {
            // Parse each property
            $sanitizedRow = null;
            foreach ($row as $key => $value) {
                // Does the property in the provided row exist as a column?
                if (isset($columns[$key])) {
                    // Sanitize accordingly
                    if ($columns[$key] == 2) {
                        // Number
                        $value = $this->getSanitizer()->double($value);
                    }
                    else if ($columns[$key] == 3) {
                        // Date
                        $value = $this->getDate()->getLocalDate($this->getDate()->parse($value));
                    }
                    else if ($columns[$key] == 5) {
                        // Media Id
                        $value = $this->getSanitizer()->int($value);
                    }
                    else {
                        // String
                        $value = $this->getSanitizer()->string($value);
                    }

                    // Data is sanitized, add to the sanitized row
                    $sanitizedRow[$key] = $value;
                }
            }

            if (count($sanitizedRow) > 0) {
                $takenSomeAction = true;

                // Check unique keys to see if this is an update
                if (!empty($data['uniqueKeys']) && is_array($data['uniqueKeys'])) {

                    // Build a filter to select existing records
                    $filter = '';
                    foreach ($data['uniqueKeys'] as $uniqueKey) {
                        if (isset($sanitizedRow[$uniqueKey])) {
                            $filter .= 'AND `' . $uniqueKey . '` = \'' . $sanitizedRow[$uniqueKey] . '\' ';
                        }
                    }
                    $filter = trim($filter, 'AND');

                    // Use the unique keys to look up this row and see if it exists
                    $existingRows = $dataSet->getData(['filter' => $filter], ['includeFormulaColumns' => false, 'requireTotal' => false]);

                    if (count($existingRows) > 0) {
                        foreach ($existingRows as $existingRow) {
                            $dataSet->editRow($existingRow['id'], array_merge($existingRow, $sanitizedRow));
                        }
                    }
                    else {
                        $dataSet->addRow($sanitizedRow);
                    }

                } else {
                    $dataSet->addRow($sanitizedRow);
                }
            }
        }

        if (!$takenSomeAction)
            throw new NotFoundException(__('No data found in request body'));

        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Imported JSON into %s'), $dataSet->dataSet)
        ]);
    }

    /**
     * Sends out a TestRequst and returns the Data as JSON to the Client so it can be shown in the Dialog
     */
    public function testRemoteRequest() {
        $dataSet = $this->dataSetFactory->createEmptyRemote();
        $dataSet->dataSet = $this->getSanitizer()->getString('dataSet');
        $dataSet->method = $this->getSanitizer()->getString('method');
        $dataSet->uri = $this->getSanitizer()->getString('uri');
        $dataSet->postData = $this->getSanitizer()->getString('postData');
        $dataSet->authentication = $this->getSanitizer()->getString('authentication');
        $dataSet->username = $this->getSanitizer()->getString('username');
        $dataSet->password = $this->getSanitizer()->getString('password');
        $dataSet->dataRoot = $this->getSanitizer()->getString('dataRoot');

        $data = $this->dataSetFactory->callRemoteService($dataSet);

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Run Test-Request for %s on %s'), $dataSet->dataSet, $dataSet->getCurlParams()[CURLOPT_URL]),
            'id' => $dataSet->dataSetId,
            'data' => $data->entries[0]
        ]);
    }

    /**
     * Tries to process received Data against the configured DataSet with all Columns
     *
     * @param \Xibo\Entity\DataSetRemote $dataSet The RemoteDataset to process
     * @param \stdClass $results A simple Object with one Property 'entries' which contains all results
     */
    public function processResults(\Xibo\Entity\DataSetRemote $dataSet, \stdClass $results) {
        if (property_exists($results, 'entries') && is_array($results->entries)) {
            foreach ($results->entries as $result) {
                $this->process($dataSet, $result);
            }
        }
    }

    /**
     * Tries to process received Data against the configured DataSet with all Columns
     *
     * @param \Xibo\Entity\DataSetRemote $dataSet The RemoteDataset to process
     * @param array The JSON received from the remote endpoint
     */
    private function process(\Xibo\Entity\DataSetRemote $dataSet, array $result) {
        // Remote Data has to have the configured DataRoot which has to be an Array
        $data = $this->getDataRootFromResult($dataSet->dataRoot, $result);
        if (($data != null) && is_array($data)) {
            $columns = $this->dataSetColumnFactory->query(null, ['dataSetId' => $dataSet->dataSetId]);
            $entries = [];

            // First process each entry form the remote and try to map the values to the configured columns
            foreach($data as $k => $entry) {
                if (is_array($entry) || is_object($entry)) {
                    $entries[] = $this->processEntry($dataSet, (array) $entry, $columns);
                } else {
                    $message = sprintf(__('DataSet \'%s\' failed: DataRoot \'%s\' contains data which are not arrays and not objects.'), $dataSet->dataSet, $dataSet->dataRoot);
                    break;
                }
            }

            // If there is a Consilidation-Function, use the Data against it
            $entries = $this->consolidateEntries($dataSet, $entries, $columns);

            // Finally add each entry as a new Row in the DataSet
            foreach ($entries as $entry) {
                $dataSet->addRow($entry);
            }
        } else {
            $message = sprintf(__('DataSet \'%s\' missconfigured: DataRoot \'%s\' is not an Array.'), $dataSet->dataSet, $dataSet->dataRoot);
        }

        // Return
        $this->getState()->hydrate([
            'message' => $message,
            'id' => $dataSet->dataSetId
        ]);
    }

    /**
     * Process the RemoteResult to get the main DataRoot value which can be stay in a structure as well as the values
     *
     * @param String Chuns splitted by a Dot where the main entries are hold
     * @param array The Value from the remote request
     * @return array The Data hold in the configured dataRoot
     */
    private function getDataRootFromResult($dataRoot, array $result) {
        if (empty($dataRoot)) {
            return $result;
        }
        $chunks = explode('.', $dataRoot);
        $entries = $this->getFieldValueFromEntry($chunks, $result);
        return $entries[1];
    }

    /**
     * Process a single Data-Entry form the remote system and map it to the configured Columns
     *
     * @param \Xibo\Entity\DataSetRemote $dataSet The DataSet which is processed currently
     * @param array $entry The Data from the remte system
     * @param array $columns The configured Columns form the current DataSet
     * @return array The processed $entry as a List of Fields from $columns
     */
    private function processEntry(\Xibo\Entity\DataSetRemote $dataSet, array $entry, array $columns) {
        $result = [];

        foreach ($columns as $k => $column) {
            if (($column->remoteField != null) && ($column->remoteField != '')) {
                $dataTypeId = $column->dataTypeId;

                // The Field may be a Date, timestamp or a real field
                if ($column->remoteField == '{{DATE}}') {
                    $value = [0, date('Y-m-d')];

                } else if ($column->remoteField == '{{TIMESTAMP}}') {
                    $value = [0, time()];

                } else {
                    $chunks = explode('.', $column->remoteField);
                    $value = $this->getFieldValueFromEntry($chunks, $entry);
                }

                // Only add it to the result if we where able to process the field
                if (($value != null) && ($value[1] != null)) {
                    switch ($dataTypeId) {
                        case 2:
                            $result[$column->heading] = $this->getSanitizer()->double($value[1]);
                            break;
                        case 3:
                            $result[$column->heading] = $this->getDate()->getLocalDate(strtotime($value[1]));
                            break;
                        case 5:
                            $result[$column->heading] = $this->getSanitizer()->int($value[1]);
                            break;
                        default:
                            $result[$column->heading] = $this->getSanitizer()->string($value[1]);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Returns the Value of the remote DataEntry based on the remoteField definition splitted into chunks
     *
     * This function is recursive, so be sure you remove the first value from chunks and pass it in again
     *
     * @param array List of Chunks which interprets the FieldNames in the actual DataEntry
     * @param array $entry Current DataEntry
     * @return array of the last FieldName and the corresponding value
     */
    private function getFieldValueFromEntry(array $chunks, array $entry) {
        $value = null;
        $key = array_shift($chunks);

        if (($entry instanceof \StdClass) && property_exists($entry, $key)) {
            $value = $entry->{$key};
        } else if (array_key_exists($key, $entry)) {
            $value = $entry[$key];
        }

        if (($value != null) && (count($chunks) > 0)) {
            return $this->getFieldValueFromEntry($chunks, (array) $value);
        }

        return [ $key, $value ];
    }

    /**
     * Consolidates all Entries by the defined Function in the DataSet
     *
     * This Method *sums* or *counts* all same entries and returns them.
     * If no consolidation function is configured, nothing is done here.
     *
     * @param \Xibo\Entity\DataSetRemote $dataSet the current DataSet
     * @param array $entries All processed entries which may be consolidated
     * @param array $columns The columns form this DataSet
     * @return \Slim\Helper\Set which contains all Entries to be added to the DataSet
     */
    private function consolidateEntries(\Xibo\Entity\DataSetRemote $dataSet, array $entries, array $columns) {
        if ((count($entries) > 0) && $dataSet->doConsolidate()) {
            $consolidated = new \Slim\Helper\Set();
            $field = $dataSet->getConsolidationField();

            // Get the Field-Heading based on the consolidation field
            foreach ($columns as $k => $column) {
                if ($column->remoteField == $dataSet->summarizeField) {
                    $field = $column->heading;
                    break;
                }
            }

            // Check each entry and consolidate the value form the defined field
            foreach ($entries as $entry) {
                if (array_key_exists($field, $entry)) {
                    $key = $field . '-' . $entry[$field];
                    $existing = $consolidated->get($key);

                    // Create a new one if there is no currently consolidated field for this value
                    if ($existing == null) {
                        $existing = $entry;
                        $existing[$field] = 0;
                    }

                    // Consolidate: Summarize, Count, Unknown
                    if ($dataSet->summarize == 'sum') {
                        $existing[$field] = $existing[$field] + $entry[$field];

                    } else if ($dataSet->summarize == 'count') {
                        $existing[$field] = $existing[$field] + 1;

                    } else {
                        // Unknown consolidation type :?
                        $existing[$field] = 0;
                    }

                    $consolidated->set($key, $existing);
                }
            }

            return $consolidated;
        }
        return new \Slim\Helper\Set($entries);
    }
}
