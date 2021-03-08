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

namespace Xibo\Factory;

use Xibo\Entity\MenuBoardCategory;
use Xibo\Entity\MenuBoardProduct;
use Xibo\Helper\SanitizerService;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

class MenuBoardCategoryFactory extends BaseFactory
{
    /** @var MenuBoardProductOptionFactory */
    private $menuBoardProductOptionFactory;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerService $sanitizerService
     * @param MenuBoardProductOptionFactory $menuBoardProductOptionFactory
     */
    public function __construct($store, $log, $sanitizerService, $menuBoardProductOptionFactory)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
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
            $this->menuBoardProductOptionFactory
        );
    }

    /**
     * Create a new Category
     * @param int $menuId
     * @param string $name
     * @param int $mediaId
     * @return MenuBoardCategory
     */
    public function create($menuId, $name, $mediaId)
    {
        $menuBoardCategory = $this->createEmpty();
        $menuBoardCategory->menuId = $menuId;
        $menuBoardCategory->name = $name;
        $menuBoardCategory->mediaId = $mediaId;

        return $menuBoardCategory;
    }

    /**
     * Create a new Product
     * @param int $menuId
     * @param int $menuCategoryId
     * @param string $name
     * @param string $price
     * @param string $description
     * @param string $allergyInfo
     * @param int $availability
     * @param int $mediaId
     * @return MenuBoardProduct
     */
    public function createProduct(
        $menuId,
        $menuCategoryId,
        $name,
        $price,
        $description,
        $allergyInfo,
        $availability,
        $mediaId
    ) {
        $menuBoardProduct = $this->createEmptyProduct();
        $menuBoardProduct->menuId = $menuId;
        $menuBoardProduct->menuCategoryId = $menuCategoryId;
        $menuBoardProduct->name = $name;
        $menuBoardProduct->price = $price;
        $menuBoardProduct->description = $description;
        $menuBoardProduct->allergyInfo = $allergyInfo;
        $menuBoardProduct->availability = $availability;
        $menuBoardProduct->mediaId = $mediaId;

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
            throw new NotFoundException(__('Menu Board Category not found'));
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
        $this->getLog()->debug('MenuBoardCategoryFactory getById ' . $menuProductId);

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
            SELECT menu_category.menuCategoryId,
               `menu_category`.menuId,
               `menu_category`.name,
               `menu_category`.mediaId
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

        // Sorting?
        $order = '';

        if (is_array($sortOrder)) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . intval($sanitizedFilter->getInt('start'), 0) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
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

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return MenuBoardProduct[]
     */
    public function getProductData($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder === null) {
            $sortOrder = ['availability DESC, menuProductId DESC'];
        }

        $sanitizedFilter = $this->getSanitizer($filterBy);

        $params = [];
        $entries = [];

        $select = '
            SELECT 
               `menu_product`.menuProductId,
               `menu_product`.menuId,
               `menu_product`.menuCategoryId,
               `menu_product`.name,
               `menu_product`.price,
               `menu_product`.description,
               `menu_product`.mediaId,
               `menu_product`.availability,
               `menu_product`.allergyInfo
            ';

        $body = ' FROM menu_product WHERE 1 = 1  ';

        if ($sanitizedFilter->getInt('menuProductId') !== null) {
            $body .= ' AND `menu_product`.menuProductId = :menuProductId ';
            $params['menuProductId'] = $sanitizedFilter->getInt('menuProductId');
        }

        if ($sanitizedFilter->getInt('menuId') !== null) {
            $body .= ' AND `menu_product`.menuId = :menuId ';
            $params['menuId'] = $sanitizedFilter->getInt('menuId');
        }

        if ($sanitizedFilter->getInt('menuCategoryId') !== null) {
            $body .= ' AND `menu_product`.menuCategoryId = :menuCategoryId ';
            $params['menuCategoryId'] = $sanitizedFilter->getInt('menuCategoryId');
        }

        if ($sanitizedFilter->getString('name') != '') {
            $terms = explode(',', $sanitizedFilter->getString('name'));
            $this->nameFilter('menu_product', 'name', $terms, $body, $params, ($sanitizedFilter->getCheckbox('useRegexForName') == 1));
        }

        if ($sanitizedFilter->getInt('availability') !== null) {
            $body .= ' AND `menu_product`.availability = :availability ';
            $params['availability'] = $sanitizedFilter->getInt('availability');
        }

        if ($sanitizedFilter->getString('categories') != null) {
            $categories = implode('","', array_map('intval', explode(',', $sanitizedFilter->getString('categories'))));
            $body .= ' AND `menu_product`.menuCategoryId IN ("' . $categories . '") ';
        }

        // Sorting?
        $order = '';

        if (is_array($sortOrder)) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . intval($sanitizedFilter->getInt('start'), 0) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $menuProduct = $this->createEmptyProduct()->hydrate($row, ['intProperties' => ['availability']]);
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
