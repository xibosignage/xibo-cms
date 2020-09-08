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

    /** @var \Xibo\Factory\UserFactory */
    private $userFactory;

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
     * @param \Xibo\Factory\UserFactory $userFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $dataSetFactory, $dataSetColumnFactory, $userFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
        $this->userFactory = $userFactory;
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
        $this->getState()->setData([
            'users' => $this->userFactory->query(),
        ]);
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
     *      in="query",
     *      description="Filter by DataSet Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dataSet",
     *      in="query",
     *      description="Filter by DataSet Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="code",
     *      in="query",
     *      description="Filter by DataSet Code",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="userId",
     *      in="query",
     *      description="Filter by user Id",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="embed",
     *      in="query",
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
            'useRegexForName' => $this->getSanitizer()->getCheckbox('useRegexForName'),
            'code' => $this->getSanitizer()->getString('code'),
            'userId' => $this->getSanitizer()->getInt('userId'),
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

                // View RSS
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_viewrss',
                    'url' => $this->urlFor('dataSet.rss.view', ['id' => $dataSet->dataSetId]),
                    'class' => 'XiboRedirectButton',
                    'text' => __('View RSS')
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
                $dataSet->buttons[] = [
                    'id' => 'dataset_button_delete',
                    'url' => $this->urlFor('dataSet.delete.form', ['id' => $dataSet->dataSetId]),
                    'text' => __('Delete'),
                    'multi-select' => true,
                    'dataAttributes' => [
                        ['name' => 'commit-url', 'value' => $this->urlFor('dataSet.delete', ['id' => $dataSet->dataSetId])],
                        ['name' => 'commit-method', 'value' => 'delete'],
                        ['name' => 'id', 'value' => 'dataset_button_delete'],
                        ['name' => 'text', 'value' => __('Delete')],
                        ['name' => 'rowtitle', 'value' => $dataSet->dataSet],
                        ['name' => 'form-callback', 'value' => 'deleteMultiSelectFormOpen']
                    ]
                ];
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
     *  @SWG\Parameter(
     *      name="isRemote",
     *      in="formData",
     *      description="Is this a remote DataSet?",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="method",
     *      in="formData",
     *      description="The Request Method GET or POST",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="uri",
     *      in="formData",
     *      description="The URI, without query parameters",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="postData",
     *      in="formData",
     *      description="query parameter encoded data to add to the request",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="authentication",
     *      in="formData",
     *      description="HTTP Authentication method None|Basic|Digest",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="username",
     *      in="formData",
     *      description="HTTP Authentication User Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="password",
     *      in="formData",
     *      description="HTTP Authentication Password",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="customHeaders",
     *      in="formData",
     *      description="Comma separated string of custom HTTP headers",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="refreshRate",
     *      in="formData",
     *      description="How often in seconds should this remote DataSet be refreshed",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="clearRate",
     *      in="formData",
     *      description="How often in seconds should this remote DataSet be truncated",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="runsAfter",
     *      in="formData",
     *      description="An optional dataSetId which should be run before this Remote DataSet",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dataRoot",
     *      in="formData",
     *      description="The root of the data in the Remote source which is used as the base for all remote columns",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="summarize",
     *      in="formData",
     *      description="Should the data be aggregated? None|Summarize|Count",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="summarizeField",
     *      in="formData",
     *      description="Which field should be used to summarize",
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
            $dataSet->postData = trim($this->getSanitizer()->getString('postData'));
            $dataSet->authentication = $this->getSanitizer()->getString('authentication');
            $dataSet->username = $this->getSanitizer()->getString('username');
            $dataSet->password = $this->getSanitizer()->getString('password');
            $dataSet->customHeaders = $this->getSanitizer()->getString('customHeaders');
            $dataSet->refreshRate = $this->getSanitizer()->getInt('refreshRate');
            $dataSet->clearRate = $this->getSanitizer()->getInt('clearRate');
            $dataSet->runsAfter = $this->getSanitizer()->getInt('runsAfter');
            $dataSet->dataRoot = $this->getSanitizer()->getString('dataRoot');
            $dataSet->summarize = $this->getSanitizer()->getString('summarize');
            $dataSet->summarizeField = $this->getSanitizer()->getString('summarizeField');
            $dataSet->sourceId = $this->getSanitizer()->getInt('sourceId');
            $dataSet->ignoreFirstRow = $this->getSanitizer()->getCheckbox('ignoreFirstRow');
        }

        // Also add one column
        $dataSetColumn = $this->dataSetColumnFactory->createEmpty();
        $dataSetColumn->columnOrder = 1;
        $dataSetColumn->heading = 'Col1';
        $dataSetColumn->dataSetColumnTypeId = 1;
        $dataSetColumn->dataTypeId = 1;

        // Add Column
        // only when we are not routing through the API
        if (!$this->isApi())
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
     *  @SWG\Parameter(
     *      name="isRemote",
     *      in="formData",
     *      description="Is this a remote DataSet?",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="method",
     *      in="formData",
     *      description="The Request Method GET or POST",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="uri",
     *      in="formData",
     *      description="The URI, without query parameters",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="postData",
     *      in="formData",
     *      description="query parameter encoded data to add to the request",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="authentication",
     *      in="formData",
     *      description="HTTP Authentication method None|Basic|Digest",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="username",
     *      in="formData",
     *      description="HTTP Authentication User Name",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="password",
     *      in="formData",
     *      description="HTTP Authentication Password",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="customHeaders",
     *      in="formData",
     *      description="Comma separated string of custom HTTP headers",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="refreshRate",
     *      in="formData",
     *      description="How often in seconds should this remote DataSet be refreshed",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="clearRate",
     *      in="formData",
     *      description="How often in seconds should this remote DataSet be truncated",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="runsAfter",
     *      in="formData",
     *      description="An optional dataSetId which should be run before this Remote DataSet",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dataRoot",
     *      in="formData",
     *      description="The root of the data in the Remote source which is used as the base for all remote columns",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="summarize",
     *      in="formData",
     *      description="Should the data be aggregated? None|Summarize|Count",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="summarizeField",
     *      in="formData",
     *      description="Which field should be used to summarize",
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
            $dataSet->postData = trim($this->getSanitizer()->getString('postData'));
            $dataSet->authentication = $this->getSanitizer()->getString('authentication');
            $dataSet->username = $this->getSanitizer()->getString('username');
            $dataSet->password = $this->getSanitizer()->getString('password');
            $dataSet->customHeaders = $this->getSanitizer()->getString('customHeaders');
            $dataSet->refreshRate = $this->getSanitizer()->getInt('refreshRate');
            $dataSet->clearRate = $this->getSanitizer()->getInt('clearRate');
            $dataSet->runsAfter = $this->getSanitizer()->getInt('runsAfter');
            $dataSet->dataRoot = $this->getSanitizer()->getString('dataRoot');
            $dataSet->summarize = $this->getSanitizer()->getString('summarize');
            $dataSet->summarizeField = $this->getSanitizer()->getString('summarizeField');
            $dataSet->sourceId = $this->getSanitizer()->getInt('sourceId');
            $dataSet->ignoreFirstRow = $this->getSanitizer()->getCheckbox('ignoreFirstRow');
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
     *  @SWG\Parameter(
     *      name="copyRows",
     *      in="formData",
     *      description="Flag whether to copy all the row data from the original dataSet",
     *      type="integer",
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
        $copyRows = $this->getSanitizer()->getCheckbox('copyRows', 0);

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
        $dataSet->userId = $this->getUser()->userId;

        $dataSet->save();

        if ($copyRows === 1)
            $dataSet->copyRows($dataSetId, $dataSet->dataSetId);

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

        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        Library::ensureLibraryExists($this->getConfig()->getSetting('LIBRARY_LOCATION'));

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
     * Import Json schema
     *
     *  @SWG\Definition(definition="importJsonSchema", type="object",
     *            @SWG\Property(property="uniqueKeys", type="array", description="A name of the unique column", @SWG\Items(type="string", @SWG\Property(property="colName", type="string"))),
     *            @SWG\Property(property="truncate", type="array", description="Flag True or False, whether to truncate existing data on import", @SWG\Items(type="string", @SWG\Property(property="truncate", type="string"))),
     *            @SWG\Property(property="rows", type="array", description="An array of objects with pairs: ColumnName:Value", @SWG\Items(type="object", @SWG\Property(property="colName", type="string"))),
     *  )
     */


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
     *      description="The row data, field name vs field data format. e.g. { uniqueKeys: [col1], rows: [{col1: value1}]}",
     *      required=true,
     *      @SWG\Schema(ref="#/definitions/importJsonSchema")
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
     * Sends out a Test Request and returns the Data as JSON to the Client so it can be shown in the Dialog
     * @throws XiboException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function testRemoteRequest()
    {
        $testDataSetId = $this->getSanitizer()->getInt('testDataSetId');

        if ($testDataSetId !== null) {
            $dataSet = $this->dataSetFactory->getById($testDataSetId);
        } else {
            $dataSet = $this->dataSetFactory->createEmpty();
        }
        $dataSet->dataSet = $this->getSanitizer()->getString('dataSet');
        $dataSet->method = $this->getSanitizer()->getString('method');
        $dataSet->uri = $this->getSanitizer()->getString('uri');
        $dataSet->postData = $this->getSanitizer()->getString('postData');
        $dataSet->authentication = $this->getSanitizer()->getString('authentication');
        $dataSet->username = $this->getSanitizer()->getString('username');
        $dataSet->password = $this->getSanitizer()->getString('password');
        $dataSet->dataRoot = $this->getSanitizer()->getString('dataRoot');
        $dataSet->sourceId = $this->getSanitizer()->getInt('sourceId');
        $dataSet->ignoreFirstRow = $this->getSanitizer()->getCheckbox('ignoreFirstRow');

        // Set this DataSet as active.
        $dataSet->setActive();

        // Getting the dependant DataSet to process the current DataSet on
        $dependant = null;
        if ($dataSet->runsAfter != null && $dataSet->runsAfter != $dataSet->dataSetId) {
            $dependant = $this->dataSetFactory->getById($dataSet->runsAfter);
        }

        // Call the remote service requested
        $data = $this->dataSetFactory->callRemoteService($dataSet, $dependant, false);

        if ($data->number > 0) {
            // Process the results, but don't record them
            if ($dataSet->sourceId === 1) {
                $this->dataSetFactory->processResults($dataSet, $data, false);
            } else {
                $this->dataSetFactory->processCsvEntries($dataSet, $data, false);
            }
        }

        $this->getLog()->debug('Results: ' . var_export($data, true));

        // Return
        $this->getState()->hydrate([
            'message' => __('Run Test-Request for %s', $dataSet->dataSet),
            'id' => $dataSet->dataSetId,
            'data' => $data
        ]);
    }
}
