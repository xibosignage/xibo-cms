<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetColumn.php)
 */


namespace Xibo\Controller;


use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetColumnTypeFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DataTypeFactory;
use Xibo\Helper\Help;
use Xibo\Helper\Sanitize;

class DataSetColumn extends Base
{
    /**
     * Column Page
     * @param $dataSetId
     */
    public function displayPage($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $this->getState()->template = 'dataset-column-page';
        $this->getState()->setData([
            'dataSet' => $dataSet
        ]);
    }

    /**
     * Column Search
     * @param $dataSetId
     */
    public function grid($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $dataSetColumns = DataSetColumnFactory::getByDataSetId($dataSetId);

        foreach ($dataSetColumns as $column) {
            /* @var \Xibo\Entity\DataSetColumn $column */

            $column->dataType = __($column->dataType);
            $column->dataSetColumnType = __($column->dataSetColumnType);

            // Edit
            $column->buttons[] = array(
                'id' => 'dataset_button_edit',
                'url' => $this->urlFor('dataSet.column.edit.form', ['id' => $dataSetId, 'colId' => $column->dataSetColumnId]),
                'text' => __('Edit')
            );

            if ($this->getUser()->checkDeleteable($dataSet)) {
                // Delete
                $column->buttons[] = array(
                    'id' => 'dataset_button_delete',
                    'url' => $this->urlFor('dataSet.column.delete.form', ['id' => $dataSetId, 'colId' => $column->dataSetColumnId]),
                    'text' => __('Delete')
                );
            }
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($dataSetColumns);
    }

    /**
     * Add form
     * @param int $dataSetId
     */
    public function addForm($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $this->getState()->template = 'dataset-column-form-add';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'dataTypes' => DataTypeFactory::query(),
            'dataSetColumnTypes' => DataSetColumnTypeFactory::query(),
            'help' => Help::Link('DataSet', 'AddColumn')
        ]);
    }

    /**
     * Add
     * @param $dataSetId
     */
    public function add($dataSetId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Create a Column
        $column = new \Xibo\Entity\DataSetColumn();
        $column->heading = Sanitize::getString('heading');
        $column->listContent = Sanitize::getString('listContent');
        $column->columnOrder = Sanitize::getInt('columnOrder');
        $column->dataTypeId = Sanitize::getInt('dataTypeId');
        $column->dataSetColumnTypeId = Sanitize::getInt('dataSetColumnTypeId');
        $column->formula = Sanitize::getString('formula');

        $dataSet->assignColumn($column);
        $dataSet->save();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Added %s'), $column->heading),
            'id' => $column->dataSetColumnId,
            'data' => $column
        ]);
    }

    /**
     * Edit Form
     * @param $dataSetId
     * @param $dataSetColumnId
     */
    public function editForm($dataSetId, $dataSetColumnId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $this->getState()->template = 'dataset-column-form-edit';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'dataSetColumn' => DataSetColumnFactory::getById($dataSetColumnId),
            'dataTypes' => DataTypeFactory::query(),
            'dataSetColumnTypes' => DataSetColumnTypeFactory::query(),
            'help' => Help::Link('DataSet', 'EditColumn')
        ]);
    }

    /**
     * Edit
     * @param $dataSetId
     * @param $dataSetColumnId
     */
    public function edit($dataSetId, $dataSetColumnId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Column
        $column = DataSetColumnFactory::getById($dataSetColumnId);
        $column->heading = Sanitize::getString('heading');
        $column->listContent = Sanitize::getString('listContent');
        $column->columnOrder = Sanitize::getInt('columnOrder');
        $column->dataTypeId = Sanitize::getInt('dataTypeId');
        $column->dataSetColumnTypeId = Sanitize::getInt('dataSetColumnTypeId');
        $column->formula = Sanitize::getString('formula');
        $column->save();

        $dataSet->notify();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Edited %s'), $column->heading),
            'id' => $column->dataSetColumnId,
            'data' => $column
        ]);
    }

    /**
     * Delete Form
     * @param $dataSetId
     * @param $dataSetColumnId
     */
    public function deleteForm($dataSetId, $dataSetColumnId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkDeleteable($dataSet))
            throw new AccessDeniedException();

        $this->getState()->template = 'dataset-column-form-delete';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'dataSetColumn' => DataSetColumnFactory::getById($dataSetColumnId),
            'help' => Help::Link('DataSet', 'DeleteColumn')
        ]);
    }

    /**
     * Delete
     * @param $dataSetId
     * @param $dataSetColumnId
     */
    public function delete($dataSetId, $dataSetColumnId)
    {
        $dataSet = DataSetFactory::getById($dataSetId);

        if (!$this->getUser()->checkDeleteable($dataSet))
            throw new AccessDeniedException();

        // Get the column
        $column = DataSetColumnFactory::getById($dataSetColumnId);
        $column->delete();

        // Return
        $this->getState()->hydrate([
            'message' => sprintf(__('Deleted %s'), $column->heading)
        ]);
    }
}