<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetColumn.php)
 */


namespace Xibo\Entity;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\DataSetColumnTypeFactory;
use Xibo\Factory\DataTypeFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;


/**
 * Class DataSetColumn
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class DataSetColumn implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID of this DataSetColumn")
     * @var int
     */
    public $dataSetColumnId;

    /**
     * @SWG\Property(description="The ID of the DataSet that this Column belongs to")
     * @var int
     */
    public $dataSetId;

    /**
     * @SWG\Property(description="The Column Heading")
     * @var string
     */
    public $heading;

    /**
     * @SWG\Property(description="The ID of the DataType for this Column")
     * @var int
     */
    public $dataTypeId;

    /**
     * @SWG\Property(description="The ID of the ColumnType for this Column")
     * @var int
     */
    public $dataSetColumnTypeId;

    /**
     * @SWG\Property(description="Comma separated list of valid content for drop down columns")
     * @var string
     */
    public $listContent;

    /**
     * @SWG\Property(description="The order this column should be displayed")
     * @var int
     */
    public $columnOrder;

    /**
     * @SWG\Property(description="A MySQL formula for this column")
     * @var string
     */
    public $formula;

    /**
     * @SWG\Property(description="The data type for this Column")
     * @var string
     */
    public $dataType;

    /**
     * @SWG\Property(description="The column type for this Column")
     * @var string
     */
    public $dataSetColumnType;

    /** @var  DataTypeFactory */
    private $dataTypeFactory;

    /** @var  DataSetColumnTypeFactory */
    private $dataSetColumnTypeFactory;

    /**
     * The prior dataset column id, when cloning
     * @var int
     */
    public $priorDatasetColumnId;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param DataTypeFactory $dataTypeFactory
     * @param DataSetColumnTypeFactory $dataSetColumnTypeFactory
     */
    public function __construct($store, $log, $dataTypeFactory, $dataSetColumnTypeFactory)
    {
        $this->excludeProperty('priorDatasetColumnId');
        $this->setCommonDependencies($store, $log);

        $this->dataTypeFactory = $dataTypeFactory;
        $this->dataSetColumnTypeFactory = $dataSetColumnTypeFactory;
    }

    /**
     * Clone
     */
    public function __clone()
    {
        $this->priorDatasetColumnId = $this->dataSetColumnId;
        $this->dataSetColumnId = null;
        $this->dataSetId = null;
    }

    /**
     * List Content Array
     * @return array
     */
    public function listContentArray()
    {
        return explode(',', $this->listContent);
    }

    /**
     * Validate
     */
    public function validate()
    {
        if ($this->dataSetId == 0 || $this->dataSetId == '')
            throw new InvalidArgumentException(__('Missing dataSetId'), 'dataSetId');

        if ($this->dataTypeId == 0 || $this->dataTypeId == '')
            throw new InvalidArgumentException(__('Missing dataTypeId'), 'dataTypeId');

        if ($this->dataSetColumnTypeId == 0 || $this->dataSetColumnTypeId == '')
            throw new InvalidArgumentException(__('Missing dataSetColumnTypeId'), 'dataSetColumnTypeId');

        if ($this->heading == '')
            throw new InvalidArgumentException(__('Please provide a column heading.'), 'heading');

        // Check the actual values
        try {
            $this->dataTypeFactory->getById($this->dataTypeId);
        } catch (NotFoundException $e) {
            throw new InvalidArgumentException(__('Provided Data Type doesn\'t exist'), 'datatype');
        }

        try {
            $this->dataSetColumnTypeFactory->getById($this->dataTypeId);
        } catch (NotFoundException $e) {
            throw new InvalidArgumentException(__('Provided DataSet Column Type doesn\'t exist'), 'dataSetColumnTypeId');
        }

        // Validation
        if ($this->dataSetColumnId != 0 && $this->listContent != '') {
            $list = $this->listContentArray();

            // We can check this is valid by building up a NOT IN sql statement, if we get results.. we know its not good
            $select = '';

            $dbh = $this->getStore()->getConnection();

            for ($i=0; $i < count($list); $i++) {
                $list_val = $dbh->quote($list[$i]);
                $select .= $list_val . ',';
            }

            $select = rtrim($select, ',');

            // $select has been quoted in the for loop
            $SQL = 'SELECT id FROM `dataset_' . $this->dataSetId . '` WHERE `' . $this->heading . '` NOT IN (' . $select . ')';

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                'datasetcolumnid' => $this->dataSetColumnId
            ));

            if ($sth->fetch())
                throw new InvalidArgumentException(__('New list content value is invalid as it does not include values for existing data'), 'listcontent');
        }
    }

    /**
     * Save
     * @param array[Optional] $options
     */
    public function save($options = [])
    {
        $options = array_merge(['validate' => true, 'rebuilding' => false], $options);

        if ($options['validate'])
            $this->validate();

        if ($this->dataSetColumnId == 0)
            $this->add();
        else
            $this->edit($options);
    }

    /**
     * Delete
     */
    public function delete()
    {
        $this->getStore()->update('DELETE FROM `datasetcolumn` WHERE DataSetColumnID = :dataSetColumnId', ['dataSetColumnId' => $this->dataSetColumnId]);

        // Delete column
        if ($this->dataSetColumnTypeId == 1) {
            $this->getStore()->update('ALTER TABLE `dataset_' . $this->dataSetId . '` DROP `' . $this->heading . '`', []);
        }
    }

    /**
     * Add
     */
    private function add()
    {
        $this->dataSetColumnId = $this->getStore()->insert('
        INSERT INTO `datasetcolumn` (DataSetID, Heading, DataTypeID, ListContent, ColumnOrder, DataSetColumnTypeID, Formula)
          VALUES (:dataSetId, :heading, :dataTypeId, :listContent, :columnOrder, :dataSetColumnTypeId, :formula)
        ', [
            'dataSetId' => $this->dataSetId,
            'heading' => $this->heading,
            'dataTypeId' => $this->dataTypeId,
            'listContent' => $this->listContent,
            'columnOrder' => $this->columnOrder,
            'dataSetColumnTypeId' => $this->dataSetColumnTypeId,
            'formula' => $this->formula
        ]);

        // Add Column to Underlying Table
        if ($this->dataSetColumnTypeId == 1) {
            $this->getStore()->update('ALTER TABLE `dataset_' . $this->dataSetId . '` ADD `' . $this->heading . '` ' . $this->sqlDataType() . ' NULL', []);
        }
    }

    /**
     * Edit
     * @param array $options
     */
    private function edit($options)
    {
        // Get the current heading
        $currentHeading = $this->getStore()->select('SELECT heading FROM `datasetcolumn` WHERE dataSetColumnId = :dataSetColumnId', ['dataSetColumnId' => $this->dataSetColumnId]);
        $currentHeading = $currentHeading[0]['heading'];

        $this->getStore()->update('
          UPDATE `datasetcolumn` SET
            dataSetId = :dataSetId,
            Heading = :heading,
            ListContent = :listContent,
            ColumnOrder = :columnOrder,
            DataTypeID = :dataTypeId,
            DataSetColumnTypeID = :dataSetColumnTypeId,
            Formula = :formula
          WHERE dataSetColumnId = :dataSetColumnId
        ', [
            'dataSetId' => $this->dataSetId,
            'heading' => $this->heading,
            'dataTypeId' => $this->dataTypeId,
            'listContent' => $this->listContent,
            'columnOrder' => $this->columnOrder,
            'dataSetColumnTypeId' => $this->dataSetColumnTypeId,
            'formula' => $this->formula,
            'dataSetColumnId' => $this->dataSetColumnId
        ]);

        if ($options['rebuilding'] && $this->dataSetColumnTypeId == 1) {
            $this->getStore()->update('ALTER TABLE `dataset_' . $this->dataSetId . '` ADD `' . $this->heading . '` ' . $this->sqlDataType() . ' NULL', []);

        } else if ($this->dataSetColumnTypeId == 1 && $currentHeading != $this->heading) {
            $sql = 'ALTER TABLE `dataset_' . $this->dataSetId . '` CHANGE `' . $currentHeading . '` `' . $this->heading . '` ' . $this->sqlDataType() . ' NULL DEFAULT NULL';
            $this->getLog()->debug($sql);
            $this->getStore()->update($sql, []);
        }
    }

    /**
     * Get the SQL Data Type for this Column Definition
     * @return string
     */
    private function sqlDataType()
    {
        $dataType = null;

        switch ($this->dataTypeId) {

            case 2:
                $dataType = 'FLOAT';
                break;

            case 3:
                $dataType = 'DATETIME';
                break;

            case 5:
                $dataType = 'INT';
                break;

            case 1:
            case 4:
            default:
                $dataType = 'VARCHAR(1000)';
        }

        return $dataType;
    }
}