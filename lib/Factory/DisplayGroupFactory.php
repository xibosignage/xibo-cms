<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayGroup.php)
 */


namespace Xibo\Factory;


class DisplayGroupFactory
{
    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[DisplayGroup]
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        $SQL = "SELECT displaygroup.DisplayGroupID, displaygroup.DisplayGroup, displaygroup.IsDisplaySpecific, displaygroup.Description ";
        //if ($isDisplaySpecific == 1)
            $SQL .= " , lkdisplaydg.DisplayID ";

        $SQL .= "  FROM displaygroup ";

        // If we are only interested in displays, then return the display
        //if ($isDisplaySpecific == 1) {
            $SQL .= "   INNER JOIN lkdisplaydg ";
            $SQL .= "   ON lkdisplaydg.DisplayGroupID = displaygroup.DisplayGroupID ";
        //}

        $SQL .= " WHERE 1 = 1 ";

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

        $SQL .= " ORDER BY displaygroup.DisplayGroup ";

        return [];
    }
}