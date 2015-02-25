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

        $sql = 'SELECT `permissionId`, `groupId`, `view`, `edit`, `delete` FROM `permission` INNER JOIN `permissionentity` ON `permissionentity`.entityId = permission.entityId WHERE entity = :entity AND objectId = :objectId';

        foreach (\PDOConnect::select($sql, array('entity' => $entity, 'objectId' => $objectId)) as $row) {
            $permission = new Permission();
            $permission->permissionId = $row['permissionId'];
            $permission->groupId = $row['groupId'];
            $permission->view = $row['view'];
            $permission->edit = $row['edit'];
            $permission->delete = $row['delete'];
            $permission->objectId = $objectId;
            $permission->entity = $entity;
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
SELECT `permission`.`permissionId`, `permission`.`groupId`, `permission`.`objectId`, `permission`.`view`, `permission`.`edit`, `permission`.`delete`
  FROM `permission`
    INNER JOIN `permissionentity`
    ON `permissionentity`.entityId = permission.entityId
    INNER JOIN `group`
    ON `group`.groupId = `permission`.groupId
    INNER JOIN `lkusergroup`
    ON `lkusergroup`.groupId = `group`.groupId
    INNER JOIN `user`
    ON lkusergroup.UserID = `user`.UserID
 WHERE entity = :entity
    AND (`user`.userId = :userId OR `group`.IsEveryone = 1)
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
        }

        return $permissions;
    }
}