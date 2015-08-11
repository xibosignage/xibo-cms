<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DataSet;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class DataSetFactory extends BaseFactory
{
    /**
     * Get DataSets by ID
     * @param $dataSetId
     * @return DataSet
     * @throws NotFoundException
     */
    public static function getById($dataSetId)
    {
        $dataSets = DataSetFactory::query(null, ['disableUserCheck' => 1, 'dataSetId' => $dataSetId]);

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
    public static function getByName($dataSet)
    {
        $dataSets = DataSetFactory::query(null, ['disableUserCheck' => 1, 'dataSet' => $dataSet]);

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
    public static function query($sortOrder = null, $filterBy = null)
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
            self::viewPermissionSql('Xibo\Entity\DataSet', $body, $params, '`dataset`.dataSetId', '`dataset`.userId', $filterBy);

            if (Sanitize::getInt('dataSetId', $filterBy) !== null) {
                $body .= ' AND dataset.dataSetId = :dataSetId ';
                $params['dataSetId'] = Sanitize::getInt('dataSetId', $filterBy);
            }

            if (Sanitize::getString('dataSet', $filterBy) != null) {
                $body .= ' AND dataset.dataSet = :dataSet ';
                $params['dataSet'] = Sanitize::getString('dataSet', $filterBy);
            }

            // Sorting?
            $order = '';
            if (is_array($sortOrder))
                $order .= 'ORDER BY ' . implode(',', $sortOrder);

            $limit = '';
            // Paging
            if (Sanitize::getInt('start', $filterBy) !== null && Sanitize::getInt('length', $filterBy) !== null) {
                $limit = ' LIMIT ' . intval(Sanitize::getInt('start'), 0) . ', ' . Sanitize::getInt('length', 10);
            }

            $sql = $select . $body . $order . $limit;

            Log::sql($sql, $params);

            foreach (PDOConnect::select($sql, $params) as $row) {
                $entries[] = (new DataSet())->hydrate($row);
            }

            // Paging
            if ($limit != '' && count($entries) > 0) {
                $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
                self::$_countLast = intval($results[0]['total']);
            }

            return $entries;

        } catch (\Exception $e) {

            Log::error($e);

            throw new NotFoundException();
        }
    }
}