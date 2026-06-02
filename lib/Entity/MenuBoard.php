<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

use Carbon\Carbon;
use OpenApi\Attributes as OA;
use Respect\Validation\Validator as v;
use Stash\Interfaces\PoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
 * Class MenuBoard
 * @package Xibo\Entity
 */
#[OA\Schema]
class MenuBoard implements \JsonSerializable
{
    use EntityTrait;

    #[OA\Property(description: 'The Menu Board Id')]
    public $menuId;

    #[OA\Property(description: 'The Menu Board name')]
    public $name;

    #[OA\Property(description: 'The Menu Board description')]
    public $description;

    #[OA\Property(description: 'The Menu Board code identifier')]
    public $code;

    #[OA\Property(description: 'The Menu Board owner Id')]
    public $userId;
    public $owner;

    #[OA\Property(description: 'The Menu Board last modified date')]
    public $modifiedDt;

    #[OA\Property(description: 'The Id of the Folder this Menu Board belongs to')]
    public $folderId;

    #[OA\Property(description: 'The id of the Folder responsible for providing permissions for this Menu Board')]
    public $permissionsFolderId;

    #[OA\Property(description: 'A comma separated list of Groups/Users that have permission to this menu Board')]
    public $groupsWithPermissions;

    /** @var Permission[] */
    private array $permissions = [];
    private $categories;

    private $datesToFormat = ['modifiedDt'];

    public function __construct(
        StorageServiceInterface $store,
        LogServiceInterface $log,
        EventDispatcherInterface $dispatcher,
        private readonly SanitizerService $sanitizerService,
        private readonly PoolInterface $pool,
        private readonly ConfigServiceInterface $config,
        private readonly PermissionFactory $permissionFactory,
        private readonly MenuBoardCategoryFactory $menuBoardCategoryFactory,
        private readonly DisplayNotifyServiceInterface $displayNotifyService,
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    protected function getSanitizer($array)
    {
        return $this->sanitizerService->getSanitizer($array);
    }

    public function __clone(): void
    {
        $this->menuId = null;
    }

    public function __toString(): string
    {
        return sprintf(
            'MenuId %d, Name %s, Description %s, Code %s',
            $this->menuId,
            $this->name,
            $this->description,
            $this->code
        );
    }

    public function getId(): int
    {
        return $this->menuId;
    }

    public function getPermissionFolderId(): int
    {
        return $this->permissionsFolderId;
    }

    public function getOwnerId(): int
    {
        return $this->userId;
    }

    public function setOwner(int $ownerId): void
    {
        $this->userId = $ownerId;
    }

    /**
     * @param array $options
     * @return MenuBoard
     */
    public function load(array $options = []): self
    {
        $options = array_merge([
            'loadPermissions' => true,
            'loadCategories' => false
        ], $options);

        if ($this->menuId == null || $this->loaded) {
            return $this;
        }

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
    public function validate(): void
    {
        if (!v::stringType()->notEmpty()->validate($this->name)) {
            throw new InvalidArgumentException(__('Name cannot be empty'), 'name');
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function save(array $options = []): void
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

        $this->setActive();
        $this->notify();
    }

    public function copyWithCascade(string $name, ?string $description, ?string $code, int $userId): self
    {
        $newBoard = clone $this;
        $newBoard->name = $name;
        $newBoard->description = $description;
        $newBoard->code = $code;
        $newBoard->userId = $userId;
        $newBoard->save();

        foreach ($this->menuBoardCategoryFactory->getByMenuId($this->menuId) as $category) {
            $category->copyWithCascade($newBoard->menuId);
        }

        return $newBoard;
    }

    public function isActive(): bool
    {
        $cache = $this->pool->getItem('/menuboard/accessed/' . $this->menuId);
        return $cache->isHit();
    }

    public function setActive(): self
    {
        $this->getLog()->debug('Setting ' . $this->menuId . ' as active');

        $cache = $this->pool->getItem('/menuboard/accessed/' . $this->menuId);
        $cache->set('true');
        $cache->expiresAfter(intval($this->config->getSetting('REQUIRED_FILES_LOOKAHEAD')) * 1.5);
        $this->pool->saveDeferred($cache);
        return $this;
    }

    public function getDisplayNotifyService(): DisplayNotifyServiceInterface
    {
        return $this->displayNotifyService->init();
    }

    public function notify(): void
    {
        $this->getLog()->debug('MenuBoard ' . $this->menuId . ' wants to notify');
        $this->getDisplayNotifyService()->collectNow()->notifyByMenuBoardId($this->menuId);
    }

    private function add(): void
    {
        $this->menuId = $this->getStore()->insert(
            'INSERT INTO `menu_board` (name, description, code, userId, modifiedDt, folderId, permissionsFolderId) 
                    VALUES (:name, :description, :code, :userId, :modifiedDt, :folderId, :permissionsFolderId)',
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

    private function update(): void
    {
        $this->getStore()->update(
            'UPDATE `menu_board` SET 
                        name = :name,
                        description = :description,
                        code = :code,
                        userId = :userId,
                        modifiedDt = :modifiedDt,
                        folderId = :folderId,
                        permissionsFolderId = :permissionsFolderId
                    WHERE menuId = :menuId',
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
     * @throws NotFoundException
     */
    public function delete(): void
    {
        $this->load(['loadCategories' => true]);

        foreach ($this->permissions as $permission) {
            $permission->delete();
        }

        foreach ($this->categories as $category) {
            /** @var MenuBoardCategory $category */
            $category->delete();
        }

        $this->getStore()->update('DELETE FROM `menu_board` WHERE menuId = :menuId', ['menuId' => $this->menuId]);
    }
}
