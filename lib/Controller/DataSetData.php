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
     */
    public function grid($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $sorting = $this->gridRenderSort();

        $this->getState()->template = 'grid';
        $this->getState()->setData($dataSet->getData([
            'order' => implode(',', $sorting)
        ]));
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
            'dataSet' => $dataSet
        ]);
    }

    /**
     * Add
     * @param int $dataSetId
     */
    public function add($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $row = [];

        // Expect input for each value-column
        foreach ($dataSet->getColumns() as $column) {
            /* @var DataSetColumn $column */
            if ($column->dataSetColumnTypeId == 1) {

                // Sanitize accordingly
                if ($column->dataTypeId == 2) {
                    // Number
                    $value = Sanitize::getDouble('dataSetColumnId_' . $column->dataSetColumnId);
                }
                else if ($column->dataTypeId == 3) {
                    // Date
                    $value = Date::getTimestampFromString(Sanitize::getString('dataSetColumnId_' . $column->dataSetColumnId));
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
            'message' => __('Added Row'),
            'id' => $rowId
        ]);
    }

    /**
     * Edit FOrm
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
            'row' => $dataSet->getData(['id' => $rowId])[0]
        ]);
    }

    /**
     * Edit Row
     * @param int $dataSetId
     * @param int $rowId
     */
    public function edit($dataSetId, $rowId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $existingRow = $dataSet->getData(['id' => $rowId])[0];
        $row = [];

        // Expect input for each value-column
        foreach ($dataSet->getColumns() as $column) {
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
                    $value = Date::getTimestampFromString(Sanitize::getString('dataSetColumnId_' . $column->dataSetColumnId, $existingValue));
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
            'message' => __('Deleted Row'),
            'id' => $rowId
        ]);
    }
}