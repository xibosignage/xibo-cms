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

use Xibo\Entity\MenuBoardProductOption;

class MenuBoardProductOptionFactory extends BaseFactory
{
    /**
     * Create Empty
     * @return MenuBoardProductOption
     */
    public function createEmpty()
    {
        return new MenuBoardProductOption($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * Create a Widget Option
     * @param int $menuProductId
     * @param string $option
     * @param mixed $value
     * @return MenuBoardProductOption
     */
    public function create($menuProductId, $option, $value)
    {
        $productOption = $this->createEmpty();
        $productOption->menuProductId = $menuProductId;
        $productOption->option = $option;
        $productOption->value = $value;

        return $productOption;
    }

    /**
     * Load by Menu Board Product Id
     * @param int $menuProductId
     * @return MenuBoardProductOption[]
     */
    public function getByMenuProductId($menuProductId)
    {
        return $this->query(null, ['menuProductId' => $menuProductId]);
    }

    /**
     * Query Menu Board Product options
     * @param array $sortOrder
     * @param array $filterBy
     * @return MenuBoardProductOption[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $sanitizedFilter = $this->getSanitizer($filterBy);
        $entries = [];

        $sql = 'SELECT * FROM `menu_product_options` WHERE menuProductId = :menuProductId ORDER BY `option`';

        foreach ($this->getStore()->select($sql, [
            'menuProductId' => $sanitizedFilter->getInt('menuProductId')
        ]) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row, ['doubleProperties' => ['value']]);
        }

        return $entries;
    }
}
