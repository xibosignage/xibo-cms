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

    /**
     * Display Page
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function displayPage(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }
        
        // Load data set
        $dataSet->load();

        $this->getState()->template = 'dataset-dataentry-page';
        $this->getState()->setData([
            'dataSet' => $dataSet
        ]);
        
        return $this->render($request, $response);
    }

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
     * @SWG\Get(
     *  path="/dataset/data/{dataSetId}",
     *  operationId="dataSetData",
     *  tags={"dataset"},
     *  summary="DataSet Data",
     *  description="Get Data for DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     */
    public function grid(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);
        $sanitizedParams = $this->getSanitizer($request->getParams());

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }
        
        $sorting = $this->gridRenderSort($sanitizedParams);

        if ($sorting != null) {
            $sorting = implode(',', $sorting);
        }
        
        // Filter criteria
        $filter = '';
        $params = [];
        $i = 0;
        foreach ($dataSet->getColumn() as $column) {
            /* @var \Xibo\Entity\DataSetColumn $column */
            if ($column->dataSetColumnTypeId == 1) {
                $i++;
                if ($sanitizedParams->getString($column->heading) != null) {
                    $filter .= 'AND `' . $column->heading . '` LIKE :heading_' . $i . ' ';
                    $params['heading_' . $i] = '%' . $sanitizedParams->getString($column->heading) . '%';
                }
            }
        }
        $filter = trim($filter, 'AND');

        // Work out the limits
        $filter = $this->gridRenderFilter(['filter' => $request->getParam('filter', $filter)], $sanitizedParams);

        try {
            $data = $dataSet->getData(
                [
                    'order' => $sorting,
                    'start' => $filter['start'],
                    'size' => $filter['length'],
                    'filter' => $filter['filter']
                ],
                [],
                $params,
            );
        } catch (\Exception $e) {
            $data = ['exception' => __('Error getting DataSet data, failed with following message: ') . $e->getMessage()];
            $this->getLog()->error('Error getting DataSet data, failed with following message: ' . $e->getMessage());
            $this->getLog()->debug($e->getTraceAsString());
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($data);

        // Output the count of records for paging purposes
        if ($dataSet->countLast() != 0)
            $this->getState()->recordsTotal = $dataSet->countLast();

        // Set this dataSet as being active
        $dataSet->setActive();
        
        return $this->render($request, $response);
    }

    /**
     * Add Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function addForm(Request $request, Response $response, $id)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }
        
        $dataSet->load();

        $this->getState()->template = 'dataset-data-form-add';
        $this->getState()->setData([
            'dataSet' => $dataSet
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
     * @throws GeneralException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     * @SWG\Post(
     *  path="/dataset/data/{dataSetId}",
     *  operationId="dataSetDataAdd",
     *  tags={"dataset"},
     *  summary="Add Row",
     *  description="Add a row of Data to a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSetColumnId_ID",
     *      in="formData",
     *      description="Parameter for each dataSetColumnId in the DataSet",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
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

    /**
     * Edit Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param $rowId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function editForm(Request $request, Response $response, $id, $rowId)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $dataSet->load();

        $row = $dataSet->getData(['id' => $rowId])[0];

        // Augment my row with any already selected library image
        foreach ($dataSet->getColumn() as $dataSetColumn) {
            if ($dataSetColumn->dataTypeId === 5) {
                // Add this image object to my row
                try {
                    if (isset($row[$dataSetColumn->heading])) {
                        $row['__images'][$dataSetColumn->dataSetColumnId] = $this->mediaFactory->getById($row[$dataSetColumn->heading]);
                    }
                } catch (NotFoundException $notFoundException) {
                    $this->getLog()->debug('DataSet ' . $id . ' references an image that no longer exists. ID is ' . $row[$dataSetColumn->heading]);
                }
            }
        }

        $this->getState()->template = 'dataset-data-form-edit';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'row' => $row
        ]);

        return $this->render($request, $response);
    }

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
     * @SWG\Put(
     *  path="/dataset/data/{dataSetId}/{rowId}",
     *  operationId="dataSetDataEdit",
     *  tags={"dataset"},
     *  summary="Edit Row",
     *  description="Edit a row of Data to a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="rowId",
     *      in="path",
     *      description="The Row ID of the Data to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSetColumnId_ID",
     *      in="formData",
     *      description="Parameter for each dataSetColumnId in the DataSet",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
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

    /**
     * Delete Form
     * @param Request $request
     * @param Response $response
     * @param $id
     * @param int $rowId
     * @return \Psr\Http\Message\ResponseInterface|Response
     * @throws AccessDeniedException
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\ControllerNotImplemented
     */
    public function deleteForm(Request $request, Response $response, $id, $rowId)
    {
        $dataSet = $this->dataSetFactory->getById($id);

        if (!$this->getUser()->checkEditable($dataSet)) {
            throw new AccessDeniedException();
        }

        $dataSet->load();

        $this->getState()->template = 'dataset-data-form-delete';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'row' => $dataSet->getData(['id' => $rowId])[0]
        ]);

        return $this->render($request, $response);
    }

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
     * @SWG\Delete(
     *  path="/dataset/data/{dataSetId}/{rowId}",
     *  operationId="dataSetDataDelete",
     *  tags={"dataset"},
     *  summary="Delete Row",
     *  description="Delete a row of Data to a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="rowId",
     *      in="path",
     *      description="The Row ID of the Data to Delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
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
}
