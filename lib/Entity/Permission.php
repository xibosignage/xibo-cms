<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Permission.php) is part of Xibo.
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


namespace Xibo\Entity;


class Permission
{
    public $permissionId;
    public $entityId;
    public $groupId;
    public $objectId;
    public $isUser;

    public $entity;
    public $objectIdString;
    public $group;

    public $view;
    public $edit;
    public $delete;

    public $modifyPermissions;

    public function __clone()
    {
        $this->permissionId = null;
    }

    public function save()
    {
        if ($this->permissionId == null || $this->permissionId == 0)
            $this->add();
        else
            $this->update();
    }

    private function add()
    {
        $this->permissionId = \Xibo\Storage\PDOConnect::insert('INSERT INTO `permission` (`entityId`, `groupId`, `objectId`, `view`, `edit`, `delete`) VALUES (:entityId, :groupId, :objectId, :view, :edit, :delete)', array(
            'entityId' => $this->entityId,
            'objectId' => $this->objectId,
            'groupId' => $this->groupId,
            'view' => $this->view,
            'edit' => $this->edit,
            'delete' => $this->delete,
        ));
    }

    private function update()
    {
        \Xibo\Storage\PDOConnect::update('UPDATE `permission` SET `view` = :view, `edit` = :edit, `delete` = :delete WHERE `entityId` = :entityId AND `groupId` = :groupId AND `objectId` = :objectId', array(
            'entityId' => $this->entityId,
            'objectId' => $this->objectId,
            'groupId' => $this->groupId,
            'view' => $this->view,
            'edit' => $this->edit,
            'delete' => $this->delete,
        ));
    }

    public function delete()
    {
        \Xibo\Storage\PDOConnect::update('DELETE FROM `permission` WHERE entityId = :entityId AND objectId = :objectId AND groupId = :groupId', array(
            'entityId' => $this->entityId,
            'objectId' => $this->objectId,
            'groupId' => $this->groupId
        ));
    }

    public function deleteAll()
    {
        \Xibo\Storage\PDOConnect::update('DELETE FROM `permission` WHERE entityId = :entityId AND objectId = :objectId', array(
            'entityId' => $this->entityId,
            'objectId' => $this->objectId,
        ));
    }
}