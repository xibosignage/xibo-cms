<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015-2017 Spring Signage Ltd
 * contributions by LukyLuke aka Lukas Zurschmiede - https://github.com/LukyLuke
 *
 * (DataSetFactory.php) This file is part of Xibo.
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
 *
 */
namespace Xibo\Factory;


use Xibo\Entity\DataSet;
use Xibo\Entity\DataSetRemote;
use Xibo\Exception\NotFoundException;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class DataSetFactory
 * @package Xibo\Factory
 */
class DataSetFactory extends BaseFactory
{
    /** @var  ConfigServiceInterface */
    private $config;

    /** @var  DataSetColumnFactory */
    private $dataSetColumnFactory;

    /** @var  PermissionFactory */
    private $permissionFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Entity\User $user
     * @param UserFactory $userFactory
     * @param ConfigServiceInterface $config
     * @param DataSetColumnFactory $dataSetColumnFactory
     * @param PermissionFactory $permissionFactory
     * @param DisplayFactory $displayFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $config, $dataSetColumnFactory, $permissionFactory, $displayFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);
        $this->config = $config;
        $this->dataSetColumnFactory = $dataSetColumnFactory;
        $this->permissionFactory = $permissionFactory;
        $this->displayFactory = $displayFactory;
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
            $this->getSanitizer(),
            $this->config,
            $this,
            $this->dataSetColumnFactory,
            $this->permissionFactory,
            $this->displayFactory
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

        if (count($dataSets) <= 0)
            throw new NotFoundException();

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

        if (count($dataSets) <= 0)
            throw new NotFoundException();

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
        $dataSets = $this->query(null, ['dataSet' => $dataSet, 'userId' => $userId]);

        if (count($dataSets) <= 0)
            throw new NotFoundException();

        return $dataSets[0];
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[DataSet]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = array();
        $params = array();

        $select  = '
          SELECT dataset.dataSetId,
            dataset.dataSet,
            dataset.description,
            dataset.userId,
            dataset.lastDataEdit,
        ';

        if (DBVERSION > 122) {
            $select .= '
                dataset.`code`,
                dataset.`isLookup`,
            ';
        }

        if (DBVERSION > 134) {
            $select .= '
                dataset.`isRemote`,
                dataset.`method`,
                dataset.`uri`,
                dataset.`postData`,
                dataset.`authentication`,
                dataset.`username`,
                dataset.`password`,
                dataset.`refreshRate`,
                dataset.`clearRate`,
                dataset.`runsAfter`,
                dataset.`dataRoot`,
                dataset.`summarize`,
                dataset.`summarizeField`,
            ';
        }

        $select .= '
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

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\DataSet', $body, $params, '`dataset`.dataSetId', '`dataset`.userId', $filterBy);

        if ($this->getSanitizer()->getInt('dataSetId', $filterBy) !== null) {
            $body .= ' AND dataset.dataSetId = :dataSetId ';
            $params['dataSetId'] = $this->getSanitizer()->getInt('dataSetId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('userId', $filterBy) !== null) {
            $body .= ' AND dataset.userId = :userId ';
            $params['userId'] = $this->getSanitizer()->getInt('userId', $filterBy);
        }

        if ($this->getSanitizer()->getString('dataSet', $filterBy) != null) {
        // convert into a space delimited array
            $names = explode(' ', $this->getSanitizer()->getString('dataSet', $filterBy));

            $i = 0;
            foreach($names as $searchName)
            {
                $i++;

                // Ignore if the word is empty
                if($searchName == '')
                  continue;

                // Not like, or like?
                if (substr($searchName, 0, 1) == '-') {
                    $body.= " AND  `dataset`.dataSet NOT LIKE :search$i ";
                    $params['search' . $i] = '%' . ltrim($searchName) . '%';
                }
                else {
                    $body.= " AND  `dataset`.dataSet LIKE :search$i ";
                    $params['search' . $i] = '%' . $searchName . '%';
                }
            }
        }

        if ($this->getSanitizer()->getString('code', $filterBy) != null) {
            $body .= ' AND `dataset`.`code` = :code ';
            $params['code'] = $this->getSanitizer()->getString('code', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, [
                'intProperties' => ['isLookup', 'isRemote']
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
     * @param \Xibo\Entity\DataSet $dataSet The Dataset to get Data for
     * @param \Xibo\Entity\DataSet|null $dependant The Dataset $dataSet depends on
     * @return \stdClass{entries:[],number:int}
     */
    public function callRemoteService(DataSet $dataSet, DataSet $dependant = null)
    {
        $result = new \stdClass();
        $result->entries = [];
        $result->number = 0;
        
        // Getting all dependant values if needed
        // just an empty array if no fields are used in the URI or PostData
        $values = [
            []
        ];

        if ($dependant != null && $dataSet->containsDependatFieldsInRequest()) {
            $values = $dependant->getData();
        }
        
        // Fetching data for every field in the dependant dataSet
        // TODO: switch to Guzzle for this and add proxy support.
        foreach ($values as $options) {
            $curl = curl_init();
            curl_setopt_array($curl, $dataSet->getCurlParams($options));
            $content = curl_exec($curl);
            $error = curl_errno($curl) . ' ' . curl_error($curl);
            curl_close($curl);

            if ($content !== false) {
                $result->entries[] = json_decode($content);
                $result->number = $result->number + 1;
            } else {
                $this->getLog()->error($error);
            }
        }
        
        return $result;
    }
}
