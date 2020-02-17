<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetColumn.php)
 */


namespace Xibo\Controller;


use Stash\Interfaces\PoolInterface;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\XiboException;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetColumnTypeFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DataTypeFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

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
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param DataSetColumnTypeFactory $dataSetColumnTypeFactory
     * @param DataTypeFactory $dataTypeFactory
     * @param PoolInterface $pool
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $dataSetFactory, $dataSetColumnFactory, $dataSetColumnTypeFactory, $dataTypeFactory, $pool)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
        $this->dataSetColumnTypeFactory = $dataSetColumnTypeFactory;
        $this->dataTypeFactory = $dataTypeFactory;
        $this->pool = $pool;
    }
    /**
     * Column Page
     * @param $dataSetId
     */
    public function displayPage($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

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
     *
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
     * @throws \Xibo\Exception\NotFoundException
     */
    public function grid($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $dataSetColumns = $this->dataSetColumnFactory->query($this->gridRenderSort(), [
            'dataSetId' => $dataSetId,
            'dataSetColumnId' => $this->getSanitizer()->getInt('dataSetColumnId')
        ]);

        foreach ($dataSetColumns as $column) {
            /* @var \Xibo\Entity\DataSetColumn $column */

            $column->dataType = __($column->dataType);
            $column->dataSetColumnType = __($column->dataSetColumnType);

            if ($this->isApi())
                break;

            $column->includeProperty('buttons');

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
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $this->getState()->template = 'dataset-column-form-add';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'dataTypes' => $this->dataTypeFactory->query(),
            'dataSetColumnTypes' => $this->dataSetColumnTypeFactory->query(),
            'help' => $this->getHelp()->link('DataSet', 'AddColumn')
        ]);
    }

    /**
     * Add
     * @param $dataSetId
     *
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
     *
     * @throws XiboException
     */
    public function add($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Create a Column
        $column = $this->dataSetColumnFactory->createEmpty();
        $column->heading = $this->getSanitizer()->getString('heading');
        $column->listContent = $this->getSanitizer()->getString('listContent');
        $column->columnOrder = $this->getSanitizer()->getInt('columnOrder');
        $column->dataTypeId = $this->getSanitizer()->getInt('dataTypeId');
        $column->dataSetColumnTypeId = $this->getSanitizer()->getInt('dataSetColumnTypeId');
        $column->formula = $this->getSanitizer()->getParam('formula', null);
        $column->remoteField = $this->getSanitizer()->getParam('remoteField', null);
        $column->showFilter = $this->getSanitizer()->getCheckbox('showFilter');
        $column->showSort = $this->getSanitizer()->getCheckbox('showSort');

        if ($column->dataSetColumnTypeId == 3){
            $this->pool->deleteItem('/dataset/cache/' . $dataSet->dataSetId);
            $this->getLog()->debug('New remote column detected, clear cache for remote dataSet ID ' . $dataSet->dataSetId);
        }

        // Assign the column to set the column order if necessary
        $dataSet->assignColumn($column);

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
    }

    /**
     * Edit Form
     * @param $dataSetId
     * @param $dataSetColumnId
     */
    public function editForm($dataSetId, $dataSetColumnId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $this->getState()->template = 'dataset-column-form-edit';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'dataSetColumn' => $this->dataSetColumnFactory->getById($dataSetColumnId),
            'dataTypes' => $this->dataTypeFactory->query(),
            'dataSetColumnTypes' => $this->dataSetColumnTypeFactory->query(),
            'help' => $this->getHelp()->link('DataSet', 'EditColumn')
        ]);
    }

    /**
     * Edit
     * @param $dataSetId
     * @param $dataSetColumnId
     *
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
     *
     * @throws XiboException
     */
    public function edit($dataSetId, $dataSetColumnId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Column
        $column = $this->dataSetColumnFactory->getById($dataSetColumnId);
        $column->heading = $this->getSanitizer()->getString('heading');
        $column->listContent = $this->getSanitizer()->getString('listContent');
        $column->columnOrder = $this->getSanitizer()->getInt('columnOrder');
        $column->dataTypeId = $this->getSanitizer()->getInt('dataTypeId');
        $column->dataSetColumnTypeId = $this->getSanitizer()->getInt('dataSetColumnTypeId');
        $column->formula = $this->getSanitizer()->getParam('formula', null);
        $column->remoteField = $this->getSanitizer()->getParam('remoteField', null);
        $column->showFilter = $this->getSanitizer()->getCheckbox('showFilter');
        $column->showSort = $this->getSanitizer()->getCheckbox('showSort');
        $column->save();

        if ($column->dataSetColumnTypeId == 3 && $column->hasPropertyChanged('remoteField')){
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
    }

    /**
     * Delete Form
     * @param $dataSetId
     * @param $dataSetColumnId
     */
    public function deleteForm($dataSetId, $dataSetColumnId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkDeleteable($dataSet))
            throw new AccessDeniedException();

        $this->getState()->template = 'dataset-column-form-delete';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'dataSetColumn' => $this->dataSetColumnFactory->getById($dataSetColumnId),
            'help' => $this->getHelp()->link('DataSet', 'DeleteColumn')
        ]);
    }

    /**
     * Delete
     * @param $dataSetId
     * @param $dataSetColumnId
     *
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
    public function delete($dataSetId, $dataSetColumnId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkDeleteable($dataSet))
            throw new AccessDeniedException();

        // Get the column
        $column = $this->dataSetColumnFactory->getById($dataSetColumnId);
        $column->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => sprintf(__('Deleted %s'), $column->heading)
        ]);
    }
}