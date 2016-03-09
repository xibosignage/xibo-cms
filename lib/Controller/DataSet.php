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

use Xibo\Exception\AccessDeniedException;
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

        $filter = [
            'dataSetId' => $this->getSanitizer()->getInt('dataSetId'),
            'dataSet' => $this->getSanitizer()->getString('dataSet')
        ];

        $dataSets = $this->dataSetFactory->query($this->gridRenderSort(), $this->gridRenderFilter($filter));

        foreach ($dataSets as $dataSet) {
            /* @var \Xibo\Entity\DataSet $dataSet */

            if ($this->isApi())
                break;

            $dataSet->includeProperty('buttons');
            $dataSet->buttons = [];
            $dataSet->importColumns = [];

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
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_import',
                    'class' => 'dataSetImportForm',
                    'url' => $this->urlFor('dataSet.import.form', ['id' => $dataSet->dataSetId]),
                    'text' => __('Import CSV')
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
     */
    public function add()
    {
        $dataSet = $this->dataSetFactory->createEmpty();
        $dataSet->dataSet = $this->getSanitizer()->getString('dataSet');
        $dataSet->description = $this->getSanitizer()->getString('description');
        $dataSet->userId = $this->getUser()->userId;

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
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DataSet")
     *  )
     * )
     */
    public function edit($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $dataSet->dataSet = $this->getSanitizer()->getString('dataSet');
        $dataSet->description = $this->getSanitizer()->getString('description');
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
     * * @param int $dataSetId
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
     */
    public function delete($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkDeleteable($dataSet))
            throw new AccessDeniedException();

        // Is there existing data?
        if ($this->getSanitizer()->getCheckbox('deleteData') == 0 && $dataSet->hasData())
            throw new \InvalidArgumentException(__('There is data assigned to this data set, cannot delete.'));

        // Otherwise delete
        $dataSet->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $dataSet->dataSet)
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
     *      name="file",
     *      in="formData",
     *      description="The file",
     *      type="file",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
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
}
