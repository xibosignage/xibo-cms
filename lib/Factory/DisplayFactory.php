<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
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
use Xibo\Helper\Environment;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class DisplayFactory
 * @package Xibo\Factory
 */
class DisplayFactory extends BaseFactory
{
    use TagTrait;

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

    /** @var FolderFactory */
    private $folderFactory;

    /**
     * Construct a factory
     * @param User $user
     * @param UserFactory $userFactory
     * @param DisplayNotifyServiceInterface $displayNotifyService
     * @param ConfigServiceInterface $config
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DisplayProfileFactory $displayProfileFactory
     * @param FolderFactory $folderFactory
     */
    public function __construct(
        $user,
        $userFactory,
        $displayNotifyService,
        $config,
        $displayGroupFactory,
        $displayProfileFactory,
        $folderFactory
    ) {
        $this->setAclDependencies($user, $userFactory);

        $this->displayNotifyService = $displayNotifyService;
        $this->config = $config;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->displayProfileFactory = $displayProfileFactory;
        $this->folderFactory = $folderFactory;
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
        return new Display(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher(),
            $this->config,
            $this->displayGroupFactory,
            $this->displayProfileFactory,
            $this,
            $this->folderFactory
        );
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

        if (count($displays) <= 0) {
            throw new NotFoundException();
        }

        return $displays[0];
    }

    /**
     * @param string $licence
     * @return Display
     * @throws NotFoundException
     */
    public function getByLicence($licence)
    {
        if (empty($licence)) {
            throw new NotFoundException(__('Hardware key cannot be empty'));
        }

        $displays = $this->query(null, ['disableUserCheck' => 1, 'license' => $licence]);

        if (count($displays) <= 0) {
            throw new NotFoundException();
        }

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
     * @param array $displayGroupIds
     * @return Display[]
     * @throws NotFoundException
     */
    public function getByDisplayGroupIds(array $displayGroupIds)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'displayGroupIds' => $displayGroupIds]);
    }

    /**
     * @param int $syncGroupId
     * @return Display[]
     * @throws NotFoundException
     */
    public function getBySyncGroupId(int $syncGroupId): array
    {
        return $this->query(null, ['syncGroupId' => $syncGroupId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return Display[]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $parsedBody = $this->getSanitizer($filterBy);

        if ($sortOrder === null) {
            $sortOrder = ['display'];
        }

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

            if ($sort == '`isCmsTransferInProgress`') {
                $newSortOrder[] = '`newCmsAddress`';
                continue;
            }

            if ($sort == '`isCmsTransferInProgress` DESC') {
                $newSortOrder[] = '`newCmsAddress` DESC';
                continue;
            }
            $newSortOrder[] = $sort;
        }
        $sortOrder = $newSortOrder;

        // SQL function for ST_X/X and ST_Y/Y dependent on MySQL version
        $version = $this->getStore()->getVersion();

        $functionPrefix = ($version === null || version_compare($version, '5.6.1', '>=')) ? 'ST_' : '';

        $entries = [];
        $params = [];
        $select = '
              SELECT display.displayId,
                  display.display,
                  display.defaultLayoutId,
                  display.rdmDeviceId,
                  display.displayTypeId,
                  display.venueId,
                  display.address,
                  display.isMobile,
                  display.languages,
                  `display_types`.displayType,
                  display.screenSize,
                  display.isOutdoor,
                  display.customId,
                  display.costPerPlay,
                  display.impressionsPerPlay,
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
                  display.osVersion,
                  display.osSdk,
                  display.manufacturer,
                  display.brand,
                  display.model,
                  displaygroup.displayGroupId,
                  displaygroup.description,
                  displaygroup.bandwidthLimit,
                  displaygroup.createdDt,
                  displaygroup.modifiedDt,
                  displaygroup.folderId,
                  displaygroup.permissionsFolderId,
                  displaygroup.ref1,
                  displaygroup.ref2,
                  displaygroup.ref3,
                  displaygroup.ref4,
                  displaygroup.ref5,
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
                  `display`.commercialLicence,
                  `display`.teamViewerSerial,
                  `display`.webkeySerial,
                  `display`.lanIpAddress,
                  `display`.syncGroupId,
                  (SELECT COUNT(*) FROM player_faults WHERE player_faults.displayId = display.displayId) AS countFaults,
                  (SELECT GROUP_CONCAT(DISTINCT `group`.group)
                    FROM `permission`
                        INNER JOIN `permissionentity`
                            ON `permissionentity`.entityId = permission.entityId
                        INNER JOIN `group`
                            ON `group`.groupId = `permission`.groupId
                        WHERE entity = :entity
                            AND objectId = `displaygroup`.displayGroupId
                            AND view = 1
                  ) AS groupsWithPermissions
              ';

        $params['entity'] = 'Xibo\\Entity\\DisplayGroup';

        $body = '
                FROM `display`
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.displayid = display.displayId
                    INNER JOIN `displaygroup`
                    ON displaygroup.displaygroupid = lkdisplaydg.displaygroupid
                        AND `displaygroup`.isDisplaySpecific = 1
                    LEFT OUTER JOIN layout 
                    ON layout.layoutid = display.defaultlayoutid
                    LEFT OUTER JOIN `display_types`
                    ON `display_types`.displayTypeId = `display`.displayTypeId
            ';

        // Restrict to members of a specific display group
        if ($parsedBody->getInt('displayGroupId') !== null) {
            $body .= '
                INNER JOIN `lkdisplaydg` othergroups
                ON othergroups.displayId = `display`.displayId
                    AND othergroups.displayGroupId = :displayGroupId
            ';

            $params['displayGroupId'] = $parsedBody->getInt('displayGroupId');
        }

        // Restrict to members of display groups
        if ($parsedBody->getIntArray('displayGroupIds') !== null) {
            $body .= '
                INNER JOIN `lkdisplaydg` othergroups
                ON othergroups.displayId = `display`.displayId
                    AND othergroups.displayGroupId IN (0 
            ';

            $i = 0;
            foreach ($parsedBody->getIntArray('displayGroupIds') as $displayGroupId) {
                $i++;
                $body .= ',:displayGroupId' . $i;
                $params['displayGroupId' . $i] = $displayGroupId;
            }
            $body .= ')';
        }

        $body .= ' WHERE 1 = 1 ';

        // Filter by map bound?
        if ($parsedBody->getString('bounds') !== null) {
            $coordinates = explode(',', $parsedBody->getString('bounds'));
            $defaultLat = $this->config->getSetting('DEFAULT_LAT');
            $defaultLng = $this->config->getSetting('DEFAULT_LONG');

            $body .= ' AND IFNULL( ' . $functionPrefix . 'X(display.GeoLocation), ' . $defaultLat
                . ')  BETWEEN :coordinates_1 AND :coordinates_3 '
                . ' AND IFNULL( ' . $functionPrefix . 'Y(display.GeoLocation), ' . $defaultLng
                . ')  BETWEEN :coordinates_0 AND :coordinates_2 ';

            $params['coordinates_0'] = $coordinates[0];
            $params['coordinates_1'] = $coordinates[1];
            $params['coordinates_2'] = $coordinates[2];
            $params['coordinates_3'] = $coordinates[3];
        }

        // Filter by Display ID?
        if ($parsedBody->getInt('displayId') !== null) {
            $body .= ' AND display.displayid = :displayId ';
            $params['displayId'] = $parsedBody->getInt('displayId');
        }

        // Display Profile
        if ($parsedBody->getInt('displayProfileId') !== null) {
            if ($parsedBody->getInt('displayProfileId') == -1) {
                $body .= ' AND IFNULL(displayProfileId, 0) = 0 ';
            } else {
                $displayProfileSelected = $this->displayProfileFactory->getById($parsedBody->getInt('displayProfileId'));
                $displayProfileDefault = $this->displayProfileFactory->getDefaultByType($displayProfileSelected->type);

                $body .= ' AND (`display`.displayProfileId = :displayProfileId OR (IFNULL(displayProfileId, :displayProfileDefaultId) = :displayProfileId AND display.client_type = :displayProfileType ) ) ';

                $params['displayProfileId'] = $parsedBody->getInt('displayProfileId');
                $params['displayProfileDefaultId'] = $displayProfileDefault->displayProfileId;
                $params['displayProfileType'] = $displayProfileDefault->type;
            }
        }

        // Filter by Wake On LAN
        if ($parsedBody->getInt('wakeOnLan') !== null) {
            $body .= ' AND display.wakeOnLan = :wakeOnLan ';
            $params['wakeOnLan'] = $parsedBody->getInt('wakeOnLan');
        }

        // Filter by Licence?
        if ($parsedBody->getString('license') !== null) {
            $body .= ' AND display.license = :license ';
            $params['license'] = $parsedBody->getString('license');
        }

        // Filter by authorised?
        if ($parsedBody->getInt('authorised', ['default' => -1]) != -1) {
            $body .= ' AND display.licensed = :authorised ';
            $params['authorised'] = $parsedBody->getInt('authorised');
        }

        // Filter by Display Name?
        if ($parsedBody->getString('display') != null) {
            $terms = explode(',', $parsedBody->getString('display'));
            $logicalOperator = $parsedBody->getString('logicalOperatorName', ['default' => 'OR']);
            $this->nameFilter(
                'display',
                'display',
                $terms,
                $body,
                $params,
                ($parsedBody->getCheckbox('useRegexForName') == 1),
                $logicalOperator
            );
        }

        if ($parsedBody->getString('macAddress') != '') {
            $body .= ' AND display.macaddress LIKE :macAddress ';
            $params['macAddress'] = '%' . $parsedBody->getString('macAddress') . '%';
        }

        if ($parsedBody->getString('clientAddress') != '') {
            $body .= ' AND display.clientaddress LIKE :clientAddress ';
            $params['clientAddress'] = '%' . $parsedBody->getString('clientAddress') . '%';
        }

        if ($parsedBody->getString('clientVersion') != '') {
            $body .= ' AND display.client_version LIKE :clientVersion ';
            $params['clientVersion'] = '%' . $parsedBody->getString('clientVersion') . '%';
        }

        if ($parsedBody->getString('clientType') != '') {
            $body .= ' AND display.client_type = :clientType ';
            $params['clientType'] = $parsedBody->getString('clientType');
        }

        if ($parsedBody->getString('clientCode') != '') {
            $body .= ' AND display.client_code LIKE :clientCode ';
            $params['clientCode'] = '%' . $parsedBody->getString('clientCode') . '%';
        }

        if ($parsedBody->getString('customId') != '') {
            $body .= ' AND display.customId LIKE :customId ';
            $params['customId'] = '%' . $parsedBody->getString('customId') . '%';
        }

        if ($parsedBody->getString('orientation', $filterBy) != '') {
            $body .= ' AND display.orientation = :orientation ';
            $params['orientation'] = $parsedBody->getString('orientation', $filterBy);
        }

        if ($parsedBody->getInt('mediaInventoryStatus', $filterBy) != '') {
            if ($parsedBody->getInt('mediaInventoryStatus', $filterBy) === -1) {
                $body .= ' AND display.mediaInventoryStatus <> 1 ';
            } else {
                $body .= ' AND display.mediaInventoryStatus = :mediaInventoryStatus ';
                $params['mediaInventoryStatus'] = $parsedBody->getInt('mediaInventoryStatus');
            }
        }

        if ($parsedBody->getInt('loggedIn', ['default' => -1]) != -1) {
            $body .= ' AND display.loggedIn = :loggedIn ';
            $params['loggedIn'] = $parsedBody->getInt('loggedIn');
        }

        if ($parsedBody->getDate('lastAccessed', ['dateFormat' => 'U']) !== null) {
            $body .= ' AND display.lastAccessed > :lastAccessed ';
            $params['lastAccessed'] = $parsedBody->getDate('lastAccessed', ['dateFormat' => 'U'])->format('U');
        }

        if ($parsedBody->getString('displayType', $filterBy) != '') {
            $body .= ' AND (`display`.brand LIKE :displayType OR `display`.model LIKE :displayType OR 
                `display`.manufacturer LIKE :displayType OR `display`.osVersion LIKE :displayType) ';
            $params['displayType'] = $parsedBody->getString('displayType', $filterBy);
        }

        if ($parsedBody->getInt('rdmDeviceId', $filterBy) != '') {
            $body .= ' AND `display`.rdmDeviceId = :rdmDeviceId ';
            $params['rdmDeviceId'] = $parsedBody->getInt('rdmDeviceId', $filterBy);
        }

        if ($parsedBody->getInt('cmsConnected') === 1) {
            $body .= ' AND `display`.rdmDeviceId != 0 OR `display`.rdmDeviceId is NOT NULL ';
        } else if ($parsedBody->getInt('cmsConnected') === 0) {
            $body .= ' AND `display`.rdmDeviceId = 0 OR `display`.rdmDeviceId is NULL ';
        }

        // Exclude a group?
        if ($parsedBody->getInt('exclude_displaygroupid') !== null) {
            $body .= " AND display.DisplayID NOT IN ";
            $body .= "       (SELECT display.DisplayID ";
            $body .= "       FROM    display ";
            $body .= "               INNER JOIN lkdisplaydg ";
            $body .= "               ON      lkdisplaydg.DisplayID = display.DisplayID ";
            $body .= "   WHERE  lkdisplaydg.DisplayGroupID   = :excludeDisplayGroupId ";
            $body .= "       )";
            $params['excludeDisplayGroupId'] = $parsedBody->getInt('exclude_displaygroupid');
        }

        // Media ID - direct assignment
        if ($parsedBody->getInt('mediaId') !== null) {
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

            $params['mediaId'] = $parsedBody->getInt('mediaId');
        }

        // Tags
        if ($parsedBody->getString('tags') != '') {
            $tagFilter = $parsedBody->getString('tags');

            if (trim($tagFilter) === '--no-tag') {
                $body .= ' AND `displaygroup`.displaygroupId NOT IN (
                    SELECT `lktagdisplaygroup`.displaygroupId
                     FROM tag
                        INNER JOIN `lktagdisplaygroup`
                        ON `lktagdisplaygroup`.tagId = tag.tagId
                    )
                ';
            } else {
                $operator = $parsedBody->getCheckbox('exactTags') == 1 ? '=' : 'LIKE';
                $logicalOperator = $parsedBody->getString('logicalOperator', ['default' => 'OR']);
                $allTags = explode(',', $tagFilter);
                $notTags = [];
                $tags = [];

                foreach ($allTags as $tag) {
                    if (str_starts_with($tag, '-')) {
                        $notTags[] = ltrim(($tag), '-');
                    } else {
                        $tags[] = $tag;
                    }
                }

                if (!empty($notTags)) {
                    $body .= ' AND `displaygroup`.displaygroupId NOT IN (
                    SELECT `lktagdisplaygroup`.displaygroupId
                      FROM tag
                        INNER JOIN `lktagdisplaygroup`
                        ON `lktagdisplaygroup`.tagId = tag.tagId
                    ';

                    $this->tagFilter(
                        $notTags,
                        'lktagdisplaygroup',
                        'lkTagDisplayGroupId',
                        'displayGroupId',
                        $logicalOperator,
                        $operator,
                        true,
                        $body,
                        $params
                    );
                }

                if (!empty($tags)) {
                    $body .= ' AND `displaygroup`.displaygroupId IN (
                                SELECT `lktagdisplaygroup`.displaygroupId
                                  FROM tag
                                    INNER JOIN `lktagdisplaygroup`
                                    ON `lktagdisplaygroup`.tagId = tag.tagId
                                ';

                    $this->tagFilter(
                        $tags,
                        'lktagdisplaygroup',
                        'lkTagDisplayGroupId',
                        'displayGroupId',
                        $logicalOperator,
                        $operator,
                        false,
                        $body,
                        $params
                    );
                }
            }
        }

        // run the special query to help sort by displays already assigned to this display group,
        // or by displays assigned to this syncGroup
        // we want to run it only if we're sorting by member column.
        if (in_array('`member`', $sortOrder) || in_array('`member` DESC', $sortOrder)) {
            $members = [];

            // DisplayGroup members with provided Display Group ID
            if ($parsedBody->getInt('displayGroupIdMembers') !== null) {
                $displayGroupId = $parsedBody->getInt('displayGroupIdMembers');

                foreach ($this->getStore()->select($select . $body, $params) as $row) {
                    $displayId = $this->getSanitizer($row)->getInt('displayId');

                    if ($this->getStore()->exists(
                        'SELECT display.display, display.displayId, displaygroup.displayGroupId
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
            } else if ($parsedBody->getInt('syncGroupIdMembers') !== null) {
                // Sync Group Members with provided Sync Group ID
                foreach ($this->getStore()->select($select . $body, $params) as $row) {
                    $displayId = $this->getSanitizer($row)->getInt('displayId');

                    if ($this->getStore()->exists(
                        'SELECT display.displayId 
                                FROM `display` 
                                WHERE `display`.syncGroupId = :syncGroupId
                                    AND `display`.displayId = :displayId',
                        [
                            'syncGroupId' => $parsedBody->getInt('syncGroupIdMembers'),
                            'displayId' => $displayId
                        ]
                    )) {
                        $members[] = $displayId;
                    }
                }
            }
        }

        // filter by commercial licence
        if ($parsedBody->getInt('commercialLicence') !== null) {
            $body .= ' AND display.commercialLicence = :commercialLicence ';
            $params['commercialLicence'] = $parsedBody->getInt('commercialLicence');
        }

        if ($parsedBody->getInt('folderId') !== null) {
            $body .= ' AND displaygroup.folderId = :folderId ';
            $params['folderId'] = $parsedBody->getInt('folderId');
        }

        if ($parsedBody->getInt('syncGroupId') !== null) {
            $body .= ' AND `display`.syncGroupId = :syncGroupId ';
            $params['syncGroupId'] = $parsedBody->getInt('syncGroupId');
        }

        if ($parsedBody->getInt('xmrRegistered') === 1) {
            $body .= ' AND `display`.xmrChannel IS NOT NULL ';
        } else if ($parsedBody->getInt('xmrRegistered') === 0) {
            $body .= ' AND `display`.xmrChannel IS NULL ';
        }

        // Player version supported
        if ($parsedBody->getInt('isPlayerSupported') !== null) {
            if ($parsedBody->getInt('isPlayerSupported') === 1) {
                $body .= ' AND `display`.client_code >= :playerSupport ';
            } else {
                $body .= ' AND `display`.client_code < :playerSupport ';
            }

            $params['playerSupport'] = Environment::$PLAYER_SUPPORT;
        }

        $this->viewPermissionSql(
            'Xibo\Entity\DisplayGroup',
            $body,
            $params,
            'displaygroup.displayGroupId',
            null,
            $filterBy,
            '`displaygroup`.permissionsFolderId'
        );

        // Sorting?
        $order = '';

        if (isset($members) && $members != []) {
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

        if (is_array($sortOrder) && (!in_array('`member`', $sortOrder) && !in_array('`member` DESC', $sortOrder))) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($parsedBody->hasParam('start') && $parsedBody->hasParam('length')) {
            $limit = ' LIMIT ' . $parsedBody->getInt('start', ['default' => 0])
                . ', ' . $parsedBody->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;
        $displayGroupIds = [];

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
                    'bandwidthLimit',
                    'countFaults',
                    'isMobile',
                    'isOutdoor'
                ],
                'stringProperties' => ['customId']
            ]);
            $display->overrideConfig = ($display->overrideConfig == '') ? [] : json_decode($display->overrideConfig, true);
            $displayGroupIds[] = $display->displayGroupId;
            $entries[] = $display;
        }

        // decorate with TagLinks
        if (count($entries) > 0) {
            $this->decorateWithTagLinks('lktagdisplaygroup', 'displayGroupId', $displayGroupIds, $entries);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            unset($params['entity']);
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}
