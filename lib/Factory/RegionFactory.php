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


use Xibo\Entity\Layout;
use Xibo\Entity\Region;
use Xibo\Exception\NotFoundException;

class RegionFactory
{
    /**
     * Create a new region
     * @param int $ownerId;
     * @param string $name
     * @param int $width
     * @param int $height
     * @param int $top
     * @param int $left
     * @return Region
     */
    public static function create($ownerId, $name, $width, $height, $top, $left)
    {
        // Validation
        if (!is_numeric($width) || !is_numeric($height) || !is_numeric($top) || !is_numeric($left))
            throw new \InvalidArgumentException(__('Size and coordinates must be generic'));

        if ($width <= 0)
            throw new \InvalidArgumentException(__('Width must be greater than 0'));

        if ($height <= 0)
            throw new \InvalidArgumentException(__('Height must be greater than 0'));

        $region = new Region();
        $region->ownerId = $ownerId;
        $region->name = $name;
        $region->width = $width;
        $region->height = $height;
        $region->top = $top;
        $region->left = $left;
        $region->zIndex = 0;
        return $region;
    }

    /**
     * Get the regions for a layout
     * @param int $layoutId
     * @return array[\Xibo\Entity\Region]
     */
    public static function getByLayoutId($layoutId)
    {
        // Get all regions for this layout
        return RegionFactory::query(array(), array('layoutId' => $layoutId));
    }

    /**
     * Load a region
     * @param int $regionId
     * @return Region
     */
    public static function loadByRegionId($regionId)
    {
        // Get a region by its ID
        $regions = RegionFactory::query(array(), array('regionId' => $regionId));
        $region = $regions[0];
        /* @var Region $region */
        $region->load();
        return $region;
    }

    /**
     * @param array $sortOrder
     * @param array $filterBy
     * @return array[Region]
     */
    public static function query($sortOrder = array(), $filterBy = array())
    {
        $entries = array();

        $params = array();
        $sql = 'SELECT * FROM `region` WHERE 1 = 1 ';

        if (\Kit::GetParam('regionId', $filterBy, _INT) != 0) {
            $sql .= ' AND regionId = :regionId ';
            $params['regionId'] = \Kit::GetParam('regionId', $filterBy, _INT);
        }

        if (\Kit::GetParam('layoutId', $filterBy, _INT) != 0) {
            $sql .= ' AND layoutId = :layoutId ';
            $params['layoutId'] = \Kit::GetParam('layoutId', $filterBy, _INT);
        }

        foreach (\PDOConnect::select($sql, $params) as $row) {
            $region = new Region();
            $region->layoutId = \Kit::ValidateParam($row['layoutId'], _INT);
            $region->regionId = \Kit::ValidateParam($row['regionId'], _INT);
            $region->ownerId = \Kit::ValidateParam($row['ownerId'], _INT);
            $region->name = \Kit::ValidateParam($row['name'], _STRING);
            $region->width = \Kit::ValidateParam($row['width'], _DOUBLE);
            $region->height = \Kit::ValidateParam($row['height'], _DOUBLE);
            $region->top = \Kit::ValidateParam($row['top'], _DOUBLE);
            $region->left = \Kit::ValidateParam($row['left'], _DOUBLE);
            $region->zIndex = \Kit::ValidateParam($row['zIndex'], _DOUBLE);

            $entries[] = $region;
        }

        return $entries;
    }
}