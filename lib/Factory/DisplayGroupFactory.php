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
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param PermissionFactory $permissionFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $permissionFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);

        $this->permissionFactory = $permissionFactory;
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
            $this->permissionFactory
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
     * Get Display Groups assigned to Notifications
     * @param int $notificationId
     * @return array[DisplayGroup]
     */
    public function getByNotificationId($notificationId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'notificationId' => $notificationId, 'isDisplaySpecific' => -1]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[DisplayGroup]
     */
    public function query($sortOrder = null, $filterBy = null)
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
                `displaygroup`.userId
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

        if ($this->getSanitizer()->getInt('isDisplaySpecific', 0, $filterBy) != -1) {
            $body .= ' AND displaygroup.isDisplaySpecific = :isDisplaySpecific ';
            $params['isDisplaySpecific'] = $this->getSanitizer()->getInt('isDisplaySpecific', 0, $filterBy);
        }

        if ($this->getSanitizer()->getInt('isDynamic', $filterBy) !== null) {
            $body .= ' AND `displaygroup`.isDynamic = :isDynamic ';
            $params['isDynamic'] = $this->getSanitizer()->getInt('isDynamic', $filterBy);
        }

        if ($this->getSanitizer()->getInt('displayId', $filterBy) !== null) {
            $body .= ' AND displaygroup.displayGroupId IN (SELECT displayGroupId FROM lkdisplaydg WHERE displayId = :displayId) ';
            $params['displayId'] = $this->getSanitizer()->getInt('displayId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('notificationId', $filterBy) !== null) {
            $body .= ' AND displaygroup.displayGroupId IN (SELECT displayGroupId FROM `lknotificationdg` WHERE notificationId = :notificationId) ';
            $params['notificationId'] = $this->getSanitizer()->getInt('notificationId', $filterBy);
        }

        // Filter by DisplayGroup Name?
        if ($this->getSanitizer()->getString('displayGroup', $filterBy) != null) {
            // convert into a space delimited array
            $names = explode(' ', $this->getSanitizer()->getString('displayGroup', $filterBy));

            $i = 0;
            foreach ($names as $searchName) {
                $i++;
                // Not like, or like?
                if (substr($searchName, 0, 1) == '-') {
                    $body .= " AND  `displaygroup`.displayGroup NOT LIKE :search$i ";
                    $params['search' . $i] = '%' . ltrim(($searchName), '-') . '%';
                }
                else {
                    $body .= " AND  `displaygroup`.displayGroup LIKE :search$i ";
                    $params['search' . $i] = '%' . $searchName . '%';
                }
            }
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}