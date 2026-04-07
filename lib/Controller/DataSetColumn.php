<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Stash\Interfaces\PoolInterface;
use Xibo\Event\DataConnectorSourceRequestEvent;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetColumnTypeFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DataTypeFactory;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\ControllerNotImplemented;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

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
    public function __construct(
        $dataSetFactory,
        $dataSetColumnFactory,
        $dataSetColumnTypeFactory,
        $dataTypeFactory,
        $pool
    ) {
        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
        $this->dataSetColumnTypeFactory = $dataSetColumnTypeFactory;
        $this->dataTypeFactory = $dataTypeFactory;
        $this->pool = $pool;
    }

    #[OA\Get(
        path: '/dataset/{dataSetId}/column',
        operationId: 'dataSetColumnSearch',
        description: 'Search Columns for DataSet',
        summary: 'Search Columns',
        tags: ['dataset']
    )]
    #[OA\Parameter(
        name: 'dataSetId',
        description: 'The DataSet ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'dataSetColumnId',
        description: 'Filter by DataSet ColumnID',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'keyword',
        description: 'Filter by column heading, list content, or tooltip',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Specifies which field the results are sorted by. Used together with sortDir',
        in: 'query',
        required: false,
        schema: new OA\Schema(
            type: 'string',
            enum: [
                'heading',
                'dataType',
                'dataSetColumnType',
                'listContent',
                'tooltip',
                'columnOrder',
                'isRequired',
            ]
        )
    )]
    #[OA\Parameter(
        name: 'sortDir',
        description: 'Sort direction',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        headers: [
            new OA\Header(
                header: 'X-Total-Count',
                description: 'The total number of records',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/DataSetColumn')
        )
    )]
    /**
     * Column Search
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function grid(Request $request, Response $response, $id): Response|ResponseInterface
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $parsedRequestParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $dataSetColumnsSortQuery = $this->gridRenderSort(
            $parsedRequestParams,
            $this->isJson($request),
            'columnOrder'
        );

        $datasetsFilterQuery = $this->getDatasetsFilterQuery($id, $parsedRequestParams);

        $dataSetColumns = $this->dataSetColumnFactory->query(
            $dataSetColumnsSortQuery,
            $datasetsFilterQuery
        );

        $userPermissions = $this->getUser()->getPermission($dataSet);

        foreach ($dataSetColumns as $column) {
            $column->dataType = __($column->dataType);
            $column->dataSetColumnType = __($column->dataSetColumnType);
            $column->setUnmatchedProperty('userPermissions', $userPermissions);
        }

        $recordsTotal = $this->dataSetColumnFactory->countLast();

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $recordsTotal)
            ->withJson($dataSetColumns);
    }

    #[OA\Get(
        path: '/dataset/{id}/column/{colId}',
        operationId: 'datasetColumnSearchById',
        description: 'Get the DataSet column object specified by the provided datasetId and columnId',
        summary: 'DataSet Column Search by ID',
        tags: ['dataset']
    )]
    #[OA\Parameter(
        name: 'datasetId',
        description: 'Numeric ID of the DataSet to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'datasetColumnId',
        description: 'Numeric ID of the DataSet column to get',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/DataSetColumn')
    )]
    /**
     * @param Request $request
     * @param Response $response
     * @param int $id
     * @param int $colId
     * @return Response|ResponseInterface
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function searchById(Request $request, Response $response, int $id, int $colId): Response|ResponseInterface
    {
        $dataset = $this->dataSetFactory->getById($id, false);
        $datasetColumn = $this->dataSetColumnFactory->getById($colId);

        $datasetColumn->setUnmatchedProperty('userPermissions', $this->getUser()->getPermission($dataset));

        return $response
            ->withStatus(200)
            ->withJson($datasetColumn);
    }

    #[OA\Post(
        path: '/dataset/{dataSetId}/column',
        operationId: 'dataSetColumnAdd',
        description: 'Add a Column to a DataSet',
        summary: 'Add Column',
        tags: ['dataset']
    )]
    #[OA\Parameter(
        name: 'dataSetId',
        description: 'The DataSet ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'heading', description: 'The heading for the Column', type: 'string'),
                    new OA\Property(
                        property: 'listContent',
                        description: 'A comma separated list of content for drop downs',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'columnOrder',
                        description: 'The display order for this column',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'dataTypeId',
                        description: 'The data type ID for this column',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'dataSetColumnTypeId',
                        description: 'The column type for this column',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'formula',
                        description: 'MySQL SELECT syntax formula for this Column if the column type is formula', // phpcs:ignore
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'remoteField',
                        description: 'JSON-String to select Data from the Remote DataSet',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'showFilter',
                        description: 'Flag indicating whether this column should present a filter on DataEntry', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'showSort',
                        description: 'Flag indicating whether this column should allow sorting on DataEntry', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'tooltip',
                        description: 'Help text that should be displayed when entering data for this Column.', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'isRequired',
                        description: 'Flag indicating whether value must be provided for this Column.', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'dateFormat',
                        description: 'PHP date format for the dates in the source of the remote DataSet', // phpcs:ignore
                        type: 'string'
                    )
                ],
                required: ['heading', 'columnOrder', 'dataTypeId', 'dataSetColumnTypeId', 'showFilter', 'showSort']
            )
        ),
        required: true
    )]
    #[OA\Response(
        response: 201,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/DataSetColumn'),
        headers: [
            new OA\Header(
                header: 'Location',
                description: 'Location of the new record',
                schema: new OA\Schema(type: 'string')
            )
        ]
    )]
    /**
     * Add
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function add(Request $request, Response $response, $id): Response|ResponseInterface
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
            $this->getLog()->debug('New remote column detected, clear cache for remote dataSet ID '
                . $dataSet->dataSetId);
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

    #[OA\Put(
        path: '/dataset/{dataSetId}/column/{dataSetColumnId}',
        operationId: 'dataSetColumnEdit',
        description: 'Edit a Column to a DataSet',
        summary: 'Edit Column',
        tags: ['dataset']
    )]
    #[OA\Parameter(
        name: 'dataSetId',
        description: 'The DataSet ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'dataSetColumnId',
        description: 'The Column ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'heading', description: 'The heading for the Column', type: 'string'),
                    new OA\Property(
                        property: 'listContent',
                        description: 'A comma separated list of content for drop downs',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'columnOrder',
                        description: 'The display order for this column',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'dataTypeId',
                        description: 'The data type ID for this column',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'dataSetColumnTypeId',
                        description: 'The column type for this column',
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'formula',
                        description: 'MySQL SELECT syntax formula for this Column if the column type is formula', // phpcs:ignore
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'remoteField',
                        description: 'JSON-String to select Data from the Remote DataSet',
                        type: 'string'
                    ),
                    new OA\Property(
                        property: 'showFilter',
                        description: 'Flag indicating whether this column should present a filter on DataEntry', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'showSort',
                        description: 'Flag indicating whether this column should allow sorting on DataEntry', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'tooltip',
                        description: 'Help text that should be displayed when entering data for this Column.', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'isRequired',
                        description: 'Flag indicating whether value must be provided for this Column.', // phpcs:ignore
                        type: 'integer'
                    ),
                    new OA\Property(
                        property: 'dateFormat',
                        description: 'PHP date format for the dates in the source of the remote DataSet', // phpcs:ignore
                        type: 'string'
                    )
                ],
                required: ['heading', 'columnOrder', 'dataTypeId', 'dataSetColumnTypeId', 'showFilter', 'showSort']
            )
        ),
        required: true
    )]
    #[OA\Response(
        response: 201,
        description: 'successful operation',
        content: new OA\JsonContent(ref: '#/components/schemas/DataSetColumn'),
        headers: [
            new OA\Header(
                header: 'Location',
                description: 'Location of the new record',
                schema: new OA\Schema(type: 'string')
            )
        ]
    )]
    /**
     * Edit
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $colId
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function edit(Request $request, Response $response, $id, $colId): Response|ResponseInterface
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
            $this->getLog()->debug('Edited remoteField detected, clear cache for remote dataSet ID '
                . $dataSet->dataSetId);
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

    #[OA\Delete(
        path: '/dataset/{dataSetId}/column/{dataSetColumnId}',
        operationId: 'dataSetColumnDelete',
        description: 'Delete DataSet Column',
        summary: 'Delete Column',
        tags: ['dataset']
    )]
    #[OA\Parameter(
        name: 'dataSetId',
        description: 'The DataSet ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'dataSetColumnId',
        description: 'The Column ID',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * Delete
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $colId
     * @return ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws ControllerNotImplemented
     * @throws GeneralException
     * @throws NotFoundException
     */
    public function delete(Request $request, Response $response, $id, $colId): Response|ResponseInterface
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

    /**
     * List of data types for dataset columns
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function getDataTypes(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if ($sanitizedParams->getInt('datasetId') <= 0) {
            throw new InvalidArgumentException(__('Missing dataSetId'), 'dataSetId');
        }

        $dataSet = $this->dataSetFactory->getById($sanitizedParams->getInt('datasetId'));

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $dataTypes = $this->dataTypeFactory->query();

        return $response
            ->withStatus(200)
            ->withJson($dataTypes);
    }

    /**
     * List of dataset column types
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws AccessDeniedException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function getDataSetColumnTypes(Request $request, Response $response): Response
    {
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if ($sanitizedParams->getInt('datasetId') <= 0) {
            throw new InvalidArgumentException(__('Missing dataSetId'), 'dataSetId');
        }

        $dataSet = $this->dataSetFactory->getById($sanitizedParams->getInt('datasetId'));

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }
        $dataSetColumnTypes = $this->dataSetColumnTypeFactory->query();

        return $response
            ->withStatus(200)
            ->withJson($dataSetColumnTypes);
    }

    /**
     * Get the dataset column filters
     * @param $id
     * @param $parsedRequestParams
     * @return array
     */
    private function getDatasetsFilterQuery($id, $parsedRequestParams): array
    {
        return $this->gridRenderFilter([
            'dataSetId' => $id,
            'dataSetColumnId' => $parsedRequestParams->getInt('dataSetColumnId'),
            'keyword' => $parsedRequestParams->getString('keyword')
        ], $parsedRequestParams);
    }
}
