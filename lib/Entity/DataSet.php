<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSet.php)
 */


namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Stash\Interfaces\PoolInterface;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\DuplicateEntityException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
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

    /**
     * @SWG\Property(description="Flag to indicate whether this DataSet is Remote")
     * @var int
     */
    public $isRemote = 0;

    /**
     * @SWG\Property(description="Method to fetch the Data, can be GET or POST")
     * @var string
     */
    public $method;

    /**
     * @SWG\Property(description="URI to call to fetch Data from. Replacements are {{DATE}}, {{TIME}} and, in case this is a sequencial used DataSet, {{COL.NAME}} where NAME is a ColumnName from the underlying DataSet.")
     * @var string
     */
    public $uri;

    /**
     * @SWG\Property(description="Data to send as POST-Data to the remote host with the same Replacements as in the URI.")
     * @var string
     */
    public $postData;

    /**
     * @SWG\Property(description="Authentication method, can be none, digest, basic")
     * @var string
     */
    public $authentication;

    /**
     * @SWG\Property(description="Username to authenticate with")
     * @var string
     */
    public $username;

    /**
     * @SWG\Property(description="Corresponding password")
     * @var string
     */
    public $password;

    /**
     * @SWG\Property(description="Comma separated string of custom HTTP headers")
     * @var string
     */
    public $customHeaders;

    /**
     * @SWG\Property(description="Time in seconds this DataSet should fetch new Datas from the remote host")
     * @var int
     */
    public $refreshRate;

    /**
     * @SWG\Property(description="Time in seconds when this Dataset should be cleared. If here is a lower value than in RefreshRate it will be cleared when the data is refreshed")
     * @var int
     */
    public $clearRate;

    /**
     * @SWG\Property(description="DataSetID of the DataSet which should be fetched and present before the Data from this DataSet are fetched")
     * @var int
     */
    public $runsAfter;

    /**
     * @SWG\Property(description="Last Synchronisation Timestamp")
     * @var int
     */
    public $lastSync = 0;

    /**
     * @SWG\Property(description="Last Clear Timestamp")
     * @var int
     */
    public $lastClear = 0;

    /**
     * @SWG\Property(description="Root-Element form JSON where the data are stored in")
     * @var String
     */
    public $dataRoot;

    /**
     * @SWG\Property(description="Optional function to use for summarize or count unique fields in a remote request")
     * @var String
     */
    public $summarize;

    /**
     * @SWG\Property(description="JSON-Element below the Root-Element on which the consolidation should be applied on")
     * @var String
     */
    public $summarizeField;

    /**
     * @SWG\Property(description="The source id for remote dataSet, 1 - JSON, 2 - CSV")
     * @var integer
     */
    public $sourceId;

    /**
     * @SWG\Property(description="A flag whether to ignore the first row, for CSV source remote dataSet")
     * @var integer
     */
    public $ignoreFirstRow;

    /** @var array Permissions */
    private $permissions = [];

    /**
     * @var DataSetColumn[]
     */
    public $columns = [];

    private $countLast = 0;

    /** @var array Blacklist for SQL */
    private $blackList = array(';', 'INSERT', 'UPDATE', 'SELECT', 'DELETE', 'TRUNCATE', 'TABLE', 'FROM', 'WHERE');

    /** @var  SanitizerServiceInterface */
    private $sanitizer;

    /** @var  ConfigServiceInterface */
    private $config;

    /** @var PoolInterface */
    private $pool;

    /** @var  DataSetFactory */
    private $dataSetFactory;

    /** @var  DataSetColumnFactory */
    private $dataSetColumnFactory;

    /** @var  PermissionFactory */
    private $permissionFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var DateServiceInterface */
    private $date;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizer
     * @param ConfigServiceInterface $config
     * @param PoolInterface $pool
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param PermissionFactory $permissionFactory
     * @param DisplayFactory $displayFactory
     * @param DateServiceInterface $date
     */
    public function __construct($store, $log, $sanitizer, $config, $pool, $dataSetFactory, $dataSetColumnFactory, $permissionFactory, $displayFactory, $date)
    {
        $this->setCommonDependencies($store, $log);
        $this->sanitizer = $sanitizer;
        $this->config = $config;
        $this->pool = $pool;
        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
        $this->permissionFactory = $permissionFactory;
        $this->displayFactory = $displayFactory;
        $this->date = $date;
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
     * Set the owner of this DataSet
     * @param $userId
     */
    public function setOwner($userId)
    {
        $this->userId = $userId;
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
     * @return DataSetColumn[]|DataSetColumn
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
     * Get Column
     * @param string $dataSetColumn
     * @return DataSetColumn[]|DataSetColumn
     * @throws NotFoundException when the heading is provided and the column cannot be found
     */
    public function getColumnByName($dataSetColumn)
    {
        $this->load();

        foreach ($this->columns as $column) {
            /* @var DataSetColumn $column */
            if ($column->heading == $dataSetColumn)
                return $column;
        }

        throw new NotFoundException(sprintf(__('Column %s not found'), $dataSetColumn));
    }

    /**
     * @param string[] $columns Column Names to select
     * @return array
     */
    public function getUniqueColumnValues($columns)
    {
        $this->load();

        $select = '';
        foreach ($columns as $heading) {
            // Check this exists
            $found = false;
            foreach ($this->columns as $column) {
                if ($column->heading == $heading) {
                    // Formula column?
                    if ($column->dataSetColumnTypeId == 2) {
                        $select .= str_replace($this->blackList, '', htmlspecialchars_decode($column->formula, ENT_QUOTES)) . ' AS `' . $column->heading . '`,';
                    }
                    else {
                        $select .= '`' . $column->heading . '`,';
                    }
                    $found = true;
                    break;
                }
            }

            if (!$found)
                throw new \InvalidArgumentException(__('Unknown Column ' . $heading));
        }
        $select = rtrim($select, ',');
        // $select is safe

        return $this->getStore()->select('SELECT DISTINCT ' . $select . ' FROM `dataset_' . $this->dataSetId . '`', []);
    }

    /**
     * Get DataSet Data
     * @param array $filterBy
     * @param array $options
     * @return array
     * @throws NotFoundException
     */
    public function getData($filterBy = [], $options = [])
    {
        $start = $this->sanitizer->getInt('start', 0, $filterBy);
        $size = $this->sanitizer->getInt('size', 0, $filterBy);
        $filter = $this->sanitizer->getParam('filter', $filterBy);
        $ordering = $this->sanitizer->getString('order', $filterBy);
        $displayId = $this->sanitizer->getInt('displayId', 0, $filterBy);

        $options = array_merge([
            'includeFormulaColumns' => true,
            'requireTotal' => true
        ], $options);

        // Params
        $params = [];

        // Sanitize the filter options provided
        // Get the Latitude and Longitude ( might be used in a formula )
        if ($displayId == 0) {
            $displayGeoLocation = "GEOMFROMTEXT('POINT(" . $this->config->getSetting('DEFAULT_LAT') . " " . $this->config->getSetting('DEFAULT_LONG') . ")')";
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

        // Are there any client side formulas
        $clientSideFormula = [];

        // Select (columns)
        foreach ($this->getColumn() as $column) {
            /* @var DataSetColumn $column */
            $allowedOrderCols[] = $column->heading;
            
            if ($column->dataSetColumnTypeId == 2 && !$options['includeFormulaColumns'])
                continue;

            // Formula column?
            if ($column->dataSetColumnTypeId == 2) {

                // Is this a client side column?
                if (substr($column->formula, 0, 1) === '$') {
                    $clientSideFormula[] = $column;
                    continue;
                }

                $formula = str_replace($this->blackList, '', htmlspecialchars_decode($column->formula, ENT_QUOTES));
                $formula = str_replace('[DisplayId]', $displayId, $formula);

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
            // Support display filtering.
            $filter = str_replace('[DisplayId]', $displayId, $filter);
            $filter = str_replace($this->blackList, '', $filter);

            $body .= ' AND ' . $filter;
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

            // handle case where lower limit is set to > 0 and upper limit to 0 https://github.com/xibosignage/xibo/issues/2187
            // it is with <= 0 because in some Widgets we calculate the size as upper - lower, https://github.com/xibosignage/xibo/issues/2263.
            if ($start != 0 && $size <= 0) {
                $size = PHP_INT_MAX;
            }

            $limit = sprintf(' LIMIT %d, %d ', $start, $size);
        }

        $sql = $select . $body . $order . $limit;

        $data = $this->getStore()->select($sql, $params);

        // If there are limits run some SQL to work out the full payload of rows
        if ($options['requireTotal']) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total FROM (' . $body, $params);
            $this->countLast = intval($results[0]['total']);
        }

        // Are there any client side formulas?
        if (count($clientSideFormula) > 0) {
            $renderedData = [];
            foreach ($data as $item) {
                foreach ($clientSideFormula as $column) {
                    // Run the formula and add the resulting value to the list
                    $value = null;
                    try {
                        if (substr($column->formula, 0, strlen('$dateFormat(')) === '$dateFormat(') {
                            // Pull out the column name and date format
                            $details = explode(',', str_replace(')', '', str_replace('$dateFormat(', '', $column->formula)));

                            if (isset($details[2])) {
                                $language = str_replace(' ', '', $details[2]);
                            } else {
                                $language = $this->config->getSetting('DEFAULT_LANGUAGE', 'en_GB');
                            }

                            $this->date->setLocale($language);
                            $value = $this->date->parse($item[$details[0]])->format($details[1]);
                        }
                    } catch (\Exception $e) {
                        $this->getLog()->error('DataSet client side formula error in dataSetId ' . $this->dataSetId . ' with column formula ' . $column->formula);
                    }

                    $item[$column->heading] = $value;
                }

                $renderedData[] = $item;
            }
        } else {
            $renderedData = $data;
        }

        return $renderedData;
    }

    /**
     * Assign a column
     * @param DataSetColumn $column
     */
    public function assignColumn($column)
    {
        $this->load();

        // Set the dataSetId
        $column->dataSetId = $this->dataSetId;

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
     * Returns a Timestamp for the next Synchronisation process.
     * @return int Seconds
     */
    public function getNextSyncTime()
    {
        return $this->lastSync + $this->refreshRate;
    }

    /**
     * @return bool
     */
    public function isTruncateEnabled()
    {
        return $this->clearRate !== 0;
    }

    /**
     * Returns a Timestamp for the next Clearing process.
     * @return int Seconds
     */
    public function getNextClearTime()
    {
        return $this->lastClear + $this->clearRate;
    }

    /**
     * Returns if there is a consolidation field and method present or not.
     * @return boolean
     */
    public function doConsolidate()
    {
        return ($this->summarizeField != null) && ($this->summarizeField != '')
            && ($this->summarize != null) && ($this->summarize != '');
    }

    /**
     * Returns the last Part of the Fieldname on which the consolidation should be applied on
     * @return String
     */
    public function getConsolidationField()
    {
        $pos = strrpos($this->summarizeField, '.');
        if ($pos !== false) {
            return substr($this->summarizeField, $pos + 1);
        }
        return $this->summarizeField;
    }

    /**
     * Tests if this DataSet contains parameters for getting values on the dependant DataSet
     * @return boolean
     */
    public function containsDependantFieldsInRequest()
    {
        return strpos($this->postData, '{{COL.') !== false || strpos($this->uri, '{{COL.') !== false;
    }

    /**
     * Validate
     * @throws InvalidArgumentException
     * @throws DuplicateEntityException
     */
    public function validate()
    {
        if (!v::stringType()->notEmpty()->length(null, 50)->validate($this->dataSet))
            throw new InvalidArgumentException(__('Name must be between 1 and 50 characters'), 'dataSet');

        if ($this->description != null && !v::stringType()->length(null, 254)->validate($this->description))
            throw new InvalidArgumentException(__('Description can not be longer than 254 characters'), 'description');

        // If we are a remote dataset do some additional checks
        if ($this->isRemote === 1) {
            if (!v::stringType()->notEmpty()->validate($this->uri))
                throw new InvalidArgumentException(__('A remote DataSet must have a URI.'), 'uri');
        }

        try {
            $existing = $this->dataSetFactory->getByName($this->dataSet, $this->userId);

            if ($this->dataSetId == 0 || $this->dataSetId != $existing->dataSetId)
                throw new DuplicateEntityException(sprintf(__('There is already dataSet called %s. Please choose another name.'), $this->dataSet));
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
     * @throws InvalidArgumentException
     * @throws DuplicateEntityException
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

        // We've been touched
        $this->setActive();

        // Notify Displays?
        $this->notify();
    }

    /**
     * @param int $time
     * @return $this
     */
    public function saveLastSync($time)
    {
        $this->lastSync = $time;

        $this->getStore()->update('UPDATE `dataset` SET lastSync = :lastSync WHERE dataSetId = :dataSetId', [
            'dataSetId' => $this->dataSetId,
            'lastSync' => $this->lastSync
        ]);

        return $this;
    }

    /**
     * @param int $time
     * @return $this
     */
    public function saveLastClear($time)
    {
        $this->lastSync = $time;

        $this->getStore()->update('UPDATE `dataset` SET lastClear = :lastClear WHERE dataSetId = :dataSetId', [
            'dataSetId' => $this->dataSetId,
            'lastClear' => $this->lastClear
        ]);

        return $this;
    }

    /**
     * Is this DataSet active currently
     * @return bool
     */
    public function isActive()
    {
        $cache = $this->pool->getItem('/dataset/accessed/' . $this->dataSetId);
        return $cache->isHit();
    }

    /**
     * Indicate that this DataSet has been accessed recently
     * @return $this
     */
    public function setActive()
    {
        $this->getLog()->debug('Setting ' . $this->dataSetId . ' as active');

        $cache = $this->pool->getItem('/dataset/accessed/' . $this->dataSetId);
        $cache->set('true');
        $cache->expiresAfter(intval($this->config->getSetting('REQUIRED_FILES_LOOKAHEAD')) * 1.5);
        $this->pool->saveDeferred($cache);
        return $this;
    }

    /**
     * Delete DataSet
     * @throws ConfigurationException
     * @throws InvalidArgumentException
     */
    public function delete()
    {
        $this->load();

        if ($this->isLookup)
            throw new ConfigurationException(__('Lookup Tables cannot be deleted'));

        // TODO: Make sure we're not used as a dependent DataSet

        // Make sure we're able to delete
        if ($this->getStore()->exists('
            SELECT widgetId 
              FROM `widgetoption`
              WHERE `widgetoption`.type = \'attrib\'
                AND `widgetoption`.option = \'dataSetId\'
                AND `widgetoption`.value = :dataSetId
        ', ['dataSetId' => $this->dataSetId])) {
            throw new InvalidArgumentException(__('Cannot delete because DataSet is in use on one or more Layouts.'), 'dataSetId');
        }

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

        // Delete any dataSet rss
        $this->getStore()->update('DELETE FROM `datasetrss` WHERE dataSetId = :dataSetId', ['dataSetId' => $this->dataSetId]);

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
        $this->getStore()->isolated('TRUNCATE TABLE `dataset_' . $this->dataSetId . '`', []);
        $this->getStore()->isolated('ALTER TABLE `dataset_' . $this->dataSetId . '` AUTO_INCREMENT = 1', []);
    }

    /**
     * Add
     */
    private function add()
    {
        $columns = 'DataSet, Description, UserID, `code`, `isLookup`, `isRemote`, `lastDataEdit`, `lastClear`';
        $values = ':dataSet, :description, :userId, :code, :isLookup, :isRemote, :lastDataEdit, :lastClear';

        $params = [
            'dataSet' => $this->dataSet,
            'description' => $this->description,
            'userId' => $this->userId,
            'code' => ($this->code == '') ? null : $this->code,
            'isLookup' => $this->isLookup,
            'isRemote' => $this->isRemote,
            'lastDataEdit' => 0,
            'lastClear' => 0
        ];

        // Insert the extra columns we expect for a remote DataSet
        if ($this->isRemote === 1) {
            $columns .= ', `method`, `uri`, `postData`, `authentication`, `username`, `password`, `customHeaders`, `refreshRate`, `clearRate`, `runsAfter`, `dataRoot`, `lastSync`, `summarize`, `summarizeField`, `sourceId`, `ignoreFirstRow`';
            $values .= ', :method, :uri, :postData, :authentication, :username, :password, :customHeaders, :refreshRate, :clearRate, :runsAfter, :dataRoot, :lastSync, :summarize, :summarizeField, :sourceId, :ignoreFirstRow';

            $params['method'] = $this->method;
            $params['uri'] = $this->uri;
            $params['postData'] = $this->postData;
            $params['authentication'] = $this->authentication;
            $params['username'] = $this->username;
            $params['password'] = $this->password;
            $params['customHeaders'] = $this->customHeaders;
            $params['refreshRate'] = $this->refreshRate;
            $params['clearRate'] = $this->clearRate;
            $params['runsAfter'] = $this->runsAfter;
            $params['dataRoot'] = $this->dataRoot;
            $params['summarize'] = $this->summarize;
            $params['summarizeField'] = $this->summarizeField;
            $params['sourceId'] = $this->sourceId;
            $params['ignoreFirstRow'] = $this->ignoreFirstRow;
            $params['lastSync'] = 0;
        }

        // Do the insert
        $this->dataSetId = $this->getStore()->insert('INSERT INTO `dataset` (' . $columns . ') VALUES (' . $values . ')', $params);

        // Create the data table for this dataSet
        $this->createTable();
    }

    /**
     * Edit
     */
    private function edit()
    {
        $sql = 'DataSet = :dataSet, Description = :description, userId = :userId, lastDataEdit = :lastDataEdit, `code` = :code, `isLookup` = :isLookup, `isRemote` = :isRemote ';
        $params = [
            'dataSetId' => $this->dataSetId,
            'dataSet' => $this->dataSet,
            'description' => $this->description,
            'userId' => $this->userId,
            'lastDataEdit' => $this->lastDataEdit,
            'code' => $this->code,
            'isLookup' => $this->isLookup,
            'isRemote' => $this->isRemote,
        ];

        if ($this->isRemote) {
            $sql .= ', method = :method, uri = :uri, postData = :postData, authentication = :authentication, `username` = :username, `password` = :password, `customHeaders` = :customHeaders, refreshRate = :refreshRate, clearRate = :clearRate, runsAfter = :runsAfter, `dataRoot` = :dataRoot, `summarize` = :summarize, `summarizeField` = :summarizeField, `sourceId` = :sourceId, `ignoreFirstRow` = :ignoreFirstRow ';

            $params['method'] = $this->method;
            $params['uri'] = $this->uri;
            $params['postData'] = $this->postData;
            $params['authentication'] = $this->authentication;
            $params['username'] = $this->username;
            $params['password'] = $this->password;
            $params['customHeaders'] = $this->customHeaders;
            $params['refreshRate'] = $this->refreshRate;
            $params['clearRate'] = $this->clearRate;
            $params['runsAfter'] = $this->runsAfter;
            $params['dataRoot'] = $this->dataRoot;
            $params['summarize'] = $this->summarize;
            $params['summarizeField'] = $this->summarizeField;
            $params['sourceId'] = $this->sourceId;
            $params['ignoreFirstRow'] = $this->ignoreFirstRow;
        }

        $this->getStore()->update('UPDATE dataset SET ' . $sql . '  WHERE DataSetID = :dataSetId', $params);
    }

    /**
     * Create the realised table structure for this DataSet
     */
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
        $this->getStore()->isolated('DROP TABLE IF EXISTS dataset_' . $this->dataSetId, []);
    }

    /**
     * Rebuild the dataSet table
     * @throws XiboException
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
        $this->getLog()->debug('DataSet ' . $this->dataSetId . ' wants to notify');

        $this->displayFactory->getDisplayNotifyService()->collectNow()->notifyByDataSetId($this->dataSetId);
    }

    /**
     * Add a row
     * @param array $row
     * @return int
     */
    public function addRow($row)
    {
        $this->getLog()->debug('Adding row ' . var_export($row, true));

        // Update the last edit date on this dataSet
        $this->lastDataEdit = time();

        // Build a query to insert
        $keys = array_keys($row);
        $keys[] = 'id';

        $values = array_values($row);
        $values[] = NULL;

        $sql = 'INSERT INTO `dataset_' . $this->dataSetId . '` (`' . implode('`, `', $keys) . '`) VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')';

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

    /**
     * Copy Row
     * @param int $dataSetIdSource
     * @param int $dataSetIdTarget
     */
    public function copyRows($dataSetIdSource, $dataSetIdTarget)
    {
        $this->getStore()->insert('INSERT INTO `dataset_' . $dataSetIdTarget . '`  SELECT * FROM `dataset_' . $dataSetIdSource . '` ' ,[]);
    }
}