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


use Xibo\Entity\Permission;
use Xibo\Entity\User;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class PermissionFactory
 * @package Xibo\Factory
 */
class PermissionFactory extends BaseFactory
{
    /**
     * Create Empty
     * @return Permission
     */
    public function createEmpty()
    {
        return new Permission(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher()
        );
    }

    /**
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function getEntityId(string $entity): int
    {
        // Lookup the entityId
        $results = $this->getStore()->select('SELECT `entityId` FROM `permissionentity` WHERE `entity` = :entity', [
            'entity' => $entity,
        ]);

        if (count($results) <= 0) {
            throw new InvalidArgumentException(__('Entity not found: ') . $entity);
        }

        return intval($results[0]['entityId']);
    }

    /**
     * Create a new Permission
     * @param int $groupId
     * @param string $entity
     * @param int $objectId
     * @param int $view
     * @param int $edit
     * @param int $delete
     * @return Permission
     * @throws InvalidArgumentException
     */
    public function create($groupId, $entity, $objectId, $view, $edit, $delete)
    {
        $permission = $this->createEmpty();
        $permission->groupId = $groupId;
        $permission->entityId = $this->getEntityId($entity);
        $permission->objectId = $objectId;
        $permission->view  =$view;
        $permission->edit = $edit;
        $permission->delete = $delete;

        return $permission;
    }

    /**
     * Create a new Permission
     * @param UserGroupFactory $userGroupFactory
     * @param string $entity
     * @param int $objectId
     * @param int $view
     * @param int $edit
     * @param int $delete
     * @return Permission
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function createForEveryone($userGroupFactory, $entity, $objectId, $view, $edit, $delete)
    {
        // Lookup the entityId
        $results = $this->getStore()->select('SELECT entityId FROM permissionentity WHERE entity = :entity', ['entity' => $entity]);

        if (count($results) <= 0) {
            throw new InvalidArgumentException(__('Entity not found: ') . $entity);
        }

        $permission = $this->createEmpty();
        $permission->groupId = $userGroupFactory->getEveryone()->groupId;
        $permission->entityId = $results[0]['entityId'];
        $permission->objectId = $objectId;
        $permission->view  =$view;
        $permission->edit = $edit;
        $permission->delete = $delete;

        return $permission;
    }

    /**
     * Get Permissions by Entity ObjectId
     * @param string $entity
     * @param int $objectId
     * @return Permission[]
     */
    public function getByObjectId($entity, $objectId)
    {
        $permissions = array();

        $sql = '
            SELECT `permissionId`, `groupId`, `view`, `edit`, `delete`, permissionentity.entityId
              FROM `permission`
                INNER JOIN `permissionentity`
                ON `permissionentity`.entityId = permission.entityId
             WHERE entity = :entity
                AND objectId = :objectId
        ';

        $params = array('entity' => $entity, 'objectId' => $objectId);

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $permission = $this->createEmpty()->hydrate($row, [
                'intProperties' => ['view', 'edit', 'delete'],
            ]);
            $permission->objectId = $objectId;
            $permission->entity = $entity;

            $permissions[] = $permission;
        }

        return $permissions;
    }

    /**
     * Get All Permissions by Entity ObjectId
     * @param User $user
     * @param string $entity
     * @param int $objectId
     * @param array[string] $sortOrder
     * @param array[mixed] $filterBy
     * @return Permission[]
     * @throws NotFoundException
     */
    public function getAllByObjectId($user, $entity, $objectId, $sortOrder = null, $filterBy = [])
    {
        // Look up the entityId for any add operation that might occur
        $entityId = $this->getStore()->select('SELECT entityId FROM permissionentity WHERE entity = :entity', ['entity' => $entity]);

        $sanitizedFilter = $this->getSanitizer($filterBy);

        if (count($entityId) <= 0) {
            throw new NotFoundException(__('Entity not found'));
        }

        $entityId = $entityId[0]['entityId'];

        $permissions = [];
        $params = ['entityId' => $entityId, 'objectId' => $objectId];

        // SQL gets all Groups/User Specific Groups for non-retired users
        // then it joins them to the permission table for the object specified
        $select = 'SELECT `permissionId`, joinedGroup.`groupId`, `view`, `edit`, `delete`, joinedGroup.isuserspecific, joinedGroup.group ';
        $body = '  FROM (
                SELECT `group`.*
                  FROM `group`
                 WHERE IsUserSpecific = 0 ';

        // Permissions for the group section
        if ($sanitizedFilter->getCheckbox('disableUserCheck') == 0) {
            // Normal users can only see their group
            if ($user->userTypeId != 1) {
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
                $params['currentUserId'] = $user->userId;
            }
        }

        $body .= '
                UNION ALL
                SELECT `group`.*
                  FROM `group`
                    INNER JOIN lkusergroup
                    ON lkusergroup.GroupID = group.GroupID
                        AND IsUserSpecific = 1
                    INNER JOIN `user`
                    ON lkusergroup.UserID = user.UserID
                        AND retired = 0 ';

        // Permissions for the user section
        if ($sanitizedFilter->getCheckbox('disableUserCheck') == 0) {
            // Normal users can only see themselves
            if ($user->userTypeId == 3) {
                $body .= ' AND `user`.userId = :currentUserId ';
                $params['currentUserId'] = $user->userId;
            }
            // Group admins can only see users from their groups.
            else if ($user->userTypeId == 2) {
                $body .= '
                    AND user.userId IN (
                        SELECT `otherUserLinks`.userId
                          FROM `lkusergroup`
                            INNER JOIN `group`
                            ON `group`.groupId = `lkusergroup`.groupId
                                AND `group`.isUserSpecific = 0
                            INNER JOIN `lkusergroup` `otherUserLinks`
                            ON `otherUserLinks`.groupId = `group`.groupId
                         WHERE `lkusergroup`.userId = :currentUserId
                    )
                ';
                $params['currentUserId'] = $user->userId;
            }
        }

        $body .= '
            ) joinedGroup
        ';

        if ($sanitizedFilter->getInt('setOnly', ['default' => 0]) == 1) {
            $body .= ' INNER JOIN ';
        } else {
            $body .= ' LEFT OUTER JOIN ';
        }

        $body .= '
             `permission`
            ON `permission`.groupId = joinedGroup.groupId
              AND objectId = :objectId
              AND entityId = :entityId
         WHERE 1 = 1
        ';

        if ($sanitizedFilter->getString('name') != null) {
            $body .= ' AND joinedGroup.group LIKE :name ';
            $params['name'] = '%' . $sanitizedFilter->getString('name') . '%';
        }

        $order = '';
        if ($sortOrder == null) {
            $order = 'ORDER BY joinedGroup.isEveryone DESC, joinedGroup.isUserSpecific, joinedGroup.`group`';
        } else if (is_array($sortOrder)) {
            $order = 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $row['entityId'] = $entityId;
            $row['entity'] = $entity;
            $row['objectId'] = $objectId;
            $permissions[] = $this->createEmpty()->hydrate($row, [
                'intProperties' => ['view', 'edit', 'delete', 'isUser'],
            ]);
        }

        // Paging
        if ($limit != '' && count($permissions) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $permissions;
    }

    /**
     * Gets all permissions for a user group
     * @param string $entity
     * @param int $groupId
     * @return Permission[]
     */
    public function getByGroupId($entity, $groupId)
    {
        $permissions = [];

        $sql = '
            SELECT `permission`.`permissionId`,
                   `permission`.`groupId`,
                   `permission`.`objectId`,
                   `permission`.`view`,
                   `permission`.`edit`,
                   `permission`.`delete`,
                   `permissionentity`.`entityId`
              FROM `permission`
                INNER JOIN `permissionentity`
                ON `permissionentity`.entityId = permission.entityId
                INNER JOIN `group`
                ON `group`.groupId = `permission`.groupId
             WHERE entity = :entity
                AND `permission`.`groupId` = :groupId
        ';
        $params = ['entity' => 'Xibo\Entity\\' . $entity, 'groupId' => $groupId];

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $row['entity'] = $entity;
            $permissions[] = $this->createEmpty()->hydrate($row, [
                'intProperties' => ['view', 'edit', 'delete'],
            ]);
        }

        return $permissions;
    }

    /**
     * Gets all permissions for a set of user groups
     * @param string $entity
     * @param int $userId
     * @return Permission[]
     */
    public function getByUserId($entity, $userId): array
    {
        $permissions = [];

        $sql = '
            SELECT `permission`.`permissionId`, 
                `permission`.`groupId`, 
                `permission`.`objectId`, 
                `permission`.`view`, 
                `permission`.`edit`, 
                `permission`.`delete`, 
                `permissionentity`.`entityId`
              FROM `permission`
                INNER JOIN `permissionentity`
                ON `permissionentity`.entityId = permission.entityId
                INNER JOIN `group`
                ON `group`.groupId = `permission`.groupId
                INNER JOIN `lkusergroup`
                ON `lkusergroup`.groupId = `group`.groupId
                INNER JOIN `user`
                ON lkusergroup.UserID = `user`.UserID
             WHERE `permissionentity`.entity = :entity 
                AND `user`.userId = :userId
            UNION
            SELECT `permission`.`permissionId`, 
                `permission`.`groupId`, 
                `permission`.`objectId`, 
                `permission`.`view`, 
                `permission`.`edit`, 
                `permission`.`delete`, 
                `permissionentity`.entityId
              FROM `permission`
                INNER JOIN `permissionentity`
                ON `permissionentity`.entityId = permission.entityId
                INNER JOIN `group`
                ON `group`.groupId = `permission`.groupId
             WHERE `permissionentity`.entity = :entity 
                AND `group`.IsEveryone = 1
        ';
        $params = ['entity' => $entity, 'userId' => $userId];

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $row['entity'] = $entity;
            $permissions[] = $this->createEmpty()->hydrate($row, [
                'intProperties' => ['view', 'edit', 'delete'],
            ]);
        }

        return $permissions;
    }

    /**
     * Get Full Permissions
     * @return Permission
     */
    public function getFullPermissions(): Permission
    {
        $permission = $this->createEmpty();
        $permission->view = 1;
        $permission->edit = 1;
        $permission->delete = 1;
        $permission->modifyPermissions = 1;
        return $permission;
    }
}
