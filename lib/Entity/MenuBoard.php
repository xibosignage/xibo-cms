<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
use Xibo\Factory\MenuBoardCategoryFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

class MenuBoard implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Menu Board Id")
     * @var int
     */
    public $menuId;

    /**
     * @SWG\Property(description="The Menu Board name")
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(description="The Menu Board description")
     * @var string
     */
    public $description;

    /**
     * @SWG\Property(description="The Menu Board owner Id")
     * @var int
     */
    public $userId;
    public $owner;

    /**
     * @SWG\Property(description="The Id of the Folder this Menu Board belongs to")
     * @var string
     */
    public $folderId;

    /**
     * @SWG\Property(description="The id of the Folder responsible for providing permissions for this Menu Board")
     * @var int
     */
    public $permissionsFolderId;

    /**
     * @SWG\Property(description="A comma separated list of Groups/Users that have permission to this menu Board")
     * @var string
     */
    public $groupsWithPermissions;

    /**
     * @var Permission[]
     */
    private $permissions = [];
    private $categories;

    private $permissionFactory;
    private $menuBoardCategoryFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param PermissionFactory $permissionFactory
     * @param MenuBoardCategoryFactory $menuBoardCategoryFactory
     */
    public function __construct($store, $log, $permissionFactory, $menuBoardCategoryFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->permissionFactory = $permissionFactory;
        $this->menuBoardCategoryFactory = $menuBoardCategoryFactory;
    }

    public function __clone()
    {
        $this->menuId = null;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('MenuId %d, Name %s, Description %d', $this->menuId, $this->name, $this->description);
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->menuId;
    }

    public function getPermissionFolderId()
    {
        return $this->permissionsFolderId;
    }

    /**
     * Get the OwnerId
     * @return int
     */
    public function getOwnerId()
    {
        return $this->userId;
    }

    /**
     * Sets the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->userId = $ownerId;
    }

    /**
     * @param array $options
     * @return MenuBoard
     * @throws NotFoundException
     */
    public function load($options = [])
    {
        $options = array_merge([
            'loadPermissions' => true,
            'loadCategories' => false
        ], $options);

        // If we are already loaded, then don't do it again
        if ($this->menuId == null || $this->loaded) {
            return $this;
        }

        // Permissions
        if ($options['loadPermissions']) {
            $this->permissions = $this->permissionFactory->getByObjectId('MenuBoard', $this->menuId);
        }

        if ($options['loadCategories']) {
            $this->categories = $this->menuBoardCategoryFactory->getByMenuId($this->menuId);
        }

        $this->loaded = true;

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (!v::stringType()->notEmpty()->validate($this->name)) {
            throw new InvalidArgumentException(__('Name cannot be empty'), 'name');
        }
    }

    /**
     * Save this Menu Board
     * @param array $options
     * @throws InvalidArgumentException
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true,
        ], $options);

        $this->getLog()->debug('Saving ' . $this);

        if ($options['validate']) {
            $this->validate();
        }

        if ($this->menuId == null || $this->menuId == 0) {
            $this->add();
            $this->loaded = true;
        } else {
            $this->update();
        }
    }

    private function add()
    {
        $this->menuId = $this->getStore()->insert('INSERT INTO `menu_board` (name, description, userId, folderId, permissionsFolderId) VALUES (:name, :description, :userId, :folderId, :permissionsFolderId)', [
            'name' => $this->name,
            'description' => $this->description,
            'userId' => $this->userId,
            'folderId' => ($this->folderId == null) ? 1 : $this->folderId,
            'permissionsFolderId' => ($this->permissionsFolderId == null) ? 1 : $this->permissionsFolderId
        ]);
    }

    private function update()
    {
        $this->getStore()->update('UPDATE `menu_board` SET name = :name, description = :description, userId = :userId, folderId = :folderId, permissionsFolderId = :permissionsFolderId WHERE menuId = :menuId', [
            'menuId' => $this->menuId,
            'name' => $this->name,
            'description' => $this->description,
            'userId' => $this->userId,
            'folderId' => $this->folderId,
            'permissionsFolderId' => $this->permissionsFolderId
        ]);
    }

    /**
     * Delete Menu Board
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function delete()
    {
        $this->load(['loadCategories' => true]);

        // Delete all permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->delete();
        }

        // Delete all
        /** @var MenuBoardCategory $category */
        foreach ($this->categories as $category) {
            $category->delete();
        }

        $this->getStore()->update('DELETE FROM `menu_board` WHERE menuId = :menuId', ['menuId' => $this->menuId]);
    }
}