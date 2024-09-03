<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */


namespace Xibo\Entity;

use Carbon\Carbon;
use Carbon\Factory;
use Respect\Validation\Validator as v;
use Stash\Interfaces\PoolInterface;
use Xibo\Factory\DataSetColumnFactory;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\DuplicateEntityException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\Definition\Sql;

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
     * @SWG\Property(description="Flag to indicate whether this DataSet is Real time")
     * @var int
     */
    public $isRealTime = 0;

    /**
     * @SWG\Property(description="Indicates the source of the data connector. Requires the Real time flag. Can be null,
     * user-defined, or a connector.")
     * @var string
     */
    public $dataConnectorSource;

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
     * @SWG\Property(description="Custom User agent")
     * @var string
     */
    public $userAgent;

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
     * @SWG\Property(description="Flag whether to truncate DataSet data if no new data is pulled from remote source")
     * @var int
     */
    public $truncateOnEmpty;

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

    /**
     * @SWG\Property(description="Soft limit on number of rows per DataSet, if left empty the global DataSet row limit will be used.")
     * @var integer
     */
    public $rowLimit = null;

    /**
     * @SWG\Property(description="Type of action that should be taken on next remote DataSet sync - stop, fifo or truncate")
     * @var string
     */
    public $limitPolicy;

    /**
     * @SWG\Property(description="Custom separator for CSV source, comma will be used by default")
     * @var string
     */
    public $csvSeparator;

    /**
     * @SWG\Property(description="The id of the Folder this DataSet belongs to")
     * @var int
     */
    public $folderId;

    /**
     * @SWG\Property(description="The id of the Folder responsible for providing permissions for this DataSet")
     * @var int
     */
    public $permissionsFolderId;

    /** @var array Permissions */
    private $permissions = [];

    /**
     * @var DataSetColumn[]
     */
    public $columns = [];

    private $countLast = 0;

    /** @var  \Xibo\Helper\SanitizerService */
    private $sanitizerService;

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

    /** @var DisplayNotifyServiceInterface */
    private $displayNotifyService;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param SanitizerService $sanitizerService
     * @param ConfigServiceInterface $config
     * @param PoolInterface $pool
     * @param DataSetFactory $dataSetFactory
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param PermissionFactory $permissionFactory
     * @param DisplayNotifyServiceInterface $displayNotifyService
     */
    public function __construct($store, $log, $dispatcher, $sanitizerService, $config, $pool, $dataSetFactory, $dataSetColumnFactory, $permissionFactory, $displayNotifyService)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
        $this->sanitizerService = $sanitizerService;
        $this->config = $config;
        $this->pool = $pool;
        $this->dataSetFactory = $dataSetFactory;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
        $this->permissionFactory = $permissionFactory;
        $this->displayNotifyService = $displayNotifyService;
    }

    /**
     * @param $array
     * @return \Xibo\Support\Sanitizer\SanitizerInterface
     */
    protected function getSanitizer($array)
    {
        return $this->sanitizerService->getSanitizer($array);
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

    public function getPermissionFolderId()
    {
        return $this->permissionsFolderId;
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
     * Get the Display Notify Service
     * @return DisplayNotifyServiceInterface
     */
    public function getDisplayNotifyService(): DisplayNotifyServiceInterface
    {
        return $this->displayNotifyService->init();
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
     * @throws InvalidArgumentException
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
                        $select .= str_replace(
                            Sql::DISALLOWED_KEYWORDS,
                            '',
                            htmlspecialchars_decode($column->formula, ENT_QUOTES)
                        ) . ' AS `' . $column->heading . '`,';
                    } else {
                        $select .= '`' . $column->heading . '`,';
                    }
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                throw new InvalidArgumentException(__('Unknown Column ' . $heading));
            }
        }
        $select = rtrim($select, ',');
        // $select is safe

        return $this->getStore()->select('SELECT DISTINCT ' . $select . ' FROM `dataset_' . $this->dataSetId . '`', []);
    }

    /**
     * Get DataSet Data
     * @param array $filterBy
     * @param array $options
     * @param array $extraParams Extra params to apply to the final query
     * @return array
     * @throws NotFoundException
     */
    public function getData($filterBy = [], $options = [], $extraParams = [])
    {
        $sanitizer = $this->getSanitizer($filterBy);

        $start = $sanitizer->getInt('start', ['default' => 0]);
        $size = $sanitizer->getInt('size', ['default' => 0]);
        $filter = $filterBy['filter'] ?? '';
        $ordering = $sanitizer->getString('order');
        $displayId = $sanitizer->getInt('displayId', ['default' => 0]);

        $options = array_merge([
            'includeFormulaColumns' => true,
            'requireTotal' => true,
            'connection' => 'default'
        ], $options);

        // Params (start from extraParams supplied)
        $params = $extraParams;

        // Fetch display tag value/s
        if ($filter != '' && $displayId != 0) {
            // Define the regular expression to match [Tag:...]
            $pattern = '/\[Tag:[^]]+\]/';

            // Find all instances of [Tag:...]
            preg_match_all($pattern, $filter, $matches);

            // Check if matches were found
            if (!empty($matches[0])) {
                $displayTags = [];

                // Iterate through the matches and process each tag
                foreach ($matches[0] as $tagString) {
                    // Remove the enclosing [Tag:] brackets
                    $tagContent = substr($tagString, 5, -1);

                    // Explode the tag content by ":" to separate tagName and defaultValue (if present)
                    $parts = explode(':', $tagContent);
                    $tagName = $parts[0];
                    $defaultTagValue = $parts[1] ?? '';

                    $displayTags[] = [
                        'tagString' => $tagString,
                        'tagName' => $tagName,
                        'defaultValue' => $defaultTagValue
                    ];
                }

                $tagCount = 1;

                // Loop through each tag and get the actual tag value from the database
                foreach ($displayTags as $tag) {
                    $tagSanitizer = $this->getSanitizer($tag);

                    $tagName = $tagSanitizer->getString('tagName');
                    $defaultTagValue = $tagSanitizer->getString('defaultValue');
                    $tagString = $tag['tagString'];

                    $query = 'SELECT `lktagdisplaygroup`.`value` AS tagValue
                                FROM `lkdisplaydg`
                                INNER JOIN `displaygroup` 
                                    ON `displaygroup`.displayGroupId = `lkdisplaydg`.displayGroupId 
                                    AND `displaygroup`.isDisplaySpecific = 1
                                INNER JOIN `lktagdisplaygroup` 
                                    ON `lktagdisplaygroup`.displayGroupId = `lkdisplaydg`.displayGroupId
                                INNER JOIN `tag` ON `lktagdisplaygroup`.tagId = `tag`.tagId
                                WHERE `lkdisplaydg`.displayId = :displayId
                                    AND `tag`.`tag` = :tagName
                                LIMIT 1';

                    $tagParams = [
                        'displayId' => $displayId,
                        'tagName' => $tagName
                    ];

                    // Execute the query
                    $results = $this->getStore()->select($query, $tagParams);

                    // Determine the tag value
                    if (!empty($results)) {
                        $tagValue = !empty($results[0]['tagValue']) ? $results[0]['tagValue'] : '';
                    } else {
                        // Use default tag value if no tag is found
                        $tagValue = $defaultTagValue;
                    }

                    // Replace the tag string in the filter with the actual tag value or default value
                    $filter = str_replace($tagString, ':tagValue_'.$tagCount, $filter);
                    $params['tagValue_'.$tagCount] = $tagValue;

                    $tagCount++;
                }
            }
        }

        // Sanitize the filter options provided
        // Get the Latitude and Longitude ( might be used in a formula )
        if ($displayId == 0) {
            $displayGeoLocation =
                "ST_GEOMFROMTEXT('POINT(" . $this->config->getSetting('DEFAULT_LAT') .
                ' ' . $this->config->getSetting('DEFAULT_LONG') . ")')";
        } else {
            $displayGeoLocation = '(SELECT GeoLocation FROM `display` WHERE DisplayID =' . $displayId. ')';
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
            if ($column->dataSetColumnTypeId == 2 && !$options['includeFormulaColumns']) {
                continue;
            }

            // Formula column?
            if ($column->dataSetColumnTypeId == 2) {
                // Is this a client side column?
                if (str_starts_with($column->formula, '$')) {
                    $clientSideFormula[] = $column;
                    continue;
                }

                $count = 0;
                $formula = str_ireplace(
                    Sql::DISALLOWED_KEYWORDS,
                    '',
                    htmlspecialchars_decode($column->formula, ENT_QUOTES),
                    $count
                );

                if ($count > 0) {
                    $this->getLog()->error(
                        'Formula contains disallowed keywords on DataSet ID ' . $this->dataSetId
                    );
                    continue;
                }

                $formula = str_replace('[DisplayId]', $displayId, $formula);

                $heading = str_replace('[DisplayGeoLocation]', $displayGeoLocation, $formula)
                    . ' AS `' . $column->heading . '`';
            } else {
                $heading = '`' . $column->heading . '`';
            }

            $allowedOrderCols[] = $column->heading;

            $body .= ', ' . $heading;
        }

        $body .= ' FROM `dataset_' . $this->dataSetId . '`) dataset WHERE 1 = 1 ';

        // Filtering
        if ($filter != '') {
            // Support display filtering.
            $filter = str_replace('[DisplayId]', $displayId, $filter);
            $filter = str_ireplace(Sql::DISALLOWED_KEYWORDS, '', $filter);

            $body .= ' AND ' . $filter;
        }

        // Filter by ID
        if ($sanitizer->getInt('id') !== null) {
            $body .= ' AND id = :id ';
            $params['id'] = $sanitizer->getInt('id');
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
                    $found = false;
                    $this->getLog()->info('Potentially disallowed column: ' . $sanitized);
                    // the gridRenderSort will strip spaces on column names go through allowed order columns
                    // and see if we can find a match by stripping spaces from the heading
                    foreach ($allowedOrderCols as $allowedOrderCol) {
                        $this->getLog()->info('Checking spaces in original name : ' . $sanitized);
                        if (str_replace(' ', '', $allowedOrderCol) === $sanitized) {
                            $found = true;
                            // put the column heading with the space as sanitized to make sql happy.
                            $sanitized = $allowedOrderCol;
                        }
                    }

                    // we tried, but it was not found, omit this pair
                    if (!$found) {
                        continue;
                    }
                }

                // Substitute
                if (strripos($orderPair, ' DESC')) {
                    $order .= sprintf(' `%s`  DESC,', $sanitized);
                } else if (strripos($orderPair, ' ASC')) {
                    $order .= sprintf(' `%s`  ASC,', $sanitized);
                } else {
                    $order .= sprintf(' `%s`,', $sanitized);
                }
            }

            $order = trim($order, ',');

            // if after all that we still do not have any column name to order by, default to order by id
            if (trim($order) === 'ORDER BY') {
                $order = ' ORDER BY id ';
            }
        } else {
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

        $data = $this->getStore()->select($sql, $params, $options['connection']);

        // If there are limits run some SQL to work out the full payload of rows
        if ($options['requireTotal']) {
            $results = $this->getStore()->select(
                'SELECT COUNT(*) AS total FROM (' . $body,
                $params,
                $options['connection']
            );
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

                            $carbonFactory = new Factory(['locale' => $language], Carbon::class);
                            $value = $carbonFactory->parse($item[$details[0]])->translatedFormat($details[1]);
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
        return $this->getStore()->exists('SELECT id FROM `dataset_' . $this->dataSetId . '` LIMIT 1', [], 'isolated');
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
        if (!v::stringType()->notEmpty()->length(null, 50)->validate($this->dataSet)) {
            throw new InvalidArgumentException(__('Name must be between 1 and 50 characters'), 'dataSet');
        }

        if ($this->description != null && !v::stringType()->length(null, 254)->validate($this->description)) {
            throw new InvalidArgumentException(__('Description can not be longer than 254 characters'), 'description');
        }

        // If we are a remote dataset do some additional checks
        if ($this->isRemote === 1) {
            if (!v::stringType()->notEmpty()->validate($this->uri)) {
                throw new InvalidArgumentException(__('A remote DataSet must have a URI.'), 'uri');
            }

            if ($this->rowLimit > $this->config->getSetting('DATASET_HARD_ROW_LIMIT')) {
                throw new InvalidArgumentException(__('DataSet row limit cannot be larger than the CMS dataSet row limit'));
            }
        }

        try {
            $existing = $this->dataSetFactory->getByName($this->dataSet, $this->userId);

            if ($this->dataSetId == 0 || $this->dataSetId != $existing->dataSetId) {
                throw new DuplicateEntityException(sprintf(__('There is already dataSet called %s. Please choose another name.'), $this->dataSet));
            }
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
        $options = array_merge([
            'validate' => true,
            'saveColumns' => true,
            'activate' => true,
            'notify' => true,
        ], $options);

        if ($options['validate']) {
            $this->validate();
        }

        if ($this->dataSetId == 0) {
            $this->add();
        } else {
            $this->edit();
        }

        // Columns
        if ($options['saveColumns']) {
            foreach ($this->columns as $column) {
                $column->dataSetId = $this->dataSetId;
                $column->save($options);
            }
        }

        // We've been touched
        if ($options['activate']) {
            $this->setActive();
        }

        // Notify Displays?
        if ($options['notify']) {
            $this->notify();
        }
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

        if ($this->isLookup) {
            throw new ConfigurationException(__('Lookup Tables cannot be deleted'));
        }

        // check if any other DataSet depends on this DataSet
        if ($this->getStore()->exists(
            'SELECT dataSetId FROM dataset WHERE runsAfter = :runsAfter AND dataSetId <> :dataSetId',
            [
                'runsAfter' => $this->dataSetId,
                'dataSetId' => $this->dataSetId
            ])) {
            throw new InvalidArgumentException(__('Cannot delete because this DataSet is set as dependent DataSet for another DataSet'), 'dataSetId');
        }

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

        if ($this->getStore()->exists('
            SELECT `eventId` 
              FROM `schedule`
              WHERE `dataSetId` = :dataSetId
        ', ['dataSetId' => $this->dataSetId])) {
            throw new InvalidArgumentException(
                __('Cannot delete because DataSet is in use on one or more Data Connector schedules.'),
                'dataSetId'
            );
        }

        // Delete Permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->deleteAll();
        }

        // Delete Columns
        foreach ($this->columns as $column) {
            $column->delete(true);
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
        $this->getStore()->update('TRUNCATE TABLE `dataset_' . $this->dataSetId . '`', []);
        $this->getStore()->update('ALTER TABLE `dataset_' . $this->dataSetId . '` AUTO_INCREMENT = 1', []);
        $this->getStore()->commitIfNecessary();
    }

    /**
     * Add
     */
    private function add()
    {
        $columns = 'DataSet, Description, UserID, `code`, `isLookup`, `isRemote`, `lastDataEdit`,';
        $columns .= '`lastClear`, `folderId`, `permissionsFolderId`, `isRealTime`, `dataConnectorSource`';
        $values = ':dataSet, :description, :userId, :code, :isLookup, :isRemote,';
        $values .= ':lastDataEdit, :lastClear, :folderId, :permissionsFolderId, :isRealTime, :dataConnectorSource';

        $params = [
            'dataSet' => $this->dataSet,
            'description' => $this->description,
            'userId' => $this->userId,
            'code' => ($this->code == '') ? null : $this->code,
            'isLookup' => $this->isLookup,
            'isRemote' => $this->isRemote,
            'isRealTime' => $this->isRealTime,
            'dataConnectorSource' => $this->dataConnectorSource,
            'lastDataEdit' => 0,
            'lastClear' => 0,
            'folderId' => ($this->folderId === null) ? 1 : $this->folderId,
            'permissionsFolderId' => ($this->permissionsFolderId == null) ? 1 : $this-> permissionsFolderId,
        ];

        // Insert the extra columns we expect for a remote DataSet
        if ($this->isRemote === 1) {
            $columns .= ', `method`, `uri`, `postData`, `authentication`, `username`, `password`, `customHeaders`, `userAgent`, `refreshRate`, `clearRate`, `truncateOnEmpty`, `runsAfter`, `dataRoot`, `lastSync`, `summarize`, `summarizeField`, `sourceId`, `ignoreFirstRow`, `rowLimit`, `limitPolicy`, `csvSeparator`';
            $values .= ', :method, :uri, :postData, :authentication, :username, :password, :customHeaders, :userAgent, :refreshRate, :clearRate, :truncateOnEmpty, :runsAfter, :dataRoot, :lastSync, :summarize, :summarizeField, :sourceId, :ignoreFirstRow, :rowLimit, :limitPolicy, :csvSeparator';

            $params['method'] = $this->method;
            $params['uri'] = $this->uri;
            $params['postData'] = $this->postData;
            $params['authentication'] = $this->authentication;
            $params['username'] = $this->username;
            $params['password'] = $this->password;
            $params['customHeaders'] = $this->customHeaders;
            $params['userAgent'] = $this->userAgent;
            $params['refreshRate'] = $this->refreshRate;
            $params['clearRate'] = $this->clearRate;
            $params['truncateOnEmpty'] = $this->truncateOnEmpty ?? 0;
            $params['runsAfter'] = $this->runsAfter;
            $params['dataRoot'] = $this->dataRoot;
            $params['summarize'] = $this->summarize;
            $params['summarizeField'] = $this->summarizeField;
            $params['sourceId'] = $this->sourceId;
            $params['ignoreFirstRow'] = $this->ignoreFirstRow;
            $params['lastSync'] = 0;
            $params['rowLimit'] = $this->rowLimit;
            $params['limitPolicy'] = $this->limitPolicy;
            $params['csvSeparator'] = $this->csvSeparator;
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
        $sql = '
            `DataSet` = :dataSet,
            `Description` = :description,
            `userId` = :userId, 
            `lastDataEdit` = :lastDataEdit, 
            `code` = :code, 
            `isLookup` = :isLookup, 
            `isRemote` = :isRemote, 
            `isRealTime` = :isRealTime, 
            `dataConnectorSource` = :dataConnectorSource, 
            `folderId` = :folderId, 
            `permissionsFolderId` = :permissionsFolderId 
        ';
        $params = [
            'dataSetId' => $this->dataSetId,
            'dataSet' => $this->dataSet,
            'description' => $this->description,
            'userId' => $this->userId,
            'lastDataEdit' => $this->lastDataEdit,
            'code' => $this->code,
            'isLookup' => $this->isLookup,
            'isRemote' => $this->isRemote,
            'isRealTime' => $this->isRealTime,
            'dataConnectorSource' => $this->dataConnectorSource,
            'folderId' => $this->folderId,
            'permissionsFolderId' => $this->permissionsFolderId
        ];

        if ($this->isRemote) {
            $sql .= ', method = :method, uri = :uri, postData = :postData, authentication = :authentication, `username` = :username, `password` = :password, `customHeaders` = :customHeaders, `userAgent` = :userAgent, refreshRate = :refreshRate, clearRate = :clearRate, truncateOnEmpty = :truncateOnEmpty, runsAfter = :runsAfter, `dataRoot` = :dataRoot, `summarize` = :summarize, `summarizeField` = :summarizeField, `sourceId` = :sourceId, `ignoreFirstRow` = :ignoreFirstRow , `rowLimit` = :rowLimit, `limitPolicy` = :limitPolicy, `csvSeparator` = :csvSeparator ';

            $params['method'] = $this->method;
            $params['uri'] = $this->uri;
            $params['postData'] = $this->postData;
            $params['authentication'] = $this->authentication;
            $params['username'] = $this->username;
            $params['password'] = $this->password;
            $params['customHeaders'] = $this->customHeaders;
            $params['userAgent'] = $this->userAgent;
            $params['refreshRate'] = $this->refreshRate;
            $params['clearRate'] = $this->clearRate;
            $params['truncateOnEmpty'] = $this->truncateOnEmpty ?? 0;
            $params['runsAfter'] = $this->runsAfter;
            $params['dataRoot'] = $this->dataRoot;
            $params['summarize'] = $this->summarize;
            $params['summarizeField'] = $this->summarizeField;
            $params['sourceId'] = $this->sourceId;
            $params['ignoreFirstRow'] = $this->ignoreFirstRow;
            $params['rowLimit'] = $this->rowLimit;
            $params['limitPolicy'] = $this->limitPolicy;
            $params['csvSeparator'] = $this->csvSeparator;
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
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1
        ', []);
    }

    private function dropTable()
    {
        $this->getStore()->update('DROP TABLE IF EXISTS dataset_' . $this->dataSetId, [], 'isolated', false, false, true);
    }

    /**
     * Rebuild the dataSet table
     * @throws GeneralException
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

        $this->getDisplayNotifyService()->collectNow()->notifyByDataSetId($this->dataSetId);
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
        $this->lastDataEdit = Carbon::now()->format('U');

        // Build a query to insert
        $params = [];
        $keys = array_keys($row);

        $sql = 'INSERT INTO `dataset_' . $this->dataSetId
            . '` (`' . implode('`, `', $keys) . '`) VALUES (';

        $i = 0;
        foreach ($row as $value) {
            $i++;
            $sql .= ':value' . $i . ',';
            $params['value' . $i] = $value;
        }
        $sql = rtrim($sql, ',');
        $sql .= ')';

        return $this->getStore()->insert($sql, $params);
    }

    /**
     * Edit a row
     * @param int $rowId
     * @param array $row
     */
    public function editRow($rowId, $row)
    {
        $this->getLog()->debug(sprintf('Editing row %s', var_export($row, true)));

        // Update the last edit date on this dataSet
        $this->lastDataEdit = Carbon::now()->format('U');

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
        $this->lastDataEdit = Carbon::now()->format('U');

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

    /**
     * Clear DataSet cache
     */
    public function clearCache()
    {
        $this->getLog()->debug('Force sync detected, clear cache for remote dataSet ID ' . $this->dataSetId);
        $this->pool->deleteItem('/dataset/cache/' . $this->dataSetId);
    }

    private function getScriptPath(): string
    {
        return $this->config->getSetting('LIBRARY_LOCATION')
            . 'data_connectors' . DIRECTORY_SEPARATOR
            . 'dataSet_' . $this->dataSetId . '.js';
    }

    public function getScript(): string
    {
        if ($this->isRealTime == 0) {
            return '';
        }

        $path = $this->getScriptPath();
        return (file_exists($path))
            ? file_get_contents($path)
            : '';
    }

    public function saveScript(string $script): void
    {
        if ($this->isRealTime == 1) {
            $path = $this->getScriptPath();
            file_put_contents($path, $script);
            file_put_contents($path . '.md5', md5_file($path));
        }
    }
}
