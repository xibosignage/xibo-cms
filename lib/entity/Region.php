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


use Xibo\Factory\PlaylistFactory;

class Region
{
    public $regionId;
    public $ownerId;

    public $name;
    public $width;
    public $height;
    public $top;
    public $left;
    public $zIndex;

    public $playlists;

    public $basicInfoLoaded = false;

    public function __construct()
    {
        $this->playlists = array();
    }

    public function __clone()
    {
        // Clear the IDs and clone the playlist
        $this->regionId = null;

        $this->playlists = array_map(function ($object) { return clone $object; }, $this->playlists);
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
     * Load
     */
    public function load()
    {
        // Load all playlists
        $this->playlists = PlaylistFactory::loadByRegionId($this->regionId);
        foreach ($this->playlists as $playlist) {
            /* @var Playlist $playlist */
            $playlist->load();
        }
    }

    public function save()
    {
        if ($this->regionId == null || $this->regionId == 0)
            $this->add();
        else
            $this->update();

        // Save all Playlists
        foreach ($this->playlists as $playlist) {
            /* @var Playlist $playlist */
            $playlist->save();
        }
    }

    public function delete()
    {

    }

    // Add / Update
    private function add()
    {

    }

    private function update()
    {

    }
}