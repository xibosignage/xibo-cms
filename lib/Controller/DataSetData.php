<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetData.php)
 */


namespace Xibo\Controller;


use Xibo\Entity\DataSetColumn;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Helper\Date;
use Xibo\Helper\Sanitize;

class DataSetData extends Base
{
    /**
     * Display Page
     * @param $dataSetId
     */
    public function displayPage($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Load data set
        $dataSet->load();

        $this->getState()->template = 'dataset-dataentry-page';
        $this->getState()->setData([
            'dataSet' => $dataSet
        ]);
    }

    /**
     * Grid
     * @param $dataSetId
     *
     * @SWG\Get(
     *  path="/dataset/data/{dataSetId}",
     *  operationId="dataSetData",
     *  tags={"dataset"},
     *  summary="DataSet Data",
     *  description="Get Data for DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="formData",
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
    public function grid($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $sorting = $this->gridRenderSort();

        // Work out the limits
        $limits = $this->gridRenderFilter();

        $this->getState()->template = 'grid';
        $this->getState()->setData($dataSet->getData([
            'order' => implode(',', $sorting),
            'start' => $limits['start'],
            'size' => $limits['length']
        ]));

        // Output the count of records for paging purposes
        if ($dataSet->countLast() != 0)
            $this->getState()->recordsTotal = $dataSet->countLast();
    }

    /**
     * Add Form
     * @param int $dataSetId
     */
    public function addForm($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $dataSet->load();

        $this->getState()->template = 'dataset-data-form-add';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'images' => MediaFactory::query(null, ['type' => 'image'])
        ]);
    }

    /**
     * Add
     * @param int $dataSetId
     *
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
    public function add($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $row = [];

        // Expect input for each value-column
        foreach ($dataSet->getColumn() as $column) {
            /* @var DataSetColumn $column */
            if ($column->dataSetColumnTypeId == 1) {

                // Sanitize accordingly
                if ($column->dataTypeId == 2) {
                    // Number
                    $value = Sanitize::getDouble('dataSetColumnId_' . $column->dataSetColumnId);
                }
                else if ($column->dataTypeId == 3) {
                    // Date
                    $value = Date::getLocalDate(Sanitize::getDate('dataSetColumnId_' . $column->dataSetColumnId));
                }
                else if ($column->dataTypeId == 5) {
                    // Media Id
                    $value = Sanitize::getInt('dataSetColumnId_' . $column->dataSetColumnId);
                }
                else {
                    // String
                    $value = Sanitize::getString('dataSetColumnId_' . $column->dataSetColumnId);
                }

                $row[$column->heading] = $value;
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
            'id' => $rowId
        ]);
    }

    /**
     * Edit Form
     * @param $dataSetId
     * @param $rowId
     */
    public function editForm($dataSetId, $rowId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $dataSet->load();

        $this->getState()->template = 'dataset-data-form-edit';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'row' => $dataSet->getData(['id' => $rowId])[0],
            'images' => MediaFactory::query(null, ['type' => 'image'])
        ]);
    }

    /**
     * Edit Row
     * @param int $dataSetId
     * @param int $rowId
     *
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
    public function edit($dataSetId, $rowId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $existingRow = $dataSet->getData(['id' => $rowId])[0];
        $row = [];

        // Expect input for each value-column
        foreach ($dataSet->getColumn() as $column) {
            /* @var DataSetColumn $column */

            $existingValue = Sanitize::getParam($column->heading, null, $existingRow);

            if ($column->dataSetColumnTypeId == 1) {

                // Sanitize accordingly
                if ($column->dataTypeId == 2) {
                    // Number
                    $value = Sanitize::getDouble('dataSetColumnId_' . $column->dataSetColumnId, $existingValue);
                }
                else if ($column->dataTypeId == 3) {
                    // Date
                    $value = Date::getLocalDate(Sanitize::getDate('dataSetColumnId_' . $column->dataSetColumnId, $existingValue));
                }
                else if ($column->dataTypeId == 5) {
                    // Media Id
                    $value = Sanitize::getInt('dataSetColumnId_' . $column->dataSetColumnId);
                }
                else {
                    // String
                    $value = Sanitize::getString('dataSetColumnId_' . $column->dataSetColumnId, $existingValue);
                }

                $row[$column->heading] = $value;
            }
        }

        // Use the data set object to add a row
        $dataSet->editRow($rowId, $row);

        // Save the dataSet
        $dataSet->save(['validate' => false, 'saveColumns' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => __('Edited Row'),
            'id' => $rowId
        ]);
    }

    /**
     * Delete Form
     * @param int $dataSetId
     * @param int $rowId
     */
    public function deleteForm($dataSetId, $rowId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $dataSet->load();

        $this->getState()->template = 'dataset-data-form-delete';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'row' => $dataSet->getData(['id' => $rowId])[0]
        ]);
    }

    /**
     * Delete Row
     * @param $dataSetId
     * @param $rowId
     *
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
    public function delete($dataSetId, $rowId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

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
    }
}