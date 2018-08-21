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
            $this->getLog()->debug('sortOrder ' . $sort);
            if ($sort == '`clientSort`') {
                $newSortOrder[] = '`clientType`';
                $newSortOrder[] = '`clientCode`';
                $newSortOrder[] = '`clientVersion`';
                continue;
            }

            if ($sort == '`clientSort` DESC') {
                $this->getLog()->debug('sortOrder ' . $sort);
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
                  display.version_instructions AS versionInstructions,
                  display.client_type AS clientType,
                  display.client_version AS clientVersion,
                  display.client_code AS clientCode,
                  display.displayProfileId,
                  display.screenShotRequested,
                  display.storageAvailableSpace,
                  display.storageTotalSpace,
                  displaygroup.displayGroupId,
                  displaygroup.description,
                  `display`.xmrChannel,
                  `display`.xmrPubKey,
                  `display`.lastCommandSuccess, 
                  `display`.deviceName , 
                  `display`.timeZone
              ';

        if ($this->getSanitizer()->getCheckbox('showTags', $filterBy) === 1 && DBVERSION >= 134) {
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
                $body .= ' AND `display`.displayProfileId = :displayProfileId ';
                $params['displayProfileId'] = $this->getSanitizer()->getInt('displayProfileId', $filterBy);
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

        if ($this->getSanitizer()->getString('clientAddress', $filterBy) != '') {
            $body .= ' AND display.clientaddress LIKE :clientAddress ';
            $params['clientAddress'] = '%' . $this->getSanitizer()->getString('clientAddress', $filterBy) . '%';
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
                               INNER JOIN `lkregionplaylist`
                               ON `lkregionplaylist`.playlistId = `widget`.playlistId
                               INNER JOIN `region`
                               ON `region`.regionId = `lkregionplaylist`.regionId
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
                $i = 0;
                foreach (explode(',', $tagFilter) as $tag) {
                    $i++;

                    if ($i == 1)
                        $body .= ' WHERE `tag` ' . $operator . ' :tags' . $i;
                    else
                        $body .= ' OR `tag` ' . $operator . ' :tags' . $i;

                    if ($operator === '=')
                        $params['tags' . $i] = $tag;
                    else
                        $params['tags' . $i] = '%' . $tag . '%';
                }

                $body .= " ) ";
            }
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