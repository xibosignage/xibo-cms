<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayGroup.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DisplayGroup;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class DisplayGroupFactory extends BaseFactory
{
    /**
     * @param int $displayGroupId
     * @return DisplayGroup
     * @throws NotFoundException
     */
    public static function getById($displayGroupId)
    {
        $groups = DisplayGroupFactory::query(null, ['disableUserCheck' => 1, 'displayGroupId' => $displayGroupId, 'isDisplaySpecific' => -1]);

        if (count($groups) <= 0)
            throw new NotFoundException();

        return $groups[0];
    }

    /**
     * @param int $displayId
     * @return array[DisplayGroup]
     */
    public static function getByDisplayId($displayId)
    {
        return DisplayGroupFactory::query(null, ['disableUserCheck' => 1, 'displayId' => $displayId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get Display Groups by MediaId
     * @param int $mediaId
     * @return array[DisplayGroup]
     */
    public static function getByMediaId($mediaId)
    {
        return DisplayGroupFactory::query(null, ['disableUserCheck' => 1, 'mediaId' => $mediaId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get Display Groups by eventId
     * @param int $eventId
     * @return array[DisplayGroup]
     */
    public static function getByEventId($eventId)
    {
        return DisplayGroupFactory::query(null, ['disableUserCheck' => 1, 'eventId' => $eventId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get Display Groups by isDynamic
     * @param int $isDynamic
     * @return array[DisplayGroup]
     */
    public static function getByIsDynamic($isDynamic)
    {
        return DisplayGroupFactory::query(null, ['disableUserCheck' => 1, 'isDynamic' => $isDynamic]);
    }

    /**
     * Get Display Groups by their ParentId
     * @param int $parentId
     * @return array[DisplayGroup]
     */
    public static function getByParentId($parentId)
    {
        return DisplayGroupFactory::query(null, ['disableUserCheck' => 1, 'parentId' => $parentId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[DisplayGroup]
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        if ($sortOrder == null)
            $sortOrder = ['displayGroup'];

        $entries = [];
        $params = [];

        $select = '
            SELECT `displaygroup`.displayGroupId,
                `displaygroup`.displayGroup,
                `displaygroup`.isDisplaySpecific,
                `displaygroup`.description,
                `displaygroup`.isDynamic,
                `displaygroup`.dynamicCriteria
        ';

        $body = '
              FROM `displaygroup`
        ';

        if (Sanitize::getInt('mediaId', $filterBy) !== null) {
            $body .= '
                INNER JOIN lkmediadisplaygroup
                ON lkmediadisplaygroup.displayGroupId = `displaygroup`.displayGroupId
                    AND lkmediadisplaygroup.mediaId = :mediaId
            ';
            $params['mediaId'] = Sanitize::getInt('mediaId', $filterBy);
        }

        if (Sanitize::getInt('eventId', $filterBy) !== null) {
            $body .= '
                INNER JOIN `lkscheduledisplaygroup`
                ON `lkscheduledisplaygroup`.displayGroupId = `displaygroup`.displayGroupId
                    AND `lkscheduledisplaygroup`.eventId = :eventId
            ';
            $params['eventId'] = Sanitize::getInt('eventId', $filterBy);
        }

        $body .= ' WHERE 1 = 1 ';

        // View Permissions
        self::viewPermissionSql('Xibo\Entity\DisplayGroup', $body, $params, '`displaygroup`.displayGroupId', null, $filterBy);

        if (Sanitize::getInt('displayGroupId', $filterBy) !== null) {
            $body .= ' AND displaygroup.displayGroupId = :displayGroupId ';
            $params['displayGroupId'] = Sanitize::getInt('displayGroupId', $filterBy);
        }

        if (Sanitize::getInt('parentId', $filterBy) !== null) {
            $body .= ' AND `displaygroup`.displayGroupId IN (SELECT `childId` FROM `lkdgdg` WHERE `parentId` = :parentId AND `depth` = 1) ';
            $params['parentId'] = Sanitize::getInt('parentId', $filterBy);
        }

        if (Sanitize::getInt('isDisplaySpecific', 0, $filterBy) != -1) {
            $body .= ' AND displaygroup.isDisplaySpecific = :isDisplaySpecific ';
            $params['isDisplaySpecific'] = Sanitize::getInt('isDisplaySpecific', 0, $filterBy);
        }

        if (Sanitize::getInt('isDynamic', $filterBy) !== null) {
            $body .= ' AND `displaygroup`.isDynamic = :isDynamic ';
            $params['isDynamic'] = Sanitize::getInt('isDynamic', $filterBy);
        }

        if (Sanitize::getInt('displayId', $filterBy) !== null) {
            $body .= ' AND displaygroup.displayGroupId IN (SELECT displayGroupId FROM lkdisplaydg WHERE displayId = :displayId) ';
            $params['displayId'] = Sanitize::getInt('displayId', $filterBy);
        }

        // Filter by DisplayGroup Name?
        if (Sanitize::getString('displayGroup', $filterBy) != null) {
            // convert into a space delimited array
            $names = explode(' ', Sanitize::getString('displayGroup', $filterBy));

            $i = 0;
            foreach ($names as $searchName) {
                $i++;
                // Not like, or like?
                if (substr($searchName, 0, 1) == '-') {
                    $body .= " AND  `displaygroup`.displayGroup NOT LIKE :search$i ";
                    $params['search' . $i] = '%' . ltrim(($searchName), '-') . '%';
                }
                else {
                    $body .= " AND  `displaygroup`.displayGroup LIKE :search$i ";
                    $params['search' . $i] = '%' . $searchName . '%';
                }
            }
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
            $entries[] = (new DisplayGroup())->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}