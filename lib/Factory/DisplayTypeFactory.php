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

use Xibo\Entity\DisplayType;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class DisplayTypeFactory
 * @package Xibo\Factory
 */
class DisplayTypeFactory extends BaseFactory
{
    /**
     * @return DisplayType
     */
    public function createEmpty()
    {
        return new DisplayType($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * Get By Id
     * @param int $id
     * @return DisplayType
     * @throws NotFoundException
     */
    public function getById($id)
    {
        $results = $this->query(null, ['displayTypeId' => $id]);

        if (count($results) <= 0) {
            throw new NotFoundException();
        }

        return $results[0];
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return DisplayType[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = [];
        $sanitizedFilter = $this->getSanitizer($filterBy);

        $params = [];
        $sql = 'SELECT displayTypeId, displayType FROM `display_types` WHERE 1 = 1 ';

        if ($sanitizedFilter->getInt('displayTypeId') !== null) {
            $sql .= ' AND `display_type`.displayTypeId = :displayTypeId ';
            $params['displayTypeId'] = $sanitizedFilter->getInt('displayTypeId');
        }

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}
