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
     * @param array[int] $groupIds
     * @return array[Permission]
     */
    public static function getByGroupIds($entity, $groupIds)
    {

    }

    /**
     * Create a Full Access Permission
     * @param $entity
     * @param $groupId
     * @param $objectId
     * @return Permission
     */
    public static function createFullAccess($entity, $groupId, $objectId)
    {
        $permission = new Permission();
        $permission->entity = $entity;
        $permission->objectId = $objectId;
        $permission->groupId = $groupId;
        $permission->view = 1;
        $permission->edit = 1;
        $permission->delete = 1;
        return $permission;
    }
}