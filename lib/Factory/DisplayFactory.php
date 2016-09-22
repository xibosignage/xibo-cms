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

use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Display;
use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\PlayerActionServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class DisplayFactory
 * @package Xibo\Factory
 */
class DisplayFactory extends BaseFactory
{
    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var PoolInterface
     */
    private $pool;

    /**
     * @var PlayerActionServiceInterface
     */
    private $playerAction;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var DisplayProfileFactory
     */
    private $displayProfileFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param ConfigServiceInterface $config
     * @param PoolInterface $pool
     * @param PlayerActionServiceInterface $playerAction
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DisplayProfileFactory $displayProfileFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $config, $pool, $playerAction, $displayGroupFactory, $displayProfileFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);

        $this->config = $config;
        $this->pool = $pool;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->displayProfileFactory = $displayProfileFactory;
        $this->playerAction = $playerAction;
    }

    /**
     * Create Empty Display Object
     * @return Display
     */
    public function createEmpty()
    {
        return new Display($this->getStore(), $this->getLog(), $this->config, $this->pool, $this->playerAction, $this->displayGroupFactory, $this->displayProfileFactory);
    }

    /**
     * @param int $displayId
     * @return Display
     * @throws NotFoundException
     */
    public function getById($displayId)
    {
        $displays = $this->query(null, ['disableUserCheck' => 1, 'displayId' => $displayId]);

        if (count($displays) <= 0)
            throw new NotFoundException();

        return $displays[0];
    }

    /**
     * @param string $licence
     * @return Display
     * @throws NotFoundException
     */
    public function getByLicence($licence)
    {
        $displays = $this->query(null, ['disableUserCheck' => 1, 'license' => $licence]);

        if (count($displays) <= 0)
            throw new NotFoundException();

        return $displays[0];
    }

    /**
     * @param int $displayGroupId
     * @return array[Display]
     * @throws NotFoundException
     */
    public function getByDisplayGroupId($displayGroupId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'displayGroupId' => $displayGroupId]);
    }

    /**
     * Get displays by active campaignId
     * @param $campaignId
     * @return array[Display]
     */
    public function getByActiveCampaignId($campaignId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'activeCampaignId' => $campaignId]);
    }

    /**
     * Get displays by assigned campaignId
     * @param $campaignId
     * @return array[Display]
     */
    public function getByAssignedCampaignId($campaignId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'assignedCampaignId' => $campaignId]);
    }

    /**
     * Get displays by dataSetId
     * @param $dataSetId
     * @return array[Display]
     */
    public function getByActiveDataSetId($dataSetId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'activeDataSetId' => $dataSetId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Display]
     */
    public function query($sortOrder = null, $filterBy = null)
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
                  display.licensed AS currentlyLicensed,
                  display.loggedIn,
                  display.lastAccessed,
                  display.auditingUntil,
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
                  displaygroup.description,
                  `display`.xmrChannel,
                  `display`.xmrPubKey,
                  `display`.lastCommandSuccess ';

        $body = '
                FROM `display`
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.displayid = display.displayId
                    INNER JOIN `displaygroup`
                    ON displaygroup.displaygroupid = lkdisplaydg.displaygroupid
                        AND `displaygroup`.isDisplaySpecific = 1
                    LEFT OUTER JOIN layout 
                    ON layout.layoutid = display.defaultlayoutid
                    LEFT OUTER JOIN layout currentLayout 
                    ON currentLayout.layoutId = display.currentLayoutId
            ';

        // Restrict to members of a specific display group
        if ($this->getSanitizer()->getInt('displayGroupId', $filterBy) !== null) {
            $body .= '
                INNER JOIN `lkdisplaydg` othergroups
                ON othergroups.displayId = `display`.displayId
                    AND othergroups.displayGroupId = :displayGroupId
            ';

            $params['displayGroupId'] = $this->getSanitizer()->getInt('displayGroupId', $filterBy);
        }

        $body .= ' WHERE 1 = 1 ';

        $this->viewPermissionSql('Xibo\Entity\DisplayGroup', $body, $params, 'displaygroup.displaygroupid', null, $filterBy);

        // Filter by Display ID?
        if ($this->getSanitizer()->getInt('displayId', $filterBy) !== null) {
            $body .= ' AND display.displayid = :displayId ';
            $params['displayId'] = $this->getSanitizer()->getInt('displayId', $filterBy);
        }

        // Filter by Wake On LAN
        if ($this->getSanitizer()->getInt('wakeOnLan', $filterBy) !== null) {
            $body .= ' AND display.wakeOnLan = :wakeOnLan ';
            $params['wakeOnLan'] = $this->getSanitizer()->getInt('wakeOnLan', $filterBy);
        }

        // Filter by Licence?
        if ($this->getSanitizer()->getString('license', $filterBy) != null) {
            $body .= ' AND display.license = :license ';
            $params['license'] = $this->getSanitizer()->getString('license', $filterBy);
        }

        // Filter by Display Name?
        if ($this->getSanitizer()->getString('display', $filterBy) != null) {
            // Convert into commas
            foreach (explode(',', $this->getSanitizer()->getString('display', $filterBy)) as $term) {

                // convert into a space delimited array
                $names = explode(' ', $term);

                $i = 0;
                foreach ($names as $searchName) {
                    $i++;
                    // Not like, or like?
                    if (substr($searchName, 0, 1) == '-') {
                        $body .= " AND  display.display NOT RLIKE (:search$i) ";
                        $params['search' . $i] = ltrim(($searchName), '-');
                    } else {
                        $body .= " AND  display.display RLIKE (:search$i) ";
                        $params['search' . $i] = $searchName;
                    }
                }
            }
        }

        if ($this->getSanitizer()->getString('macAddress', $filterBy) != '') {
            $body .= ' AND display.macaddress LIKE :macAddress ';
            $params['macAddress'] = '%' . $this->getSanitizer()->getString('macAddress', $filterBy) . '%';
        }

        if ($this->getSanitizer()->getString('clientVersion', $filterBy) != '') {
            $body .= ' AND display.client_version LIKE :clientVersion ';
            $params['clientVersion'] = '%' . $this->getSanitizer()->getString('clientVersion', $filterBy) . '%';
        }

        // Exclude a group?
        if ($this->getSanitizer()->getInt('exclude_displaygroupid', $filterBy) !== null) {
            $body .= " AND display.DisplayID NOT IN ";
            $body .= "       (SELECT display.DisplayID ";
            $body .= "       FROM    display ";
            $body .= "               INNER JOIN lkdisplaydg ";
            $body .= "               ON      lkdisplaydg.DisplayID = display.DisplayID ";
            $body .= "   WHERE  lkdisplaydg.DisplayGroupID   = :excludeDisplayGroupId ";
            $body .= "       )";
            $params['excludeDisplayGroupId'] = $this->getSanitizer()->getInt('exclude_displaygroupid', $filterBy);
        }

        // Only ones with a particular active campaign
        if ($this->getSanitizer()->getInt('activeCampaignId', $filterBy) !== null) {
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
            $rfLookAhead = $this->config->GetSetting('REQUIRED_FILES_LOOKAHEAD');
            $rfLookAhead = intval($currentDate) + intval($rfLookAhead);

            $params['fromDt'] = $rfLookAhead;
            $params['toDt'] = $currentDate - 3600;
            $params['activeCampaignId'] = $this->getSanitizer()->getInt('activeCampaignId', $filterBy);
            $params['activeCampaignId2'] = $this->getSanitizer()->getInt('activeCampaignId', $filterBy);
        }

        // Only Display Groups with a Campaign containing particular Layout assigned to them
        if ($this->getSanitizer()->getInt('assignedCampaignId', $filterBy) !== null) {
            $body .= '
                AND display.displayId IN (
                    SELECT `lkdisplaydg`.displayId
                      FROM `lkdisplaydg`
                        INNER JOIN `lklayoutdisplaygroup`
                        ON `lklayoutdisplaygroup`.displayGroupId = `lkdisplaydg`.displayGroupId
                        INNER JOIN `lkcampaignlayout`
                        ON `lkcampaignlayout`.layoutId = `lklayoutdisplaygroup`.layoutId
                     WHERE `lkcampaignlayout`.campaignId = :assignedCampaignId
                )
            ';

            $params['assignedCampaignId'] = $this->getSanitizer()->getInt('assignedCampaignId', $filterBy);
        }

        // Only Display Groups that are running Layouts that have the provided data set on them
        if ($this->getSanitizer()->getInt('activeDataSetId', $filterBy) !== null) {
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
                       INNER JOIN `lkcampaignlayout`
                       ON `lkcampaignlayout`.campaignId = `schedule`.campaignId
                       INNER JOIN `region`
                       ON `region`.layoutId = `lkcampaignlayout`.layoutId
                       INNER JOIN `lkregionplaylist`
                       ON `lkregionplaylist`.regionId = `region`.regionId
                       INNER JOIN `widget`
                       ON `widget`.playlistId = `lkregionplaylist`.playlistId
                       INNER JOIN `widgetoption`
                       ON `widgetoption`.widgetId = `widget`.widgetId
                            AND `widgetoption`.type = \'attrib\'
                            AND `widgetoption`.option = \'dataSetId\'
                            AND `widgetoption`.value = :activeDataSetId
                    WHERE `schedule_detail`.FromDT < :fromDt
                      AND `schedule_detail`.ToDT > :toDt
                   UNION
                   SELECT DISTINCT display.DisplayID
                     FROM `display`
                       INNER JOIN `lkcampaignlayout`
                       ON `lkcampaignlayout`.LayoutID = `display`.DefaultLayoutID
                       INNER JOIN `region`
                       ON `region`.layoutId = `lkcampaignlayout`.layoutId
                       INNER JOIN `lkregionplaylist`
                       ON `lkregionplaylist`.regionId = `region`.regionId
                       INNER JOIN `widget`
                       ON `widget`.playlistId = `lkregionplaylist`.playlistId
                       INNER JOIN `widgetoption`
                       ON `widgetoption`.widgetId = `widget`.widgetId
                            AND `widgetoption`.type = \'attrib\'
                            AND `widgetoption`.option = \'dataSetId\'
                            AND `widgetoption`.value = :activeDataSetId2
                   UNION
                   SELECT `lkdisplaydg`.displayId
                      FROM `lkdisplaydg`
                        INNER JOIN `lklayoutdisplaygroup`
                        ON `lklayoutdisplaygroup`.displayGroupId = `lkdisplaydg`.displayGroupId
                        INNER JOIN `lkcampaignlayout`
                        ON `lkcampaignlayout`.layoutId = `lklayoutdisplaygroup`.layoutId
                        INNER JOIN `region`
                        ON `region`.layoutId = `lkcampaignlayout`.layoutId
                        INNER JOIN `lkregionplaylist`
                       ON `lkregionplaylist`.regionId = `region`.regionId
                        INNER JOIN `widget`
                        ON `widget`.playlistId = `lkregionplaylist`.playlistId
                        INNER JOIN `widgetoption`
                        ON `widgetoption`.widgetId = `widget`.widgetId
                            AND `widgetoption`.type = \'attrib\'
                            AND `widgetoption`.option = \'dataSetId\'
                            AND `widgetoption`.value = :activeDataSetId3
              )
            ';

            $currentDate = time();
            $rfLookAhead = $this->config->GetSetting('REQUIRED_FILES_LOOKAHEAD');
            $rfLookAhead = intval($currentDate) + intval($rfLookAhead);

            $params['fromDt'] = $rfLookAhead;
            $params['toDt'] = $currentDate - 3600;
            $params['activeDataSetId'] = $this->getSanitizer()->getInt('activeDataSetId', $filterBy);
            $params['activeDataSetId2'] = $this->getSanitizer()->getInt('activeDataSetId', $filterBy);
            $params['activeDataSetId3'] = $this->getSanitizer()->getInt('activeDataSetId', $filterBy);
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
            $entries[] = $this->createEmpty()->hydrate($row, [
                'intProperties' => [
                    'auditingUntil',
                    'wakeOnLanEnabled',
                    'numberOfMacAddressChanges',
                    'loggedIn',
                    'incSchedule',
                    'licensed',
                    'lastAccessed',
                    'emailAlert',
                    'alertTimeout',
                    'mediaInventoryStatus',
                    'clientCode',
                    'screenShotRequested',
                    'lastCommandSuccess'
                ]
            ]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}