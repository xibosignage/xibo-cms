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


use Xibo\Entity\RegionOption;

/**
 * Class RegionOptionFactory
 * @package Xibo\Factory
 */
class RegionOptionFactory extends BaseFactory
{
    /**
     * @return RegionOption
     */
    public function createEmpty()
    {
        return new RegionOption($this->getStore(), $this->getLog(), $this->getDispatcher());
    }

    /**
     * Load by Region Id
     * @param int $regionId
     * @return array[RegionOption]
     */
    public function getByRegionId($regionId)
    {
        return $this->query(null, array('regionId' => $regionId));
    }

    /**
     * Create a region option
     * @param int $regionId
     * @param string $option
     * @param mixed $value
     * @return RegionOption
     */
    public function create($regionId, $option, $value)
    {
        $regionOption = $this->createEmpty();
        $regionOption->regionId = $regionId;
        $regionOption->option = $option;
        $regionOption->value = $value;

        return $regionOption;
    }

    /**
     * Query Region options
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[RegionOption]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        $entries = [];
        $sanitizedFilter = $this->getSanitizer($filterBy);

        $sql = 'SELECT * FROM `regionoption` WHERE regionId = :regionId';

        foreach ($this->getStore()->select($sql, array('regionId' => $sanitizedFilter->getInt('regionId'))) as $row) {
            $entries[] = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}