<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Str;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Event\DataConnectorScriptRequestEvent;
use Xibo\Event\DataConnectorSourceRequestEvent;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Helper\DataSetUploadHandler;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Random;
use Xibo\Helper\SendFile;
use Xibo\Service\MediaService;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

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

    /** @var FolderFactory */
    private $folderFactory;

    /**
     * Set common dependencies.
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param \Xibo\Factory\UserFactory $userFactory
     * @param FolderFactory $folderFactory
     */
    public function __construct($dataSetFactory, $dataSetColumnFactory, $userFactory, $folderFactory)
    {
        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
        $this->userFactory = $userFactory;
        $this->folderFactory = $folderFactory;
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
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displayPage(Request $request, Response $response)
    {
        $this->getState()->template = 'dataset-page';

        return $this->render($request, $response);
    }

    /**
     * Search Data
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
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
     *      name="isRealTime",
     *      in="query",
     *      description="Filter by real time",
     *      type="integer",
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
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="query",
     *      description="Filter by Folder ID",
     *      type="integer",
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
    public function grid(Request $request, Response $response)
    {
        $user = $this->getUser();
        $sanitizedParams = $this->getSanitizer($request->getQueryParams());

        // Embed?
        $embed = ($sanitizedParams->getString('embed') != null) ? explode(',', $sanitizedParams->getString('embed')) : [];

        $filter = [
            'dataSetId' => $sanitizedParams->getInt('dataSetId'),
            'dataSet' => $sanitizedParams->getString('dataSet'),
            'useRegexForName' => $sanitizedParams->getCheckbox('useRegexForName'),
            'code' => $sanitizedParams->getString('code'),
            'isRealTime' => $sanitizedParams->getInt('isRealTime'),
            'userId' => $sanitizedParams->getInt('userId'),
            'folderId' => $sanitizedParams->getInt('folderId'),
            'logicalOperatorName' => $sanitizedParams->getString('logicalOperatorName'),
        ];

        $dataSets = $this->dataSetFactory->query($this->gridRenderSort($sanitizedParams), $this->gridRenderFilter($filter, $sanitizedParams));

        foreach ($dataSets as $dataSet) {
            /* @var \Xibo\Entity\DataSet $dataSet */
            if (in_array('columns', $embed)) {
                $dataSet->load();
            }
            if ($this->isApi($request)) {
                break;
            }

            $dataSet->includeProperty('buttons');
            $dataSet->buttons = [];

            // Load the dataSet to get the columns
            $dataSet->load();

            if ($this->getUser()->featureEnabled('dataset.data') && $user->checkEditable($dataSet)) {
                // View Data
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_viewdata',
                    'class' => 'XiboRedirectButton',
                    'url' => $this->urlFor($request, 'dataSet.view.data', ['id' => $dataSet->dataSetId]),
                    'text' => __('View Data')
                );
            }

            if ($this->getUser()->featureEnabled('dataset.modify')) {
                if ($user->checkEditable($dataSet)) {
                    // View Columns
                    $dataSet->buttons[] = array(
                        'id' => 'dataset_button_viewcolumns',
                        'url' => $this->urlFor($request, 'dataSet.column.view', ['id' => $dataSet->dataSetId]),
                        'class' => 'XiboRedirectButton',
                        'text' => __('View Columns')
                    );

                    // View RSS
                    $dataSet->buttons[] = array(
                        'id' => 'dataset_button_viewrss',
                        'url' => $this->urlFor($request, 'dataSet.rss.view', ['id' => $dataSet->dataSetId]),
                        'class' => 'XiboRedirectButton',
                        'text' => __('View RSS')
                    );

                    if ($this->getUser()->featureEnabled('dataset.realtime') && $dataSet->isRealTime === 1) {
                        $dataSet->buttons[] = [
                            'id' => 'dataset_button_view_data_connector',
                            'url' => $this->urlFor($request, 'dataSet.dataConnector.view', [
                                'id' => $dataSet->dataSetId
                            ]),
                            'class' => 'XiboRedirectButton',
                            'text' => __('View Data Connector'),
                        ];
                    }

                    // Divider
                    $dataSet->buttons[] = ['divider' => true];

                    // Import DataSet
                    if ($dataSet->isRemote !== 1) {
                        $dataSet->buttons[] = array(
                            'id' => 'dataset_button_import',
                            'class' => 'dataSetImportForm',
                            'text' => __('Import CSV')
                        );
                    }

                    // Copy
                    $dataSet->buttons[] = array(
                        'id' => 'dataset_button_copy',
                        'url' => $this->urlFor($request, 'dataSet.copy.form', ['id' => $dataSet->dataSetId]),
                        'text' => __('Copy')
                    );

                    // Divider
                    $dataSet->buttons[] = ['divider' => true];

                    // Edit DataSet
                    $dataSet->buttons[] = array(
                        'id' => 'dataset_button_edit',
                        'url' => $this->urlFor($request, 'dataSet.edit.form', ['id' => $dataSet->dataSetId]),
                        'text' => __('Edit')
                    );

                    if ($this->getUser()->featureEnabled('folder.view')) {
                        // Select Folder
                        $dataSet->buttons[] = [
                            'id' => 'dataSet_button_selectfolder',
                            'url' => $this->urlFor($request, 'dataSet.selectfolder.form', ['id' => $dataSet->dataSetId]),
                            'text' => __('Select Folder'),
                            'multi-select' => true,
                            'dataAttributes' => [
                                [
                                    'name' => 'commit-url',
                                    'value' => $this->urlFor($request, 'dataSet.selectfolder', ['id' => $dataSet->dataSetId])
                                ],
                                ['name' => 'commit-method', 'value' => 'put'],
                                ['name' => 'id', 'value' => 'dataSet_button_selectfolder'],
                                ['name' => 'text', 'value' => __('Move to Folder')],
                                ['name' => 'rowtitle', 'value' => $dataSet->dataSet],
                                ['name' => 'form-callback', 'value' => 'moveFolderMultiSelectFormOpen']
                            ]
                        ];
                    }

                    $dataSet->buttons[] = [
                        'id' => 'dataset_button_csv_export',
                        'linkType' => '_self', 'external' => true,
                        'url' => $this->urlFor($request, 'dataSet.export.csv', ['id' => $dataSet->dataSetId]),
                        'text' => __('Export (CSV)')
                    ];

                    if ($dataSet->isRemote === 1) {
                        $dataSet->buttons[] = [
                            'id' => 'dataset_button_clear_cache',
                            'url' => $this->urlFor($request, 'dataSet.clear.cache.form', ['id' => $dataSet->dataSetId]),
                            'text' => __('Clear Cache'),
                            'dataAttributes' => [
                                ['name' => 'auto-submit', 'value' => true],
                                ['name' => 'commit-url', 'value' => $this->urlFor($request, 'dataSet.clear.cache', ['id' => $dataSet->dataSetId])],
                                ['name' => 'commit-method', 'value' => 'POST']
                            ]
                        ];
                    }
                }

                if ($user->checkDeleteable($dataSet)
                    && $dataSet->isLookup == 0
                    && ($dataSet->isRealTime === 0 || $this->getUser()->featureEnabled('dataset.realtime'))
                ) {
                    $dataSet->buttons[] = ['divider' => true];
                    // Delete DataSet
                    $dataSet->buttons[] = [
                        'id' => 'dataset_button_delete',
                        'url' => $this->urlFor($request, 'dataSet.delete.form', ['id' => $dataSet->dataSetId]),
                        'text' => __('Delete'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            ['name' => 'commit-url', 'value' => $this->urlFor($request, 'dataSet.delete', ['id' => $dataSet->dataSetId])],
                            ['name' => 'commit-method', 'value' => 'delete'],
                            ['name' => 'id', 'value' => 'dataset_button_delete'],
                            ['name' => 'text', 'value' => __('Delete')],
                            ['name' => 'rowtitle', 'value' => $dataSet->dataSet],
                            ['name' => 'sort-group', 'value' => 1],
                            ['name' => 'form-callback', 'value' => 'deleteMultiSelectFormOpen']
                        ]
                    ];
                }

                // Divider
                $dataSet->buttons[] = ['divider' => true];

                if ($user->checkPermissionsModifyable($dataSet)) {
                    // Edit Permissions
                    $dataSet->buttons[] = [
                        'id' => 'dataset_button_permissions',
                        'url' => $this->urlFor($request,'user.permissions.form', ['entity' => 'DataSet', 'id' => $dataSet->dataSetId]),
                        'text' => __('Share'),
                        'multi-select' => true,
                        'dataAttributes' => [
                            ['name' => 'commit-url', 'value' => $this->urlFor($request,'user.permissions.multi', ['entity' => 'DataSet', 'id' => $dataSet->dataSetId])],
                            ['name' => 'commit-method', 'value' => 'post'],
                            ['name' => 'id', 'value' => 'dataset_button_permissions'],
                            ['name' => 'text', 'value' => __('Share')],
                            ['name' => 'rowtitle', 'value' => $dataSet->dataSet],
                            ['name' => 'sort-group', 'value' => 2],
                            ['name' => 'custom-handler', 'value' => 'XiboMultiSelectPermissionsFormOpen'],
                            ['name' => 'custom-handler-url', 'value' => $this->urlFor($request,'user.permissions.multi.form', ['entity' => 'DataSet'])],
                            ['name' => 'content-id-name', 'value' => 'dataSetId']
                        ]
                    ];
                }
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = $this->dataSetFactory->countLast();
        $this->getState()->setData($dataSets);

        return $this->render($request, $response);
    }

    /**
     * Add DataSet Form
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function addForm(Request $request, Response $response)
    {

        // Dispatch an event to initialize list of data sources for data connectors
        $event = new DataConnectorSourceRequestEvent();
        $this->getDispatcher()->dispatch($event, DataConnectorSourceRequestEvent::$NAME);

        // Retrieve data connector sources from the event
        $dataConnectorSources = $event->getDataConnectorSources();

        $this->getState()->template = 'dataset-form-add';
        $this->getState()->setData([
            'dataSets' => $this->dataSetFactory->query(),
            'dataConnectorSources' => $dataConnectorSources,
        ]);

        return $this->render($request, $response);
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
     *      name="isRealTime",
     *      in="formData",
     *      description="Is this a real time DataSet?",
     *      type="integer",
     *      required=true
     *   ),
     *   @SWG\Parameter(
     *       name="dataConnectorSource",
     *       in="formData",
     *       description="Source of the data connector",
     *       type="string",
     *       required=true
     *    ),
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
     *      name="userAgent",
     *      in="formData",
     *      description="Custom user Agent value",
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
     *      name="truncateOnEmpty",
     *      in="formData",
     *      description="Should the DataSet data be truncated even if no new data is pulled from the source?",
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
     *  @SWG\Parameter(
     *      name="sourceId",
     *      in="formData",
     *      description="For remote DataSet, what type data is it? 1 - json, 2 - csv",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ignoreFirstRow",
     *      in="formData",
     *      description="For remote DataSet with sourceId 2 (CSV), should we ignore first row?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="rowLimit",
     *      in="formData",
     *      description="For remote DataSet, maximum number of rows this DataSet can hold, if left empty the CMS Setting for DataSet row limit will be used.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="limitPolicy",
     *      in="formData",
     *      description="For remote DataSet, what should happen when the DataSet row limit is reached? stop, fifo or truncate",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="csvSeparator",
     *      in="formData",
     *      description="Separator that should be used when using Remote DataSets with CSV source, comma will be used by default.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dataConnectorScript",
     *      in="formData",
     *      description="If isRealTime then provide a script to connect to the data source",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this object should be assigned to",
     *      type="integer",
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
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $dataSet = $this->dataSetFactory->createEmpty();
        $dataSet->dataSet = $sanitizedParams->getString('dataSet');
        $dataSet->description = $sanitizedParams->getString('description');
        $dataSet->code = $sanitizedParams->getString('code');
        $dataSet->isRemote = $sanitizedParams->getCheckbox('isRemote');
        $dataSet->isRealTime = $sanitizedParams->getCheckbox('isRealTime');
        $dataSet->dataConnectorSource = $sanitizedParams->getString('dataConnectorSource');
        $dataSet->userId = $this->getUser()->userId;

        // Folders
        $folderId = $sanitizedParams->getInt('folderId');
        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        if (empty($folderId) || !$this->getUser()->featureEnabled('folder.view')) {
            $folderId = $this->getUser()->homeFolderId;
        }

        $folder = $this->folderFactory->getById($folderId, 0);
        $dataSet->folderId = $folder->getId();
        $dataSet->permissionsFolderId = $folder->getPermissionFolderIdOrThis();

        // Fields for remote
        if ($dataSet->isRemote === 1) {
            $dataSet->method = $sanitizedParams->getString('method');
            $dataSet->uri = $sanitizedParams->getString('uri');
            $dataSet->postData = trim($sanitizedParams->getString('postData'));
            $dataSet->authentication = $sanitizedParams->getString('authentication');
            $dataSet->username = $sanitizedParams->getString('username');
            $dataSet->password = $sanitizedParams->getString('password');
            $dataSet->customHeaders = $sanitizedParams->getString('customHeaders');
            $dataSet->userAgent = $sanitizedParams->getString('userAgent');
            $dataSet->refreshRate = $sanitizedParams->getInt('refreshRate');
            $dataSet->clearRate = $sanitizedParams->getInt('clearRate');
            $dataSet->truncateOnEmpty = $sanitizedParams->getCheckbox('truncateOnEmpty');
            $dataSet->runsAfter = $sanitizedParams->getInt('runsAfter');
            $dataSet->dataRoot = $sanitizedParams->getString('dataRoot');
            $dataSet->summarize = $sanitizedParams->getString('summarize');
            $dataSet->summarizeField = $sanitizedParams->getString('summarizeField');
            $dataSet->sourceId = $sanitizedParams->getInt('sourceId');
            $dataSet->ignoreFirstRow = $sanitizedParams->getCheckbox('ignoreFirstRow');
            $dataSet->rowLimit = $sanitizedParams->getInt('rowLimit');
            $dataSet->limitPolicy = $sanitizedParams->getString('limitPolicy') ?? 'stop';
            $dataSet->csvSeparator = ($dataSet->sourceId === 2) ? $sanitizedParams->getString('csvSeparator') ?? ',' : null;
        }

        // Also add one column
        $dataSetColumn = $this->dataSetColumnFactory->createEmpty();
        $dataSetColumn->columnOrder = 1;
        $dataSetColumn->heading = 'Col1';
        $dataSetColumn->dataSetColumnTypeId = 1;
        $dataSetColumn->dataTypeId = 1;

        // Add Column
        // only when we are not routing through the API
        if (!$this->isApi($request)) {
            $dataSet->assignColumn($dataSetColumn);
        }

        // Save
        $dataSet->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $dataSet->dataSet),
            'id' => $dataSet->dataSetId,
            'data' => $dataSet
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit DataSet Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function editForm(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        // Dispatch an event to initialize list of data sources for data connectors
        $event = new DataConnectorSourceRequestEvent();
        $this->getDispatcher()->dispatch($event, DataConnectorSourceRequestEvent::$NAME);

        // Retrieve data sources from the event
        $dataConnectorSources = $event->getDataConnectorSources();

        // retrieve the columns of the selected dataset
        $dataSet->getColumn();

        // Set the form
        $this->getState()->template = 'dataset-form-edit';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'dataSets' => $this->dataSetFactory->query(),
            'script' => $dataSet->getScript(),
            'dataConnectorSources' => $dataConnectorSources
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit DataSet
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
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
     *      name="isRealTime",
     *      in="formData",
     *      description="Is this a real time DataSet?",
     *      type="integer",
     *      required=true
     *   ),
     *   @SWG\Parameter(
     *       name="dataConnectorSource",
     *       in="formData",
     *       description="Source of the data connector",
     *       type="string",
     *       required=true
     *    ),
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
     *      name="userAgent",
     *      in="formData",
     *      description="Custom user Agent value",
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
     *      name="truncateOnEmpty",
     *      in="formData",
     *      description="Should the DataSet data be truncated even if no new data is pulled from the source?",
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
     *  @SWG\Parameter(
     *      name="sourceId",
     *      in="formData",
     *      description="For remote DataSet, what type data is it? 1 - json, 2 - csv",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="ignoreFirstRow",
     *      in="formData",
     *      description="For remote DataSet with sourceId 2 (CSV), should we ignore first row?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="rowLimit",
     *      in="formData",
     *      description="For remote DataSet, maximum number of rows this DataSet can hold, if left empty the CMS Setting for DataSet row limit will be used.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="limitPolicy",
     *      in="formData",
     *      description="For remote DataSet, what should happen when the DataSet row limit is reached? stop, fifo or truncate",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="csvSeparator",
     *      in="formData",
     *      description="Separator that should be used when using Remote DataSets with CSV source, comma will be used by default.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dataConnectorScript",
     *      in="formData",
     *      description="If isRealTime then provide a script to connect to the data source",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this object should be assigned to",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DataSet")
     *  )
     * )
     */
    public function edit(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $dataSet->dataSet = $sanitizedParams->getString('dataSet');
        $dataSet->description = $sanitizedParams->getString('description');
        $dataSet->code = $sanitizedParams->getString('code');
        $dataSet->isRemote = $sanitizedParams->getCheckbox('isRemote');
        $dataSet->isRealTime = $sanitizedParams->getCheckbox('isRealTime');
        $dataSet->dataConnectorSource = $sanitizedParams->getString('dataConnectorSource');
        $dataSet->folderId = $sanitizedParams->getInt('folderId', ['default' => $dataSet->folderId]);

        if ($dataSet->hasPropertyChanged('folderId')) {
            if ($dataSet->folderId === 1) {
                $this->checkRootFolderAllowSave();
            }
            $folder = $this->folderFactory->getById($dataSet->folderId);
            $dataSet->permissionsFolderId = ($folder->getPermissionFolderId() == null) ? $folder->id : $folder->getPermissionFolderId();
        }

        if ($dataSet->isRemote === 1) {
            $dataSet->method = $sanitizedParams->getString('method');
            $dataSet->uri = $sanitizedParams->getString('uri');
            $dataSet->postData = trim($sanitizedParams->getString('postData'));
            $dataSet->authentication = $sanitizedParams->getString('authentication');
            $dataSet->username = $sanitizedParams->getString('username');
            $dataSet->password = $sanitizedParams->getString('password');
            $dataSet->customHeaders = $sanitizedParams->getString('customHeaders');
            $dataSet->userAgent = $sanitizedParams->getString('userAgent');
            $dataSet->refreshRate = $sanitizedParams->getInt('refreshRate');
            $dataSet->clearRate = $sanitizedParams->getInt('clearRate');
            $dataSet->truncateOnEmpty = $sanitizedParams->getCheckbox('truncateOnEmpty');
            $dataSet->runsAfter = $sanitizedParams->getInt('runsAfter');
            $dataSet->dataRoot = $sanitizedParams->getString('dataRoot');
            $dataSet->summarize = $sanitizedParams->getString('summarize');
            $dataSet->summarizeField = $sanitizedParams->getString('summarizeField');
            $dataSet->sourceId = $sanitizedParams->getInt('sourceId');
            $dataSet->ignoreFirstRow = $sanitizedParams->getCheckbox('ignoreFirstRow');
            $dataSet->rowLimit = $sanitizedParams->getInt('rowLimit');
            $dataSet->limitPolicy = $sanitizedParams->getString('limitPolicy') ?? 'stop';
            $dataSet->csvSeparator = ($dataSet->sourceId === 2)
                ? $sanitizedParams->getString('csvSeparator') ?? ','
                : null;
        }

        $dataSet->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $dataSet->dataSet),
            'id' => $dataSet->dataSetId,
            'data' => $dataSet
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit DataSet Data Connector
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     *
     * @SWG\Put(
     *  path="/dataset/dataConnector/{dataSetId}",
     *  operationId="dataSetDataConnectorEdit",
     *  tags={"dataset"},
     *  summary="Edit DataSet Data Connector",
     *  description="Edit a DataSet Data Connector",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataConnectorScript",
     *      in="formData",
     *      description="If isRealTime then provide a script to connect to the data source",
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
    public function updateDataConnector(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        if ($dataSet->isRealTime === 1) {
            // Set the script.
            $dataSet->saveScript($sanitizedParams->getParam('dataConnectorScript'));
            $dataSet->notify();
        } else {
            throw new InvalidArgumentException(__('This DataSet does not have a data connector'), 'isRealTime');
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $dataSet->dataSet),
            'id' => $dataSet->dataSetId,
            'data' => $dataSet
        ]);

        return $this->render($request, $response);
    }

    /**
     * DataSet Delete
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function deleteForm(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($dataSet)) {
            throw new AccessDeniedException();
        }

        if ($dataSet->isLookup) {
            throw new InvalidArgumentException(__('Lookup Tables cannot be deleted'));
        }

        // Set the form
        $this->getState()->template = 'dataset-form-delete';
        $this->getState()->setData([
            'dataSet' => $dataSet,
        ]);

        return $this->render($request, $response);
    }

    /**
     * DataSet Delete
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ConfigurationException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
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
    public function delete(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkDeleteable($dataSet)) {
            throw new AccessDeniedException();
        }

        // Is there existing data?
        if ($sanitizedParams->getCheckbox('deleteData') == 0 && $dataSet->hasData())
            throw new InvalidArgumentException(__('There is data assigned to this data set, cannot delete.'), 'dataSetId');

        // Otherwise delete
        $dataSet->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $dataSet->dataSet)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Select Folder Form
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function selectFolderForm(Request $request, Response $response, $id)
    {
        // Get the data set
        $dataSet = $this->dataSetFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $data = [
            'dataSet' => $dataSet
        ];

        $this->getState()->template = 'dataset-form-selectfolder';
        $this->getState()->setData($data);

        return $this->render($request, $response);
    }

    /**
     * @SWG\Put(
     *  path="/dataset/{id}/selectfolder",
     *  operationId="dataSetSelectFolder",
     *  tags={"dataSet"},
     *  summary="DataSet Select folder",
     *  description="Select Folder for DataSet",
     *  @SWG\Parameter(
     *      name="menuId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="folderId",
     *      in="formData",
     *      description="Folder ID to which this object should be assigned to",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DataSet")
     *  )
     * )
     *
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function selectFolder(Request $request, Response $response, $id)
    {
        // Get the DataSet
        $dataSet = $this->dataSetFactory->getById($id);

        // Check Permissions
        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $folderId = $this->getSanitizer($request->getParams())->getInt('folderId');

        if ($folderId === 1) {
            $this->checkRootFolderAllowSave();
        }

        $dataSet->folderId = $folderId;
        $folder = $this->folderFactory->getById($dataSet->folderId);
        $dataSet->permissionsFolderId = ($folder->getPermissionFolderId() == null) ? $folder->id : $folder->getPermissionFolderId();

        // Save
        $dataSet->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('DataSet %s moved to Folder %s'), $dataSet->dataSet, $folder->text)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Copy DataSet Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function copyForm(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        // Set the form
        $this->getState()->template = 'dataset-form-copy';
        $this->getState()->setData([
            'dataSet' => $dataSet,
        ]);

        return $this->render($request, $response);
    }

    /**
     * Copy DataSet
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
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
     */
    public function copy(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $copyRows = $sanitizedParams->getCheckbox('copyRows');

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        // Load for the Copy
        $dataSet->load();
        $oldName = $dataSet->dataSet;

        // Clone and reset parameters
        $dataSet = clone $dataSet;
        $dataSet->dataSet = $sanitizedParams->getString('dataSet');
        $dataSet->description = $sanitizedParams->getString('description');
        $dataSet->code = $sanitizedParams->getString('code');
        $dataSet->userId = $this->getUser()->userId;

        $dataSet->save();

        if ($copyRows === 1) {
            $dataSet->copyRows($id, $dataSet->dataSetId);
        }

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Copied %s as %s'), $oldName, $dataSet->dataSet),
            'id' => $dataSet->dataSetId,
            'data' => $dataSet
        ]);

        return $this->render($request, $response);
    }

    /**
     * Import CSV
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\ConfigurationException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
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
     */
    public function import(Request $request, Response $response, $id)
    {
        $this->getLog()->debug('Import DataSet');

        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        MediaService::ensureLibraryExists($this->getConfig()->getSetting('LIBRARY_LOCATION'));

        $sanitizer = $this->getSanitizer($request->getParams());

        $options = array(
            'userId' => $this->getUser()->userId,
            'dataSetId' => $id,
            'controller' => $this,
            'accept_file_types' => '/\.csv/i',
            'sanitizer' => $sanitizer
        );

        try {
            // Hand off to the Upload Handler provided by jquery-file-upload
            new DataSetUploadHandler($libraryFolder . 'temp/', $this->getLog()->getLoggerInterface(), $options);
        } catch (\Exception $e) {
            // We must not issue an error, the file upload return should have the error object already
            $this->getState()->setCommitState(false);
        }

        $this->setNoOutput(true);

        // Explicitly set the Content-Type header to application/json
        $response = $response->withHeader('Content-Type', 'application/json');

        return $this->render($request, $response);
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
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
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
    public function importJson(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $body = json_encode($request->getParsedBody());

        if (empty($body)) {
            throw new InvalidArgumentException(__('Missing JSON Body'));
        }

        // Expect 2 parameters
        $data = json_decode($body, true);

        if (!isset($data['rows']) || !isset($data['uniqueKeys'])) {
            throw new InvalidArgumentException(__('Malformed JSON body, rows and uniqueKeys are required'));
        }

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
            $sanitizedRow = $this->getSanitizer($row);
            $rowToAdd = null;
            foreach ($row as $key => $value) {
                // Does the property in the provided row exist as a column?
                if (isset($columns[$key])) {
                    // Sanitize accordingly
                    if ($columns[$key] == 2) {
                        // Number
                        $value = $sanitizedRow->getDouble($key);
                    } elseif ($columns[$key] == 3) {
                        // Date
                        try {
                            $date = $sanitizedRow->getDate($key);
                            $value = $date->format(DateFormatHelper::getSystemFormat());
                        } catch (\Exception $e) {
                            $this->getLog()->error(sprintf('Incorrect date provided %s, expected date format Y-m-d H:i:s ', $value));
                            throw new InvalidArgumentException(sprintf(__('Incorrect date provided %s, expected date format Y-m-d H:i:s '), $value), 'date');
                        }
                    } elseif ($columns[$key] == 5) {
                        // Media Id
                        $value = $sanitizedRow->getInt($key);
                    } else {
                        // String
                        $value = $sanitizedRow->getString($key);
                    }

                    // Data is sanitized, add to the sanitized row
                    $rowToAdd[$key] = $value;
                }
            }

            if (count($rowToAdd) > 0) {
                $takenSomeAction = true;

                // Check unique keys to see if this is an update
                if (!empty($data['uniqueKeys']) && is_array($data['uniqueKeys'])) {
                    // Build a filter to select existing records
                    $filter = '';
                    $params = [];
                    $i = 0;
                    foreach ($data['uniqueKeys'] as $uniqueKey) {
                        if (isset($rowToAdd[$uniqueKey])) {
                            $i++;
                            $filter .= 'AND `' . $uniqueKey . '` = :uniqueKey_' . $i . ' ';
                            $params['uniqueKey_' . $i] = $rowToAdd[$uniqueKey];
                        }
                    }
                    $filter = trim($filter, 'AND');

                    // Use the unique keys to look up this row and see if it exists
                    $existingRows = $dataSet->getData(
                        ['filter' => $filter],
                        ['includeFormulaColumns' => false, 'requireTotal' => false],
                        $params,
                    );

                    if (count($existingRows) > 0) {
                        foreach ($existingRows as $existingRow) {
                            $dataSet->editRow($existingRow['id'], array_merge($existingRow, $rowToAdd));
                        }
                    } else {
                        $dataSet->addRow($rowToAdd);
                    }
                } else {
                    $dataSet->addRow($rowToAdd);
                }
            }
        }

        if (!$takenSomeAction)
            throw new NotFoundException(__('No data found in request body'));

        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Imported JSON into %s'), $dataSet->dataSet)
        ]);

        return $this->render($request, $response);
    }

    /**
     * Sends out a Test Request and returns the Data as JSON to the Client so it can be shown in the Dialog
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function testRemoteRequest(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $testDataSetId = $sanitizedParams->getInt('testDataSetId');

        if ($testDataSetId !== null) {
            $dataSet = $this->dataSetFactory->getById($testDataSetId);
        } else {
            $dataSet = $this->dataSetFactory->createEmpty();
        }

        $dataSet->dataSet = $sanitizedParams->getString('dataSet');
        $dataSet->method = $sanitizedParams->getString('method');
        $dataSet->uri = $sanitizedParams->getString('uri');
        $dataSet->postData = $sanitizedParams->getString('postData');
        $dataSet->authentication = $sanitizedParams->getString('authentication');
        $dataSet->username = $sanitizedParams->getString('username');
        $dataSet->password = $sanitizedParams->getString('password');
        $dataSet->dataRoot = $sanitizedParams->getString('dataRoot');
        $dataSet->sourceId = $sanitizedParams->getInt('sourceId');
        $dataSet->ignoreFirstRow = $sanitizedParams->getCheckbox('ignoreFirstRow');

        // Before running the test, check if the length is within the current URI character limit
        if (strlen($dataSet->uri) > 250) {
            throw new InvalidArgumentException(__('URI can not be longer than 250 characters'), 'uri');
        }

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

        return $this->render($request, $response);
    }

    /**
     * Export DataSet to csv
     *
     * @SWG\GET(
     *  path="/dataset/export/csv/{dataSetId}",
     *  operationId="dataSetExportCsv",
     *  tags={"dataset"},
     *  summary="Export to CSV",
     *  description="Export DataSet data to a csv file",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID to export.",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function exportToCsv(Request $request, Response $response, $id)
    {
        $this->setNoOutput();
        $i = 0;
        $dataSet = $this->dataSetFactory->getById($id);

        // Create a CSV file
        $tempFileName = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'temp/' . Random::generateString() .'.csv';

        $out = fopen($tempFileName, 'w');

        foreach ($dataSet->getData() as $row) {
            $columnHeaders = [];
            $rowData = [];

            foreach ($dataSet->columns as $column) {
                if ($i === 0) {
                    $columnHeaders[] = $column->heading;
                }

                $rowData[] = $row[$column->heading];
            }

            if (!empty($columnHeaders)) {
                fputcsv($out, $columnHeaders);
            }

            fputcsv($out, $rowData);
            $i++;
        }

        fclose($out);
        $this->getLog()->debug('Exported DataSet ' . $dataSet->dataSet . ' with ' . $i . ' rows of data');

        return $this->render($request, SendFile::decorateResponse(
            $response,
            $this->getConfig()->getSetting('SENDFILE_MODE'),
            $tempFileName,
            $dataSet->dataSet.'.csv'
        )->withHeader('Content-Type', 'text/csv;charset=utf-8'));
    }

    public function clearCacheForm(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        $this->getState()->template = 'dataset-form-clear-cache';
        $this->getState()->autoSubmit = $this->getAutoSubmit('dataSetClearCacheForm');
        $this->getState()->setData([
            'dataSet' => $dataSet
        ]);

        return $this->render($request, $response);
    }

    /**
     * Clear cache for remote dataSet, only available via web interface
     *
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function clearCache(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $dataSet->clearCache();

        // Return
        $this->getState()->hydrate([
            'message' => __('Cache cleared for %s', $dataSet->dataSet),
            'id' => $dataSet->dataSetId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Real-time data script editor
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response
     * @throws GeneralException
     */
    public function dataConnectorView(Request $request, Response $response, $id): Response
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $dataSet->load();

        if ($dataSet->dataConnectorSource == 'user_defined') {
            // retrieve the user defined javascript
            $script = $dataSet->getScript();
        } else {
            // Dispatch the event to get the script from the connector
            $event = new DataConnectorScriptRequestEvent($dataSet);
            $this->getDispatcher()->dispatch($event, DataConnectorScriptRequestEvent::$NAME);
            $script = $dataSet->getScript();
        }

        $this->getState()->template = 'dataset-data-connector-page';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'script' => $script,
            ]);
    
            return $this->render($request, $response);
    }

    /**
     * Real-time data script test
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response
     * @throws GeneralException
     */
    public function dataConnectorTest(Request $request, Response $response, $id): Response
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $dataSet->load();

        $this->getState()->template = 'dataset-data-connector-test-page';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'script' => $dataSet->getScript(),
        ]);

        return $this->render($request, $response);
    }

    /**
     * Real-time data script test
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return Response
     * @throws GeneralException
     */
    public function dataConnectorRequest(Request $request, Response $response, $id): Response
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $params = $this->getSanitizer($request->getParams());
        $url = $params->getString('url');
        $method = $params->getString('method', ['default' => 'GET']);
        $headers = $params->getArray('headers');
        $body = $params->getArray('body');

        // Verify that the requested URL appears in the script somewhere.
        $script = $dataSet->getScript();

        if (!Str::contains($script, $url)) {
            throw new InvalidArgumentException(__('URL not found in data connector script'), 'url');
        }

        // Make the request
        $options = [];
        if (is_array($headers)) {
            $options['headers'] = $headers;
        }

        if ($method === 'GET') {
            $options['query'] = $body;
        } else {
            $options['body'] = $body;
        }

        $this->getLog()->debug('dataConnectorRequest: making request with options ' . var_export($options, true));

        // Use guzzle to make the request
        try {
            $client = new Client();
            $remoteResponse = $client->request($method, $url, $options);

            // Format the response
            $response->getBody()->write($remoteResponse->getBody()->getContents());
            $response = $response->withAddedHeader('Content-Type', $remoteResponse->getHeader('Content-Type')[0]);
            $response = $response->withStatus($remoteResponse->getStatusCode());
        } catch (RequestException $exception) {
            $this->getLog()->error('dataConnectorRequest: error with request: ' . $exception->getMessage());

            if ($exception->hasResponse()) {
                $remoteResponse = $exception->getResponse();
                $response = $response->withStatus($remoteResponse->getStatusCode());
                $response->getBody()->write($remoteResponse->getBody()->getContents());
            } else {
                $response = $response->withStatus(500);
            }
        }

        return $response;
    }
}
