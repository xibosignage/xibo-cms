<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

use Xibo\Entity\DisplayGroup;
use Xibo\Entity\User;
use Xibo\Helper\SanitizerService;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class DisplayGroupFactory
 * @package Xibo\Factory
 */
class DisplayGroupFactory extends BaseFactory
{
    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var TagFactory
     */
    private $tagFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param PermissionFactory $permissionFactory
     * @param TagFactory $tagFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $permissionFactory, $tagFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);

        $this->permissionFactory = $permissionFactory;
        $this->tagFactory = $tagFactory;
    }

    /**
     * @param int|null $userId
     * @param int $bandwidthLimit
     * @return DisplayGroup
     */
    public function create($userId = null, $bandwidthLimit = 0)
    {
        $displayGroup = $this->createEmpty();

        if ($userId === null) {
            $userId = $this->getUserFactory()->getSystemUser()->userId;
        }

        $displayGroup->userId = $userId;
        $displayGroup->bandwidthLimit = $bandwidthLimit;

        return $displayGroup;
    }

    /**
     * Create Empty
     * @return DisplayGroup
     */
    public function createEmpty()
    {
        return new DisplayGroup(
            $this->getStore(),
            $this->getLog(),
            $this,
            $this->permissionFactory,
            $this->tagFactory
        );
    }

    /**
     * @param int $displayGroupId
     * @return DisplayGroup
     * @throws NotFoundException
     */
    public function getById($displayGroupId)
    {
        $groups = $this->query(null, ['disableUserCheck' => 1, 'displayGroupId' => $displayGroupId, 'isDisplaySpecific' => -1]);

        if (count($groups) <= 0)
            throw new NotFoundException();

        return $groups[0];
    }

    /**
     * @param int $displayId
     * @return DisplayGroup[]
     * @throws NotFoundException
     */
    public function getByDisplayId($displayId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'displayId' => $displayId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get Display Groups by MediaId
     * @param int $mediaId
     * @return DisplayGroup[]
     * @throws NotFoundException
     */
    public function getByMediaId($mediaId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'mediaId' => $mediaId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get Display Groups by eventId
     * @param int $eventId
     * @return DisplayGroup[]
     * @throws NotFoundException
     */
    public function getByEventId($eventId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'eventId' => $eventId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get Display Groups by isDynamic
     * @param int $isDynamic
     * @return DisplayGroup[]
     * @throws NotFoundException
     */
    public function getByIsDynamic($isDynamic)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'isDynamic' => $isDynamic]);
    }

    /**
     * Get Display Groups by their ParentId
     * @param int $parentId
     * @return DisplayGroup[]
     * @throws NotFoundException
     */
    public function getByParentId($parentId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'parentId' => $parentId]);
    }

    /**
     * @param string $tag
     * @return DisplayGroup[]
     * @throws NotFoundException
     */
    public function getByTag($tag)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'tags' => $tag, 'exactTags' => 1, 'isDisplaySpecific' => 1]);
    }

    /**
     * Get Relationship Tree
     * @param $displayGroupId
     * @return DisplayGroup[]
     */
    public function getRelationShipTree($displayGroupId)
    {
        $tree = [];

        foreach ($this->getStore()->select('
            SELECT `displaygroup`.displayGroupId, `displaygroup`.displayGroup, depth, 1 AS level
              FROM `lkdgdg`
                INNER JOIN `displaygroup`
                ON `lkdgdg`.childId = `displaygroup`.displayGroupId
             WHERE `lkdgdg`.parentId = :displayGroupId AND displaygroup.isDynamic = 0
            UNION ALL
            SELECT `displaygroup`.displayGroupId, `displaygroup`.displayGroup, depth * -1, 0 AS level
              FROM `lkdgdg`
                INNER JOIN `displaygroup`
                ON `lkdgdg`.parentId = `displaygroup`.displayGroupId
             WHERE `lkdgdg`.childId = :displayGroupId AND `lkdgdg`.parentId <> :displayGroupId AND displaygroup.isDynamic = 0
            ORDER BY level, depth, displayGroup
        ', [
            'displayGroupId' => $displayGroupId
        ]) as $row) {
            $item = $this->createEmpty()->hydrate($row);
            $item->depth = intval($row['depth']);
            $item->level = intval($row['level']);
            $tree[] = $item;
        }

        return $tree;
    }

    /**
     * Get Display Groups assigned to Notifications
     * @param int $notificationId
     * @return array[DisplayGroup]
     * @throws NotFoundException
     */
    public function getByNotificationId($notificationId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'notificationId' => $notificationId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get by OwnerId
     * @param int $ownerId
     * @param int $isDisplaySpecific
     * @return DisplayGroup[]
     * @throws NotFoundException
     */
    public function getByOwnerId($ownerId, $isDisplaySpecific = 0)
    {
        return $this->query(null, ['userId' => $ownerId, 'isDisplaySpecific' => $isDisplaySpecific]);
    }

    /**
     * Set Bandwidth limit
     * @param int $bandwidthLimit
     * @param array $displayIds
     * @return DisplayGroup[]
     * @throws NotFoundException
     */
    public function setBandwidth($bandwidthLimit, $displayGroupIds)
    {
        $sql = 'UPDATE `displaygroup` SET bandwidthLimit = :bandwidthLimit WHERE displayGroupId IN (0';
        $params['bandwidthLimit'] = $bandwidthLimit;
        
        $i = 0;
        foreach ($displayGroupIds as $displayGroupId) {
            $i++;
            $sql .= ',:displayGroupId' . $i;
            $params['displayGroupId' . $i] = $displayGroupId;
        }
        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[DisplayGroup]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $parsedBody = $this->getSanitizer($filterBy);
        if ($sortOrder == null)
            $sortOrder = ['displayGroup'];

        $entries = [];
        $params = [];

        $select = '
            SELECT `displaygroup`.displayGroupId,
                `displaygroup`.displayGroup,
                `displaygroup`.isDisplaySpecific,
                `displaygroup`.description,
                `displaygroup`.isDynamic,
                `displaygroup`.dynamicCriteria,
                `displaygroup`.dynamicCriteriaTags,
                `displaygroup`.bandwidthLimit,
                `displaygroup`.createdDt,
                `displaygroup`.modifiedDt,
                `displaygroup`.userId,
                `displaygroup`.folderId,
                `displaygroup`.permissionsFolderId,
                (
                  SELECT GROUP_CONCAT(DISTINCT tag) 
                    FROM tag 
                      INNER JOIN lktagdisplaygroup 
                      ON lktagdisplaygroup.tagId = tag.tagId 
                   WHERE lktagdisplaygroup.displayGroupId = displaygroup.displayGroupID 
                  GROUP BY lktagdisplaygroup.displayGroupId
                ) AS tags,
                (
                  SELECT GROUP_CONCAT(IFNULL(value, \'NULL\')) 
                    FROM tag 
                      INNER JOIN lktagdisplaygroup 
                      ON lktagdisplaygroup.tagId = tag.tagId 
                   WHERE lktagdisplaygroup.displayGroupId = displaygroup.displayGroupID 
                  GROUP BY lktagdisplaygroup.displayGroupId
                ) AS tagValues  
        ';

        $body = '
              FROM `displaygroup`
        ';

        if ($parsedBody->getInt('mediaId') !== null) {
            $body .= '
                INNER JOIN lkmediadisplaygroup
                ON lkmediadisplaygroup.displayGroupId = `displaygroup`.displayGroupId
                    AND lkmediadisplaygroup.mediaId = :mediaId
            ';
            $params['mediaId'] = $parsedBody->getInt('mediaId');
        }

        if ($parsedBody->getInt('eventId') !== null) {
            $body .= '
                INNER JOIN `lkscheduledisplaygroup`
                ON `lkscheduledisplaygroup`.displayGroupId = `displaygroup`.displayGroupId
                    AND `lkscheduledisplaygroup`.eventId = :eventId
            ';
            $params['eventId'] = $parsedBody->getInt('eventId');
        }

        $body .= ' WHERE 1 = 1 ';

        // Always include Display specific Display Groups for DOOH.
        if ($parsedBody->getInt('disableUserCheck') == 0 && ($this->getUser()->userTypeId == 4 || ($this->getUser()->isSuperAdmin() && $this->getUser()->showContentFrom == 2))) {
            $body .= ' OR `displaygroup`.isDisplaySpecific = 1 ';
        }

        if ($parsedBody->getInt('displayGroupId') !== null) {
            $body .= ' AND displaygroup.displayGroupId = :displayGroupId ';
            $params['displayGroupId'] = $parsedBody->getInt('displayGroupId');
        }

        if ($parsedBody->getInt('parentId') !== null) {
            $body .= ' AND `displaygroup`.displayGroupId IN (SELECT `childId` FROM `lkdgdg` WHERE `parentId` = :parentId AND `depth` = 1) ';
            $params['parentId'] = $parsedBody->getInt('parentId');
        }

        if ($parsedBody->getInt('userId') !== null) {
            $body .= ' AND `displaygroup`.userId = :userId ';
            $params['userId'] = $parsedBody->getInt('userId');
        }

        if ($parsedBody->getInt('isDisplaySpecific', ['default' => 0]) != -1) {
            $body .= ' AND displaygroup.isDisplaySpecific = :isDisplaySpecific ';
            $params['isDisplaySpecific'] = $parsedBody->getInt('isDisplaySpecific', ['default' => 0]);
        }

        if ($parsedBody->getInt('isDynamic') !== null) {
            $body .= ' AND `displaygroup`.isDynamic = :isDynamic ';
            $params['isDynamic'] = $parsedBody->getInt('isDynamic');
        }
        if (!empty($parsedBody->getString('dynamicCriteria'))) {
            $body .= ' AND `displaygroup`.dynamicCriteria = :dynamicCriteria ';
            $params['dynamicCriteria'] = $parsedBody->getString('dynamicCriteria');
        }

        if ($parsedBody->getInt('displayId') !== null) {
            $body .= ' AND displaygroup.displayGroupId IN (SELECT displayGroupId FROM lkdisplaydg WHERE displayId = :displayId) ';
            $params['displayId'] = $parsedBody->getInt('displayId');
        }

        if ($parsedBody->getInt('nestedDisplayId') !== null) {
            $body .= ' 
                AND displaygroup.displayGroupId IN (
                    SELECT DISTINCT parentId
                      FROM `lkdgdg`
                        INNER JOIN `lkdisplaydg`
                        ON `lkdisplaydg`.displayGroupId = `lkdgdg`.childId 
                     WHERE displayId = :nestedDisplayId
                ) 
            ';
            $params['nestedDisplayId'] = $parsedBody->getInt('nestedDisplayId');
        }

        if ($parsedBody->getInt('notificationId') !== null) {
            $body .= ' AND displaygroup.displayGroupId IN (SELECT displayGroupId FROM `lknotificationdg` WHERE notificationId = :notificationId) ';
            $params['notificationId'] = $parsedBody->getInt('notificationId');
        }

        // Filter by DisplayGroup Name?
        if ($parsedBody->getString('displayGroup') != null) {
            $terms = explode(',', $parsedBody->getString('displayGroup'));
            $this->nameFilter('displaygroup', 'displayGroup', $terms, $body, $params, ($parsedBody->getCheckbox('useRegexForName') == 1));
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

        if ($parsedBody->getInt('displayGroupIdMembers') !== null) {
            $members = [];
            foreach ($this->getStore()->select($select . $body, $params) as $row) {
                $displayGroupId = $parsedBody->getInt($row['displayGroupId']);
                $parentId = $parsedBody->getInt('displayGroupIdMembers');

                if ($this->getStore()->exists('SELECT `childId` FROM `lkdgdg` WHERE `parentId` = :parentId AND `childId` = :childId AND `depth` = 1',
                    [
                        'parentId' => $parentId,
                        'childId' => $displayGroupId
                    ]
                )) {
                    $members[] = $displayGroupId;
                }
            }
        }

        if ($parsedBody->getInt('folderId') !== null) {
            $body .= ' AND `displaygroup`.folderId = :folderId ';
            $params['folderId'] = $parsedBody->getInt('folderId');
        }

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\DisplayGroup', $body, $params, '`displaygroup`.displayGroupId', '`displaygroup`.userId', $filterBy, '`displaygroup`.permissionsFolderId');

        // Sorting?
        $order = '';

        if (isset($members) && $members != []) {
            $sqlOrderMembers = 'ORDER BY FIELD(displaygroup.displayGroupId,' . implode(',', $members) . ')';

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
            $order .= ' ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $parsedBody->getInt('start') !== null && $parsedBody->getInt('length') !== null) {
            $limit = ' LIMIT ' . intval($parsedBody->getInt('start'), 0) . ', ' . $parsedBody->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['intProperties' => ['isDisplaySpecific', 'isDynamic']]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}