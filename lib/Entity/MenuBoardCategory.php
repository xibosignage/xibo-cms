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

use OpenApi\Attributes as OA;
use Respect\Validation\Validator as v;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Factory\MenuBoardCategoryFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\DataType\ProductCategory;

/**
 * Class MenuBoardCategory
 * @package Xibo\Entity
 */
#[OA\Schema]
class MenuBoardCategory implements \JsonSerializable
{
    use EntityTrait;

    #[OA\Property(description: 'The Menu Board Category Id')]
    public $menuCategoryId;

    #[OA\Property(description: 'The Menu Board Id')]
    public $menuId;

    #[OA\Property(description: 'The Menu Board Category name')]
    public $name;

    #[OA\Property(description: 'The Menu Board Category description')]
    public $description;

    #[OA\Property(description: 'The Menu Board Category code identifier')]
    public $code;

    #[OA\Property(description: 'The Menu Board Category associated mediaId')]
    public $mediaId;

    private $products;

    public function __construct(
        StorageServiceInterface $store,
        LogServiceInterface $log,
        EventDispatcherInterface $dispatcher,
        private readonly MenuBoardCategoryFactory $menuCategoryFactory,
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    public function __clone(): void
    {
        $this->menuCategoryId = null;
    }

    public function __toString(): string
    {
        return sprintf(
            'MenuCategoryId %d MenuId %d, Name %s, Media %d, Code %s',
            $this->menuCategoryId,
            $this->menuId,
            $this->name,
            $this->mediaId,
            $this->code
        );
    }

    public function toProductCategory(): ProductCategory
    {
        $productCategory = new ProductCategory();
        $productCategory->name = $this->name;
        $productCategory->description = $this->description;
        $productCategory->image = $this->mediaId;
        return $productCategory;
    }

    public function getId(): int
    {
        return $this->menuCategoryId;
    }

    /**
     * @throws NotFoundException
     */
    public function load(array $options = []): self
    {
        $options = array_merge([
            'loadProducts' => false
        ], $options);

        if ($this->menuId == null || $this->loaded) {
            return $this;
        }

        if ($options['loadProducts']) {
            $this->products = $this->getProducts();
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
     * @return MenuBoardProduct[]
     */
    public function getProducts(?array $sort = null): array
    {
        return $this->menuCategoryFactory->getProductData($sort, [
            'menuCategoryId' => $this->menuCategoryId
        ]);
    }

    /**
     * @return MenuBoardProduct[]
     */
    public function getAvailableProducts(?array $sort = null): array
    {
        return $this->menuCategoryFactory->getProductData($sort, [
            'menuCategoryId' => $this->menuCategoryId,
            'availability' => 1
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function save(array $options = []): void
    {
        $options = array_merge([
            'validate' => true,
        ], $options);

        $this->getLog()->debug('Saving ' . $this);

        if ($options['validate']) {
            $this->validate();
        }

        if ($this->menuCategoryId == null || $this->menuCategoryId == 0) {
            $this->add();
            $this->loaded = true;
        } else {
            $this->update();
        }
    }

    public function copyWithCascade(
        int $newMenuId,
        ?string $name = null,
        ?string $description = null,
        ?string $code = null
    ): self {
        $newCategory = clone $this;
        $newCategory->menuId = $newMenuId;
        if ($name !== null) {
            $newCategory->name = $name;
        }
        if ($description !== null) {
            $newCategory->description = $description;
        }
        if ($code !== null) {
            $newCategory->code = $code;
        }
        $newCategory->save();

        foreach ($this->getProducts() as $product) {
            $newProduct = clone $product;
            $newProduct->menuId = $newMenuId;
            $newProduct->menuCategoryId = $newCategory->menuCategoryId;
            $newProduct->save();

            foreach ($product->getOptions() as $option) {
                $newOption = clone $option;
                $newOption->menuProductId = $newProduct->menuProductId;
                $newOption->save();
            }
        }

        return $newCategory;
    }

    private function add(): void
    {
        $this->menuCategoryId = $this->getStore()->insert('
            INSERT INTO `menu_category` (`name`, `menuId`, `mediaId`, `code`, `description`)
                VALUES (:name, :menuId, :mediaId, :code, :description)
        ', [
            'name' => $this->name,
            'mediaId' => $this->mediaId ?: null,
            'menuId' => $this->menuId,
            'code' => $this->code,
            'description' => $this->description,
        ]);
    }

    private function update(): void
    {
        $this->getStore()->update('
            UPDATE `menu_category`
                SET `name` = :name, `mediaId` = :mediaId, `code` = :code, `description` = :description
             WHERE `menuCategoryId` = :menuCategoryId
        ', [
            'menuCategoryId' => $this->menuCategoryId,
            'name' => $this->name,
            'mediaId' => $this->mediaId,
            'code' => $this->code,
            'description' => $this->description,
        ]);
    }

    /**
     * @throws NotFoundException
     */
    public function delete(): void
    {
        $this->load(['loadProducts' => true]);

        foreach ($this->products as $product) {
            /** @var MenuBoardProduct $product */
            $product->delete();
        }

        $this->getStore()->update(
            'DELETE FROM `menu_category` WHERE menuCategoryId = :menuCategoryId',
            ['menuCategoryId' => $this->menuCategoryId]
        );
    }
}
