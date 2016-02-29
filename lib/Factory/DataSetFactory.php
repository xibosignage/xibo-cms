<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DataSet;
use Xibo\Exception\NotFoundException;

class DataSetFactory extends BaseFactory
{
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
            if ($this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
                $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start'), 0) . ', ' . $this->getSanitizer()->getInt('length', 10);
            }

            $sql = $select . $body . $order . $limit;



            foreach ($this->getStore()->select($sql, $params) as $row) {
                $entries[] = (new DataSet())->hydrate($row)->setApp($this->getApp())->setApp($this->getApp());
            }

            // Paging
            if ($limit != '' && count($entries) > 0) {
                $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
                $this->_countLast = intval($results[0]['total']);
            }

            return $entries;

        } catch (\Exception $e) {

            $this->getLog()->error($e);

            throw new NotFoundException();
        }
    }
}