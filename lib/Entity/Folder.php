<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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

namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class Folder
 * @package Xibo\Entity
 * @SWG\Definition()
 */
class Folder implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID of this Folder")
     * @var int
     */
    public $id;

    /**
     * @SWG\Property(description="The type of folder (home or root)")
     * @var string
     */
    public $type;

    /**
     * @SWG\Property(description="The name of this Folder")
     * @var string
     */
    public $text;

    /**
     * @SWG\Property(description="The folderId of the parent of this Folder")
     * @var int
     */
    public $parentId;

    /**
     * @SWG\Property(description="Flag indicating whether this is root Folder")
     * @var int
     */
    public $isRoot;

    /**
     * @SWG\Property(description="An array of children folderIds")
     * @var string
     */
    public $children;

    public $permissionsFolderId;

    /** @var FolderFactory */
    private $folderFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    private $permissions = [];

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param FolderFactory $folderFactory
     * @param PermissionFactory $permissionFactory
     */
    public function __construct($store, $log, $dispatcher, $folderFactory, $permissionFactory)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
        $this->setPermissionsClass('Xibo\Entity\Folder');
        $this->folderFactory = $folderFactory;
        $this->permissionFactory = $permissionFactory;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getPermissionFolderId()
    {
        return $this->permissionsFolderId;
    }

    /**
     * When you set ACL on a folder the permissionsFolderId on the folder record is set to null
     * any objects inside this folder get the permissionsFolderId set to this folderId
     * @return int
     */
    public function getPermissionFolderIdOrThis(): int
    {
        return $this->permissionsFolderId == null ? $this->id : $this->permissionsFolderId;
    }

    /**
     * Get Owner Id
     * @return int
     */
    public function getOwnerId()
    {
        return -1;
    }

    public function getParentId()
    {
        return $this->parentId;
    }

    public function isRoot(): bool
    {
        return $this->isRoot === 1;
    }

    public function getChildren()
    {
        return explode(',', $this->children);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (!v::stringType()->notEmpty()->length(1, 254)->validate($this->text)) {
            throw new InvalidArgumentException(__('Folder needs to have a name, between 1 and 254 characters.'), 'folderName');
        }

        if (empty($this->parentId)) {
            throw new InvalidArgumentException(__('Folder needs a specified parent Folder id'), 'parentId');
        }
    }

    public function load()
    {
        if ($this->loaded || $this->id == null) {
            return;
        }

        // Permissions
        $this->permissions = $this->permissionFactory->getByObjectId(get_class($this), $this->id);
        $this->loaded = true;
    }

    /**
     * @param bool $validate
     * @throws InvalidArgumentException
     */
    public function save($validate = true)
    {
        if ($validate) {
            $this->validate();
        }

        if ($this->id == null || $this->id == 0) {
            $this->add();
        } else {
            $this->edit();
        }
    }

    public function delete()
    {
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->delete();
        }

        $this->manageChildren('delete');

        $this->getStore()->update('DELETE FROM `folder` WHERE folderId = :folderId', [
            'folderId' => $this->id
        ]);
    }

    private function add()
    {
        $parent = $this->folderFactory->getById($this->parentId);

        $this->id = $this->getStore()->insert('INSERT INTO `folder` (folderName, parentId, isRoot, permissionsFolderId) VALUES (:folderName, :parentId, :isRoot, :permissionsFolderId)',
            [
                'folderName' => $this->text,
                'parentId' => $this->parentId,
                'isRoot' => 0,
                'permissionsFolderId' => ($parent->permissionsFolderId == null) ? $this->parentId : $parent->permissionsFolderId
            ]);

        $this->manageChildren('add');
    }

    private function edit()
    {
        $this->getStore()->update('UPDATE `folder` SET folderName = :folderName, parentId = :parentId WHERE folderId = :folderId',
            [
                'folderId' => $this->id,
                'folderName' => $this->text,
                'parentId' => $this->parentId
            ]);
    }

    /**
     * Manages folder tree structure
     *
     * If mode delete is passed then it will remove selected folder and all its children down the tree
     * Then update children property on parent accordingly
     *
     * On add mode we just add this folder id to parent children property
     *
     * @param $mode
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    private function manageChildren($mode)
    {
        $parent = $this->folderFactory->getById($this->parentId);
        $parentChildren = array_filter(explode(',', $parent->children ?? ''));
        $children = array_filter(explode(',', $this->children ?? ''));

        if ($mode === 'delete') {
            // remove this folder from children of the parent
            foreach ($parentChildren as $index => $child) {
                if ((int)$child === (int)$this->id) {
                    unset($parentChildren[$index]);
                }
            }

            // remove this folder children
            foreach ($children as $child) {
                $childObject = $this->folderFactory->getById($child);
                $childObject->manageChildren('delete');
                $this->getStore()->update('DELETE FROM `folder` WHERE folderId = :folderId', [
                    'folderId' => $childObject->id
                ]);
            }
        } else {
            $parentChildren[] = $this->id;
        }

        $updatedChildren = implode(',', array_filter($parentChildren));

        $this->getStore()->update('UPDATE `folder` SET children = :children WHERE folderId = :folderId', [
                'folderId' => $this->parentId,
                'children' => $updatedChildren
            ]);
    }

    /**
     * Manages folder permissions
     *
     * When permissions are added on folder, this starts new ACL from that folder and is cascaded down to all folders under this folder
     * permissionsFolderId is also updated on all relevant objects that are in this folder or under this folder in folder tree structure
     *
     * When permissions are removed from a folder, this sets the permissionsFolderId to parent folderId (or parent permissionsFolderId)
     * same is cascaded down the folder tree and all relevant objects
     *
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function managePermissions()
    {
        // this function happens after permissions are inserted into permission table
        // with that we can look up if there are any permissions for edited folder and act accordingly.
        $permissionExists = $this->getStore()->exists('SELECT permissionId FROM permission INNER JOIN permissionentity ON permission.entityId = permissionentity.entityId WHERE objectId = :folderId AND permissionentity.entity = :folderEntity', [
            'folderId' => $this->id,
            'folderEntity' => 'Xibo\Entity\Folder'
        ]);

        if ($permissionExists) {
            // if we added/edited permission on this folder, then new ACL starts here, cascade this folderId as permissionFolderId to all children
            $this->getStore()->update('UPDATE `folder` SET permissionsFolderId = NULL WHERE folderId = :folderId', [
                'folderId' => $this->id
            ]);
            $permissionFolderId = $this->id;
        } else {
            // if there are no permissions for this folder, basically reset the permissions on this folder and its children
            if ($this->id === 1 && $this->isRoot()) {
                $permissionFolderId = 1;
            } else {
                $parent = $this->folderFactory->getById($this->parentId);
                $permissionFolderId = ($parent->permissionsFolderId == null) ? $parent->id : $parent->permissionsFolderId;
            }

            $this->getStore()->update('UPDATE `folder` SET permissionsFolderId = :permissionsFolderId WHERE folderId = :folderId', [
                'folderId' => $this->id,
                'permissionsFolderId' => $permissionFolderId
            ]);
        }

        $this->updateChildObjects($permissionFolderId, $this->id);


        $this->manageChildPermissions($permissionFolderId);
    }

    /**
     * Helper recursive function to make sure all folders under the edited parent folder have correct permissionsFolderId set on them
     * along with all relevant objects in those folders.
     *
     *
     * @param $permissionFolderId
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    private function manageChildPermissions($permissionFolderId)
    {
        $children = array_filter(explode(',', $this->children ?? ''));

        foreach ($children as $child) {

            $this->updateChildObjects($permissionFolderId, $child);

            $childObject = $this->folderFactory->getById($child);
            $childObject->manageChildPermissions($permissionFolderId);
        }
    }

    private function updateChildObjects($permissionFolderId, $folderId)
    {
        $this->getStore()->update('UPDATE `folder` SET permissionsFolderId = :permissionsFolderId WHERE parentId = :folderId', [
            'permissionsFolderId' => $permissionFolderId,
            'folderId' => $folderId
        ]);

        $this->getStore()->update('UPDATE `media` SET permissionsFolderId = :permissionsFolderId WHERE folderId = :folderId', [
            'permissionsFolderId' => $permissionFolderId,
            'folderId' => $folderId
        ]);

        $this->getStore()->update('UPDATE `campaign` SET permissionsFolderId = :permissionsFolderId WHERE folderId = :folderId', [
            'permissionsFolderId' => $permissionFolderId,
            'folderId' => $folderId
        ]);

        $this->getStore()->update('UPDATE `displaygroup` SET permissionsFolderId = :permissionsFolderId WHERE folderId = :folderId', [
            'permissionsFolderId' => $permissionFolderId,
            'folderId' => $folderId
        ]);

        $this->getStore()->update('UPDATE `dataset` SET permissionsFolderId = :permissionsFolderId WHERE folderId = :folderId', [
            'permissionsFolderId' => $permissionFolderId,
            'folderId' => $folderId
        ]);

        $this->getStore()->update('UPDATE `playlist` SET permissionsFolderId = :permissionsFolderId WHERE folderId = :folderId', [
            'permissionsFolderId' => $permissionFolderId,
            'folderId' => $folderId
        ]);

        $this->getStore()->update('UPDATE `menu_board` SET permissionsFolderId = :permissionsFolderId WHERE folderId = :folderId', [
            'permissionsFolderId' => $permissionFolderId,
            'folderId' => $folderId
        ]);

        $this->getStore()->update('UPDATE `syncgroup` SET permissionsFolderId = :permissionsFolderId WHERE folderId = :folderId', [
            'permissionsFolderId' => $permissionFolderId,
            'folderId' => $folderId
        ]);
    }

    /**
     * Update old parent, new parent records with adjusted children
     * Update current folders records with new parent, permissionsFolderId
     * Recursively go through the current folder's children folder and objects and adjust permissionsFolderId if needed.
     * @param int $oldParentFolder
     * @param int $newParentFolder
     */
    public function updateFoldersAfterMove(int $oldParentFolderId, int $newParentFolderId)
    {
        $oldParentFolder = $this->folderFactory->getById($oldParentFolderId, 0);
        $newParentFolder = $this->folderFactory->getById($newParentFolderId, 0);

        // new parent folder that adopted this folder, adjust children
        $newParentChildren = array_filter(explode(',', $newParentFolder->children));
        $newParentChildren[] = $this->id;
        $newParentUpdatedChildren = implode(',', array_filter($newParentChildren));
        $this->getStore()->update('UPDATE `folder` SET children = :children WHERE folderId = :folderId', [
            'folderId' => $newParentFolder->id,
            'children' => $newParentUpdatedChildren
        ]);

        // old parent that gave this folder for adoption, adjust children
        $oldParentChildren = array_filter(explode(',', $oldParentFolder->children));
        foreach ($oldParentChildren as $index => $child) {
            if ((int)$child === $this->id) {
                unset($oldParentChildren[$index]);
            }
        }

        $oldParentUpdatedChildren = implode(',', array_filter($oldParentChildren));

        $this->getStore()->update('UPDATE `folder` SET children = :children WHERE folderId = :folderId', [
            'folderId' => $oldParentFolder->id,
            'children' => $oldParentUpdatedChildren
        ]);

        // if we had permissions set on this folder, then permissionsFolderId stays as it was
        if ($this->getPermissionFolderId() !== null) {
            $this->permissionsFolderId = $newParentFolder->getPermissionFolderIdOrThis();
            $this->manageChildPermissions($this->permissionsFolderId);
        }

        $this->getStore()->update('UPDATE `folder` SET parentId = :parentId, permissionsFolderId = :permissionsFolderId WHERE folderId = :folderId', [
            'parentId' => $newParentFolder->id,
            'permissionsFolderId' => $this->permissionsFolderId,
            'folderId' => $this->id
        ]);
    }

    /**
     * We do not allow moving a parent Folder inside of one of its sub-folders
     * If that's what was requested, throw an error
     * @param int $newParentFolderId
     * @return bool
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function isTheSameBranch(int $newParentFolderId): bool
    {
        $children = array_filter(explode(',', $this->children ?? ''));
        $found = false;

        foreach ($children as $child) {
            if ((int)$child === $newParentFolderId) {
                $found = true;
                break;
            }
            $childObject = $this->folderFactory->getById($child);
            $childObject->isTheSameBranch($newParentFolderId);
        }

        return $found;
    }

    /**
     * Check if this folder is used as Home Folder for any existing Users
     * @return bool
     */
    public function isHome(): bool
    {
        $userIds = $this->getStore()->select('SELECT userId FROM `user` WHERE `user`.homeFolderId = :folderId', [
            'folderId' => $this->id
        ]);

        return count($userIds) > 0;
    }
}
