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

namespace Xibo\Factory;

use Xibo\Entity\MenuBoardCategory;
use Xibo\Entity\MenuBoardProduct;
use Xibo\Support\Exception\NotFoundException;

class MenuBoardCategoryFactory extends BaseFactory
{
    /** @var MenuBoardProductOptionFactory */
    private $menuBoardProductOptionFactory;

    /**
     * Construct a factory
     * @param MenuBoardProductOptionFactory $menuBoardProductOptionFactory
     */
    public function __construct($menuBoardProductOptionFactory)
    {
        $this->menuBoardProductOptionFactory = $menuBoardProductOptionFactory;
    }

    /**
     * Create Empty
     * @return MenuBoardCategory
     */
    public function createEmpty()
    {
        return new MenuBoardCategory(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher(),
            $this
        );
    }

    /**
     * Create Empty
     * @return MenuBoardProduct
     */
    public function createEmptyProduct()
    {
        return new MenuBoardProduct(
            $this->getStore(),
            $this->getLog(),
            $this->getDispatcher(),
            $this->menuBoardProductOptionFactory
        );
    }

    /**
     * Create a new Category
     * @param int $menuId
     * @param string $name
     * @param int $mediaId
     * @param string $code
     * @return MenuBoardCategory
     */
    public function create($menuId, $name, $mediaId, $code, $description)
    {
        $menuBoardCategory = $this->createEmpty();
        $menuBoardCategory->menuId = $menuId;
        $menuBoardCategory->name = $name;
        $menuBoardCategory->mediaId = $mediaId;
        $menuBoardCategory->code = $code;
        $menuBoardCategory->description = $description;
        return $menuBoardCategory;
    }

    /**
     * Create a new Product
     * @param int $menuId
     * @param int $menuCategoryId
     * @param string $name
     * @param float $price
     * @param string $description
     * @param string $allergyInfo
     * @param int $calories
     * @param int $availability
     * @param int $mediaId
     * @param string $code
     * @return MenuBoardProduct
     */
    public function createProduct(
        $menuId,
        $menuCategoryId,
        $name,
        $price,
        $description,
        $allergyInfo,
        $calories,
        $displayOrder,
        $availability,
        $mediaId,
        $code
    ) {
        $menuBoardProduct = $this->createEmptyProduct();
        $menuBoardProduct->menuId = $menuId;
        $menuBoardProduct->menuCategoryId = $menuCategoryId;
        $menuBoardProduct->name = $name;
        $menuBoardProduct->price = $price;
        $menuBoardProduct->description = $description;
        $menuBoardProduct->allergyInfo = $allergyInfo;
        $menuBoardProduct->calories = $calories;
        $menuBoardProduct->displayOrder = $displayOrder;
        $menuBoardProduct->availability = $availability;
        $menuBoardProduct->mediaId = $mediaId;
        $menuBoardProduct->code = $code;
        return $menuBoardProduct;
    }

    /**
     * @param int $menuCategoryId
     * @return MenuBoardCategory
     * @throws NotFoundException
     */
    public function getById(int $menuCategoryId)
    {
        $this->getLog()->debug('MenuBoardCategoryFactory getById ' . $menuCategoryId);

        $menuCategories = $this->query(null, ['disableUserCheck' => 1, 'menuCategoryId' => $menuCategoryId]);

        if (count($menuCategories) <= 0) {
            $this->getLog()->debug('Menu Board Category not found with ID ' . $menuCategoryId);
            throw new NotFoundException(__('Menu Board Category not found'));
        }

        return $menuCategories[0];
    }

    /**
     * @param int $menuId
     * @return MenuBoardCategory[]
     * @throws NotFoundException
     */
    public function getByMenuId(int $menuId)
    {
        $this->getLog()->debug('MenuBoardCategoryFactory getById ' . $menuId);

        $menuCategories = $this->query(null, ['disableUserCheck' => 1, 'menuId' => $menuId]);

        if (count($menuCategories) <= 0) {
            $this->getLog()->debug('Menu Board Category not found for Menu Board ID ' . $menuId);
        }

        return $menuCategories;
    }

    /**
     * @param int $menuProductId
     * @return MenuBoardProduct
     * @throws NotFoundException
     */
    public function getByProductId(int $menuProductId)
    {
        $this->getLog()->debug('MenuBoardCategoryFactory getByProductId ' . $menuProductId);

        $menuProducts = $this->getProductData(null, ['disableUserCheck' => 1, 'menuProductId' => $menuProductId]);

        if (count($menuProducts) <= 0) {
            $this->getLog()->debug('Menu Board Product not found with ID ' . $menuProductId);
            throw new NotFoundException(__('Menu Board Product not found'));
        }

        return $menuProducts[0];
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return MenuBoardCategory[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder === null) {
            $sortOrder = ['menuCategoryId DESC'];
        }

        $sanitizedFilter = $this->getSanitizer($filterBy);

        $params = [];
        $entries = [];

        $select = '
            SELECT `menu_category`.`menuCategoryId`,
               `menu_category`.`menuId`,
               `menu_category`.`name`,
               `menu_category`.`description`,
               `menu_category`.`code`,
               `menu_category`.`mediaId`
            ';

        $body = ' FROM menu_category WHERE 1 = 1 ';

        if ($sanitizedFilter->getInt('menuCategoryId') !== null) {
            $body .= ' AND `menu_category`.menuCategoryId = :menuCategoryId ';
            $params['menuCategoryId'] = $sanitizedFilter->getInt('menuCategoryId');
        }

        if ($sanitizedFilter->getInt('menuId') !== null) {
            $body .= ' AND `menu_category`.menuId = :menuId ';
            $params['menuId'] = $sanitizedFilter->getInt('menuId');
        }

        if ($sanitizedFilter->getInt('userId') !== null) {
            $body .= ' AND `menu_category`.userId = :userId ';
            $params['userId'] = $sanitizedFilter->getInt('userId');
        }

        if ($sanitizedFilter->getString('name') != '') {
            $terms = explode(',', $sanitizedFilter->getString('name'));
            $this->nameFilter('menu_category', 'name', $terms, $body, $params, ($sanitizedFilter->getCheckbox('useRegexForName') == 1));
        }

        if ($sanitizedFilter->getString('code') != '') {
            $body.= ' AND `menu_category`.code LIKE :code ';
            $params['code'] = '%' . $sanitizedFilter->getString('code') . '%';
        }

        if ($sanitizedFilter->getInt('mediaId') !== null) {
            $body .= ' AND `menu_category`.mediaId = :mediaId ';
            $params['mediaId'] = $sanitizedFilter->getInt('mediaId');
        }

        // Sorting?
        $order = '';

        if (is_array($sortOrder)) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $menuCategory = $this->createEmpty()->hydrate($row);
            $entries[] = $menuCategory;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }

    public function getNextDisplayOrder(int $categoryId): int
    {
        $results = $this->getStore()->select('
            SELECT MAX(`displayOrder`) AS next
              FROM menu_product
            WHERE menuCategoryId = :categoryId
        ', [
            'categoryId' => $categoryId,
        ]);

        return ($results[0]['next'] ?? 0) + 1;
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return MenuBoardProduct[]
     */
    public function getProductData($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder === null) {
            $sortOrder = ['`displayOrder`, `availability` DESC, `menuProductId`'];
        }

        $sanitizedFilter = $this->getSanitizer($filterBy);

        $params = [];
        $entries = [];

        $select = '
            SELECT 
               `menu_product`.`menuProductId`,
               `menu_product`.`menuId`,
               `menu_product`.`menuCategoryId`,
               `menu_product`.`name`,
               `menu_product`.`price`,
               `menu_product`.`description`,
               `menu_product`.`mediaId`,
               `menu_product`.`displayOrder`,
               `menu_product`.`availability`,
               `menu_product`.`allergyInfo`,
               `menu_product`.`calories`,
               `menu_product`.`code`
            ';

        $body = ' FROM menu_product WHERE 1 = 1  ';

        if ($sanitizedFilter->getInt('menuProductId') !== null) {
            $body .= ' AND `menu_product`.`menuProductId` = :menuProductId ';
            $params['menuProductId'] = $sanitizedFilter->getInt('menuProductId');
        }

        if ($sanitizedFilter->getInt('menuId') !== null) {
            $body .= ' AND `menu_product`.`menuId` = :menuId ';
            $params['menuId'] = $sanitizedFilter->getInt('menuId');
        }

        if ($sanitizedFilter->getInt('menuCategoryId') !== null) {
            $body .= ' AND `menu_product`.`menuCategoryId` = :menuCategoryId ';
            $params['menuCategoryId'] = $sanitizedFilter->getInt('menuCategoryId');
        }

        if ($sanitizedFilter->getString('name') != '') {
            $terms = explode(',', $sanitizedFilter->getString('name'));
            $this->nameFilter('menu_product', 'name', $terms, $body, $params, ($sanitizedFilter->getCheckbox('useRegexForName') == 1));
        }

        if ($sanitizedFilter->getInt('availability') !== null) {
            $body .= ' AND `menu_product`.`availability` = :availability ';
            $params['availability'] = $sanitizedFilter->getInt('availability');
        }

        if ($sanitizedFilter->getString('categories') != null) {
            $categories = implode('","', array_map('intval', explode(',', $sanitizedFilter->getString('categories'))));
            $body .= ' AND `menu_product`.`menuCategoryId` IN ("' . $categories . '") ';
        }

        if ($sanitizedFilter->getInt('mediaId') !== null) {
            $body .= ' AND `menu_product`.`mediaId` = :mediaId ';
            $params['mediaId'] = $sanitizedFilter->getInt('mediaId');
        }

        if ($sanitizedFilter->getString('code') != '') {
            $body.= ' AND `menu_product`.`code` LIKE :code ';
            $params['code'] = '%' . $sanitizedFilter->getString('code') . '%';
        }

        // Sorting?
        $order = '';

        if (is_array($sortOrder)) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $menuProduct = $this->createEmptyProduct()->hydrate($row, [
                'intProperties' => [
                    'availability',
                    'calories',
                    'displayOrder',
                ],
                'doubleProperties' => [
                    'price',
                ]
            ]);
            $entries[] = $menuProduct;
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}
