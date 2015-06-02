<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (DisplayFactory.php) is part of Xibo.
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

use Xibo\Entity\Display;
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class DisplayFactory
{
    /**
     * @param int $displayId
     * @return Display
     * @throws NotFoundException
     */
    public static function getById($displayId)
    {
        $displays = DisplayFactory::query(null, ['displayId' => $displayId]);

        if (count($displays) <= 0)
            throw new NotFoundException();

        return $displays[0];
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Display]
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();
        $params = array();
        $SQL = '
              SELECT display.displayId,
                  display.display,
                  display.defaultLayoutId,
                  display.license,
                  display.licensed,
                  display.licensed AS currentlyLicenced,
                  display.loggedIn,
                  display.lastAccessed,
                  display.inc_schedule AS incSchedule,
                  display.email_alert AS emailAlert,
                  display.alert_timeout AS alertTimeout,
                  display.clientAddress,
                  display.mediaInventoryStatus,
                  display.mediaInventoryXml,
                  display.macAddress,
                  display.macAddress AS currentMacAddress,
                  display.lastChanged,
                  display.numberOfMacAddressChanges,
                  display.lastWakeOnLanCommandSent,
                  display.wakeOnLan AS wakeOnLanEnabled,
                  display.wakeOnLanTime,
                  display.broadCastAddress,
                  display.secureOn,
                  display.cidr,
                  X(display.GeoLocation) AS latitude,
                  Y(display.GeoLocation) AS longitude,
                  display.version_instructions AS versionInstructions,
                  display.client_type AS clientType,
                  display.client_version AS clientVersion,
                  display.client_code AS clientCode,
                  display.displayProfileId,
                  display.currentLayoutId,
                  currentLayout.layout AS currentLayout,
                  display.screenShotRequested,
                  display.storageAvailableSpace,
                  display.storageTotalSpace,
                  displaygroup.displayGroupId,
                  displaygroup.description
                FROM `display`
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.displayid = display.displayId
                    INNER JOIN `displaygroup`
                    ON displaygroup.displaygroupid = lkdisplaydg.displaygroupid
                        AND isdisplayspecific = 1
                    LEFT OUTER JOIN layout 
                    ON layout.layoutid = display.defaultlayoutid
                    LEFT OUTER JOIN layout currentLayout 
                    ON currentLayout.layoutId = display.currentLayoutId
               WHERE 1 = 1
            ';

        if (Sanitize::getInt('displayGroupId', $filterBy) != 0) {
            // Restrict to a specific display group
            $SQL .= ' AND displaygroup.displaygroupid = :displayGroupId ';
            $params['displayGroupId'] = Sanitize::getInt('displayGroupId', $filterBy);
        } else {
            // Restrict to display specific groups
            $SQL .= ' AND displaygroup.isDisplaySpecific = 1 ';
        }

        // Filter by Display ID?
        if (Sanitize::getInt('displayId', $filterBy) != 0) {
            $SQL .= ' AND display.displayid = :displayId ';
            $params['displayId'] = Sanitize::getInt('displayId', $filterBy);
        }

        // Filter by Display Name?
        if (Sanitize::getString('display', $filterBy) != '') {
            // convert into a space delimited array
            $names = explode(' ', Sanitize::getString('display', $filterBy));

            $i = 0;
            foreach ($names as $searchName) {
                $i++;
                // Not like, or like?
                if (substr($searchName, 0, 1) == '-') {
                    $SQL .= " AND  (display.display NOT LIKE :search$i ";
                    $params['search' . $i] = '%' . ltrim(($searchName), '-') . '%';
                }
                else {
                    $SQL .= " AND  (display.display LIKE :search$i ";
                    $params['search' . $i] = '%' . $searchName . '%';
                }
            }
        }

        if (Sanitize::getString('macAddress', $filterBy) != '') {
            $SQL .= ' AND display.macaddress LIKE :macAddress ';
            $params['macAddress'] = '%' . Sanitize::getString('macAddress', $filterBy) . '%';
        }

        // Exclude a group?
        if (Sanitize::getInt('exclude_displaygroupid', $filterBy) != 0) {
            $SQL .= " AND display.DisplayID NOT IN ";
            $SQL .= "       (SELECT display.DisplayID ";
            $SQL .= "       FROM    display ";
            $SQL .= "               INNER JOIN lkdisplaydg ";
            $SQL .= "               ON      lkdisplaydg.DisplayID = display.DisplayID ";
            $SQL .= "   WHERE  lkdisplaydg.DisplayGroupID   = :excludeDisplayGroupId ";
            $SQL .= "       )";
            $params['excludeDisplayGroupId'] = Sanitize::getInt('exclude_displaygroupid', $filterBy);
        }

        // Sorting?
        if (is_array($sortOrder))
            $SQL .= 'ORDER BY ' . implode(',', $sortOrder);

        foreach (PDOConnect::select($SQL, $params) as $row) {
            $entries[] = (new Display())->hydrate($row);
        }

        return $entries;
    }
}