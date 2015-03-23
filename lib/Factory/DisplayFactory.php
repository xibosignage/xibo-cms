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
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class DisplayFactory
{
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
              SELECT display.*, displaygroup.displaygroupid, displaygroup.description, X(display.GeoLocation) AS Latitude, Y(display.GeoLocation) AS Longitude
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

        if (Sanitize::getInt('displaygroupid', $filterBy) != 0) {
            // Restrict to a specific display group
            $SQL .= ' AND displaygroup.displaygroupid = :displayGroupId ';
            $params['displayGroupId'] = Sanitize::getInt('displayGroupId', $filterBy);
        } else {
            // Restrict to display specific groups
            $SQL .= ' AND displaygroup.isDisplaySpecific = 1 ';
        }

        // Filter by Display ID?
        if (Sanitize::getInt('displayid', $filterBy) != 0) {
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
            $display = new Display();
            $display->isAuditing = Sanitize::int($row['isAuditing']);
            $display->display = Sanitize::string($row['display']);
            $display->description = Sanitize::string($row['description']);
            $display->defaultLayoutId = Sanitize::int($row['defaultlayoutid']);
            $display->license = Sanitize::string($row['license']);
            $display->licensed = Sanitize::int($row['licensed']);
            $display->loggedIn = Sanitize::int($row['loggedin']);
            $display->lastAccessed = Sanitize::int($row['lastaccessed']);
            $display->incSchedule = Sanitize::int($row['inc_schedule']);
            $display->emailAlert = Sanitize::int($row['email_alert']);
            $display->alertTimeout = Sanitize::int($row['alert_timeout']);
            $display->clientAddress = Sanitize::string($row['ClientAddress']);
            $display->mediaInventoryStatus = Sanitize::int($row['MediaInventoryStatus']);
            $display->mediaInventoryXml = Sanitize::htmlString($row['MediaInventoryXml']);
            $display->macAddress = Sanitize::string($row['MacAddress']);
            $display->lastChanged = Sanitize::int($row['LastChanged']);
            $display->numberOfMacAddressChanges = Sanitize::int($row['NumberOfMacAddressChanges']);
            $display->lastWakeOnLanCommandSent = Sanitize::int($row['LastWakeOnLanCommandSent']);
            $display->wakeOnLanEnabled = Sanitize::int($row['WakeOnLan']);
            $display->wakeOnLanTime = Sanitize::string($row['WakeOnLanTime']);
            $display->broadCastAddress = Sanitize::string($row['BroadCastAddress']);
            $display->secureOn = Sanitize::string($row['SecureOn']);
            $display->cidr = Sanitize::string($row['Cidr']);
            $display->latitude = Sanitize::double($row['Latitude']);
            $display->longitude = Sanitize::double($row['Longitude']);
            $display->versionInstructions = Sanitize::string($row['version_instructions']);
            $display->clientType = Sanitize::string($row['client_type']);
            $display->clientVersion = Sanitize::string($row['client_version']);
            $display->clientCode = Sanitize::int($row['client_code']);
            $display->displayProfileId = Sanitize::int($row['displayprofileid']);
            $display->currentLayoutId = Sanitize::int($row['currentLayoutId']);
            $display->screenShotRequested = Sanitize::int($row['screenShotRequested']);
            $display->storageAvailableSpace = Sanitize::int($row['storageAvailableSpace']);
            $display->storageTotalSpace = Sanitize::int($row['storageTotalSpace']);

            $display->displayGroupId = Sanitize::int($row['displaygroupid']);

            $entries[] = $display;
        }

        return $entries;
    }
}