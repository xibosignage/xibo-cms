<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetData.php)
 */


namespace Xibo\Controller;


use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\DataSetFactory;
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

        $this->getState()->template = 'grid';
        $this->getState()->setData($dataSet->getData());
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

        // Use the data set object to add a row
        $rowId = $dataSet->addRow();

        // Save the dataSet
        $dataSet->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => __('Added Row'),
            'id' => $rowId
        ]);
    }

    /**
     * Edit Row
     * @param int $dataSetId
     * @param int $rowId
     * @param int $columnId
     */
    public function edit($dataSetId, $rowId, $columnId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Use the data object to edit a row
        $dataSet->editRow($rowId, $columnId, Sanitize::getParam('value', null));

        // Save the dataSet
        $dataSet->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => __('Edited Row'),
            'id' => $rowId
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

        $dataSet->deleteRow($rowId);

        // Save the dataSet
        $dataSet->save(['validate' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => __('Deleted Row'),
            'id' => $rowId
        ]);
    }
}