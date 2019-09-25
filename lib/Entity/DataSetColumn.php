<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetColumn.php)
 */


namespace Xibo\Entity;
use Respect\Validation\Validator as v;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\DataSetColumnFactory;
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
     * @SWG\Property(description="The data field of the remote DataSet as a JSON-String")
     * @var string
     */
    public $remoteField;

    /**
     * @SWG\Property(description="Does this column show a filter on the data entry page?")
     * @var string
     */
    public $showFilter = 0;

    /**
     * @SWG\Property(description="Does this column allow a sorting on the data entry page?")
     * @var string
     */
    public $showSort = 0;

    /**
     * @SWG\Property(description="The column type for this Column")
     * @var string
     */
    public $dataSetColumnType;

    /** @var  DataSetColumnFactory */
    private $dataSetColumnFactory;

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
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param DataTypeFactory $dataTypeFactory
     * @param DataSetColumnTypeFactory $dataSetColumnTypeFactory
     */
    public function __construct($store, $log, $dataSetColumnFactory, $dataTypeFactory, $dataSetColumnTypeFactory)
    {
        $this->excludeProperty('priorDatasetColumnId');
        $this->setCommonDependencies($store, $log);

        $this->dataSetColumnFactory = $dataSetColumnFactory;
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
     * @throws InvalidArgumentException
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

        if (!v::stringType()->alnum()->validate($this->heading) || strtolower($this->heading) == 'id')
            throw new InvalidArgumentException(__('Please provide an alternative column heading %s can not be used.', $this->heading), 'heading');

        if ($this->dataSetColumnTypeId == 2 && $this->formula == '') {
            throw new InvalidArgumentException(__('Please enter a valid formula'), 'formula');
        }

        // Make sure this column name is unique
        $columns = $this->dataSetColumnFactory->getByDataSetId($this->dataSetId);

        foreach ($columns as $column) {
            if ($column->heading == $this->heading && ($this->dataSetColumnId == null || $column->dataSetColumnId != $this->dataSetColumnId))
                throw new InvalidArgumentException(__('A column already exists with this name, please choose another'), 'heading');
        }

        // Check the actual values
        try {
            $this->dataTypeFactory->getById($this->dataTypeId);
        } catch (NotFoundException $e) {
            throw new InvalidArgumentException(__('Provided Data Type doesn\'t exist'), 'datatype');
        }

        try {
            $dataSetColumnType = $this->dataSetColumnTypeFactory->getById($this->dataSetColumnTypeId);

            // If we are a remote column, validate we have a field
            if (strtolower($dataSetColumnType->dataSetColumnType) === 'remote' && ($this->remoteField === '' || $this->remoteField === null))
                throw new InvalidArgumentException(__('Remote field is required when the column type is set to Remote'), 'remoteField');

        } catch (NotFoundException $e) {
            throw new InvalidArgumentException(__('Provided DataSet Column Type doesn\'t exist'), 'dataSetColumnTypeId');
        }

        // Should we validate the list content?
        if ($this->dataSetColumnId != 0 && $this->listContent != '') {
            // Look up all DataSet data in this table to make sure that the existing data is covered by the list content
            $list = $this->listContentArray();

            // Add an empty field
            $list[] = '';

            // We can check this is valid by building up a NOT IN sql statement, if we get results.. we know its not good
            $select = '';

            $dbh = $this->getStore()->getConnection();

            for ($i=0; $i < count($list); $i++) {
                $list_val = $dbh->quote($list[$i]);
                $select .= $list_val . ',';
            }

            $select = rtrim($select, ',');

            // $select has been quoted in the for loop - always test the original value of the column (we won't have changed the actualised table yet)
            $SQL = 'SELECT id FROM `dataset_' . $this->dataSetId . '` WHERE `' . $this->getOriginalValue('heading') . '` NOT IN (' . $select . ')';

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                'datasetcolumnid' => $this->dataSetColumnId
            ));

            if ($sth->fetch())
                throw new InvalidArgumentException(__('New list content value is invalid as it does not include values for existing data'), 'listcontent');
        }

        // if formula dataSetType is set and formula is not empty, try to execute the SQL to validate it - we're ignoring client side formulas here.
        if ($this->dataSetColumnTypeId == 2 && $this->formula != '' && substr($this->formula, 0, 1) !== '$') {
           try {
               $formula = str_replace('[DisplayId]', 0, $this->formula);
               $this->getStore()->select('SELECT * FROM (SELECT `id`, ' . $formula . ' AS `' . $this->heading . '`  FROM `dataset_' . $this->dataSetId . '`) dataset WHERE 1 = 1 ', []);
           } catch (\Exception $e) {
               $this->getLog()->debug('Formula validation failed with following message ' . $e->getMessage());
               throw new InvalidArgumentException(__('Provided formula is invalid'), 'formula');
           }
        }
    }

    /**
     * Save
     * @param array[Optional] $options
     * @throws InvalidArgumentException
     */
    public function save($options = [])
    {
        $options = array_merge(['validate' => true, 'rebuilding' => false], $options);

        if ($options['validate'] && !$options['rebuilding'])
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
        if (($this->dataSetColumnTypeId == 1) || ($this->dataSetColumnTypeId == 3)) {
            $this->getStore()->update('ALTER TABLE `dataset_' . $this->dataSetId . '` DROP `' . $this->heading . '`', []);
        }
    }

    /**
     * Add
     */
    private function add()
    {
        $this->dataSetColumnId = $this->getStore()->insert('
        INSERT INTO `datasetcolumn` (DataSetID, Heading, DataTypeID, ListContent, ColumnOrder, DataSetColumnTypeID, Formula, RemoteField, `showFilter`, `showSort`)
          VALUES (:dataSetId, :heading, :dataTypeId, :listContent, :columnOrder, :dataSetColumnTypeId, :formula, :remoteField, :showFilter, :showSort)
        ', [
            'dataSetId' => $this->dataSetId,
            'heading' => $this->heading,
            'dataTypeId' => $this->dataTypeId,
            'listContent' => $this->listContent,
            'columnOrder' => $this->columnOrder,
            'dataSetColumnTypeId' => $this->dataSetColumnTypeId,
            'formula' => $this->formula,
            'remoteField' => $this->remoteField,
            'showFilter' => $this->showFilter,
            'showSort' => $this->showSort
        ]);

        // Add Column to Underlying Table
        if (($this->dataSetColumnTypeId == 1) || ($this->dataSetColumnTypeId == 3)) {
            // Use a separate connection for DDL (it operates outside transactions)
            $this->getStore()->isolated('ALTER TABLE `dataset_' . $this->dataSetId . '` ADD `' . $this->heading . '` ' . $this->sqlDataType() . ' NULL', []);
        }
    }

    /**
     * Edit
     * @param array $options
     * @throws InvalidArgumentException
     */
    private function edit($options)
    {
        $params = [
            'dataSetId' => $this->dataSetId,
            'heading' => $this->heading,
            'dataTypeId' => $this->dataTypeId,
            'listContent' => $this->listContent,
            'columnOrder' => $this->columnOrder,
            'dataSetColumnTypeId' => $this->dataSetColumnTypeId,
            'formula' => $this->formula,
            'dataSetColumnId' => $this->dataSetColumnId,
            'remoteField' => $this->remoteField,
            'showFilter' => $this->showFilter,
            'showSort' => $this->showSort
        ];

        $sql = '
          UPDATE `datasetcolumn` SET
            dataSetId = :dataSetId,
            Heading = :heading,
            ListContent = :listContent,
            ColumnOrder = :columnOrder,
            DataTypeID = :dataTypeId,
            DataSetColumnTypeID = :dataSetColumnTypeId,
            Formula = :formula,
            RemoteField = :remoteField, 
            `showFilter` = :showFilter, 
            `showSort` = :showSort
         WHERE dataSetColumnId = :dataSetColumnId 
        ';

        $this->getStore()->update($sql, $params);

        try {
            if ($options['rebuilding'] && ($this->dataSetColumnTypeId == 1 || $this->dataSetColumnTypeId == 3)) {
                $this->getStore()->isolated('ALTER TABLE `dataset_' . $this->dataSetId . '` ADD `' . $this->heading . '` ' . $this->sqlDataType() . ' NULL', []);

            } else if (($this->dataSetColumnTypeId == 1 || $this->dataSetColumnTypeId == 3)
                   && ($this->hasPropertyChanged('heading') || $this->hasPropertyChanged('dataTypeId'))) {
                $sql = 'ALTER TABLE `dataset_' . $this->dataSetId . '` CHANGE `' . $this->getOriginalValue('heading') . '` `' . $this->heading . '` ' . $this->sqlDataType() . ' NULL DEFAULT NULL';
                $this->getStore()->isolated($sql, []);
            }
        } catch (\PDOException $PDOException) {
            $this->getLog()->error('Unable to change DataSetColumn because ' . $PDOException->getMessage());
            throw new InvalidArgumentException(__('Existing data is incompatible with your new configuration'), 'dataSetData');
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
                $dataType = 'TEXT';
                break;

            case 4:
            default:
                $dataType = 'VARCHAR(1000)';
        }

        return $dataType;
    }
}