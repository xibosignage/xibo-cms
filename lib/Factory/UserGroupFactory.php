<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UserGroupFactory.php)
 */


namespace Xibo\Factory;


use Xibo\Entity\User;
use Xibo\Entity\UserGroup;
use Xibo\Exception\NotFoundException;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class UserGroupFactory
 * @package Xibo\Factory
 */
class UserGroupFactory extends BaseFactory
{
    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);

        $this->setAclDependencies($user, $userFactory);
    }

    /**
     * Create Empty User Group Object
     * @return UserGroup
     */
    public function createEmpty()
    {
        return new UserGroup($this->getStore(), $this->getLog(), $this, $this->getUserFactory());
    }

    /**
     * Create User Group
     * @param $userGroup
     * @param $libraryQuota
     * @return UserGroup
     */
    public function create($userGroup, $libraryQuota)
    {
        $group = $this->createEmpty();
        $group->group = $userGroup;
        $group->libraryQuota = $libraryQuota;

        return $group;
    }

    /**
     * Get by Group Id
     * @param int $groupId
     * @return UserGroup
     * @throws NotFoundException
     */
    public function getById($groupId)
    {
        $groups = $this->query(null, ['disableUserCheck' => 1, 'groupId' => $groupId, 'isUserSpecific' => -1]);

        if (count($groups) <= 0)
            throw new NotFoundException(__('Group not found'));

        return $groups[0];
    }

    /**
     * Get by Group Name
     * @param string $group
     * @param int $isUserSpecific
     * @return UserGroup
     * @throws NotFoundException
     */
    public function getByName($group, $isUserSpecific = 0)
    {
        $groups = $this->query(null, ['disableUserCheck' => 1, 'exactGroup' => $group, 'isUserSpecific' => $isUserSpecific]);

        if (count($groups) <= 0)
            throw new NotFoundException(__('Group not found'));

        return $groups[0];
    }

    /**
     * Get Everyone Group
     * @return UserGroup
     * @throws NotFoundException
     */
    public function getEveryone()
    {
        $groups = $this->query(null, ['disableUserCheck' => 1, 'isEveryone' => 1]);

        if (count($groups) <= 0)
            throw new NotFoundException(__('Group not found'));

        return $groups[0];
    }

    /**
     * Get isSystemNotification Group
     * @return UserGroup[]
     */
    public function getSystemNotificationGroups()
    {
        return $this->query(null, ['disableUserCheck' => 1, 'isSystemNotification' => 1, 'isUserSpecific' => -1]);
    }

    /**
     * Get isDisplayNotification Group
     * @param int|null $displayGroupId Optionally provide a displayGroupId to restrict to view permissions.
     * @return UserGroup[]
     */
    public function getDisplayNotificationGroups($displayGroupId = null)
    {
        return $this->query(null, [
            'disableUserCheck' => 1,
            'isDisplayNotification' => 1,
            'isUserSpecific' => -1,
            'displayGroupId' => $displayGroupId
        ]);
    }

    /**
     * Get by User Id
     * @param int $userId
     * @return array[UserGroup]
     * @throws NotFoundException
     */
    public function getByUserId($userId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'userId' => $userId, 'isUserSpecific' => 0]);
    }

    /**
     * Get User Groups assigned to Notifications
     * @param int $notificationId
     * @return array[UserGroup]
     */
    public function getByNotificationId($notificationId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'notificationId' => $notificationId, 'isUserSpecific' => -1]);
    }

    /**
     * Get by Display Group
     * @param int $displayGroupId
     * @return UserGroup[]
     */
    public function getByDisplayGroupId($displayGroupId)
    {
        return $this->query(null, ['disableUserCheck' => 1, 'displayGroupId' => $displayGroupId]);
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return UserGroup[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = array();
        $params = array();

        if ($sortOrder === null)
            $sortOrder = ['`group`'];

        $select = '
        SELECT 	`group`.group,
            `group`.groupId,
            `group`.isUserSpecific,
            `group`.isEveryone,
            `group`.libraryQuota,
            `group`.isSystemNotification,
            `group`.isDisplayNotification
        ';

        $body = '
          FROM `group`
         WHERE 1 = 1
        ';

        // Permissions
        if ($this->getSanitizer()->getCheckbox('disableUserCheck', 0, $filterBy) == 0) {
            // Normal users can only see their group
            if ($this->getUser()->userTypeId != 1) {
                $body .= '
                AND `group`.groupId IN (
                    SELECT `group`.groupId
                      FROM `lkusergroup`
                        INNER JOIN `group`
                        ON `group`.groupId = `lkusergroup`.groupId
                            AND `group`.isUserSpecific = 0
                     WHERE `lkusergroup`.userId = :currentUserId
                )
                ';
                $params['currentUserId'] = $this->getUser()->userId;
            }
        }

        // Filter by Group Id
        if ($this->getSanitizer()->getInt('groupId', $filterBy) !== null) {
            $body .= ' AND `group`.groupId = :groupId ';
            $params['groupId'] = $this->getSanitizer()->getInt('groupId', $filterBy);
        }

        // Filter by Group Name
        if ($this->getSanitizer()->getString('group', $filterBy) != null) {
            $terms = explode(',', $this->getSanitizer()->getString('group', $filterBy));
            $this->nameFilter('group', 'group', $terms, $body, $params, ($this->getSanitizer()->getCheckbox('useRegexForName', $filterBy) == 1));
        }

        if ($this->getSanitizer()->getString('exactGroup', $filterBy) != null) {
            $body .= ' AND `group`.group = :exactGroup ';
            $params['exactGroup'] = $this->getSanitizer()->getString('exactGroup', $filterBy);
        }

        // Filter by User Id
        if ($this->getSanitizer()->getInt('userId', $filterBy) !== null) {
            $body .= ' AND `group`.groupId IN (SELECT groupId FROM `lkusergroup` WHERE userId = :userId) ';
            $params['userId'] = $this->getSanitizer()->getInt('userId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('isUserSpecific', $filterBy) != -1) {
            $body .= ' AND isUserSpecific = :isUserSpecific ';
            $params['isUserSpecific'] = $this->getSanitizer()->getInt('isUserSpecific', 0, $filterBy);
        }

        if ($this->getSanitizer()->getInt('isEveryone', $filterBy) != -1) {
            $body .= ' AND isEveryone = :isEveryone ';
            $params['isEveryone'] = $this->getSanitizer()->getInt('isEveryone', 0, $filterBy);
        }

        if ($this->getSanitizer()->getInt('isSystemNotification', $filterBy) !== null) {
            $body .= ' AND isSystemNotification = :isSystemNotification ';
            $params['isSystemNotification'] = $this->getSanitizer()->getInt('isSystemNotification', $filterBy);
        }

        if ($this->getSanitizer()->getInt('isDisplayNotification', $filterBy) !== null) {
            $body .= ' AND isDisplayNotification = :isDisplayNotification ';
            $params['isDisplayNotification'] = $this->getSanitizer()->getInt('isDisplayNotification', $filterBy);
        }

        if ($this->getSanitizer()->getInt('notificationId', $filterBy) !== null) {
            $body .= ' AND `group`.groupId IN (SELECT groupId FROM `lknotificationgroup` WHERE notificationId = :notificationId) ';
            $params['notificationId'] = $this->getSanitizer()->getInt('notificationId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('displayGroupId', $filterBy) !== null) {
            $body .= ' 
                AND `group`.groupId IN (
                    SELECT DISTINCT `permission`.groupId
                      FROM `permission`
                        INNER JOIN `permissionentity`
                        ON `permissionentity`.entityId = permission.entityId
                            AND `permissionentity`.entity = \'Xibo\\Entity\\DisplayGroup\'
                     WHERE `permission`.objectId = :displayGroupId
                        AND `permission`.view = 1
                )
            ';
            $params['displayGroupId'] = $this->getSanitizer()->getInt('displayGroupId', $filterBy);
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
                    'isUserSpecific', 'isEveryone', 'libraryQuota', 'isSystemNotification', 'isDisplayNotification'
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