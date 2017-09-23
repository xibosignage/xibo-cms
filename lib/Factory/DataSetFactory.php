<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetFactory.php)
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
     * @return DataSetRemote
     */
    public function createEmptyRemote()
    {
        return new DataSetRemote(
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
     * @return DataSet
     * @throws NotFoundException
     */
    public function getByName($dataSet)
    {
        $dataSets = $this->query(null, ['disableUserCheck' => 1, 'dataSet' => $dataSet]);

        if (count($dataSets) <= 0)
            throw new NotFoundException();

        return $dataSets[0];
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[DataSet]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();
        $params = array();

        try {

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

            if ($this->getSanitizer()->getString('dataSet', $filterBy) != null) {
                $body .= ' AND dataset.dataSet = :dataSet ';
                $params['dataSet'] = $this->getSanitizer()->getString('dataSet', $filterBy);
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
                $id = $this->getDataSetIdFromRow($row);
                if ($this->isRemoteDataSet($id)) {
                    $row = $this->extendRemoteRow($row);
                    $entries[] = $this->createEmptyRemote()->hydrate($row);
                } else {
                    $entries[] = $this->createEmpty()->hydrate($row);
                }
            }

            // Paging
            if ($limit != '' && count($entries) > 0) {
                unset($params['groupsWithPermissionsEntity']);
                $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
                $this->_countLast = intval($results[0]['total']);
            }

            return $entries;

        } catch (\Exception $e) {

            $this->getLog()->error($e);

            throw new NotFoundException();
        }
    }
    
    /**
     * Extends a DataSet row with values from the DataSetRemote Table
     * @param array $row The row to extend
     * @return array the extended row
     */
    protected function extendRemoteRow(array $row = [])
    {
        $params = array('dataSetId' => $this->getDataSetIdFromRow($row));
        $sql = 'SELECT * FROM datasetremote WHERE DataSetID = :dataSetId;';
        foreach ($this->getStore()->select($sql, $params) as $data) {
            $row = array_merge($data, $row);
            break;
        }
        return $row;
    }
    
    /**
     * Returns the DataSetId from a Row if existing, otherwise '0'
     * @param array $row
     * @return int
     */
    private function getDataSetIdFromRow(array $row = null)
    {
        if ($row == null || !array_key_exists('dataSetId', $row)) {
            return 0;
        }
        return intval($row['dataSetId']);
    }
    
    /**
     * Returns if the given DataSetId is from a Remote DataSet or not
     * @param int $checkId
     * @return boolean
     */
    public function isRemoteDataSet($checkId = 0)
    {
        if ($checkId <= 0) {
            return false;
        }
        $params = array('dataSetId' => $checkId);
        $sql = 'SELECT datasetremote.DataSetID FROM datasetremote WHERE datasetremote.DataSetID = :dataSetId;';
        return $this->getStore()->exists($sql, $params);
    }
}