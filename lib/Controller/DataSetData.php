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
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Support\Exception\AccessDeniedException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class DataSetData
 * @package Xibo\Controller
 */
class DataSetData extends Base
{
    /** @var  DataSetFactory */
    private $dataSetFactory;

    /** @var  MediaFactory */
    private $mediaFactory;

    /**
     * Set common dependencies.
     * @param DataSetFactory $dataSetFactory
     * @param MediaFactory $mediaFactory
     */
    public function __construct($dataSetFactory, $mediaFactory)
    {
        $this->dataSetFactory = $dataSetFactory;
        $this->mediaFactory = $mediaFactory;
    }

    #[OA\Get(
        path: '/dataset/data/{dataSetId}',
        operationId: 'dataSetData',
        description: 'Get Data for DataSet',
        summary: 'DataSet Data',
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
        name: 'keyword',
        description: 'Filter by dataset data column',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Parameter(
        name: 'sortBy',
        description: 'Specifies which field the results are sorted by. Used together with sortDir',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string')
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
        ]

    )]
    /**
     * Grid
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function grid(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $sorting = $this->gridRenderSort(
            $sanitizedParams,
            $this->isJson($request)
        );

        if ($sorting != null) {
            $sorting = implode(',', $sorting);
        }

        $columnFilter = $this->buildColumnFilter($dataSet, $sanitizedParams);
        $keywordFilter = $this->buildKeywordFilter($dataSet, $sanitizedParams);

        $params = array_merge($columnFilter['params'], $keywordFilter['params']);

        $filter = $this->gridRenderFilter([
            'filter' => $request->getParam('filter', $columnFilter['filter']),
            'keyword' => $keywordFilter['keyword']
        ], $sanitizedParams);

        try {
            $data = $dataSet->getData(
                [
                    'order' => $sorting,
                    'start' => $filter['start'],
                    'size' => $filter['length'],
                    'filter' => $filter['filter'],
                    'keyword' => $filter['keyword']
                ],
                [],
                $params,
            );
        } catch (\Exception $e) {
            $data = [
                'exception' => __('Error getting DataSet data, failed with following message: ')
                    . $e->getMessage()
            ];
            $this->getLog()->error('Error getting DataSet data, failed with following message: '
                . $e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());
        }

        $dataSet->setActive();

        $recordsTotal = $dataSet->countLast();

        return $response
            ->withStatus(200)
            ->withHeader('X-Total-Count', $recordsTotal)
            ->withJson($data);
    }

    #[OA\Post(
        path: '/dataset/data/{dataSetId}',
        operationId: 'dataSetDataAdd',
        description: 'Add a row of Data to a DataSet',
        summary: 'Add Row',
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
                    new OA\Property(
                        property: 'dataSetColumnId_ID',
                        description: 'Parameter for each dataSetColumnId in the DataSet',
                        type: 'string'
                    )
                ],
                required: ['dataSetColumnId_ID']
            )
        ),
        required: true
    )]
    #[OA\Response(
        response: 201,
        description: 'successful operation',
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
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function add(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $row = [];

        // Expect input for each value-column
        foreach ($dataSet->getColumn() as $column) {
            /* @var \Xibo\Entity\DataSetColumn $column */
            if ($column->dataSetColumnTypeId == 1) {
                // Sanitize accordingly
                if ($column->dataTypeId == 2) {
                    // Number
                    $value = $sanitizedParams->getDouble('dataSetColumnId_' . $column->dataSetColumnId);
                } else if ($column->dataTypeId == 3) {
                    // Date
                    $date = $sanitizedParams->getDate('dataSetColumnId_' . $column->dataSetColumnId);
                    // format only if we have the date provided.
                    $value = $date === null ? $date : $date->format(DateFormatHelper::getSystemFormat());
                } else if ($column->dataTypeId == 5) {
                    // Media Id
                    $value = $sanitizedParams->getInt('dataSetColumnId_' . $column->dataSetColumnId);
                } else if ($column->dataTypeId === 6) {
                    // HTML
                    $value = $sanitizedParams->getHtml('dataSetColumnId_' . $column->dataSetColumnId);
                } else {
                    // String
                    $value = $sanitizedParams->getString('dataSetColumnId_' . $column->dataSetColumnId);
                }

                $row[$column->heading] = $value;
            } elseif ($column->dataSetColumnTypeId == 3) {
                throw new InvalidArgumentException(__('Cannot add new rows to remote dataSet'), 'dataSetColumnTypeId');
            }
        }

        // Use the data set object to add a row
        $rowId = $dataSet->addRow($row);


        // Save the dataSet
        $dataSet->save(['validate' => false, 'saveColumns' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => __('Added Row'),
            'id' => $rowId,
            'data' => [
                'id' => $rowId
            ]
        ]);

        return $this->render($request, $response);
    }

    #[OA\Put(
        path: '/dataset/data/{dataSetId}/{rowId}',
        operationId: 'dataSetDataEdit',
        description: 'Edit a row of Data to a DataSet',
        summary: 'Edit Row',
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
        name: 'rowId',
        description: 'The Row ID of the Data to Edit',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: 'application/x-www-form-urlencoded',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'dataSetColumnId_ID',
                        description: 'Parameter for each dataSetColumnId in the DataSet',
                        type: 'string'
                    )
                ],
                required: ['dataSetColumnId_ID']
            )
        ),
        required: true
    )]
    #[OA\Response(response: 200, description: 'successful operation')]
    /**
     * Edit Row
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param int $rowId
     *
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function edit(Request $request, Response $response, $id, $rowId)
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $existingRow = $dataSet->getData(['id' => $rowId])[0];
        $row = [];

        // Expect input for each value-column
        foreach ($dataSet->getColumn() as $column) {
            $existingValue = $existingRow[$column->heading];
            /* @var \Xibo\Entity\DataSetColumn $column */
            if ($column->dataSetColumnTypeId == 1) {
                // Pull out the value
                $value = $request->getParam('dataSetColumnId_' . $column->dataSetColumnId, null);

                $this->getLog()->debug('Value is: ' . var_export($value, true)
                    . ', existing value is ' . var_export($existingValue, true));

                // Sanitize accordingly
                if ($column->dataTypeId == 2) {
                    // Number
                    if (isset($value)) {
                        $value = $sanitizedParams->getDouble('dataSetColumnId_' . $column->dataSetColumnId);
                    } else {
                        $value = $existingValue;
                    }
                } else if ($column->dataTypeId == 3) {
                    // Date
                    if (isset($value)) {
                        $value = $sanitizedParams->getDate('dataSetColumnId_' . $column->dataSetColumnId);
                    } else {
                        $value = $existingValue;
                    }
                } else if ($column->dataTypeId == 5) {
                    // Media Id
                    if (isset($value)) {
                        $value = $sanitizedParams->getInt('dataSetColumnId_' . $column->dataSetColumnId);
                    } else {
                        $value = null;
                    }
                } else if ($column->dataTypeId === 6) {
                    // HTML
                    if (isset($value)) {
                        $value = $sanitizedParams->getHtml('dataSetColumnId_' . $column->dataSetColumnId);
                    } else {
                        $value = null;
                    }
                } else {
                    // String
                    if (isset($value)) {
                        $value = $sanitizedParams->getString('dataSetColumnId_' . $column->dataSetColumnId);
                    } else {
                        $value = $existingValue;
                    }
                }

                $row[$column->heading] = $value;
            }
        }

        // Use the data set object to edit a row
        if ($row != []) {
            $dataSet->editRow($rowId, $row);
        } else {
            throw new InvalidArgumentException(__('Cannot edit data of remote columns'), 'dataSetColumnTypeId');
        }
        // Save the dataSet
        $dataSet->save(['validate' => false, 'saveColumns' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => __('Edited Row'),
            'id' => $rowId,
            'data' => [
                'id' => $rowId
            ]
        ]);

        return $this->render($request, $response);
    }

    #[OA\Delete(
        path: '/dataset/data/{dataSetId}/{rowId}',
        operationId: 'dataSetDataDelete',
        description: 'Delete a row of Data to a DataSet',
        summary: 'Delete Row',
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
        name: 'rowId',
        description: 'The Row ID of the Data to Delete',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'successful operation')]
    /**
     * Delete Row
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $rowId
     *
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function delete(Request $request, Response $response, $id, $rowId)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        if (empty($dataSet->getData(['id' => $rowId])[0])) {
            throw new NotFoundException(__('row not found'), 'dataset');
        }

        // Delete the row
        $dataSet->deleteRow($rowId);

        // Save the dataSet
        $dataSet->save(['validate' => false, 'saveColumns' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => __('Deleted Row'),
            'id' => $rowId
        ]);

        return $this->render($request, $response);
    }

    /**
     * Build the query for filter
     * @param $dataSet
     * @param $sanitizedParams
     * @return array
     */
    private function buildColumnFilter($dataSet, $sanitizedParams): array
    {
        $filter = '';
        $params = [];
        $i = 0;

        foreach ($dataSet->getColumn() as $column) {
            if ($column->dataSetColumnTypeId == 1) {
                $i++;
                if ($sanitizedParams->getString($column->heading) != null) {
                    $filter .= 'AND `' . $column->heading . '` LIKE :heading_' . $i . ' ';
                    $params['heading_' . $i] = '%' . $sanitizedParams->getString($column->heading) . '%';
                }
            }
        }

        return [
            'filter' => trim($filter, 'AND'),
            'params' => $params
        ];
    }

    /**
     * Build the query for keyword
     * @param $dataSet
     * @param $sanitizedParams
     * @return array
     */
    private function buildKeywordFilter($dataSet, $sanitizedParams): array
    {
        $keyword = $sanitizedParams->getString('keyword');

        if ($keyword === null) {
            return ['keyword' => '', 'params' => []];
        }

        // Get the keywords separated by comma
        $keywords = array_filter(array_map('trim', explode(',', $keyword)));
        $keywordClauses = [];
        $params = [];
        $i = 0;

        // Create a separate SQL query for each keyword
        foreach ($keywords as $word) {
            $wordClauses = [];

            foreach ($dataSet->getColumn() as $column) {
                if ($column->dataSetColumnTypeId == 1) {
                    $i++;
                    $wordClauses[] = '`' . $column->heading . '` LIKE :keyword_' . $i;
                    $params['keyword_' . $i] = '%' . $word . '%';
                }
            }

            if (!empty($wordClauses)) {
                $keywordClauses[] = '(' . implode(' OR ', $wordClauses) . ')';
            }
        }

        return [
            'keyword' => !empty($keywordClauses) ? implode(' OR ', $keywordClauses) : '',
            'params' => $params
        ];
    }
}
