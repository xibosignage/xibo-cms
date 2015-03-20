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
use Xibo\Exception\NotFoundException;

class PermissionFactory
{
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
        //\Debug::sql($sql, $params);

        foreach (\PDOConnect::select($sql, $params) as $row) {
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
     * @return array[Permission]
     * @throws NotFoundException
     */
    public static function getAllByObjectId($entity, $objectId)
    {
        // Look up the entityId for any add operation that might occur
        $entityId = \Xibo\Storage\PDOConnect::select('SELECT entityId FROM permissionentity WHERE entity = :entity', array('entity' => $entity));

        if (count($entityId) <= 0)
            throw new NotFoundException(__('Entity not found'));

        $entityId = $entityId[0]['entityId'];

        $permissions = array();

        $sql = '
SELECT `permissionId`, joinedGroup.`groupId`, `view`, `edit`, `delete`, joinedGroup.isuserspecific, joinedGroup.group
  FROM (
        SELECT `group`.*
          FROM `group`
         WHERE IsUserSpecific = 0
        UNION ALL
        SELECT `group`.*
          FROM `group`
            INNER JOIN lkusergroup
            ON lkusergroup.GroupID = group.GroupID
                AND IsUserSpecific = 1
            INNER JOIN `user`
            ON lkusergroup.UserID = user.UserID
                AND retired = 0
    ) joinedGroup
    LEFT OUTER JOIN `permission`
    ON `permission`.groupId = joinedGroup.groupId
      AND objectId = :objectId
      AND entityId = :entityId
ORDER BY joinedGroup.IsEveryone DESC, joinedGroup.IsUserSpecific, joinedGroup.`Group`
';
        $params = array('entityId' => $entityId, 'objectId' => $objectId);

        \Debug::sql($sql, $params);

        foreach (\PDOConnect::select($sql, $params) as $row) {
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
            $permission->group = \Kit::ValidateParam($row['group'], _STRING);

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

        \Debug::sql($sql, $params);

        foreach (\PDOConnect::select($sql, $params) as $row) {
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