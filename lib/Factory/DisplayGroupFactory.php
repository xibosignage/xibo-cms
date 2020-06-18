<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayGroup.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\DisplayGroup;
use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

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
     * @param SanitizerServiceInterface $sanitizerService
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
     * @return array[DisplayGroup]
     */
    public function getByDisplayId($displayId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'displayId' => $displayId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get Display Groups by MediaId
     * @param int $mediaId
     * @return array[DisplayGroup]
     */
    public function getByMediaId($mediaId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'mediaId' => $mediaId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get Display Groups by eventId
     * @param int $eventId
     * @return array[DisplayGroup]
     */
    public function getByEventId($eventId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'eventId' => $eventId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get Display Groups by isDynamic
     * @param int $isDynamic
     * @return array[DisplayGroup]
     */
    public function getByIsDynamic($isDynamic)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'isDynamic' => $isDynamic]);
    }

    /**
     * Get Display Groups by their ParentId
     * @param int $parentId
     * @return array[DisplayGroup]
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
     */
    public function getByNotificationId($notificationId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'notificationId' => $notificationId, 'isDisplaySpecific' => -1]);
    }

    /**
     * Get by OwnerId
     * @param int $ownerId
     * @return DisplayGroup[]
     */
    public function getByOwnerId($ownerId)
    {
        return $this->query(null, ['userId' => $ownerId, 'isDisplaySpecific' => 0]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[DisplayGroup]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
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
                `displaygroup`.userId,
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

        if ($this->getSanitizer()->getInt('mediaId', $filterBy) !== null) {
            $body .= '
                INNER JOIN lkmediadisplaygroup
                ON lkmediadisplaygroup.displayGroupId = `displaygroup`.displayGroupId
                    AND lkmediadisplaygroup.mediaId = :mediaId
            ';
            $params['mediaId'] = $this->getSanitizer()->getInt('mediaId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('eventId', $filterBy) !== null) {
            $body .= '
                INNER JOIN `lkscheduledisplaygroup`
                ON `lkscheduledisplaygroup`.displayGroupId = `displaygroup`.displayGroupId
                    AND `lkscheduledisplaygroup`.eventId = :eventId
            ';
            $params['eventId'] = $this->getSanitizer()->getInt('eventId', $filterBy);
        }

        $body .= ' WHERE 1 = 1 ';

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\DisplayGroup', $body, $params, '`displaygroup`.displayGroupId', '`displaygroup`.userId', $filterBy);

        if ($this->getSanitizer()->getInt('displayGroupId', $filterBy) !== null) {
            $body .= ' AND displaygroup.displayGroupId = :displayGroupId ';
            $params['displayGroupId'] = $this->getSanitizer()->getInt('displayGroupId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('parentId', $filterBy) !== null) {
            $body .= ' AND `displaygroup`.displayGroupId IN (SELECT `childId` FROM `lkdgdg` WHERE `parentId` = :parentId AND `depth` = 1) ';
            $params['parentId'] = $this->getSanitizer()->getInt('parentId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('userId', $filterBy) !== null) {
            $body .= ' AND `displaygroup`.userId = :userId ';
            $params['userId'] = $this->getSanitizer()->getInt('userId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('isDisplaySpecific', 0, $filterBy) != -1) {
            $body .= ' AND displaygroup.isDisplaySpecific = :isDisplaySpecific ';
            $params['isDisplaySpecific'] = $this->getSanitizer()->getInt('isDisplaySpecific', 0, $filterBy);
        }

        if ($this->getSanitizer()->getInt('isDynamic', $filterBy) !== null) {
            $body .= ' AND `displaygroup`.isDynamic = :isDynamic ';
            $params['isDynamic'] = $this->getSanitizer()->getInt('isDynamic', $filterBy);
        }

        if ($this->getSanitizer()->getString('dynamicCriteria', $filterBy) !== null) {
            $body .= ' AND `displaygroup`.dynamicCriteria = :dynamicCriteria ';
            $params['dynamicCriteria'] = $this->getSanitizer()->getString('dynamicCriteria', $filterBy);
        }

        if ($this->getSanitizer()->getInt('displayId', $filterBy) !== null) {
            $body .= ' AND displaygroup.displayGroupId IN (SELECT displayGroupId FROM lkdisplaydg WHERE displayId = :displayId) ';
            $params['displayId'] = $this->getSanitizer()->getInt('displayId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('nestedDisplayId', $filterBy) !== null) {
            $body .= ' 
                AND displaygroup.displayGroupId IN (
                    SELECT DISTINCT parentId
                      FROM `lkdgdg`
                        INNER JOIN `lkdisplaydg`
                        ON `lkdisplaydg`.displayGroupId = `lkdgdg`.childId 
                     WHERE displayId = :nestedDisplayId
                ) 
            ';
            $params['nestedDisplayId'] = $this->getSanitizer()->getInt('nestedDisplayId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('notificationId', $filterBy) !== null) {
            $body .= ' AND displaygroup.displayGroupId IN (SELECT displayGroupId FROM `lknotificationdg` WHERE notificationId = :notificationId) ';
            $params['notificationId'] = $this->getSanitizer()->getInt('notificationId', $filterBy);
        }

        // Filter by DisplayGroup Name?
        if ($this->getSanitizer()->getString('displayGroup', $filterBy) != null) {
            $terms = explode(',', $this->getSanitizer()->getString('displayGroup', $filterBy));
            $this->nameFilter('displaygroup', 'displayGroup', $terms, $body, $params, ($this->getSanitizer()->getCheckbox('useRegexForName', $filterBy) == 1));
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

        if ($this->getSanitizer()->getInt('displayGroupIdMembers', $filterBy) !== null) {
            $members = [];
            foreach ($this->getStore()->select($select . $body, $params) as $row) {
                $displayGroupId = $this->getSanitizer()->int($row['displayGroupId']);
                $parentId = $this->getSanitizer()->getInt('displayGroupIdMembers', $filterBy);

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
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
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