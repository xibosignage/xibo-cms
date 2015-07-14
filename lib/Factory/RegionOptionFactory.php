<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (RegionOptionFactory.php) is part of Xibo.
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


use Xibo\Entity\RegionOption;

class RegionOptionFactory extends BaseFactory
{
    /**
     * Load by Region Id
     * @param int $regionId
     * @return array[RegionOption]
     */
    public static function getByRegionId($regionId)
    {
        return RegionOptionFactory::query(null, array('regionId' => $regionId));
    }

    /**
     * Create a region option
     * @param int $regionId
     * @param string $option
     * @param mixed $value
     * @return RegionOption
     */
    public static function create($regionId, $option, $value)
    {
        $regionOption = new RegionOption();
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
    public static function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();

        $sql = 'SELECT * FROM `regionoption` WHERE regionId = :regionId';

        foreach (\Xibo\Storage\PDOConnect::select($sql, array('regionId' => \Xibo\Helper\Sanitize::int('regionId', $filterBy))) as $row) {
            $region = new RegionOption();
            $region->regionId = \Xibo\Helper\Sanitize::int($row['regionId']);
            $region->option = \Xibo\Helper\Sanitize::string($row['option']);
            $region->value = \Kit::ValidateParam($row['value'], _HTMLSTRING);

            $entries[] = $region;
        }

        return $entries;
    }
}