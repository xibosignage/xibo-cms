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
use Xibo\Helper\Config;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class DisplayFactory extends BaseFactory
{
    /**
     * @param int $displayId
     * @return Display
     * @throws NotFoundException
     */
    public static function getById($displayId)
    {
        $displays = DisplayFactory::query(null, ['disableUserCheck' => 1, 'displayId' => $displayId]);

        if (count($displays) <= 0)
            throw new NotFoundException();

        return $displays[0];
    }

    /**
     * @param string $licence
     * @return Display
     * @throws NotFoundException
     */
    public static function getByLicence($licence)
    {
        $displays = DisplayFactory::query(null, ['disableUserCheck' => 1, 'license' => $licence]);

        if (count($displays) <= 0)
            throw new NotFoundException();

        return $displays[0];
    }

    /**
     * @param int $displayGroupId
     * @return array[Display]
     * @throws NotFoundException
     */
    public static function getByDisplayGroupId($displayGroupId)
    {
        return DisplayFactory::query(null, ['disableUserCheck' => 1, 'displayGroupId' => $displayGroupId]);
    }

    /**
     * Get displays by active campaignId
     * @param $campaignId
     * @return array[Display]
     */
    public static function getByActiveCampaignId($campaignId)
    {
        return DisplayFactory::query(null, ['disableUserCheck' => 1, 'activeCampaignId' => $campaignId]);
    }

    /**
     * Get displays by dataSetId
     * @param $dataSetId
     * @return array[Display]
     */
    public static function getByDataSetId($dataSetId)
    {
        //TODO
        return DisplayFactory::query(null, ['disableUserCheck' => 1, 'dataSetUd' => $dataSetId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Display]
     */
    public static function query($sortOrder = null, $filterBy = null)
    {
        if ($sortOrder === null)
            $sortOrder = ['display'];

        $entries = array();
        $params = array();
        $select = '
              SELECT display.displayId,
                  display.display,
                  display.defaultLayoutId,
                  layout.layout AS defaultLayout,
                  display.license,
                  display.licensed,
                  display.licensed AS currentlyLicenced,
                  display.loggedIn,
                  display.lastAccessed,
                  display.isAuditing,
                  display.inc_schedule AS incSchedule,
                  display.email_alert AS emailAlert,
                  display.alert_timeout AS alertTimeout,
                  display.clientAddress,
                  display.mediaInventoryStatus,
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
                  displaygroup.description ';

        $body = '
                FROM `display`
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.displayid = display.displayId
                    INNER JOIN `displaygroup`
                    ON displaygroup.displaygroupid = lkdisplaydg.displaygroupid
                    LEFT OUTER JOIN layout 
                    ON layout.layoutid = display.defaultlayoutid
                    LEFT OUTER JOIN layout currentLayout 
                    ON currentLayout.layoutId = display.currentLayoutId
               WHERE 1 = 1
            ';

        self::viewPermissionSql('Xibo\Entity\DisplayGroup', $body, $params, 'display.displayId', null, $filterBy);

        if (Sanitize::getInt('displayGroupId', $filterBy) !== null) {
            // Restrict to a specific display group
            $body .= ' AND displaygroup.displaygroupid = :displayGroupId ';
            $params['displayGroupId'] = Sanitize::getInt('displayGroupId', $filterBy);
        } else {
            // Restrict to display specific groups
            $body .= ' AND displaygroup.isDisplaySpecific = 1 ';
        }

        // Filter by Display ID?
        if (Sanitize::getInt('displayId', $filterBy) !== null) {
            $body .= ' AND display.displayid = :displayId ';
            $params['displayId'] = Sanitize::getInt('displayId', $filterBy);
        }

        // Filter by Wake On LAN
        if (Sanitize::getInt('wakeOnLan', $filterBy) !== null) {
            $body .= ' AND display.wakeOnLan = :wakeOnLan ';
            $params['wakeOnLan'] = Sanitize::getInt('wakeOnLan', $filterBy);
        }

        // Filter by Licence?
        if (Sanitize::getString('license', $filterBy) != null) {
            $body .= ' AND display.license = :license ';
            $params['license'] = Sanitize::getString('license', $filterBy);
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
                    $body .= " AND  (display.display NOT LIKE :search$i ";
                    $params['search' . $i] = '%' . ltrim(($searchName), '-') . '%';
                }
                else {
                    $body .= " AND  (display.display LIKE :search$i ";
                    $params['search' . $i] = '%' . $searchName . '%';
                }
            }
        }

        if (Sanitize::getString('macAddress', $filterBy) != '') {
            $body .= ' AND display.macaddress LIKE :macAddress ';
            $params['macAddress'] = '%' . Sanitize::getString('macAddress', $filterBy) . '%';
        }

        // Exclude a group?
        if (Sanitize::getInt('exclude_displaygroupid', $filterBy) !== null) {
            $body .= " AND display.DisplayID NOT IN ";
            $body .= "       (SELECT display.DisplayID ";
            $body .= "       FROM    display ";
            $body .= "               INNER JOIN lkdisplaydg ";
            $body .= "               ON      lkdisplaydg.DisplayID = display.DisplayID ";
            $body .= "   WHERE  lkdisplaydg.DisplayGroupID   = :excludeDisplayGroupId ";
            $body .= "       )";
            $params['excludeDisplayGroupId'] = Sanitize::getInt('exclude_displaygroupid', $filterBy);
        }

        // Only ones with a particular active campaign
        if (Sanitize::getInt('activeCampaignId', $filterBy) !== null) {
            // Which displays does a change to this layout effect?
            $body .= '
              AND display.displayId IN (
                   SELECT DISTINCT display.DisplayID
                     FROM `schedule`
                       INNER JOIN `schedule_detail`
                       ON schedule_detail.eventid = schedule.eventid
                       INNER JOIN `lkscheduledisplaygroup`
                       ON `lkscheduledisplaygroup`.eventId = `schedule`.eventId
                       INNER JOIN `lkdisplaydg`
                       ON lkdisplaydg.DisplayGroupID = `lkscheduledisplaygroup`.displayGroupId
                       INNER JOIN `display`
                       ON lkdisplaydg.DisplayID = display.displayID
                    WHERE `schedule`.CampaignID = :activeCampaignId
                      AND `schedule_detail`.FromDT < :fromDt
                      AND `schedule_detail`.ToDT > :toDt
                   UNION
                   SELECT DISTINCT display.DisplayID
                     FROM `display`
                       INNER JOIN `lkcampaignlayout`
                       ON `lkcampaignlayout`.LayoutID = `display`.DefaultLayoutID
                    WHERE `lkcampaignlayout`.CampaignID = :activeCampaignId2
              )
            ';

            $currentDate = time();
            $rfLookAhead = Config::GetSetting('REQUIRED_FILES_LOOKAHEAD');
            $rfLookAhead = intval($currentDate) + intval($rfLookAhead);

            $params['fromDt'] = $rfLookAhead;
            $params['toDt'] = $currentDate - 3600;
            $params['activeCampaignId'] = Sanitize::getInt('activeCampaignId', $filterBy);
            $params['activeCampaignId2'] = Sanitize::getInt('activeCampaignId', $filterBy);
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
            $entries[] = (new Display())->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}