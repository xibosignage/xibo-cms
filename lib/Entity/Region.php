<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Region.php) is part of Xibo.
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

namespace Xibo\Entity;


use Xibo\Exception\NotFoundException;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionOptionFactory;

class Region
{
    private $hash;
    public $regionId;
    public $layoutId;
    public $ownerId;

    public $name;
    public $width;
    public $height;
    public $top;
    public $left;
    public $zIndex;

    public $playlists;
    public $regionOptions;
    public $permissions;

    public function __construct()
    {
        $this->hash = null;
        $this->playlists = array();
        $this->regionOptions = array();
    }

    public function __clone()
    {
        // Clear the IDs and clone the playlist
        $this->regionId = null;
        $this->hash = null;

        $this->playlists = array_map(function ($object) { return clone $object; }, $this->playlists);
        $this->regionOptions = array_map(function ($object) { return clone $object; }, $this->regionOptions);
    }

    public function __toString()
    {
        return sprintf('Region %s - %d x %d (%d, %d). RegionId = %d, LayoutId = %d', $this->name, $this->width, $this->height, $this->top, $this->left, $this->regionId, $this->layoutId);
    }

    private function hash()
    {
        return md5($this->name . $this->width . $this->height . $this->top . $this->left . $this->regionId . $this->zIndex);
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->regionId;
    }

    /**
     * Get the OwnerId
     * @return int
     */
    public function getOwnerId()
    {
        return $this->ownerId;
    }

    /**
     * Sets the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->ownerId = $ownerId;

        foreach ($this->playlists as $playlist) {
            /* @var Playlist $playlist */
            $playlist->setOwner($ownerId);
        }
    }

    /**
     * Get Option
     * @param string $option
     * @return RegionOption
     * @throws NotFoundException
     */
    public function getOption($option)
    {
        foreach ($this->regionOptions as $regionOption) {
            /* @var RegionOption $regionOption */
            if ($regionOption->option == $option)
                return $regionOption;
        }

        throw new NotFoundException('Region Option not found');
    }

    /**
     * Get Region Option Value
     * @param string $option
     * @param mixed $default
     * @return mixed
     */
    public function getOptionValue($option, $default)
    {
        try {
            $regionOption = $this->getOption($option);
            return $regionOption->value;
        }
        catch (NotFoundException $e) {
            return $default;
        }
    }

    /**
     * Set Region Option Value
     * @param string $option
     * @param mixed $value
     */
    public function setOptionValue($option, $value)
    {
        try {
            $this->getOption($option)->value = $value;
        }
        catch (NotFoundException $e) {
            $this->regionOptions[] = RegionOptionFactory::create($this->regionId, $option, $value);
        }
    }

    /**
     * Load
     */
    public function load()
    {
        // Load permissions
        $this->permissions = PermissionFactory::getByObjectId(get_class(), $this->regionId);

        // Load all playlists
        $this->playlists = PlaylistFactory::getByRegionId($this->regionId);
        foreach ($this->playlists as $playlist) {
            /* @var Playlist $playlist */
            $playlist->load();

            // Assign my regionId
            $playlist->assignRegion($this->regionId);
        }

        // Get region options
        $this->regionOptions = RegionOptionFactory::getByRegionId($this->regionId);

        $this->hash = $this->hash();
    }

    /**
     * Save
     */
    public function save()
    {
        if ($this->regionId == null || $this->regionId == 0)
            $this->add();
        else if ($this->hash != $this->hash())
            $this->update();

        // Save all Playlists
        foreach ($this->playlists as $playlist) {
            /* @var Playlist $playlist */

            // Make sure this region is assigned
            $playlist->assignRegion($this->regionId);

            // Save the playlist
            $playlist->save();
        }

        // Save all Options
        foreach ($this->regionOptions as $regionOption) {
            /* @var RegionOption $regionOption */
            $regionOption->save();
        }
    }

    /**
     * Delete Region
     */
    public function delete()
    {
        // We must ensure everything is loaded before we delete
        if ($this->hash() == null)
            $this->load();

        \Debug::Audit('Deleting ' . $this);

        // Delete Permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->deleteAll();
        }

        // To delete a region we must delete all playlists
        foreach ($this->playlists as $playlist) {
            /* @var Playlist $playlist */

            // Make sure this region is assigned
            $playlist->assignRegion($this->regionId);

            // Save the playlist
            $playlist->delete();
        }

        // Delete all region options
        foreach ($this->regionOptions as $regionOption) {
            /* @var RegionOption $regionOption */
            $regionOption->delete();
        }

        // Delete this region
        \PDOConnect::update('DELETE FROM `region` WHERE regionId = :regionId', array('regionId' => $this->regionId));
    }

    // Add / Update
    /**
     * Add
     */
    private function add()
    {
        \Debug::Audit('Adding region to LayoutId ' . $this->layoutId);

        $sql = 'INSERT INTO `region` (`layoutId`, `ownerId`, `name`, `width`, `height`, `top`, `left`, `zIndex`) VALUES (:layoutId, :ownerId, :name, :width, :height, :top, :left, :zIndex)';

        $this->regionId = \PDOConnect::insert($sql, array(
            'layoutId' => $this->layoutId,
            'ownerId' => $this->ownerId,
            'name' => $this->name,
            'width' => $this->width,
            'height' => $this->height,
            'top' => $this->top,
            'left' => $this->left,
            'zIndex' => $this->zIndex
        ));
    }

    /**
     * Update Database
     */
    private function update()
    {
        \Debug::Audit('Editing region ' . $this->regionId . ' on LayoutId ' . $this->layoutId . ' zIndex ' . $this->zIndex);

        $sql = 'UPDATE `region` SET `ownerId` = :ownerId, `name` = :name, `width` = :width, `height` = :height, `top` = :top, `left` = :left, zIndex = :zIndex WHERE `regionId` = :regionId';

        \PDOConnect::update($sql, array(
            'ownerId' => $this->ownerId,
            'name' => $this->name,
            'width' => $this->width,
            'height' => $this->height,
            'top' => $this->top,
            'left' => $this->left,
            'zIndex' => $this->zIndex,
            'regionId' => $this->regionId
        ));
    }
}