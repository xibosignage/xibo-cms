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
use Xibo\Factory\MenuBoardCategoryFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\DataType\ProductCategory;

/**
 * @SWG\Definition()
 */
class MenuBoardCategory implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Menu Board Category Id")
     * @var int
     */
    public $menuCategoryId;

    /**
     * @SWG\Property(description="The Menu Board Id")
     * @var int
     */
    public $menuId;

    /**
     * @SWG\Property(description="The Menu Board Category name")
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(description="The Menu Board Category description")
     * @var string
     */
    public $description;

    /**
     * @SWG\Property(description="The Menu Board Category code identifier")
     * @var string
     */
    public $code;

    /**
     * @SWG\Property(description="The Menu Board Category associated mediaId")
     * @var int
     */
    public $mediaId;

    private $products;

    /** @var MenuBoardCategoryFactory */
    private $menuCategoryFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param MenuBoardCategoryFactory $menuCategoryFactory
     */
    public function __construct($store, $log, $dispatcher, $menuCategoryFactory)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
        $this->menuCategoryFactory = $menuCategoryFactory;
    }

    public function __clone()
    {
        $this->menuCategoryId = null;
    }

    /**
     * @return string
     */
    public function __toString()
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

    /**
     * Convert this to a product category
     * @return ProductCategory
     */
    public function toProductCategory(): ProductCategory
    {
        $productCategory = new ProductCategory();
        $productCategory->name = $this->name;
        $productCategory->description = $this->description;
        $productCategory->image = $this->mediaId;
        return $productCategory;
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->menuCategoryId;
    }

    /**
     * @param array $options
     * @return MenuBoardCategory
     * @throws NotFoundException
     */
    public function load($options = [])
    {
        $options = array_merge([
            'loadProducts' => false
        ], $options);

        // If we are already loaded, then don't do it again
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
    public function validate()
    {
        if (!v::stringType()->notEmpty()->validate($this->name)) {
            throw new InvalidArgumentException(__('Name cannot be empty'), 'name');
        }
    }

    /**
     * @param array|null $sort The sort order to be applied
     * @return MenuBoardProduct[]
     */
    public function getProducts($sort = null): array
    {
        return $this->menuCategoryFactory->getProductData($sort, [
            'menuCategoryId' => $this->menuCategoryId
        ]);
    }

    /**
     * @param array|null $sort The sort order to be applied
     * @return MenuBoardProduct[]
     */
    public function getAvailableProducts($sort = null): array
    {
        return $this->menuCategoryFactory->getProductData($sort, [
            'menuCategoryId' => $this->menuCategoryId,
            'availability' => 1
        ]);
    }

    /**
     * Save this Menu Board Category
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

        if ($this->menuCategoryId == null || $this->menuCategoryId == 0) {
            $this->add();
            $this->loaded = true;
        } else {
            $this->update();
        }
    }

    private function add(): void
    {
        $this->menuCategoryId = $this->getStore()->insert('
            INSERT INTO `menu_category` (`name`, `menuId`, `mediaId`, `code`, `description`)
                VALUES (:name, :menuId, :mediaId, :code, :description)
        ', [
            'name' => $this->name,
            'mediaId' => $this->mediaId,
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
     * Delete Menu Board
     * @throws NotFoundException
     */
    public function delete()
    {
        $this->load(['loadProducts' => true]);

        /** @var MenuBoardProduct $product */
        foreach ($this->products as $product) {
            $product->delete();
        }

        $this->getStore()->update('DELETE FROM `menu_category` WHERE menuCategoryId = :menuCategoryId', ['menuCategoryId' => $this->menuCategoryId]);
    }
}
