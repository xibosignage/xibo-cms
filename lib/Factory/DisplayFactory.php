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
use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class DisplayFactory
 * @package Xibo\Factory
 */
class DisplayFactory extends BaseFactory
{
    /** @var  DisplayNotifyServiceInterface */
    private $displayNotifyService;

    /**
     * @var ConfigServiceInterface
     */
    private $config;

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
     * @param DisplayNotifyServiceInterface $displayNotifyService
     * @param ConfigServiceInterface $config
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DisplayProfileFactory $displayProfileFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $displayNotifyService, $config, $displayGroupFactory, $displayProfileFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);

        $this->displayNotifyService = $displayNotifyService;
        $this->config = $config;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->displayProfileFactory = $displayProfileFactory;
    }

    /**
     * Get the Display Notify Service
     * @return DisplayNotifyServiceInterface
     */
    public function getDisplayNotifyService()
    {
        return $this->displayNotifyService->init();
    }

    /**
     * Create Empty Display Object
     * @return Display
     */
    public function createEmpty()
    {
        return new Display($this->getStore(), $this->getLog(), $this->config, $this->displayGroupFactory, $this->displayProfileFactory, $this);
    }

    /**
     * @param int $displayId
     * @param bool|false $showTags
     * @return Display
     * @throws NotFoundException
     */
    public function getById($displayId, $showTags = false)
    {
        $displays = $this->query(null, ['disableUserCheck' => 1, 'displayId' => $displayId, 'showTags' => $showTags]);

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
     * @return Display[]
     * @throws NotFoundException
     */
    public function getByDisplayGroupId($displayGroupId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'displayGroupId' => $displayGroupId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return Display[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder === null)
            $sortOrder = ['display'];

        $newSortOrder = [];
        foreach ($sortOrder as $sort) {
            if ($sort == '`clientSort`') {
                $newSortOrder[] = '`clientType`';
                $newSortOrder[] = '`clientCode`';
                $newSortOrder[] = '`clientVersion`';
                continue;
            }

            if ($sort == '`clientSort` DESC') {
                $newSortOrder[] = '`clientType` DESC';
                $newSortOrder[] = '`clientCode` DESC';
                $newSortOrder[] = '`clientVersion` DESC';
                continue;
            }
            $newSortOrder[] = $sort;
        }
        $sortOrder = $newSortOrder;

        // SQL function for ST_X/X and ST_Y/Y dependent on MySQL version
        $version = $this->getStore()->getVersion();

        $functionPrefix = ($version === null || version_compare($version, '5.6.1', '>=')) ? 'ST_' : '';

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
                  ' . $functionPrefix . 'X(display.GeoLocation) AS latitude,
                  ' . $functionPrefix . 'Y(display.GeoLocation) AS longitude,
                  display.client_type AS clientType,
                  display.client_version AS clientVersion,
                  display.client_code AS clientCode,
                  display.displayProfileId,
                  display.screenShotRequested,
                  display.storageAvailableSpace,
                  display.storageTotalSpace,
                  displaygroup.displayGroupId,
                  displaygroup.description,
                  displaygroup.bandwidthLimit,
                  `display`.xmrChannel,
                  `display`.xmrPubKey,
                  `display`.lastCommandSuccess, 
                  `display`.deviceName, 
                  `display`.timeZone,
                  `display`.overrideConfig,
                  `display`.newCmsAddress,
                  `display`.newCmsKey,
                  `display`.orientation,
                  `display`.resolution,
                  `display`.commercialLicence
              ';

        if ($this->getSanitizer()->getCheckbox('showTags', $filterBy) === 1) {
            $select .= ', 
                (
                  SELECT GROUP_CONCAT(DISTINCT tag) 
                    FROM tag 
                      INNER JOIN lktagdisplaygroup 
                      ON lktagdisplaygroup.tagId = tag.tagId 
                   WHERE lktagdisplaygroup.displayGroupId = displaygroup.displayGroupID 
                  GROUP BY lktagdisplaygroup.displayGroupId
                ) AS tags
            ';

            $select .= ", 
                (
                  SELECT GROUP_CONCAT(IFNULL(value, 'NULL')) 
                    FROM tag 
                      INNER JOIN lktagdisplaygroup 
                      ON lktagdisplaygroup.tagId = tag.tagId 
                   WHERE lktagdisplaygroup.displayGroupId = displaygroup.displayGroupID 
                  GROUP BY lktagdisplaygroup.displayGroupId
                ) AS tagValues
            ";
        }

        $body = '
                FROM `display`
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.displayid = display.displayId
                    INNER JOIN `displaygroup`
                    ON displaygroup.displaygroupid = lkdisplaydg.displaygroupid
                        AND `displaygroup`.isDisplaySpecific = 1
                    LEFT OUTER JOIN layout 
                    ON layout.layoutid = display.defaultlayoutid
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

        $this->viewPermissionSql('Xibo\Entity\DisplayGroup', $body, $params, 'displaygroup.displayGroupId', null, $filterBy);

        // Filter by Display ID?
        if ($this->getSanitizer()->getInt('displayId', $filterBy) !== null) {
            $body .= ' AND display.displayid = :displayId ';
            $params['displayId'] = $this->getSanitizer()->getInt('displayId', $filterBy);
        }

        // Display Profile
        if ($this->getSanitizer()->getInt('displayProfileId', $filterBy) !== null) {
            if ($this->getSanitizer()->getInt('displayProfileId', $filterBy) == -1) {
                $body .= ' AND IFNULL(displayProfileId, 0) = 0 ';
            } else {
                $displayProfileSelected = $this->displayProfileFactory->getById($this->getSanitizer()->getInt('displayProfileId', $filterBy));
                $displayProfileDefault = $this->displayProfileFactory->getDefaultByType($displayProfileSelected->type);

                $body .= ' AND (`display`.displayProfileId = :displayProfileId OR (IFNULL(displayProfileId, :displayProfileDefaultId) = :displayProfileId AND display.client_type = :displayProfileType ) ) ';

                $params['displayProfileId'] = $this->getSanitizer()->getInt('displayProfileId', $filterBy);
                $params['displayProfileDefaultId'] = $displayProfileDefault->displayProfileId;
                $params['displayProfileType'] = $displayProfileDefault->type;
            }
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
        
        // Filter by authorised?
        if ($this->getSanitizer()->getInt('authorised', -1, $filterBy) != -1) {
            $body .= ' AND display.licensed = :authorised ';
            $params['authorised'] = $this->getSanitizer()->getInt('authorised', $filterBy);
        }

        // Filter by Display Name?
        if ($this->getSanitizer()->getString('display', $filterBy) != null) {
            $terms = explode(',', $this->getSanitizer()->getString('display', $filterBy));
            $this->nameFilter('display', 'display', $terms, $body, $params, ($this->getSanitizer()->getCheckbox('useRegexForName', $filterBy) == 1));
        }

        if ($this->getSanitizer()->getString('macAddress', $filterBy) != '') {
            $body .= ' AND display.macaddress LIKE :macAddress ';
            $params['macAddress'] = '%' . $this->getSanitizer()->getString('macAddress', $filterBy) . '%';
        }

        if ($this->getSanitizer()->getString('clientAddress', $filterBy) != '') {
            $body .= ' AND display.clientaddress LIKE :clientAddress ';
            $params['clientAddress'] = '%' . $this->getSanitizer()->getString('clientAddress', $filterBy) . '%';
        }

        if ($this->getSanitizer()->getString('clientVersion', $filterBy) != '') {
            $body .= ' AND display.client_version LIKE :clientVersion ';
            $params['clientVersion'] = '%' . $this->getSanitizer()->getString('clientVersion', $filterBy) . '%';
        }

        if ($this->getSanitizer()->getString('clientType', $filterBy) != '') {
            $body .= ' AND display.client_type = :clientType ';
            $params['clientType'] = $this->getSanitizer()->getString('clientType', $filterBy);
        }

        if ($this->getSanitizer()->getString('clientCode', $filterBy) != '') {
            $body .= ' AND display.client_code LIKE :clientCode ';
            $params['clientCode'] = '%' . $this->getSanitizer()->getString('clientCode', $filterBy) . '%';
        }

        if ($this->getSanitizer()->getString('orientation', $filterBy) != '') {
            $body .= ' AND display.orientation = :orientation ';
            $params['orientation'] = $this->getSanitizer()->getString('orientation', $filterBy);
        }

        if ($this->getSanitizer()->getInt('mediaInventoryStatus', $filterBy) != '') {
            if ($this->getSanitizer()->getInt('mediaInventoryStatus', $filterBy) === -1) {
                $body .= ' AND display.mediaInventoryStatus <> 1 ';
            } else {
                $body .= ' AND display.mediaInventoryStatus = :mediaInventoryStatus ';
                $params['mediaInventoryStatus'] = $this->getSanitizer()->getInt('mediaInventoryStatus', $filterBy);
            }
        }

        if ($this->getSanitizer()->getInt('loggedIn', -1, $filterBy) != -1) {
            $body .= ' AND display.loggedIn = :loggedIn ';
            $params['loggedIn'] = $this->getSanitizer()->getInt('loggedIn', $filterBy);
        }

        if ($this->getSanitizer()->getInt('lastAccessed', $filterBy) !== null) {
            $body .= ' AND display.lastAccessed > :lastAccessed ';
            $params['lastAccessed'] = $this->getSanitizer()->getInt('lastAccessed', $filterBy);
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

        // Media ID - direct assignment
        if ($this->getSanitizer()->getInt('mediaId', $filterBy) !== null) {

            $body .= '
                AND display.displayId IN (
                    SELECT `lkdisplaydg`.displayId
                       FROM `lkmediadisplaygroup`
                        INNER JOIN `lkdgdg`
                        ON `lkdgdg`.parentId = `lkmediadisplaygroup`.displayGroupId
                        INNER JOIN `lkdisplaydg`
                        ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                     WHERE `lkmediadisplaygroup`.mediaId = :mediaId
                    UNION
                    SELECT `lkdisplaydg`.displayId
                      FROM `lklayoutdisplaygroup`
                        INNER JOIN `lkdgdg`
                        ON `lkdgdg`.parentId = `lklayoutdisplaygroup`.displayGroupId
                        INNER JOIN `lkdisplaydg`
                        ON lkdisplaydg.DisplayGroupID = `lkdgdg`.childId
                     WHERE `lklayoutdisplaygroup`.layoutId IN (
                         SELECT `region`.layoutId
                              FROM `lkwidgetmedia`
                               INNER JOIN `widget`
                               ON `widget`.widgetId = `lkwidgetmedia`.widgetId
                               INNER JOIN `playlist`
                               ON `playlist`.playlistId = `widget`.playlistId
                               INNER JOIN `region`
                               ON `region`.regionId = `playlist`.regionId
                               INNER JOIN layout
                               ON layout.LayoutID = region.layoutId
                             WHERE lkwidgetmedia.mediaId = :mediaId
                            UNION
                            SELECT `layout`.layoutId
                              FROM `layout`
                             WHERE `layout`.backgroundImageId = :mediaId
                        )
                )
            ';

            $params['mediaId'] = $this->getSanitizer()->getInt('mediaId', $filterBy);
        }

        // Tags
        if ($this->getSanitizer()->getString('tags', $filterBy) != '') {

            $tagFilter = $this->getSanitizer()->getString('tags', $filterBy);

            if (trim($tagFilter) === '--no-tag') {
                $body .= ' AND `displaygroup`.displaygroupId NOT IN (
                    SELECT `lktagdisplaygroup`.displaygroupId
                     FROM tag
                        INNER JOIN `lktagdisplaygroup`
                        ON `lktagdisplaygroup`.tagId = tag.tagId
                    )
                ';
            } else {
                $operator = $this->getSanitizer()->getCheckbox('exactTags') == 1 ? '=' : 'LIKE';

                $body .= " AND `displaygroup`.displaygroupId IN (
                SELECT `lktagdisplaygroup`.displaygroupId
                  FROM tag
                    INNER JOIN `lktagdisplaygroup`
                    ON `lktagdisplaygroup`.tagId = tag.tagId
                ";

                $tags = explode(',', $tagFilter);
                $this->tagFilter($tags, $operator, $body, $params);
            }
        }

        // run the special query to help sort by displays already assigned to this display group, we want to run it only if we're sorting by member column.
        if ($this->getSanitizer()->getInt('displayGroupIdMembers', $filterBy) !== null && ($sortOrder == ['`member`'] || $sortOrder == ['`member` DESC'] )) {
            $members = [];
            foreach ($this->getStore()->select($select . $body, $params) as $row) {
                $displayId = $this->getSanitizer()->int($row['displayId']);
                $displayGroupId = $this->getSanitizer()->getInt('displayGroupIdMembers', $filterBy);

                if ($this->getStore()->exists('SELECT display.display, display.displayId, displaygroup.displayGroupId
                                                    FROM display
                                                      INNER JOIN `lkdisplaydg` 
                                                          ON lkdisplaydg.displayId = `display`.displayId 
                                                          AND lkdisplaydg.displayGroupId = :displayGroupId 
                                                          AND lkdisplaydg.displayId = :displayId
                                                      INNER JOIN `displaygroup` 
                                                          ON displaygroup.displaygroupid = lkdisplaydg.displaygroupid
                                                          AND `displaygroup`.isDisplaySpecific = 0',
                    [
                        'displayGroupId' => $displayGroupId,
                        'displayId' => $displayId
                    ]
                )) {
                    $members[] = $displayId;
                }
            }
        }

        // filter by commercial licence
        if ($this->getSanitizer()->getInt('commercialLicence', $filterBy) !== null) {
            $body .= ' AND display.commercialLicence = :commercialLicence ';
            $params['commercialLicence'] = $this->getSanitizer()->getInt('commercialLicence', $filterBy);
        }

        // Sorting?
        $order = '';

        if (isset($members) && $members != [] ) {
            $sqlOrderMembers = 'ORDER BY FIELD(display.displayId,' . implode(',', $members) . ')';

            foreach ($sortOrder as $sort) {
                if ($sort == '`member`') {
                    $order .= $sqlOrderMembers;
                    continue;
                }

                if ($sort == '`member` DESC') {
                    $order .= $sqlOrderMembers . ' DESC';
                    continue;
                }
            }
        }

        if (is_array($sortOrder) && ($sortOrder != ['`member`'] && $sortOrder != ['`member` DESC'] )) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $display = $this->createEmpty()->hydrate($row, [
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
                    'lastCommandSuccess',
                    'bandwidthLimit'
                ]
            ]);
            $display->overrideConfig = ($display->overrideConfig == '') ? [] : json_decode($display->overrideConfig, true);
            $entries[] = $display;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}