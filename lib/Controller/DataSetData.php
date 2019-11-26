<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetData.php)
 */


namespace Xibo\Controller;


//use Xibo\Entity\DataSetColumn;
use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

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
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param DataSetFactory $dataSetFactory
     * @param MediaFactory $mediaFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $dataSetFactory, $mediaFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->dataSetFactory = $dataSetFactory;
        $this->mediaFactory = $mediaFactory;
    }

    /**
     * Display Page
     * @param $dataSetId
     */
    public function displayPage($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

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
     *
     * @throws \Xibo\Exception\XiboException
     */
    public function grid($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $sorting = $this->gridRenderSort();

        if ($sorting != null)
            $sorting = implode(',', $sorting);

        // Filter criteria
        $filter = '';
        foreach ($dataSet->getColumn() as $column) {
            /* @var \Xibo\Entity\DataSetColumn $column */
            if ($column->dataSetColumnTypeId == 1) {
                if ($this->getSanitizer()->getString($column->heading) != null) {
                    $filter .= 'AND ' . $column->heading . ' LIKE \'%' . $this->getSanitizer()->getString($column->heading) . '%\' ';
                }
            }
        }
        $filter = trim($filter, 'AND');

        // Work out the limits
        $filter = $this->gridRenderFilter(['filter' => $this->getSanitizer()->getParam('filter', $filter)]);

        try {
            $data = $dataSet->getData([
                'order' => $sorting,
                'start' => $filter['start'],
                'size' => $filter['length'],
                'filter' => $filter['filter']
            ]);
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
    }

    /**
     * Add Form
     * @param int $dataSetId
     */
    public function addForm($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

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
     *
     * @throws XiboException
     */
    public function add($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $row = [];

        // Expect input for each value-column
        foreach ($dataSet->getColumn() as $column) {
            /* @var \Xibo\Entity\DataSetColumn $column */
            if ($column->dataSetColumnTypeId == 1) {

                // Sanitize accordingly
                if ($column->dataTypeId == 2) {
                    // Number
                    $value = $this->getSanitizer()->getDouble('dataSetColumnId_' . $column->dataSetColumnId);
                }
                else if ($column->dataTypeId == 3) {
                    // Date
                    $value = $this->getDate()->getLocalDate($this->getSanitizer()->getDate('dataSetColumnId_' . $column->dataSetColumnId));
                }
                else if ($column->dataTypeId == 5) {
                    // Media Id
                    $value = $this->getSanitizer()->getInt('dataSetColumnId_' . $column->dataSetColumnId);
                }
                else {
                    // String
                    $value = $this->getSanitizer()->getString('dataSetColumnId_' . $column->dataSetColumnId);
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
    }

    /**
     * Edit Form
     * @param $dataSetId
     * @param $rowId
     * @throws \Xibo\Exception\XiboException
     */
    public function editForm($dataSetId, $rowId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

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
                    $this->getLog()->debug('DataSet ' . $dataSetId . ' references an image that no longer exists. ID is ' . $row[$dataSetColumn->heading]);
                }
            }
        }

        $this->getState()->template = 'dataset-data-form-edit';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'row' => $row
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
     *
     * @throws XiboException
     */
    public function edit($dataSetId, $rowId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $existingRow = $dataSet->getData(['id' => $rowId])[0];
        $row = [];

        // Expect input for each value-column
        foreach ($dataSet->getColumn() as $column) {
            /* @var \Xibo\Entity\DataSetColumn $column */
            $existingValue = $this->getSanitizer()->getParam($column->heading, null, $existingRow);

            if ($column->dataSetColumnTypeId == 1) {

                // Pull out the value
                $value = $this->getSanitizer()->getParam('dataSetColumnId_' . $column->dataSetColumnId, null, null, false);

                $this->getLog()->debug('Value is: ' . var_export($value, true) . ', existing value is ' . var_export($existingValue, true));

                // Sanitize accordingly
                if ($column->dataTypeId == 2) {
                    // Number
                    if ($value === null)
                        $value = $existingValue;

                    $value = $this->getSanitizer()->double($value);
                }
                else if ($column->dataTypeId == 3) {
                    // Date
                    if ($value === null) {
                        // Keep it as it was
                        $value = $existingValue;
                    } else {
                        // Parse the new date and convert to a local date/time
                        $value = $this->getDate()->getLocalDate($this->getDate()->parse($value));
                    }
                }
                else if ($column->dataTypeId == 5) {
                    // Media Id
                    if (isset($value)) {
                        $value = $this->getSanitizer()->int($value);
                    } else {
                        $value = null;
                    }
                }
                else {
                    // String
                    if ($value === null)
                        $value = $existingValue;

                    $value = $this->getSanitizer()->string($value);
                }

                $row[$column->heading] = $value;
            }
        }

        // Use the data set object to edit a row
        if ($row != [])
            $dataSet->editRow($rowId, $row);
        else
            throw new InvalidArgumentException(__('Cannot edit data of remote columns'), 'dataSetColumnTypeId');

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
    }

    /**
     * Delete Form
     * @param int $dataSetId
     * @param int $rowId
     */
    public function deleteForm($dataSetId, $rowId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

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
     * @throws NotFoundException
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
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

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
    }
}