<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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

use Carbon\Carbon;
use Respect\Validation\Validator as v;
use Stash\Interfaces\PoolInterface;
use Xibo\Factory\MenuBoardCategoryFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Helper\SanitizerService;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * @SWG\Definition()
 */
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
     * @SWG\Property(description="The Menu Board code identifier")
     * @var string
     */
    public $code;

    /**
     * @SWG\Property(description="The Menu Board owner Id")
     * @var int
     */
    public $userId;
    public $owner;

    /**
     * @SWG\Property(description="The Menu Board last modified date")
     * @var int
     */
    public $modifiedDt;

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

    /** @var  SanitizerService */
    private $sanitizerService;

    /** @var PoolInterface */
    private $pool;

    /** @var  ConfigServiceInterface */
    private $config;

    /**
     * @var Permission[]
     */
    private $permissions = [];
    private $categories;

    /** @var PermissionFactory */
    private $permissionFactory;

    /** @var MenuBoardCategoryFactory */
    private $menuBoardCategoryFactory;

    /** @var DisplayNotifyServiceInterface */
    private $displayNotifyService;

    private $datesToFormat = ['modifiedDt'];

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param SanitizerService $sanitizerService
     * @param PoolInterface $pool
     * @param ConfigServiceInterface $config
     * @param PermissionFactory $permissionFactory
     * @param MenuBoardCategoryFactory $menuBoardCategoryFactory
     * @param DisplayNotifyServiceInterface $displayNotifyService
     */
    public function __construct(
        $store,
        $log,
        $dispatcher,
        $sanitizerService,
        $pool,
        $config,
        $permissionFactory,
        $menuBoardCategoryFactory,
        $displayNotifyService
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
        $this->sanitizerService = $sanitizerService;
        $this->config = $config;
        $this->pool = $pool;
        $this->permissionFactory = $permissionFactory;
        $this->menuBoardCategoryFactory = $menuBoardCategoryFactory;
        $this->displayNotifyService = $displayNotifyService;
    }

    /**
     * @param $array
     * @return \Xibo\Support\Sanitizer\SanitizerInterface
     */
    protected function getSanitizer($array)
    {
        return $this->sanitizerService->getSanitizer($array);
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
        return sprintf('MenuId %d, Name %s, Description %s, Code %s', $this->menuId, $this->name, $this->description, $this->code);
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
            'audit' => true
        ], $options);

        if ($options['audit']) {
            $this->getLog()->debug('Saving ' . $this);
        }

        if ($options['validate']) {
            $this->validate();
        }

        if ($this->menuId == null || $this->menuId == 0) {
            $this->add();
            $this->loaded = true;
        } else {
            $this->update();
        }

        // We've been touched
        $this->setActive();

        // Notify Displays?
        $this->notify();
    }

    /**
     * Is this MenuBoard active currently
     * @return bool
     */
    public function isActive()
    {
        $cache = $this->pool->getItem('/menuboard/accessed/' . $this->menuId);
        return $cache->isHit();
    }

    /**
     * Indicate that this MenuBoard has been accessed recently
     * @return $this
     */
    public function setActive()
    {
        $this->getLog()->debug('Setting ' . $this->menuId . ' as active');

        $cache = $this->pool->getItem('/menuboard/accessed/' . $this->menuId);
        $cache->set('true');
        $cache->expiresAfter(intval($this->config->getSetting('REQUIRED_FILES_LOOKAHEAD')) * 1.5);
        $this->pool->saveDeferred($cache);
        return $this;
    }

    /**
     * Get the Display Notify Service
     * @return DisplayNotifyServiceInterface
     */
    public function getDisplayNotifyService(): DisplayNotifyServiceInterface
    {
        return $this->displayNotifyService->init();
    }

    /**
     * Notify displays of this campaign change
     */
    public function notify()
    {
        $this->getLog()->debug('MenuBoard ' . $this->menuId . ' wants to notify');

        $this->getDisplayNotifyService()->collectNow()->notifyByMenuBoardId($this->menuId);
    }

    private function add()
    {
        $this->menuId = $this->getStore()->insert(
            'INSERT INTO `menu_board` (name, description, code, userId, modifiedDt, folderId, permissionsFolderId) VALUES (:name, :description, :code, :userId, :modifiedDt, :folderId, :permissionsFolderId)',
            [
                'name' => $this->name,
                'description' => $this->description,
                'code' => $this->code,
                'userId' => $this->userId,
                'modifiedDt' => Carbon::now()->format('U'),
                'folderId' => ($this->folderId == null) ? 1 : $this->folderId,
                'permissionsFolderId' => ($this->permissionsFolderId == null) ? 1 : $this->permissionsFolderId
            ]
        );
    }

    private function update()
    {
        $this->getStore()->update(
            'UPDATE `menu_board` SET name = :name, description = :description, code = :code, userId = :userId, modifiedDt = :modifiedDt, folderId = :folderId, permissionsFolderId = :permissionsFolderId WHERE menuId = :menuId',
            [
                'menuId' => $this->menuId,
                'name' => $this->name,
                'description' => $this->description,
                'code' => $this->code,
                'userId' => $this->userId,
                'modifiedDt' => Carbon::now()->format('U'),
                'folderId' => $this->folderId,
                'permissionsFolderId' => $this->permissionsFolderId
            ]
        );
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
