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
use Xibo\Factory\MenuBoardProductOptionFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Widget\DataType\Product;

/**
 * Class MenuBoardProduct
 * @package Xibo\Entity
 */
#[OA\Schema]
class MenuBoardProduct implements \JsonSerializable
{
    use EntityTrait;

    #[OA\Property(description: 'The Menu Board Product Id')]
    public $menuProductId;

    #[OA\Property(description: 'The Menu Board Category Id')]
    public $menuCategoryId;

    #[OA\Property(description: 'The Menu Board Id')]
    public $menuId;

    #[OA\Property(description: 'The Menu Board Category name')]
    public $name;

    #[OA\Property(description: 'The Menu Board Product price')]
    public $price;

    #[OA\Property(description: 'The Menu Board Product description')]
    public $description;

    #[OA\Property(description: 'The Menu Board Product code identifier')]
    public $code;

    #[OA\Property(description: 'The Menu Board Product display order, used for sorting')]
    public $displayOrder;

    #[OA\Property(description: 'The Menu Board Product availability')]
    public $availability;

    #[OA\Property(description: 'The Menu Board Product allergy information')]
    public $allergyInfo;

    #[OA\Property(description: 'The Menu Board Product allergy information')]
    public $calories;

    #[OA\Property(description: 'The Menu Board Product associated mediaId')]
    public $mediaId;

    /** @var MenuBoardProductOption[] */
    #[OA\Property(description: 'The Menu Board Product array of options', items: new OA\Items(type: 'string'))]
    public $productOptions;

    public function __construct(
        StorageServiceInterface $store,
        LogServiceInterface $log,
        EventDispatcherInterface $dispatcher,
        private readonly MenuBoardProductOptionFactory $menuBoardProductOptionFactory,
    ) {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

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

    public function __clone(): void
    {
        $this->menuProductId = null;
    }

    public function __toString(): string
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

        if ($this->menuProductId == null || $this->menuProductId == 0) {
            $this->add();
        } else {
            $this->update();
        }
    }

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
            'mediaId' => $this->mediaId ?: null,
            'displayOrder' => $this->displayOrder,
            'availability' => $this->availability,
            'allergyInfo' => $this->allergyInfo,
            'calories' => $this->calories,
            'code' => $this->code,
        ]);
    }

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

    public function delete(): void
    {
        $this->removeOptions();
        $this->getStore()->update(
            'DELETE FROM `menu_product` WHERE menuProductId = :menuProductId',
            ['menuProductId' => $this->menuProductId]
        );
    }

    /**
     * @return MenuBoardProductOption[]
     */
    public function getOptions(): array
    {
        return $this->menuBoardProductOptionFactory->getByMenuProductId($this->menuProductId);
    }

    public function removeOptions(): void
    {
        $this->getStore()->update(
            'DELETE FROM `menu_product_options` WHERE menuProductId = :menuProductId',
            ['menuProductId' => $this->menuProductId]
        );
    }
}
