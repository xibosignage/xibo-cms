<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Stash\Interfaces\PoolInterface;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetColumnTypeFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DataTypeFactory;
use Xibo\Support\Exception\AccessDeniedException;

/**
 * Class DataSetColumn
 * @package Xibo\Controller
 */
class DataSetColumn extends Base
{
    /** @var  DataSetFactory */
    private $dataSetFactory;

    /** @var  DataSetColumnFactory */
    private $dataSetColumnFactory;

    /** @var  DataSetColumnTypeFactory */
    private $dataSetColumnTypeFactory;

    /** @var  DataTypeFactory */
    private $dataTypeFactory;

    /** @var PoolInterface */
    private $pool;

    /**
     * Set common dependencies.
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param DataSetColumnTypeFactory $dataSetColumnTypeFactory
     * @param DataTypeFactory $dataTypeFactory
     * @param PoolInterface $pool
     */
    public function __construct($dataSetFactory, $dataSetColumnFactory, $dataSetColumnTypeFactory, $dataTypeFactory, $pool)
    {
        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
        $this->dataSetColumnTypeFactory = $dataSetColumnTypeFactory;
        $this->dataTypeFactory = $dataTypeFactory;
        $this->pool = $pool;
    }

    /**
     * Column Page
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function displayPage(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'dataset-column-page';
        $this->getState()->setData([
            'dataSet' => $dataSet
        ]);

        return $this->render($request, $response);
    }

    /**
     * Column Search
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     * @SWG\Get(
     *  path="/dataset/{dataSetId}/column",
     *  operationId="dataSetColumnSearch",
     *  tags={"dataset"},
     *  summary="Search Columns",
     *  description="Search Columns for DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSetColumnId",
     *      in="query",
     *      description="Filter by DataSet ColumnID",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/DataSetColumn")
     *      )
     *  )
     * )
     */
    public function grid(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $parsedRequestParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $dataSetColumns = $this->dataSetColumnFactory->query(
            $this->gridRenderSort($parsedRequestParams),
            $this->gridRenderFilter(
                ['dataSetId' => $id, 'dataSetColumnId' => $parsedRequestParams->getInt('dataSetColumnId')],
                $parsedRequestParams
            )
        );

        foreach ($dataSetColumns as $column) {
            /* @var \Xibo\Entity\DataSetColumn $column */

            $column->dataType = __($column->dataType);
            $column->dataSetColumnType = __($column->dataSetColumnType);

            if ($this->isApi($request))
                break;

            $column->includeProperty('buttons');

            if ($this->getUser()->featureEnabled('dataset.modify')) {
                // Edit
                $column->buttons[] = array(
                    'id' => 'dataset_button_edit',
                    'url' => $this->urlFor($request,'dataSet.column.edit.form', ['id' => $id, 'colId' => $column->dataSetColumnId]),
                    'text' => __('Edit')
                );

                if ($this->getUser()->checkDeleteable($dataSet)) {
                    // Delete
                    $column->buttons[] = array(
                        'id' => 'dataset_button_delete',
                        'url' => $this->urlFor($request,'dataSet.column.delete.form', ['id' => $id, 'colId' => $column->dataSetColumnId]),
                        'text' => __('Delete')
                    );
                }
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($dataSetColumns);
        $this->getState()->recordsTotal = $this->dataSetColumnFactory->countLast();

        return $this->render($request, $response);
    }

    /**
     * Add form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function addForm(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'dataset-column-form-add';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'dataTypes' => $this->dataTypeFactory->query(),
            'dataSetColumnTypes' => $this->dataSetColumnTypeFactory->query(),
        ]);

        return $this->render($request, $response);
    }

    /**
     * Add
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     * @SWG\Post(
     *  path="/dataset/{dataSetId}/column",
     *  operationId="dataSetColumnAdd",
     *  tags={"dataset"},
     *  summary="Add Column",
     *  description="Add a Column to a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="heading",
     *      in="formData",
     *      description="The heading for the Column",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="listContent",
     *      in="formData",
     *      description="A comma separated list of content for drop downs",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="columnOrder",
     *      in="formData",
     *      description="The display order for this column",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataTypeId",
     *      in="formData",
     *      description="The data type ID for this column",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSetColumnTypeId",
     *      in="formData",
     *      description="The column type for this column",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="formula",
     *      in="formData",
     *      description="MySQL SELECT syntax formula for this Column if the column type is formula",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="remoteField",
     *      in="formData",
     *      description="JSON-String to select Data from the Remote DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="showFilter",
     *      in="formData",
     *      description="Flag indicating whether this column should present a filter on DataEntry",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="showSort",
     *      in="formData",
     *      description="Flag indicating whether this column should allow sorting on DataEntry",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="tooltip",
     *      in="formData",
     *      description="Help text that should be displayed when entering data for this Column.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isRequired",
     *      in="formData",
     *      description="Flag indicating whether value must be provided for this Column.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dateFormat",
     *      in="formData",
     *      description="PHP date format for the dates in the source of the remote DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DataSetColumn"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function add(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        // Create a Column
        $column = $this->dataSetColumnFactory->createEmpty();
        $column->heading = $sanitizedParams->getString('heading');
        $column->listContent = $sanitizedParams->getString('listContent');
        $column->columnOrder = $sanitizedParams->getInt('columnOrder');
        $column->dataTypeId = $sanitizedParams->getInt('dataTypeId');
        $column->dataSetColumnTypeId = $sanitizedParams->getInt('dataSetColumnTypeId');
        $column->formula = $request->getParam('formula', null);
        $column->remoteField = $request->getParam('remoteField', null);
        $column->showFilter = $sanitizedParams->getCheckbox('showFilter');
        $column->showSort = $sanitizedParams->getCheckbox('showSort');
        $column->tooltip = $sanitizedParams->getString('tooltip');
        $column->isRequired = $sanitizedParams->getCheckbox('isRequired', ['default' => 0]);
        $column->dateFormat = $sanitizedParams->getString('dateFormat', ['default' => null]);

        if ($column->dataSetColumnTypeId == 3) {
            $this->pool->deleteItem('/dataset/cache/' . $dataSet->dataSetId);
            $this->getLog()->debug('New remote column detected, clear cache for remote dataSet ID ' . $dataSet->dataSetId);
        }

        // Assign the column to set the column order if necessary
        $dataSet->assignColumn($column);

        // client side formula disable sort
        if (substr($column->formula, 0, 1) === '$') {
            $column->showSort = 0;
        }

        // Save the column
        $column->save();

        // Notify the change
        $dataSet->notify();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => sprintf(__('Added %s'), $column->heading),
            'id' => $column->dataSetColumnId,
            'data' => $column
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $colId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function editForm(Request $request, Response $response, $id, $colId)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'dataset-column-form-edit';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'dataSetColumn' => $this->dataSetColumnFactory->getById($colId),
            'dataTypes' => $this->dataTypeFactory->query(),
            'dataSetColumnTypes' => $this->dataSetColumnTypeFactory->query(),
        ]);

        return $this->render($request, $response);
    }

    /**
     * Edit
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $colId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     * @SWG\Put(
     *  path="/dataset/{dataSetId}/column/{dataSetColumnId}",
     *  operationId="dataSetColumnEdit",
     *  tags={"dataset"},
     *  summary="Edit Column",
     *  description="Edit a Column to a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSetColumnId",
     *      in="path",
     *      description="The Column ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="heading",
     *      in="formData",
     *      description="The heading for the Column",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="listContent",
     *      in="formData",
     *      description="A comma separated list of content for drop downs",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="columnOrder",
     *      in="formData",
     *      description="The display order for this column",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataTypeId",
     *      in="formData",
     *      description="The data type ID for this column",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSetColumnTypeId",
     *      in="formData",
     *      description="The column type for this column",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="formula",
     *      in="formData",
     *      description="MySQL SELECT syntax formula for this Column if the column type is formula",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="remoteField",
     *      in="formData",
     *      description="JSON-String to select Data from the Remote DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="showFilter",
     *      in="formData",
     *      description="Flag indicating whether this column should present a filter on DataEntry",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="showSort",
     *      in="formData",
     *      description="Flag indicating whether this column should allow sorting on DataEntry",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="tooltip",
     *      in="formData",
     *      description="Help text that should be displayed when entering data for this Column.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="isRequired",
     *      in="formData",
     *      description="Flag indicating whether value must be provided for this Column.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="dateFormat",
     *      in="formData",
     *      description="PHP date format for the dates in the source of the remote DataSet",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/DataSetColumn"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function edit(Request $request, Response $response, $id, $colId)
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        // Column
        $column = $this->dataSetColumnFactory->getById($colId);
        $column->heading = $sanitizedParams->getString('heading');
        $column->listContent = $sanitizedParams->getString('listContent');
        $column->columnOrder = $sanitizedParams->getInt('columnOrder');
        $column->dataTypeId = $sanitizedParams->getInt('dataTypeId');
        $column->dataSetColumnTypeId = $sanitizedParams->getInt('dataSetColumnTypeId');
        $column->formula = $request->getParam('formula', null);
        $column->remoteField = $request->getParam('remoteField', null);
        $column->showFilter = $sanitizedParams->getCheckbox('showFilter');
        $column->showSort = $sanitizedParams->getCheckbox('showSort');
        $column->tooltip = $sanitizedParams->getString('tooltip');
        $column->isRequired = $sanitizedParams->getCheckbox('isRequired');
        $column->dateFormat = $sanitizedParams->getString('dateFormat', ['default' => null]);

        // client side formula disable sort
        if (substr($column->formula, 0, 1) === '$') {
            $column->showSort = 0;
        }
        
        $column->save();

        if ($column->dataSetColumnTypeId == 3 && $column->hasPropertyChanged('remoteField')) {
            $this->pool->deleteItem('/dataset/cache/' . $dataSet->dataSetId);
            $this->getLog()->debug('Edited remoteField detected, clear cache for remote dataSet ID ' . $dataSet->dataSetId);
        }

        $dataSet->notify();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $column->heading),
            'id' => $column->dataSetColumnId,
            'data' => $column
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $colId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function deleteForm(Request $request, Response $response, $id, $colId)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($dataSet)) {
            throw new AccessDeniedException();
        }

        $this->getState()->template = 'dataset-column-form-delete';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'dataSetColumn' => $this->dataSetColumnFactory->getById($colId),
        ]);

        return $this->render($request, $response);
    }

    /**
     * Delete
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $colId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     * @SWG\Delete(
     *  path="/dataset/{dataSetId}/column/{dataSetColumnId}",
     *  operationId="dataSetColumnDelete",
     *  tags={"dataset"},
     *  summary="Delete Column",
     *  description="Delete DataSet Column",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSetColumnId",
     *      in="path",
     *      description="The Column ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function delete(Request $request, Response $response, $id, $colId)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkDeleteable($dataSet)) {
            throw new AccessDeniedException();
        }

        // Get the column
        $column = $this->dataSetColumnFactory->getById($colId);
        $column->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $column->heading)
        ]);

        return $this->render($request, $response);
    }
}