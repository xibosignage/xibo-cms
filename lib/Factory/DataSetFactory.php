<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\Factory;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\DataSet;
use Xibo\Entity\DataSetColumn;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Environment;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class DataSetFactory
 * @package Xibo\Factory
 */
class DataSetFactory extends BaseFactory
{
    /** @var  ConfigServiceInterface */
    private $config;

    /** @var PoolInterface */
    private $pool;

    /** @var  DataSetColumnFactory */
    private $dataSetColumnFactory;

    /** @var  PermissionFactory */
    private $permissionFactory;

    /** @var DisplayNotifyServiceInterface */
    private $displayNotifyService;

    /**
     * Construct a factory
     * @param \Xibo\Entity\User $user
     * @param UserFactory $userFactory
     * @param ConfigServiceInterface $config
     * @param PoolInterface $pool
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param PermissionFactory $permissionFactory
     * @param DisplayNotifyServiceInterface $displayNotifyService
     */
    public function __construct($user, $userFactory, $config, $pool, $dataSetColumnFactory, $permissionFactory, $displayNotifyService)
    {
        $this->setAclDependencies($user, $userFactory);
        $this->config = $config;
        $this->pool = $pool;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
        $this->permissionFactory = $permissionFactory;
        $this->displayNotifyService = $displayNotifyService;
    }

    /**
     * @return DataSetColumnFactory
     */
    public function getDataSetColumnFactory()
    {
        return $this->dataSetColumnFactory;
    }

    /**
     * @return DataSet
     */
    public function createEmpty()
    {
        return new DataSet(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher(),
            $this->getSanitizerService(),
            $this->config,
            $this->pool,
            $this,
            $this->dataSetColumnFactory,
            $this->permissionFactory,
            $this->displayNotifyService
        );
    }

    /**
     * Get DataSets by ID
     * @param $dataSetId
     * @return DataSet
     * @throws NotFoundException
     */
    public function getById($dataSetId)
    {
        $dataSets = $this->query(null, ['disableUserCheck' => 1, 'dataSetId' => $dataSetId]);

        if (count($dataSets) <= 0) {
            throw new NotFoundException();
        }

        return $dataSets[0];
    }

    /**
     * Get DataSets by Code
     * @param $code
     * @return DataSet
     * @throws NotFoundException
     */
    public function getByCode($code)
    {
        $dataSets = $this->query(null, ['disableUserCheck' => 1, 'code' => $code]);

        if (count($dataSets) <= 0) {
            throw new NotFoundException();
        }

        return $dataSets[0];
    }

    /**
     * Get DataSets by Name
     * @param $dataSet
     * @param int|null $userId the userId
     * @return DataSet
     * @throws NotFoundException
     */
    public function getByName($dataSet, $userId = null)
    {
        $dataSets = $this->query(null, ['dataSetExact' => $dataSet, 'userId' => $userId]);

        if (count($dataSets) <= 0) {
            throw new NotFoundException();
        }

        return $dataSets[0];
    }

    /**
     * @param $userId
     * @return DataSet[]
     * @throws NotFoundException
     */
    public function getByOwnerId($userId)
    {
        $dataSets = $this->query(null, ['disableUserCheck' => 1, 'userId' => $userId]);

        return $dataSets;
    }

    /**
     * @param int $folderId
     * @return DataSet[]
     * @throws NotFoundException
     */
    public function getByFolderId(int $folderId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'folderId' => $folderId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[DataSet]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = [];
        $params = [];
        $parsedFilter = $this->getSanitizer($filterBy);

        if ($sortOrder === null) {
            $sortOrder = ['dataSet'];
        }

        $select  = '
          SELECT dataset.dataSetId,
            dataset.dataSet,
            dataset.description,
            dataset.userId,
            dataset.lastDataEdit,
            dataset.`code`,
            dataset.`isLookup`,
            dataset.`isRemote`,
            dataset.`isRealTime`,
            dataset.`dataConnectorSource`,
            dataset.`method`,
            dataset.`uri`,
            dataset.`postData`,
            dataset.`authentication`,
            dataset.`username`,
            dataset.`password`,
            dataset.`customHeaders`,
            dataset.`userAgent`,
            dataset.`refreshRate`,
            dataset.`clearRate`,
            dataset.`truncateOnEmpty`,
            dataset.`runsAfter`,
            dataset.`dataRoot`,
            dataset.`summarize`,
            dataset.`summarizeField`,
            dataset.`lastSync`,
            dataset.`lastClear`,
            dataset.`sourceId`,
            dataset.`ignoreFirstRow`,
            dataset.`rowLimit`,
            dataset.`limitPolicy`,
            dataset.`csvSeparator`,
            dataset.`folderId`,
            dataset.`permissionsFolderId`,
            user.userName AS owner,
            (
              SELECT GROUP_CONCAT(DISTINCT `group`.group)
                  FROM `permission`
                    INNER JOIN `permissionentity`
                    ON `permissionentity`.entityId = permission.entityId
                    INNER JOIN `group`
                    ON `group`.groupId = `permission`.groupId
                 WHERE entity = :groupsWithPermissionsEntity
                    AND objectId = dataset.dataSetId
            ) AS groupsWithPermissions
        ';

        $params['groupsWithPermissionsEntity'] = 'Xibo\\Entity\\DataSet';

        $body = '
              FROM dataset
               INNER JOIN `user` ON user.userId = dataset.userId
             WHERE 1 = 1
        ';

        if ($parsedFilter->getInt('dataSetId') !== null) {
            $body .= ' AND dataset.dataSetId = :dataSetId ';
            $params['dataSetId'] = $parsedFilter->getInt('dataSetId');
        }

        if ($parsedFilter->getInt('userId') !== null) {
            $body .= ' AND dataset.userId = :userId ';
            $params['userId'] = $parsedFilter->getInt('userId');
        }

        if ($parsedFilter->getInt('isRemote') !== null) {
            $body .= ' AND dataset.isRemote = :isRemote ';
            $params['isRemote'] = $parsedFilter->getInt('isRemote');
        }

        if ($parsedFilter->getInt('isRealTime') !== null) {
            $body .= ' AND dataset.isRealTime = :isRealTime ';
            $params['isRealTime'] = $parsedFilter->getInt('isRealTime');
        }

        if ($parsedFilter->getString('dataSet') != null) {
            $terms = explode(',', $parsedFilter->getString('dataSet'));
            $logicalOperator = $parsedFilter->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'dataset',
                'dataSet',
                $terms,
                $body,
                $params,
                ($parsedFilter->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
        }

        if ($parsedFilter->getString('dataSetExact') != '') {
            $body.= " AND dataset.dataSet = :exact ";
            $params['exact'] = $parsedFilter->getString('dataSetExact');
        }

        if ($parsedFilter->getString('code') != null) {
            $body .= ' AND `dataset`.`code` = :code ';
            $params['code'] = $parsedFilter->getString('code');
        }

        if ($parsedFilter->getInt('folderId') !== null) {
            $body .= ' AND dataset.folderId = :folderId ';
            $params['folderId'] = $parsedFilter->getInt('folderId');
        }

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\DataSet', $body, $params, '`dataset`.dataSetId', '`dataset`.userId', $filterBy, '`dataset`.permissionsFolderId');

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $parsedFilter->getInt('start') !== null && $parsedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $parsedFilter->getInt('start', ['default' => 0]) . ', ' . $parsedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, [
                'intProperties' => [
                    'isLookup',
                    'isRemote',
                    'isRealTime',
                    'clearRate',
                    'refreshRate',
                    'lastDataEdit',
                    'runsAfter',
                    'lastSync',
                    'lastClear',
                    'ignoreFirstRow',
                ],
            ]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            unset($params['groupsWithPermissionsEntity']);
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }

    /**
     * Makes a call to a Remote Dataset and returns all received data as a JSON decoded Object.
     * In case of an Error, null is returned instead.
     * @param DataSet $dataSet The Dataset to get Data for
     * @param DataSet|null $dependant The Dataset $dataSet depends on
     * @param bool $enableCaching Should we cache check the results and store the resulting cache
     * @return \stdClass {entries:[], number:int, isEligibleToTruncate:bool}
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function callRemoteService(DataSet $dataSet, DataSet $dependant = null, $enableCaching = true)
    {
        $this->getLog()->debug('Calling remote service for DataSet: ' . $dataSet->dataSet . ' and URL ' . $dataSet->uri);

        // Record our max memory
        $maxMemory = Environment::getMemoryLimitBytes() / 2;

        // Guzzle for this and add proxy support.
        $client = new Client($this->config->getGuzzleProxy());

        $result = new \stdClass();
        $result->entries = [];
        $result->number = 0;
        $result->isEligibleToTruncate = false;

        // Getting all dependant values if needed
        // just an empty array if we don't have a dependent
        $values = [
            []
        ];

        if ($dependant != null && $dataSet->containsDependantFieldsInRequest()) {
            $this->getLog()->debug('Dependant provided with fields in the request.');

            $values = $dependant->getData();
        }

        // Fetching data for every field in the dependant dataSet
        foreach ($values as $options) {
            // Make some request params to provide to the HTTP client
            $resolvedUri = $this->replaceParams($dataSet->uri, $options);
            $requestParams = [];

            // Auth
            switch ($dataSet->authentication) {
                case 'basic':
                    $requestParams['auth'] = [$dataSet->username, $dataSet->password];
                    break;

                case 'digest':
                    $requestParams['auth'] = [$dataSet->username, $dataSet->password, 'digest'];
                    break;

                case 'ntlm':
                    $requestParams['auth'] = [$dataSet->username, $dataSet->password, 'ntlm'];
                    break;

                case 'bearer':
                    $requestParams['headers'] = ['Authorization' => 'Bearer ' . $dataSet->password];
                    break;

                case 'none':
                default:
                    $this->getLog()->debug('No authentication required');
            }

            if (isset($dataSet->customHeaders)) {
                $arrayOfCustomHeaders = array_filter(explode(',', $dataSet->customHeaders));

                foreach ($arrayOfCustomHeaders as $customHeader) {
                    $header = array_filter(explode(':', $customHeader));
                    $requestParams['headers'][$header[0]] = $header[1];
                }
            }

            // Post request?
            if ($dataSet->method === 'POST') {
                parse_str($this->replaceParams($dataSet->postData, $options), $requestParams['form_params']);
            } else {
                // Get the query params from the URL.
                $queryParamsArray = [];
                $dataSetPostData = [];
                $parsedUrl = parse_url($resolvedUri, PHP_URL_QUERY);
                if ($parsedUrl) {
                    parse_str($parsedUrl, $queryParamsArray);
                }
                parse_str($this->replaceParams($dataSet->postData, $options), $dataSetPostData);
                $requestParams['query'] = array_merge($queryParamsArray, $dataSetPostData);
            }

            if (!empty($dataSet->userAgent)) {
                $requestParams['headers']['User-Agent'] = trim($dataSet->userAgent);
            }

            $this->getLog()->debug('Making request to ' . $resolvedUri . ' with params: ' . var_export($requestParams, true));

            try {
                // Make a HEAD request to the URI and see if we are able to process this.
                if ($dataSet->method === 'GET') {
                    try {
                        $request = $client->head($resolvedUri, $requestParams);

                        $contentLength = $request->getHeader('Content-Length');
                        if ($maxMemory > 0 && count($contentLength) > 0 && $contentLength[0] > $maxMemory)
                            throw new InvalidArgumentException(__('The request %d is too large to fit inside the configured memory limit. %d', $contentLength[0], $maxMemory), 'contentLength');
                    } catch (RequestException $requestException) {
                        $this->getLog()->info('Cannot make head request for remote dataSet ' . $dataSet->dataSetId);
                    }
                }

                $request = $client->request($dataSet->method, $resolvedUri, $requestParams);

                // Check the cache control situation
                if ($enableCaching) {
                    // recache if necessary
                    $cacheControlKey = $this->pool->getItem('/dataset/cache/' . $dataSet->dataSetId . '/' . md5($resolvedUri . json_encode($requestParams)));
                    $cacheControlKeyValue = ($cacheControlKey->isMiss()) ? '' : $cacheControlKey->get();

                    $this->getLog()->debug('Cache Control Key is ' . $cacheControlKeyValue);

                    $etags = $request->getHeader('E-Tag');
                    $lastModifieds = $request->getHeader('Last-Modified');

                    if (count($etags) > 0) {
                        // Compare the etag with the cache key and see if they are the same, if they are
                        // then we stop processing this data set
                        if ($cacheControlKeyValue === $etags[0]) {
                            $this->getLog()->debug('Skipping due to eTag');
                            continue;
                        }

                        $cacheControlKeyValue = $etags[0];
                    } else if (count($lastModifieds) > 0) {
                        if ($cacheControlKeyValue === $lastModifieds[0]) {
                            $this->getLog()->debug('Skipping due to Last-Modified');
                            continue;
                        }

                        $cacheControlKeyValue = $lastModifieds[0];
                    } else {
                        // Request doesn't have any cache control of its own
                        // use the md5
                        $md5 = md5($request->getBody());

                        // Rewind so we can use it again
                        $request->getBody()->rewind();

                        if ($cacheControlKeyValue === $md5) {
                            $this->getLog()->debug('Skipping due to MD5');
                            continue;
                        }

                        $cacheControlKeyValue = $md5;
                    }

                    $this->getLog()->debug('Cache Control Key is now ' . $cacheControlKeyValue);

                    // Store the cache key
                    $cacheControlKey->set($cacheControlKeyValue);
                    $cacheControlKey->expiresAfter(86400 * 365);
                    $this->pool->saveDeferred($cacheControlKey);
                }

                // We have passed any caching and therefore expect results
                $result->isEligibleToTruncate = true;

                if ($dataSet->sourceId === 1) {
                    // Make sure we have JSON in the response
                    $body = $request->getBody()->getContents();

                    try {
                        $json = \GuzzleHttp\json_decode($body);
                    } catch (\GuzzleHttp\Exception\InvalidArgumentException $invalidArgumentException) {
                        $this->getLog()->debug('JSON decode error: ' . $invalidArgumentException->getMessage());
                        throw new InvalidArgumentException(__('Unable to get Data for %s because the response was not valid JSON.', $dataSet->dataSet), 'url');
                    }

                    $result->entries[] = $json;
                    foreach ($result->entries as $entry) {
                        $data = $this->getDataRootFromResult($dataSet->dataRoot, $entry);

                        if (is_array($data)) {
                            $result->number = count($data);
                        } elseif (is_object($data)) {
                            $result->number = count(get_object_vars($data));
                        }
                    }
                } else {
                    $csv = $request->getBody()->getContents();
                    $array = array_map(
                        function ($v) use ($dataSet) {
                            return str_getcsv($v, $dataSet->csvSeparator ?? ',');
                        },
                        explode("\n", $csv)
                    );

                    if ($dataSet->ignoreFirstRow == 1) {
                        array_shift($array);
                    }

                    // Filter out rows that are entirely empty
                    $array = array_filter($array, function($row) {
                        // Check if the row is empty (all elements are empty or null)
                        return array_filter($row, function($value) {
                            return !empty($value);
                        });
                    });

                    $result->entries = $array;
                    $result->number = count($array);
                }
            } catch (RequestException $requestException) {
                $this->getLog()->error('Error making request. ' . $requestException->getMessage());

                // No point in carrying on through this stack of requests, dependent or original data will be
                // missing
                throw new InvalidArgumentException(__('Unable to get Data for %s because %s.', $dataSet->dataSet, $requestException->getMessage()), 'dataSetId');
            }
        }

        return $result;
    }

    /**
     * Replaces all URI/PostData parameters
     * @param string String to replace {{DATE}}, {{TIME}} and {{COL.xxx}}
     * @param array $values ColumnValues to use on {{COL.xxx}} parts
     * @return string
     */
    private function replaceParams($string = '', array $values = [])
    {
        if (empty($string)) {
            return $string;
        }

        $string = str_replace('{{DATE}}', date('Y-m-d'), $string);
        $string = str_replace('%7B%7BDATE%7D%7D', date('Y-m-d'), $string);
        $string = str_replace('{{TIME}}', date('H:m:s'), $string);
        $string = str_replace('%7B%7BTIME%7D%7D', date('H:m:s'), $string);

        foreach ($values as $k => $v) {
            $string = str_replace('{{COL.' . $k . '}}', urlencode($v), $string);
            $string = str_replace('%7B%7BCOL.' . $k . '%7D%7D', urlencode($v), $string);
        }

        return $string;
    }

    /**
     * Tries to process received Data against the configured DataSet with all Columns
     *
     * @param DataSet $dataSet The RemoteDataset to process
     * @param \stdClass $results A simple Object with one Property 'entries' which contains all results
     * @param bool $save
     * @throws InvalidArgumentException
     */
    public function processResults(DataSet $dataSet, \stdClass $results, $save = true)
    {
        $results->processed = [];

        if (property_exists($results, 'entries') && is_array($results->entries)) {
            // Load the DataSet fully
            $dataSet->load();

            $results->messages = [__('Processing %d results into %d potential columns', count($results->entries), count($dataSet->columns))];

            foreach ($results->entries as $result) {
                $results->messages[] = __('Processing Result with Data Root %s', $dataSet->dataRoot);

                // Remote Data has to have the configured DataRoot which has to be an Array
                $data = $this->getDataRootFromResult($dataSet->dataRoot, $result);

                $columns = $dataSet->columns;
                $entries = [];

                // Process the data root according to its type
                if (is_array($data)) {
                    // An array of results as the DataRoot
                    $results->messages[] = 'DataRoot is an array';

                    // First process each entry form the remote and try to map the values to the configured columns
                    foreach ($data as $k => $entry) {
                        $this->getLog()->debug('Processing key ' . $k . ' from the remote results');
                        $this->getLog()->debug('Entry is: ' . var_export($entry, true));

                        $results->messages[] = 'Processing ' . $k;

                        if (is_array($entry) || is_object($entry)) {
                            $entries[] = $this->processEntry((array)$entry, $columns);
                        } else {
                            $this->getLog()->error('DataSet ' . $dataSet->dataSet . ' failed: DataRoot ' . $dataSet->dataRoot . ' contains data which is not arrays or objects.');
                            break;
                        }
                    }
                } else if (is_object($data)) {
                    // An object as the DataRoot.
                    $results->messages[] = 'DataRoot is an object';

                    // We should treat this as a single row? Or as multiple rows?
                    // we could try and guess from the configuration of the dataset columns
                    $singleRow = false;
                    foreach ($columns as $column) {
                        if ($column->dataSetColumnTypeId === 3 && $column->remoteField != null && !is_numeric($column->remoteField)) {
                            $singleRow = true;
                            break;
                        }
                    }

                    if ($singleRow) {
                        // Process as a single row
                        $results->messages[] = __('Processing as a Single Row');

                        $entries[] = $this->processEntry((array)$data, $columns);
                    } else {
                        // Process as multiple rows
                        $results->messages[] = __('Processing as Multiple Rows');

                        foreach (get_object_vars($data) as $property => $value) {
                            // Treat each property as an index key (flattening the array)
                            $results->messages[] = 'Processing ' . $property;

                            $entries[] = $this->processEntry([$property, $value], $columns);
                        }
                    }
                } else {
                    throw new InvalidArgumentException(__('No data found at the DataRoot %s', $dataSet->dataRoot), 'dataRoot');
                }

                $results->messages[] = __('Consolidating entries');

                // If there is a Consolidation-Function, use the Data against it
                $entries = $this->consolidateEntries($dataSet, $entries, $columns);

                $results->messages[] = __('There are %d entries in total', count($entries));

                // Finally add each entry as a new Row in the DataSet
                if ($save) {
                    foreach ($entries as $entry) {
                        $dataSet->addRow($entry);
                    }
                }

                $results->processed[] = $entries;
            }
        }
    }

    /**
     * Process the RemoteResult to get the main DataRoot value which can be stay in a structure as well as the values
     *
     * @param String Chunks split by a Dot where the main entries are hold
     * @param array|\stdClass The Value from the remote request
     * @return array|\stdClass The Data hold in the configured dataRoot
     */
    private function getDataRootFromResult($dataRoot, $result)
    {
        $this->getLog()->debug('Getting ' . $dataRoot . 'from result.');

        if (empty($dataRoot)) {
            return $result;
        }
        $chunks = explode('.', $dataRoot);
        $entries = $this->getFieldValueFromEntry($chunks, $result);
        return $entries[1];
    }

    /**
     * Process a single Data-Entry form the remote system and map it to the configured Columns
     *
     * @param array $entry The Data from the remote system
     * @param DataSetColumn[] $dataSetColumns The configured Columns form the current DataSet
     * @return array The processed $entry as a List of Fields from $columns
     */
    private function processEntry(array $entry, array $dataSetColumns)
    {
        $result = [];

        foreach ($dataSetColumns as $column) {
            if ($column->dataSetColumnTypeId === 3 && $column->remoteField != null) {
                $this->getLog()->debug('Trying to match dataSetColumn ' . $column->heading . ' with remote field ' . $column->remoteField);

                // The Field may be a Date, timestamp or a real field
                if ($column->remoteField == '{{DATE}}') {
                    $value = [0, date('Y-m-d')];
                } else if ($column->remoteField == '{{TIMESTAMP}}') {
                    $value = [0, Carbon::now()->format('U')];
                } else {
                    $chunks = explode('.', $column->remoteField);
                    $value = $this->getFieldValueFromEntry($chunks, $entry);
                }

                $this->getLog()->debug('Resolved value: ' . var_export($value, true));

                // Only add it to the result if we were able to process the field
                if ($value != null && $value[1] !== null) {
                    // 1,String
                    // 2,Number
                    // 3,Date
                    // 4,External Image
                    // 5,Library Image
                    // 6,HTML
                    $validator = $this->getValidator();

                    switch ($column->dataTypeId) {
                        case 2:
                            // Number
                            if (empty($value[1]) || !($validator->double($value[1]) || $validator->int($value[1]))) {
                                $result[$column->heading] = 0;
                            } else {
                                $result[$column->heading] = doubleval($value[1]);
                            }
                            break;
                        case 3:
                            // Date
                            // This expects an ISO date
                            // check if we were provided with custom dateFormat
                            $dateFormat = $column->dateFormat ?: DateFormatHelper::getSystemFormat();

                            try {
                                // Parse into a date object from any format, and then save using the system format
                                $result[$column->heading] = Carbon::createFromFormat($dateFormat, $value[1])
                                    ->format(DateFormatHelper::getSystemFormat());
                            } catch (\Exception $e) {
                                $this->getLog()->error(
                                    sprintf(
                                        'Incorrect date provided %s, expected date format %s',
                                        $value[1],
                                        $dateFormat
                                    )
                                );
                            }

                            break;
                        case 5:
                            // Library Image
                            if (empty($value[1]) || !$validator->int($value[1])) {
                                $result[$column->heading] = 0;
                            } else {
                                $result[$column->heading] = intval($value[1]);
                            }
                            break;
                        case 6:
                            // HTML, without any sanitization
                            $result[$column->heading] = $value[1];
                            break;
                        default:
                            // Default value, assume it will be a string and filter it accordingly.
                            $result[$column->heading] = strip_tags($value[1]);
                    }
                }
            } else {
                $this->getLog()->debug('Column not matched');
            }
        }

        return $result;
    }

    /**
     * Returns the Value of the remote DataEntry based on the remoteField definition split into chunks
     *
     * This function is recursive, so be sure you remove the first value from chunks and pass it in again
     *
     * @param array List of Chunks which interprets the FieldNames in the actual DataEntry
     * @param array|\stdClass $entry Current DataEntry
     * @return array of the last FieldName and the corresponding value
     */
    private function getFieldValueFromEntry(array $chunks, $entry)
    {
        $value = null;
        $key = array_shift($chunks);

        $this->getLog()->debug('Entry: ' . var_export($entry, true));
        $this->getLog()->debug('Looking for key: ' . $key . '. Chunks: ' . var_export($chunks, true));

        if (($entry instanceof \stdClass) && property_exists($entry, $key)) {
            $value = $entry->{$key};
        } else if (array_key_exists($key, $entry)) {
            $value = $entry[$key];
        }

        $this->getLog()->debug('Value found is: ' . var_export($value, true));

        if (($value != null) && (count($chunks) > 0)) {
            return $this->getFieldValueFromEntry($chunks, (array) $value);
        }

        return [ $key, $value ];
    }

    /**
     * Consolidates all Entries by the defined Function in the DataSet
     *
     * This Method *sums* or *counts* all same entries and returns them.
     * If no consolidation function is configured, nothing is done here.
     *
     * @param DataSet $dataSet the current DataSet
     * @param array $entries All processed entries which may be consolidated
     * @param array $columns The columns form this DataSet
     * @return array which contains all Entries to be added to the DataSet
     */
    private function consolidateEntries(DataSet $dataSet, array $entries, array $columns)
    {
        // Do we need to consolidate?
        if ((count($entries) > 0) && $dataSet->doConsolidate()) {
            // Yes
            $this->getLog()->debug('Consolidate Required on field ' . $dataSet->getConsolidationField());

            $consolidated = [];
            $field = $dataSet->getConsolidationField();

            // Get the Field-Heading based on the consolidation field
            foreach ($columns as $k => $column) {
                if ($column->remoteField == $dataSet->summarizeField) {
                    $field = $column->heading;
                    break;
                }
            }

            // Check each entry and consolidate the value form the defined field
            foreach ($entries as $entry) {
                if (array_key_exists($field, $entry)) {
                    $key = $field . '-' . $entry[$field];
                    $existing = (isset($consolidated[$key])) ? $consolidated[$key] : null;

                    // Create a new one if there is no currently consolidated field for this value
                    if ($existing == null) {
                        $existing = $entry;
                        $existing[$field] = 0;
                    }

                    // Consolidate: Summarize, Count, Unknown
                    if ($dataSet->summarize == 'sum') {
                        $existing[$field] = $existing[$field] + $entry[$field];

                    } else if ($dataSet->summarize == 'count') {
                        $existing[$field] = $existing[$field] + 1;

                    } else {
                        // Unknown consolidation type :?
                        $existing[$field] = 0;
                    }

                    $consolidated[$key] = $existing;
                }
            }

            return $consolidated;
        }

        return $entries;
    }

    public function processCsvEntries(DataSet $dataSet, \stdClass $results, $save = true)
    {
        $this->getLog()->debug('Processing CSV results');

        $dataSet->load();
        $entries = [];

        foreach ($results->entries as $entry) {
            $entries[] = $this->processEntry((array)$entry, $dataSet->columns);
        }

        $results->processed = $entries;

        if ($save) {
            foreach ($entries as $row) {
                $dataSet->addRow($row);
            }
        }
    }
}
