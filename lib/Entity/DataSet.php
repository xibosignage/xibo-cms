<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSet.php)
 */


namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Helper\Log;
use Xibo\Storage\PDOConnect;

class DataSet
{
    use EntityTrait;

    public $dataSetId;
    public $dataSet;
    public $description;
    public $userId;
    public $lastDataEdit;

    // Read only properties
    public $owner;
    public $groupsWithPermissions;

    private $permissions = [];
    private $columns = [];

    public function getId()
    {
        return $this->dataSetId;
    }

    public function getOwnerId()
    {
        return $this->userId;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @param array[Optional] $params
     * @return array The Data
     */
    public function getData($params = [])
    {
        // Build a SQL statement, based on the columns for this dataset
        $this->load();

        $sql = '';

        foreach ($this->getColumns() as $column) {
            /* @var DataSetColumn $column */

        }

        return PDOConnect::select($sql, $params);
    }

    /**
     * Assign a column
     * @param DataSetColumn $column
     */
    public function assignColumn($column)
    {
        $this->load();

        // Set the column order if we need to
        if ($column->columnOrder == 0)
            $column->columnOrder = count($this->columns) + 1;

        $this->columns[] = $column;
    }

    /**
     * Has Data?
     * @return bool
     */
    public function hasData()
    {
        return PDOConnect::exists('SELECT id FROM dataset_' . $this->dataSetId . ' LIMIT 1', []);
    }

    /**
     * Validate
     */
    public function validate()
    {
        if (!v::string()->notEmpty()->length(1, 50)->validate($this->dataSet))
            throw new \InvalidArgumentException(__('Name must be between 1 and 50 characters'));

        if (!v::string()->length(null, 254)->validate($this->description))
            throw new \InvalidArgumentException(__('Description can not be longer than 254 characters'));

        try {
            $existing = DataSetFactory::getByName($this->dataSet);

            if ($this->dataSetId == 0 || $this->dataSetId != $existing->dataSetId)
                throw new \InvalidArgumentException(sprintf(__('There is already dataSet called %s. Please choose another name.'), $this->dataSet));
        }
        catch (NotFoundException $e) {
            // This is good
        }
    }

    /**
     * Load all known information
     */
    public function load()
    {
        if ($this->loaded || $this->dataSetId == 0)
            return;

        // Load Columns
        $this->columns = DataSetColumnFactory::getByDataSetId($this->dataSetId);

        // Load Permissions
        $this->permissions = PermissionFactory::getByObjectId('DataSet', $this->getId());

        $this->loaded = true;
    }

    /**
     * Save this DataSet
     * @param array $options
     */
    public function save($options = [])
    {
        $options = array_merge(['validate' => true], $options);

        if ($options['validate'])
            $this->validate();

        if ($this->dataSetId == 0)
            $this->add();
        else
            $this->edit();

        // Columns
        foreach ($this->columns as $column) {
            /* @var \Xibo\Entity\DataSetColumn $column */
            $column->dataSetId = $this->dataSetId;
            $column->save();
        }

        // Notify Displays?
        $this->notify();
    }

    /**
     * Delete DataSet
     */
    public function delete()
    {
        $this->load();

        // TODO check we aren't being used

        // Delete Permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->deleteAll();
        }

        // Delete Columns
        foreach ($this->columns as $column) {
            /* @var \Xibo\Entity\DataSetColumn $column */
            $column->delete();
        }

        // Delete the data set
        PDOConnect::update('DELETE FROM `dataset` WHERE dataSetId = :dataSetId', ['dataSetId' => $this->dataSetId]);

        // The last thing we do is drop the dataSet table
        PDOConnect::update('DROP TABLE dataset_' . $this->dataSetId, []);
    }

    /**
     * Add
     */
    private function add()
    {
        $this->dataSetId = PDOConnect::insert('
          INSERT INTO `dataset` (DataSet, Description, UserID)
            VALUES (:dataSet, :description, :userId)
        ', [
            'dataSet' => $this->dataSet,
            'description' => $this->description,
            'userId' => $this->userId
        ]);

        // Create the data table for this dataset
        PDOConnect::update('
          CREATE TABLE `dataset_' . $this->dataSetId . '` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1
        ', []);
    }

    /**
     * Edit
     */
    private function edit()
    {
        PDOConnect::update('
          UPDATE dataset SET DataSet = :dataSet, Description = :description, lastDataEdit = :lastDataEdit WHERE DataSetID = :dataSetId
        ', [
            'dataSetId' => $this->dataSetId,
            'dataSet' => $this->dataSet,
            'description' => $this->description,
            'lastDataEdit' => $this->lastDataEdit
        ]);
    }

    /**
     * Notify displays of this campaign change
     */
    public function notify()
    {
        Log::debug('Checking for Displays to refresh for DataSet %d', $this->dataSetId);

        foreach (DisplayFactory::getByDataSetId($this->dataSetId) as $display) {
            /* @var \Xibo\Entity\Display $display */
            $display->setMediaIncomplete();
            $display->save(false);
        }
    }

    /**
     * Add a row
     * @return int
     */
    public function addRow()
    {
        $this->lastDataEdit = time();

        return PDOConnect::insert('INSERT INTO `' . $this->dataSetId . '` (id) VALUES (NULL)', []);
    }

    /**
     * Edit a row
     * @param $rowId
     * @param $columnId
     * @param $value
     */
    public function editRow($rowId, $columnId, $value)
    {
        $this->lastDataEdit = time();

        // Get the column
        $column = DataSetColumnFactory::getById($columnId);

        PDOConnect::update('UPDATE `' . $this->dataSetId . '` SET `' . $column->heading . '` = :value WHERE id = :id', [
            'value' => $value,
            'id' => $rowId
        ]);
    }

    /**
     * Delete Row
     * @param $rowId
     */
    public function deleteRow($rowId)
    {
        $this->lastDataEdit = time();

        PDOConnect::update('DELETE FROM `' . $this->dataSetId . '` WHERE id = :id', [
            'id' => $rowId
        ]);
    }
}