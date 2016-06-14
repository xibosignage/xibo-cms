<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSet.php)
 */


namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class DataSet
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class DataSet implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The dataSetId")
     * @var int
     */
    public $dataSetId;

    /**
     * @SWG\Property(description="The dataSet Name")
     * @var string
     */
    public $dataSet;

    /**
     * @SWG\Property(description="The dataSet description")
     * @var string
     */
    public $description;

    /**
     * @SWG\Property(description="The userId of the User that owns this DataSet")
     * @var int
     */
    public $userId;

    /**
     * @SWG\Property(description="Timestamp indicating the date/time this DataSet was edited last")
     * @var int
     */
    public $lastDataEdit;

    /**
     * @SWG\Property(description="The user name of the User that owns this DataSet")
     * @var string
     */
    public $owner;

    /**
     * @SWG\Property(description="A comma separated list of Groups/Users that have permission to this DataSet")
     * @var string
     */
    public $groupsWithPermissions;

    /**
     * @SWG\Property(description="A code for this Data Set")
     * @var string
     */
    public $code;

    /**
     * @SWG\Property(description="Flag to indicate whether this DataSet is a lookup table")
     * @var int
     */
    public $isLookup = 0;

    private $permissions = [];

    /**
     * @var DataSetColumn[]
     */
    public $columns = [];

    private $countLast = 0;

    /** @var  SanitizerServiceInterface */
    private $sanitizer;

    /** @var  ConfigServiceInterface */
    private $config;

    /** @var  DataSetFactory */
    private $dataSetFactory;

    /** @var  DataSetColumnFactory */
    private $dataSetColumnFactory;

    /** @var  PermissionFactory */
    private $permissionFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizer
     * @param ConfigServiceInterface $config
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param PermissionFactory $permissionFactory
     * @param DisplayFactory $displayFactory
     */
    public function __construct($store, $log, $sanitizer, $config, $dataSetFactory, $dataSetColumnFactory, $permissionFactory, $displayFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->sanitizer = $sanitizer;
        $this->config = $config;
        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
        $this->permissionFactory = $permissionFactory;
        $this->displayFactory = $displayFactory;
    }

    /**
     * Clone
     */
    public function __clone()
    {
        $this->dataSetId = null;

        $this->columns = array_map(function ($object) { return clone $object; }, $this->columns);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->dataSetId;
    }

    /**
     * @return int
     */
    public function getOwnerId()
    {
        return $this->userId;
    }

    /**
     * Get the Count of Records in the last getData()
     * @return int
     */
    public function countLast()
    {
        return $this->countLast;
    }

    /**
     * Get Column
     * @param int[Optional] $dataSetColumnId
     * @return array[DataSetColumn]|DataSetColumn
     * @throws NotFoundException when the heading is provided and the column cannot be found
     */
    public function getColumn($dataSetColumnId = 0)
    {
        $this->load();

        if ($dataSetColumnId != 0) {

            foreach ($this->columns as $column) {
                /* @var DataSetColumn $column */
                if ($column->dataSetColumnId == $dataSetColumnId)
                    return $column;
            }

            throw new NotFoundException(sprintf(__('Column %s not found'), $dataSetColumnId));

        } else {
            return $this->columns;
        }
    }

    /**
     * Get DataSet Data
     * @param array $filterBy
     * @param array $options
     * @return array
     */
    public function getData($filterBy = [], $options = [])
    {
        $start = $this->sanitizer->getInt('start', 0, $filterBy);
        $size = $this->sanitizer->getInt('size', 0, $filterBy);
        $filter = $this->sanitizer->getParam('filter', $filterBy);
        $ordering = $this->sanitizer->getString('order', $filterBy);
        $displayId = $this->sanitizer->getInt('displayId', 0, $filterBy);

        $options = array_merge([
            'includeFormulaColumns' => true
        ], $options);

        // Params
        $params = [];

        // Sanitize the filter options provided
        $blackList = array(';', 'INSERT', 'UPDATE', 'SELECT', 'DELETE', 'TRUNCATE', 'TABLE', 'FROM', 'WHERE');

        // Get the Latitude and Longitude ( might be used in a formula )
        if ($displayId == 0) {
            $displayGeoLocation = "GEOMFROMTEXT('POINT(" . $this->config->GetSetting('DEFAULT_LAT') . " " . $this->config->GetSetting('DEFAULT_LONG') . ")')";
        }
        else {
            $displayGeoLocation = '(SELECT GeoLocation FROM `display` WHERE DisplayID = :displayId)';
            $params['displayId'] = $displayId;
        }

        // Build a SQL statement, based on the columns for this dataset
        $this->load();

        $select  = 'SELECT * FROM ( ';
        $body = 'SELECT id';

        // Keep track of the columns we are allowed to order by
        $allowedOrderCols = ['id'];

        // Select (columns)
        foreach ($this->getColumn() as $column) {
            /* @var DataSetColumn $column */
            $allowedOrderCols[] = $column->heading;
            
            if ($column->dataSetColumnTypeId == 2 && !$options['includeFormulaColumns'])
                continue;

            // Formula column?
            if ($column->dataSetColumnTypeId == 2) {
                $formula = str_replace($blackList, '', htmlspecialchars_decode($column->formula, ENT_QUOTES));

                $heading = str_replace('[DisplayGeoLocation]', $displayGeoLocation, $formula) . ' AS `' . $column->heading . '`';
            }
            else {
                $heading = '`' . $column->heading . '`';
            }

            $body .= ', ' . $heading;
        }

        $body .= ' FROM `dataset_' . $this->dataSetId . '`) dataset WHERE 1 = 1 ';

        // Filtering
        if ($filter != '') {
            $body .= ' AND ' . str_replace($blackList, '', $filter);
        }

        // Filter by ID
        if (
            $this->sanitizer->getInt('id', $filterBy) !== null) {
            $body .= ' AND id = :id ';
            $params['id'] = $this->sanitizer->getInt('id', $filterBy);
        }

        // Ordering
        $order = '';
        if ($ordering != '') {
            $order = ' ORDER BY ';

            $ordering = explode(',', $ordering);

            foreach ($ordering as $orderPair) {
                // Sanitize the clause
                $sanitized = str_replace('`', '', str_replace(' ASC', '', str_replace(' DESC', '', $orderPair)));

                // Check allowable
                if (!in_array($sanitized, $allowedOrderCols)) {
                    $this->getLog()->info('Disallowed column: ' . $sanitized);
                    continue;
                }

                // Substitute
                if (strripos($orderPair, ' DESC')) {
                    $order .= sprintf(' `%s`  DESC,', $sanitized);
                }
                else if (strripos($orderPair, ' ASC')) {
                    $order .= sprintf(' `%s`  ASC,', $sanitized);
                }
                else {
                    $order .= sprintf(' `%s`,', $sanitized);
                }
            }

            $order = trim($order, ',');
        }
        else {
            $order = ' ORDER BY id ';
        }

        // Limit
        $limit = '';
        if ($start != 0 || $size != 0) {
            // Substitute in
            $limit = sprintf(' LIMIT %d, %d ', $start, $size);
        }

        $sql = $select . $body . $order . $limit;



        $data = $this->getStore()->select($sql, $params);

        // If there are limits run some SQL to work out the full payload of rows
        $results = $this->getStore()->select('SELECT COUNT(*) AS total FROM (' . $body, $params);
        $this->countLast = intval($results[0]['total']);

        return $data;
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
        return $this->getStore()->exists('SELECT id FROM `dataset_' . $this->dataSetId . '` LIMIT 1', []);
    }

    /**
     * Validate
     */
    public function validate()
    {
        if (!v::string()->notEmpty()->length(null, 50)->validate($this->dataSet))
            throw new \InvalidArgumentException(__('Name must be between 1 and 50 characters'));

        if ($this->description != null && !v::string()->length(null, 254)->validate($this->description))
            throw new \InvalidArgumentException(__('Description can not be longer than 254 characters'));

        try {
            $existing = $this->dataSetFactory->getByName($this->dataSet);

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
        $this->columns = $this->dataSetColumnFactory->getByDataSetId($this->dataSetId);

        // Load Permissions
        $this->permissions = $this->permissionFactory->getByObjectId(get_class($this), $this->getId());

        $this->loaded = true;
    }

    /**
     * Save this DataSet
     * @param array $options
     */
    public function save($options = [])
    {
        $options = array_merge(['validate' => true, 'saveColumns' => true], $options);

        if ($options['validate'])
            $this->validate();

        if ($this->dataSetId == 0)
            $this->add();
        else
            $this->edit();

        // Columns
        if ($options['saveColumns']) {
            foreach ($this->columns as $column) {
                /* @var \Xibo\Entity\DataSetColumn $column */
                $column->dataSetId = $this->dataSetId;
                $column->save();
            }
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

        if ($this->isLookup)
            throw new ConfigurationException(__('Lookup Tables cannot be deleted'));

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
        $this->getStore()->update('DELETE FROM `dataset` WHERE dataSetId = :dataSetId', ['dataSetId' => $this->dataSetId]);

        // The last thing we do is drop the dataSet table
        $this->dropTable();
    }

    /**
     * Delete all data
     */
    public function deleteData()
    {
        // The last thing we do is drop the dataSet table
        $this->getStore()->update('TRUNCATE TABLE `dataset_' . $this->dataSetId . '`', []);
        $this->getStore()->update('ALTER TABLE `dataset_' . $this->dataSetId . '` AUTO_INCREMENT = 1', []);
    }

    /**
     * Add
     */
    private function add()
    {
        $this->dataSetId = $this->getStore()->insert('
          INSERT INTO `dataset` (DataSet, Description, UserID, `code`, `isLookup`)
            VALUES (:dataSet, :description, :userId, :code, :isLookup)
        ', [
            'dataSet' => $this->dataSet,
            'description' => $this->description,
            'userId' => $this->userId,
            'code' => ($this->code == '') ? null : $this->code,
            'isLookup' => $this->isLookup
        ]);

        // Create the data table for this dataSet
        $this->createTable();
    }

    /**
     * Edit
     */
    private function edit()
    {
        $this->getStore()->update('
          UPDATE dataset SET DataSet = :dataSet, Description = :description, lastDataEdit = :lastDataEdit, `code` = :code, `isLookup` = :isLookup WHERE DataSetID = :dataSetId
        ', [
            'dataSetId' => $this->dataSetId,
            'dataSet' => $this->dataSet,
            'description' => $this->description,
            'lastDataEdit' => $this->lastDataEdit,
            'code' => $this->code,
            'isLookup' => $this->isLookup
        ]);
    }

    private function createTable()
    {
        // Create the data table for this dataset
        $this->getStore()->update('
          CREATE TABLE `dataset_' . $this->dataSetId . '` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1
        ', []);
    }

    private function dropTable()
    {
        $this->getStore()->update('DROP TABLE IF EXISTS dataset_' . $this->dataSetId, []);
    }

    /**
     * Rebuild the dataSet table
     */
    public function rebuild()
    {
        $this->load();

        // Drop the data table
        $this->dropTable();

        // Add the data table
        $this->createTable();

        foreach ($this->columns as $column) {
            /* @var \Xibo\Entity\DataSetColumn $column */
            $column->dataSetId = $this->dataSetId;
            $column->save(['rebuilding' => true]);
        }
    }

    /**
     * Notify displays of this campaign change
     */
    public function notify()
    {
        $this->getLog()->debug('Checking for Displays to refresh for DataSet %d', $this->dataSetId);

        foreach ($this->displayFactory->getByActiveDataSetId($this->dataSetId) as $display) {
            /* @var \Xibo\Entity\Display $display */
            $display->setMediaIncomplete();
            $display->setCollectRequired(false);
            $display->save(['validate' => false, 'audit' => false]);
        }
    }

    /**
     * Add a row
     * @param array $row
     * @return int
     */
    public function addRow($row)
    {
        $this->getLog()->debug('Adding row %s', var_export($row, true));

        // Update the last edit date on this dataSet
        $this->lastDataEdit = time();

        // Build a query to insert
        $keys = array_keys($row);
        $keys[] = 'id';

        $values = array_values($row);
        $values[] = NULL;

        $sql = 'INSERT INTO `dataset_' . $this->dataSetId . '` (`' . implode('`, `', $keys) . '`) VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')';

        $this->getLog()->sql($sql, $values);

        return $this->getStore()->insert($sql, $values);
    }

    /**
     * Edit a row
     * @param int $rowId
     * @param array $row
     */
    public function editRow($rowId, $row)
    {
        $this->getLog()->debug('Editing row %s', var_export($row, true));

        // Update the last edit date on this dataSet
        $this->lastDataEdit = time();

        // Params
        $params = ['id' => $rowId];

        // Generate a SQL statement
        $sql = 'UPDATE `dataset_' . $this->dataSetId . '` SET';

        $i = 0;
        foreach ($row as $key => $value) {
            $i++;
            $sql .= ' `' . $key . '` = :value' . $i . ',';
            $params['value' . $i] = $value;
        }

        $sql = rtrim($sql, ',');

        $sql .= ' WHERE `id` = :id ';



        $this->getStore()->update($sql, $params);
    }

    /**
     * Delete Row
     * @param $rowId
     */
    public function deleteRow($rowId)
    {
        $this->lastDataEdit = time();

        $this->getStore()->update('DELETE FROM `dataset_' . $this->dataSetId . '` WHERE id = :id', [
            'id' => $rowId
        ]);
    }
}