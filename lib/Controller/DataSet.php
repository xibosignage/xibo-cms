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

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Slim\Views\Twig;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Helper\DataSetUploadHandler;
use Xibo\Helper\SanitizerService;
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
     * @param SanitizerService $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param Twig $view
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $dataSetFactory, $dataSetColumnFactory, Twig $view)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config, $view);

        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
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
    public function grid(Request $request, Response $response)
    {
        $user = $this->getUser($request);
        $sanitizedParams = $this->getSanitizer($request->getQueryParams());
        
        // Embed?
        $embed = ($sanitizedParams->getString('embed') != null) ? explode(',', $sanitizedParams->getString('embed')) : [];
        
        $filter = [
            'dataSetId' => $sanitizedParams->getInt('dataSetId'),
            'dataSet' => $sanitizedParams->getString('dataSet'),
            'code' => $sanitizedParams->getString('code'),
        ];

        $dataSets = $this->dataSetFactory->query($this->gridRenderSort($request), $this->gridRenderFilter($filter, $request), $request);

        foreach ($dataSets as $dataSet) {
            /* @var \Xibo\Entity\DataSet $dataSet */
            if (in_array('columns', $embed)) {
                $dataSet->load();
            }
            if ($this->isApi($request))
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
                    'url' => $this->urlFor($request,'dataSet.view.data', ['id' => $dataSet->dataSetId]),
                    'text' => __('View Data')
                );

                // View Columns
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_viewcolumns',
                    'url' => $this->urlFor($request,'dataSet.column.view', ['id' => $dataSet->dataSetId]),
                    'class' => 'XiboRedirectButton',
                    'text' => __('View Columns')
                );

                // View RSS
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_viewrss',
                    'url' => $this->urlFor($request,'dataSet.rss.view', ['id' => $dataSet->dataSetId]),
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
                        'url' => $this->urlFor($request,'dataSet.import.form', ['id' => $dataSet->dataSetId]),
                        'text' => __('Import CSV')
                    );
                }

                // Copy
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_copy',
                    'url' => $this->urlFor($request,'dataSet.copy.form', ['id' => $dataSet->dataSetId]),
                    'text' => __('Copy')
                );

                // Divider
                $dataSet->buttons[] = ['divider' => true];

                // Edit DataSet
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_edit',
                    'url' => $this->urlFor($request,'dataSet.edit.form', ['id' => $dataSet->dataSetId]),
                    'text' => __('Edit')
                );
            }

            if ($user->checkDeleteable($dataSet) && $dataSet->isLookup == 0) {
                // Delete DataSet
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_delete',
                    'url' => $this->urlFor($request,'dataSet.delete.form', ['id' => $dataSet->dataSetId]),
                    'text' => __('Delete')
                );
            }

            // Divider
            $dataSet->buttons[] = ['divider' => true];

            if ($user->checkPermissionsModifyable($dataSet)) {
                // Edit Permissions
                $dataSet->buttons[] = array(
                    'id' => 'dataset_button_permissions',
                    'url' => $this->urlFor($request,'user.permissions.form', ['entity' => 'DataSet', 'id' => $dataSet->dataSetId]),
                    'text' => __('Permissions')
                );
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
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function addForm(Request $request, Response $response)
    {
        $this->getState()->template = 'dataset-form-add';
        $this->getState()->setData([
            'dataSets' => $this->dataSetFactory->query(null, [], $request),
            'help' => $this->getHelp()->link('DataSet', 'Add')
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
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\DuplicateEntityException
     */
    public function add(Request $request, Response $response)
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());
        
        $dataSet = $this->dataSetFactory->createEmpty();
        $dataSet->dataSet = $sanitizedParams->getString('dataSet');
        $dataSet->description = $sanitizedParams->getString('description');
        $dataSet->code = $sanitizedParams->getString('code');
        $dataSet->isRemote = $sanitizedParams->getCheckbox('isRemote');
        $dataSet->userId = $this->getUser($request)->userId;

        // Fields for remote
        if ($dataSet->isRemote === 1) {
            $dataSet->method = $sanitizedParams->getString('method');
            $dataSet->uri = $sanitizedParams->getString('uri');
            $dataSet->postData = trim($sanitizedParams->getString('postData'));
            $dataSet->authentication = $sanitizedParams->getString('authentication');
            $dataSet->username = $sanitizedParams->getString('username');
            $dataSet->password = $sanitizedParams->getString('password');
            $dataSet->customHeaders = $sanitizedParams->getString('customHeaders');
            $dataSet->refreshRate = $sanitizedParams->getInt('refreshRate');
            $dataSet->clearRate = $sanitizedParams->getInt('clearRate');
            $dataSet->runsAfter = $sanitizedParams->getInt('runsAfter');
            $dataSet->dataRoot = $sanitizedParams->getString('dataRoot');
            $dataSet->summarize = $sanitizedParams->getString('summarize');
            $dataSet->summarizeField = $sanitizedParams->getString('summarizeField');
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
     * @throws NotFoundException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function editForm(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser($request)->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        // Set the form
        $this->getState()->template = 'dataset-form-edit';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'dataSets' => $this->dataSetFactory->query(null, [], $request),
            'help' => $this->getHelp()->link('DataSet', 'Edit')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit DataSet
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\DuplicateEntityException
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
     */
    public function edit(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser($request)->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $dataSet->dataSet = $sanitizedParams->getString('dataSet');
        $dataSet->description = $sanitizedParams->getString('description');
        $dataSet->code = $sanitizedParams->getString('code');
        $dataSet->isRemote = $sanitizedParams->getCheckbox('isRemote');

        if ($dataSet->isRemote === 1) {
            $dataSet->method = $sanitizedParams->getString('method');
            $dataSet->uri = $sanitizedParams->getString('uri');
            $dataSet->postData = trim($sanitizedParams->getString('postData'));
            $dataSet->authentication = $sanitizedParams->getString('authentication');
            $dataSet->username = $sanitizedParams->getString('username');
            $dataSet->password = $sanitizedParams->getString('password');
            $dataSet->customHeaders = $sanitizedParams->getString('customHeaders');
            $dataSet->refreshRate = $sanitizedParams->getInt('refreshRate');
            $dataSet->clearRate = $sanitizedParams->getInt('clearRate');
            $dataSet->runsAfter = $sanitizedParams->getInt('runsAfter');
            $dataSet->dataRoot = $sanitizedParams->getString('dataRoot');
            $dataSet->summarize = $sanitizedParams->getString('summarize');
            $dataSet->summarizeField = $sanitizedParams->getString('summarizeField');
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
     * DataSet Delete
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws NotFoundException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function deleteForm(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser($request)->checkDeleteable($dataSet)) {
            throw new AccessDeniedException();
        }

        if ($dataSet->isLookup) {
            throw new \InvalidArgumentException(__('Lookup Tables cannot be deleted'));
        }

        // Set the form
        $this->getState()->template = 'dataset-form-delete';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'help' => $this->getHelp()->link('DataSet', 'Delete')
        ]);

        return $this->render($request, $response);
    }

    /**
     * DataSet Delete
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
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
     */
    public function delete(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser($request)->checkDeleteable($dataSet)) {
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
     * Copy DataSet Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws NotFoundException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     */
    public function copyForm(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser($request)->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        // Set the form
        $this->getState()->template = 'dataset-form-copy';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'help' => $this->getHelp()->link('DataSet', 'Edit')
        ]);

        return $this->render($request, $response);
    }

    /**
     * Copy DataSet
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
     * @throws \Xibo\Exception\DuplicateEntityException
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
     */
    public function copy(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        $copyRows = $sanitizedParams->getCheckbox('copyRows');

        if (!$this->getUser($request)->checkEditable($dataSet)) {
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
    public function import(Request $request, Response $response, $id)
    {
        $this->getLog()->debug('Import DataSet');

        $libraryFolder = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Make sure the library exists
        Library::ensureLibraryExists($this->getConfig()->getSetting('LIBRARY_LOCATION'));

        $options = array(
            'userId' => $this->getUser()->userId,
            'dataSetId' => $id,
            'controller' => $this,
            'upload_dir' => $libraryFolder . 'temp/',
            'download_via_php' => true,
            'script_url' => $this->urlFor($request,'dataSet.import'),
            'upload_url' => $this->urlFor($request,'dataSet.import'),
            'image_versions' => array(),
            'accept_file_types' => '/\.csv/i'
        );

        try {
            // Hand off to the Upload Handler provided by jquery-file-upload
            new DataSetUploadHandler($options);

        } catch (\Exception $e) {
            // We must not issue an error, the file upload return should have the error object already TODO
            $this->getApp()->commit = false;
        }

        $this->setNoOutput(true);

        $this->render($request, $response);
    }

    /**
     * Import JSON
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws NotFoundException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
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
    public function importJson(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser($request)->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $body = $request->getParsedBody();

        if (empty($body)) {
            throw new \InvalidArgumentException(__('Missing JSON Body'));
        }

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
                        $value = $sanitizedParams->getDouble($value);
                    }
                    else if ($columns[$key] == 3) {
                        // Date
                        $value = $this->getDate()->getLocalDate($this->getDate()->parse($value));
                    }
                    else if ($columns[$key] == 5) {
                        // Media Id
                        $value = $sanitizedParams->getInt($value);
                    }
                    else {
                        // String
                        $value = $sanitizedParams->getString($value);
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

        return $this->render($request, $response);
    }

    /**
     * Sends out a Test Request and returns the Data as JSON to the Client so it can be shown in the Dialog
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws XiboException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Xibo\Exception\ConfigurationException
     * @throws \Xibo\Exception\ControllerNotImplemented
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

        // Set this DataSet as active.
        $dataSet->setActive();

        // Call the remote service requested
        $data = $this->dataSetFactory->callRemoteService($dataSet, null, false);

        if ($data->number > 0) {
            // Process the results, but don't record them
            $this->dataSetFactory->processResults($dataSet, $data, false);
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
}
