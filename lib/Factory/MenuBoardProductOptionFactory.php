<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Factory;

use Xibo\Entity\MenuBoardProductOption;

class MenuBoardProductOptionFactory extends BaseFactory
{
    public function createEmpty(): MenuBoardProductOption
    {
        return new MenuBoardProductOption($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    public function create(int $menuProductId, string $option, mixed $value): MenuBoardProductOption
    {
        $productOption = $this->createEmpty();
        $productOption->menuProductId = $menuProductId;
        $productOption->option = $option;
        $productOption->value = $value;

        return $productOption;
    }

    /**
     * @return MenuBoardProductOption[]
     */
    public function getByMenuProductId(int $menuProductId): array
    {
        return $this->query(null, ['menuProductId' => $menuProductId]);
    }

    /**
     * @return MenuBoardProductOption[]
     */
    public function query(?array $sortOrder = null, array $filterBy = []): array
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
