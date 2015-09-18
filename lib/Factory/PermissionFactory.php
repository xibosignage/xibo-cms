<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (PermissionFactory.php) is part of Xibo.
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
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class PermissionFactory extends BaseFactory
{
    /**
     * Create a new Permission
     * @param int $groupId
     * @param string $entity
     * @param int $objectId
     * @param int $view
     * @param int $edit
     * @param int $delete
     * @return Permission
     */
    public static function create($groupId, $entity, $objectId, $view, $edit, $delete)
    {
        // Lookup the entityId
        $results = PDOConnect::select('SELECT entityId FROM permissionentity WHERE entity = :entity', ['entity' => $entity]);

        if (count($results) <= 0)
            throw new \InvalidArgumentException('Entity not found: ' . $entity);

        $permission = new Permission();
        $permission->groupId = $groupId;
        $permission->entityId = $results[0]['entityId'];
        $permission->objectId = $objectId;
        $permission->view  =$view;
        $permission->edit = $edit;
        $permission->delete = $delete;

        return $permission;
    }

    /**
     * Create a new Permission
     * @param string $entity
     * @param int $objectId
     * @param int $view
     * @param int $edit
     * @param int $delete
     * @return Permission
     */
    public static function createForEveryone($entity, $objectId, $view, $edit, $delete)
    {
        // Lookup the entityId
        $results = PDOConnect::select('SELECT entityId FROM permissionentity WHERE entity = :entity', ['entity' => $entity]);

        if (count($results) <= 0)
            throw new \InvalidArgumentException('Entity not found: ' . $entity);

        $permission = new Permission();
        $permission->groupId = UserGroupFactory::getEveryone()->groupId;
        $permission->entityId = $results[0]['entityId'];
        $permission->objectId = $objectId;
        $permission->view  =$view;
        $permission->edit = $edit;
        $permission->delete = $delete;

        return $permission;
    }

    /**
     * Create Permissions for new Entity
     * @param User $user
     * @param string $entity
     * @param int $objectId
     * @param string $level
     * @return array[Permission]
     */
    public static function createForNewEntity($user, $entity, $objectId, $level)
    {
        $permissions = [];

        switch ($level) {

            case 'public':
                $permissions[] = PermissionFactory::createForEveryone($entity, $objectId, 1, 0, 0);
                break;

            case 'group':
                foreach ($user->groups as $group) {
                    $permission = PermissionFactory::create($group->groupId, $entity, $objectId, 1, 0, 0);
                    $permission->save();
                }
                break;

            case 'private':
                break;

            default:
                throw new \InvalidArgumentException(__('Unknown Permissions Level: ' . $level));
        }

        return $permissions;
    }

    /**
     * Get Permissions by Entity ObjectId
     * @param string $entity
     * @param int $objectId
     * @return array[Permission]
     */
    public static function getByObjectId($entity, $objectId)
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
        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $permission = new Permission();
            $permission->permissionId = $row['permissionId'];
            $permission->groupId = $row['groupId'];
            $permission->view = $row['view'];
            $permission->edit = $row['edit'];
            $permission->delete = $row['delete'];
            $permission->objectId = $objectId;
            $permission->entity = $entity;
            $permission->entityId = $row['entityId'];

            $permissions[] = $permission;
        }

        return $permissions;
    }

    /**
     * Get All Permissions by Entity ObjectId
     * @param string $entity
     * @param int $objectId
     * @param array[string] $sortOrder
     * @param array[mixed] $filterBy
     * @return array[Permission]
     * @throws NotFoundException
     */
    public static function getAllByObjectId($entity, $objectId, $sortOrder = null, $filterBy = null)
    {
        // Look up the entityId for any add operation that might occur
        $entityId = PDOConnect::select('SELECT entityId FROM permissionentity WHERE entity = :entity', array('entity' => $entity));

        if (count($entityId) <= 0)
            throw new NotFoundException(__('Entity not found'));

        $entityId = $entityId[0]['entityId'];

        $permissions = array();
        $params = array('entityId' => $entityId, 'objectId' => $objectId);

        // SQL gets all Groups/User Specific Groups for non-retired users
        // then it joins them to the permission table for the object specified
        $select = 'SELECT `permissionId`, joinedGroup.`groupId`, `view`, `edit`, `delete`, joinedGroup.isuserspecific, joinedGroup.group ';
        $body = '  FROM (
                SELECT `group`.*
                  FROM `group`
                 WHERE IsUserSpecific = 0 ';

        // Permissions for the group section
        if (Sanitize::getCheckbox('disableUserCheck', 0, $filterBy) == 0) {
            // Normal users can only see their group
            if (self::getUser()->userTypeId != 1) {
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
                $params['currentUserId'] = self::getUser()->userId;
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
        if (Sanitize::getCheckbox('disableUserCheck', 0, $filterBy) == 0) {
            // Normal users can only see themselves
            if (self::getUser()->userTypeId == 3) {
                $filterBy['userId'] = self::getUser()->userId;
            }
            // Group admins can only see users from their groups.
            else if (self::getUser()->userTypeId == 2) {
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
                $params['currentUserId'] = self::getUser()->userId;
            }
        }

        $body .= '
            ) joinedGroup
            LEFT OUTER JOIN `permission`
            ON `permission`.groupId = joinedGroup.groupId
              AND objectId = :objectId
              AND entityId = :entityId
         WHERE 1 = 1
        ';

        if (Sanitize::getString('name', $filterBy) != null) {
            $body .= ' AND joinedGroup.group LIKE :name ';
            $params['name'] = '%' . Sanitize::getString('name', $filterBy) . '%';
        }

        $order = '';
        if ($sortOrder == null)
            $order = 'ORDER BY joinedGroup.isEveryone DESC, joinedGroup.isUserSpecific, joinedGroup.`group`';
        else if (is_array($sortOrder))
            $order = 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if (Sanitize::getInt('start', $filterBy) !== null && Sanitize::getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval(Sanitize::getInt('start'), 0) . ', ' . Sanitize::getInt('length', 10);
        }

        $sql = $select . $body . $order . $limit;

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $permission = new Permission();
            $permission->permissionId = $row['permissionId'];
            $permission->groupId = $row['groupId'];
            $permission->view = $row['view'];
            $permission->edit = $row['edit'];
            $permission->delete = $row['delete'];
            $permission->objectId = $objectId;
            $permission->entity = $entity;
            $permission->entityId = $entityId;
            $permission->isUser = $row['isuserspecific'];
            $permission->group = \Xibo\Helper\Sanitize::string($row['group']);

            $permissions[] = $permission;
        }

        // Paging
        if ($limit != '' && count($permissions) > 0) {
            $results = PDOConnect::select('SELECT COUNT(*) AS total ' . $body, $params);
            self::$_countLast = intval($results[0]['total']);
        }

        return $permissions;
    }

    /**
     * Gets all permissions for a user group
     * @param string $entity
     * @param int $groupId
     * @return array[Permission]
     */
    public static function getByGroupId($entity, $groupId)
    {
        $permissions = array();

        $sql = '
            SELECT `permission`.`permissionId`, `permission`.`groupId`, `permission`.`objectId`, `permission`.`view`, `permission`.`edit`, `permission`.`delete`, permissionentity.entityId
              FROM `permission`
                INNER JOIN `permissionentity`
                ON `permissionentity`.entityId = permission.entityId
                INNER JOIN `group`
                ON `group`.groupId = `permission`.groupId
             WHERE entity = :entity
                AND `permission`.`groupId` = :groupId
        ';
        $params = array('entity' => 'Xibo\Entity\\' . $entity, 'groupId' => $groupId);

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $permission = new Permission();
            $permission->permissionId = $row['permissionId'];
            $permission->groupId = $row['groupId'];
            $permission->view = $row['view'];
            $permission->edit = $row['edit'];
            $permission->delete = $row['delete'];
            $permission->objectId = $row['objectId'];
            $permission->entity = $entity;
            $permission->entityId = $row['entityId'];

            $permissions[] = $permission;
        }

        return $permissions;
    }

    /**
     * Gets all permissions for a set of user groups
     * @param string $entity
     * @param int $userId
     * @return array[Permission]
     */
    public static function getByUserId($entity, $userId)
    {
        $permissions = array();

        $sql = '
SELECT `permission`.`permissionId`, `permission`.`groupId`, `permission`.`objectId`, `permission`.`view`, `permission`.`edit`, `permission`.`delete`, permissionentity.entityId
  FROM `permission`
    INNER JOIN `permissionentity`
    ON `permissionentity`.entityId = permission.entityId
    INNER JOIN `group`
    ON `group`.groupId = `permission`.groupId
    LEFT OUTER JOIN `lkusergroup`
    ON `lkusergroup`.groupId = `group`.groupId
    LEFT OUTER JOIN `user`
    ON lkusergroup.UserID = `user`.UserID
      AND `user`.userId = :userId
 WHERE entity = :entity
    AND (`user`.userId IS NOT NULL OR `group`.IsEveryone = 1)
';
        $params = array('entity' => $entity, 'userId' => $userId);

        \Xibo\Helper\Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $permission = new Permission();
            $permission->permissionId = $row['permissionId'];
            $permission->groupId = $row['groupId'];
            $permission->view = $row['view'];
            $permission->edit = $row['edit'];
            $permission->delete = $row['delete'];
            $permission->objectId = $row['objectId'];
            $permission->entity = $entity;
            $permission->entityId = $row['entityId'];

            $permissions[] = $permission;
        }

        return $permissions;
    }

    /**
     * Get Full Permissions
     * @return Permission
     */
    public static function getFullPermissions()
    {
        $permission = new Permission();
        $permission->view = 1;
        $permission->edit = 1;
        $permission->delete = 1;
        $permission->modifyPermissions = 1;
        return $permission;
    }
}