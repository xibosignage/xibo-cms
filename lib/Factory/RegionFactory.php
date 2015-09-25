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
use Xibo\Exception\NotFoundException;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Storage\PDOConnect;

class RegionFactory extends BaseFactory
{
    /**
     * Create a new region
     * @param int $ownerId;
     * @param string $name
     * @param int $width
     * @param int $height
     * @param int $top
     * @param int $left
     * @param int $zIndex
     * @return Region
     */
    public static function create($ownerId, $name, $width, $height, $top, $left, $zIndex = 0)
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
        $region->zIndex = $zIndex;

        // Create a Playlist for this region
        // many to many relationship
        $playlist = PlaylistFactory::create($name, $ownerId);
        $region->assignPlaylist($playlist);

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
        return RegionFactory::query(array(), array('disableUserCheck' => 1, 'layoutId' => $layoutId));
    }

    /**
     * Get the regions for a playlist
     * @param int $playlistId
     * @return array[\Xibo\Entity\Region]
     */
    public static function getByPlaylistId($playlistId)
    {
        // Get all regions for this layout
        return RegionFactory::query(array(), array('disableUserCheck' => 1, 'playlistId' => $playlistId));
    }

    /**
     * Load a region
     * @param int $regionId
     * @return Region
     */
    public static function loadByRegionId($regionId)
    {
        $region = RegionFactory::getById($regionId);
        $region->load();
        return $region;
    }

    /**
     * Get by RegionId
     * @param int $regionId
     * @return Region
     * @throws NotFoundException
     */
    public static function getById($regionId)
    {
        // Get a region by its ID
        $regions = RegionFactory::query(array(), array('disableUserCheck' => 1, 'regionId' => $regionId));

        if (count($regions) <= 0)
            throw new NotFoundException(__('Region not found'));

        return $regions[0];
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
        $sql = '
          SELECT `region`.regionId,
              `region`.layoutId,
              `region`.ownerId,
              `region`.name,
              `region`.width,
              `region`.height,
              `region`.top,
              `region`.left,
              `region`.zIndex,
              `region`.duration
        ';

        if (Sanitize::getInt('playlistId', $filterBy) !== null) {
            $sql .= ', `lkregionplaylist`.displayOrder ';
        }

        $sql .= '
            FROM `region`
        ';

        if (Sanitize::getInt('playlistId', $filterBy) !== null) {
            // Restrict to assigned playlists
            $sql .= '
                INNER JOIN `lkregionplaylist`
                ON `lkregionplaylist`.regionId = `region`.regionId
                    AND `lkregionplaylist`.playlistId = :playlistId
            ';

            $params['playlistId'] = Sanitize::getInt('playlistId', $filterBy);
        }

        $sql .= ' WHERE 1 = 1 ';

        if (Sanitize::getInt('regionId', $filterBy) != 0) {
            $sql .= ' AND regionId = :regionId ';
            $params['regionId'] = Sanitize::getInt('regionId', $filterBy);
        }

        if (Sanitize::getInt('layoutId', $filterBy) != 0) {
            $sql .= ' AND layoutId = :layoutId ';
            $params['layoutId'] = Sanitize::getInt('layoutId', $filterBy);
        }

        Log::sql($sql, $params);

        foreach (PDOConnect::select($sql, $params) as $row) {
            $entries[] = (new Region())->hydrate($row, ['intProperties' => ['zIndex']]);
        }

        return $entries;
    }
}