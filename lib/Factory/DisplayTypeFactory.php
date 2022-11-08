<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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
