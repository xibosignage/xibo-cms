<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (RegionFactory.php) is part of Xibo.
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


use Xibo\Entity\Region;

class RegionFactory
{
    /**
     * Create a new region
     * @param string $name
     * @param int $width
     * @param int $height
     * @param int $top
     * @param int $left
     * @return Region
     */
    public static function create($name, $width, $height, $top, $left)
    {
        // TODO: Implement region creation
        return new Region();
    }

    /**
     * Load the regions for a layout
     * @param int $layoutId
     * @return array[\Xibo\Entity\Region]
     */
    public static function loadByLayoutId($layoutId)
    {
        // Get all regions for this layout
        return RegionFactory::query(array(), array('layoutId' => $layoutId));
    }

    public static function query($sortOrder = array(), $filterBy = array())
    {
        $entries = array();


        return $entries;
    }
}