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
use Xibo\Factory\MenuBoardProductOptionFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Widget\DataType\Product;

/**
 * @SWG\Definition()
 */
class MenuBoardProduct implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The Menu Board Product Id")
     * @var int
     */
    public $menuProductId;

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
     * @SWG\Property(description="The Menu Board Product price")
     * @var double
     */
    public $price;

    /**
     * @SWG\Property(description="The Menu Board Product description")
     * @var string
     */
    public $description;

    /**
     * @SWG\Property(description="The Menu Board Product code identifier")
     * @var string
     */
    public $code;

    /**
     * @SWG\Property(description="The Menu Board Product display order, used for sorting")
     * @var int
     */
    public $displayOrder;

    /**
     * @SWG\Property(description="The Menu Board Product availability")
     * @var int
     */
    public $availability;

    /**
     * @SWG\Property(description="The Menu Board Product allergy information")
     * @var string
     */
    public $allergyInfo;

    /**
     * @SWG\Property(description="The Menu Board Product allergy information")
     * @var int
     */
    public $calories;

    /**
     * @SWG\Property(description="The Menu Board Product associated mediaId")
     * @var int
     */
    public $mediaId;

    /**
     * @SWG\Property(description="The Menu Board Product array of options", @SWG\Items(type="string"))
     * @var MenuBoardProductOption[]
     */
    public $productOptions;

    /**
     * @var MenuBoardProductOptionFactory
     */
    private $menuBoardProductOptionFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param MenuBoardProductOptionFactory $menuBoardProductOptionFactory
     */
    public function __construct($store, $log, $dispatcher, $menuBoardProductOptionFactory)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
        $this->menuBoardProductOptionFactory = $menuBoardProductOptionFactory;
    }


    /**
     * Get the Id
     * @return int
     */
    public function getId(): int
    {
        return $this->menuProductId;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function validate(): void
    {
        if (!v::stringType()->notEmpty()->validate($this->name)) {
            throw new InvalidArgumentException(__('Name cannot be empty'), 'name');
        }

        if (!empty($this->calories) && !v::intType()->min(0)->max(32767)->validate($this->calories)) {
            throw new InvalidArgumentException(
                __('Calories must be a whole number between 0 and 32767'),
                'calories'
            );
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf(
            'MenuProductId %d, MenuCategoryId %d, MenuId %d, Name %s, Price %s, Media %d, Code %s',
            $this->menuProductId,
            $this->menuCategoryId,
            $this->menuId,
            $this->name,
            $this->price,
            $this->mediaId,
            $this->code
        );
    }

    /**
     * Convert this to a Product
     * @return Product
     */
    public function toProduct(): Product
    {
        $product = new Product();
        $product->name = $this->name;
        $product->price = $this->price;
        $product->description = $this->description;
        $product->availability = $this->availability;
        $product->allergyInfo = $this->allergyInfo;
        $product->calories = $this->calories;
        $product->image = $this->mediaId;
        foreach (($this->productOptions ?? []) as $productOption) {
            $product->productOptions[] = [
                'name' => $productOption->option,
                'value' => $productOption->value,
            ];
        }
        return $product;
    }

    /**
     * Save this Menu Board Product
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

        if ($this->menuProductId == null || $this->menuProductId == 0) {
            $this->add();
        } else {
            $this->update();
        }
    }

    /**
     * Add Menu Board Product
     */
    private function add(): void
    {
        $this->menuProductId = $this->getStore()->insert('
            INSERT INTO `menu_product` (
                `menuCategoryId`,
                `menuId`,
                `name`,
                `price`,
                `description`,
                `mediaId`,
                `displayOrder`,
                `availability`,
                `allergyInfo`,
                `calories`,
                `code`
            )
            VALUES (
                :menuCategoryId,
                :menuId,
                :name,
                :price,
                :description,
                :mediaId,
                :displayOrder,
                :availability,
                :allergyInfo,
                :calories,
                :code
            )
        ', [
            'menuCategoryId' => $this->menuCategoryId,
            'menuId' => $this->menuId,
            'name' => $this->name,
            'price' => $this->price,
            'description' => $this->description,
            'mediaId' => $this->mediaId,
            'displayOrder' => $this->displayOrder,
            'availability' => $this->availability,
            'allergyInfo' => $this->allergyInfo,
            'calories' => $this->calories,
            'code' => $this->code,
        ]);
    }

    /**
     * Update Menu Board Product
     */
    private function update(): void
    {
        $this->getStore()->update('
            UPDATE `menu_product` SET
                `name` = :name,
                `price` = :price,
                `description` = :description,
                `mediaId` = :mediaId,
                `displayOrder` = :displayOrder,
                `availability` = :availability,
                `allergyInfo` = :allergyInfo,
                `calories` = :calories,
                `code` = :code
             WHERE `menuProductId` = :menuProductId
        ', [
            'name' => $this->name,
            'price' => $this->price,
            'description' => $this->description,
            'mediaId' => $this->mediaId,
            'displayOrder' => $this->displayOrder,
            'availability' => $this->availability,
            'allergyInfo' => $this->allergyInfo,
            'calories' => $this->calories,
            'code' => $this->code,
            'menuProductId' => $this->menuProductId
        ]);
    }

    /**
     * Delete Menu Board Product
     */
    public function delete()
    {
        $this->removeOptions();
        $this->getStore()->update('DELETE FROM `menu_product` WHERE menuProductId = :menuProductId', ['menuProductId' => $this->menuProductId]);
    }

    /**
     * @return MenuBoardProductOption[]
     */
    public function getOptions()
    {
        $options = $this->menuBoardProductOptionFactory->getByMenuProductId($this->menuProductId);

        return $options;
    }

    public function removeOptions()
    {
        $this->getStore()->update('DELETE FROM `menu_product_options` WHERE menuProductId = :menuProductId', ['menuProductId' => $this->menuProductId]);
    }
}
