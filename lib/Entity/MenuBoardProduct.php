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
use Xibo\Factory\MenuBoardProductOptionFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

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
     * @var string
     */
    public $price;

    /**
     * @SWG\Property(description="The Menu Board Product description")
     * @var string
     */
    public $description;

    /**
     * @SWG\Property(description="The Menu Board Product availability")
     * @var string
     */
    public $availability;

    /**
     * @SWG\Property(description="The Menu Board Product allergy information")
     * @var string
     */
    public $allergyInfo;

    /**
     * @SWG\Property(description="The Menu Board Product associated mediaId")
     * @var int
     */
    public $mediaId;

    /**
     * @var MenuBoardProductOptionFactory
     */
    private $menuBoardProductOptionFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param MenuBoardProductOptionFactory $menuBoardProductOptionFactory
     */
    public function __construct($store, $log, $menuBoardProductOptionFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->menuBoardProductOptionFactory = $menuBoardProductOptionFactory;
    }


    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->menuProductId;
    }

    /**
     * Get the Id
     * @return int
     */
    public function getCategoryId()
    {
        return $this->menuCategoryId;
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
     * @return string
     */
    public function __toString()
    {
        return sprintf('MenuProductId %d, MenuCategoryId %d, MenuId %d, Name %s, Price %d, Media %d', $this->menuProductId, $this->menuCategoryId, $this->menuId, $this->name, $this->price, $this->mediaId);
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
    private function add()
    {
        $this->menuProductId = $this->getStore()->insert('INSERT INTO `menu_product` (menuCategoryId, menuId, name, price, description, mediaId, availability, allergyInfo) VALUES (:menuCategoryId, :menuId, :name, :price, :description, :mediaId, :availability, :allergyInfo)', [
            'menuCategoryId' => $this->menuCategoryId,
            'menuId' => $this->menuId,
            'name' => $this->name,
            'price' => $this->price,
            'description' => $this->description,
            'mediaId' => $this->mediaId,
            'availability' => $this->availability,
            'allergyInfo' => $this->allergyInfo
        ]);
    }

    /**
     * Update Menu Board Product
     */
    private function update()
    {
        $this->getStore()->update('UPDATE `menu_product` SET name = :name, price = :price, description = :description, mediaId = :mediaId, availability = :availability, allergyInfo = :allergyInfo WHERE menuProductId = :menuProductId', [
            'name' => $this->name,
            'price' => $this->price,
            'description' => $this->description,
            'mediaId' => $this->mediaId,
            'availability' => $this->availability,
            'allergyInfo' => $this->allergyInfo,
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