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

class DisplayGroupFactory
{
    /**
     * @param int $displayGroupId
     * @return DisplayGroup
     * @throws NotFoundException
     */
    public static function getById($displayGroupId)
    {
        $groups = DisplayGroupFactory::query(null, ['displayGroupId' => $displayGroupId, 'isDisplaySpecific' => -1]);

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
        return DisplayGroupFactory::query(null, ['displayId' => $displayId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get Display Groups by MediaId
     * @param int $mediaId
     * @return array[DisplayGroup]
     */
    public static function getByMediaId($mediaId)
    {
        return DisplayGroupFactory::query(null, ['mediaId' => $mediaId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get Display Groups by eventId
     * @param int $eventId
     * @return array[DisplayGroup]
     */
    public static function getByEventId($eventId)
    {
        return DisplayGroupFactory::query(null, ['eventId' => $eventId, 'isDisplaySpecific' => -1]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[DisplayGroup]
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = [];
        $params = [];

        $sql = '
            SELECT displaygroup.displayGroupId, displaygroup.displayGroup, displaygroup.isDisplaySpecific, displaygroup.description
              FROM `displaygroup`
        ';

        if (Sanitize::getInt('mediaId', $filterBy) != null) {
            $sql .= '
                INNER JOIN lkmediadisplaygroup
                ON lkmediadisplaygroup.displayGroupId = displayGroup.displayGroupId
                    AND lkmediadisplaygroup.mediaId = :mediaId
            ';
            $params['mediaId'] = Sanitize::getInt('mediaId', $filterBy);
        }

        if (Sanitize::getInt('eventId', $filterBy) != null) {
            $sql .= '
                INNER JOIN `lkscheduledisplaygroup`
                ON `lkscheduledisplaygroup`.displayGroupId = `displaygroup`.displayGroupId
                    AND `lkscheduledisplaygroup`.eventId = :eventId
            ';
            $params['eventId'] = Sanitize::getInt('eventId', $filterBy);
        }

        $sql .= ' WHERE 1 = 1 ';

        if (Sanitize::getInt('displayGroupId', $filterBy) != null) {
            $sql .= ' AND displaygroup.displayGroupId = :displayGroupId ';
            $params['displayGroupId'] = Sanitize::getInt('displayGroupId', $filterBy);
        }

        if (Sanitize::getInt('isDisplaySpecific', 0, $filterBy) != -1) {
            $sql .= ' AND displaygroup.isDisplaySpecific = :isDisplaySpecific ';
            $params['isDisplaySpecific'] = Sanitize::getInt('isDisplaySpecific', 0, $filterBy);
        }

        /*if ($name != '') {
            // convert into a space delimited array
            $names = explode(' ', $name);

            foreach ($names as $searchName) {
                // Not like, or like?
                if (substr($searchName, 0, 1) == '-')
                    $SQL .= " AND  (displaygroup.DisplayGroup NOT LIKE '%" . sprintf('%s', ltrim($db->escape_string($searchName), '-')) . "%') ";
                else
                    $SQL .= " AND  (displaygroup.DisplayGroup LIKE '%" . sprintf('%s', $db->escape_string($searchName)) . "%') ";
            }
        }*/

        //if ($isDisplaySpecific != -1)
        //    $SQL .= sprintf(" AND displaygroup.IsDisplaySpecific = %d ", $isDisplaySpecific);

        // Sorting?
        if (is_array($sortOrder))
            $sql .= 'ORDER BY ' . implode(',', $sortOrder);

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new DisplayGroup())->hydrate($row);
        }

        return $entries;
    }
}