<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayGroup.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DisplayGroup;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Sanitize;

class DisplayGroupFactory
{
    /**
     * @param int $displayGroupId
     * @return DisplayGroup
     * @throws NotFoundException
     */
    public static function getById($displayGroupId)
    {
        $groups = DisplayGroupFactory::query(null, ['displayGroupId' => $displayGroupId]);

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
        $displayGroups = DisplayGroupFactory::query(null, ['displayId' => $displayId, 'isDisplaySpecific' => -1]);

        foreach ($displayGroups as $displayGroup) {
            /* @var DisplayGroup $displayGroup */
            $displayGroup->assignDisplay($displayId);
        }

        return $displayGroups;
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[DisplayGroup]
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        $params = [];

        $sql = "SELECT displaygroup.DisplayGroupID, displaygroup.DisplayGroup, displaygroup.IsDisplaySpecific, displaygroup.Description ";
        //if ($isDisplaySpecific == 1)
            $sql .= " , lkdisplaydg.DisplayID ";

        $sql .= "  FROM displaygroup ";

        // If we are only interested in displays, then return the display
        //if ($isDisplaySpecific == 1) {
            $sql .= "   INNER JOIN lkdisplaydg ";
            $sql .= "   ON lkdisplaydg.DisplayGroupID = displaygroup.DisplayGroupID ";
        //}

        $sql .= " WHERE 1 = 1 ";

        if (Sanitize::getInt('displayGroupId', $filterBy) != null) {
            $sql .= ' AND displaygroup.displayGroupId = :displayGroupId ';
            $params['displayGroupId'] = Sanitize::getInt('displayGroupId', $filterBy);
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

        $sql .= " ORDER BY displaygroup.DisplayGroup ";

        return [];
    }
}