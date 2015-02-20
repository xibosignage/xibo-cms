<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Playlist.php) is part of Xibo.
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


use Xibo\Factory\WidgetFactory;

class Playlist
{
    public $playlistId;
    public $ownerId;

    public $name;

    public $tags;
    public $widgets;

    /**
     * The regions that this Playlist belongs to
     * @var array[int]
     */
    public $regionIds;

    public function __construct()
    {
        $this->widgets = array();
        $this->tags = array();
        $this->regionIds = array();
    }

    public function __clone()
    {
        $this->playlistId = null;

        $this->widgets = array_map(function ($object) { return clone $object; }, $this->widgets);
    }

    /**
     * Sets the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->ownerId = $ownerId;

        foreach ($this->widgets as $widget) {
            /* @var Widget $widget */
            $widget->setOwner($ownerId);
        }
    }

    /**
     * Assign this Playlist to a Region
     * @param int $regionId
     */
    public function assignRegion($regionId)
    {
        if (!in_array($regionId, $this->regionIds))
            $this->regionIds[] = $regionId;
    }

    /**
     * Load
     */
    public function load()
    {
        $this->widgets = WidgetFactory::loadByPlaylistId($this->playlistId);

        // Load the widgets
        foreach ($this->widgets as $widget) {
            /* @var Widget $widget */
            $widget->load();
        }
    }

    /**
     * Saves
     */
    public function save()
    {
        if ($this->playlistId == null || $this->playlistId == 0)
            $this->add();
        else
            $this->update();

        foreach ($this->widgets as $widget) {
            /* @var Widget $widget */

            // Assert the playlistId
            $widget->playlistId = $this->playlistId;
            $widget->save();
        }

        // Manage the assignments to regions
        $this->linkRegions();
    }

    private function add()
    {
        \Debug::Audit('Adding Playlist ' . $this->name);

        $sql = 'INSERT INTO `playlist` (`name`, `ownerId`) VALUES (:name, :ownerId)';
        $this->playlistId = \PDOConnect::insert($sql, array(
            'name' => $this->name,
            'ownerId' => $this->ownerId
        ));
    }

    private function update()
    {
        \Debug::Audit('Updating Playlist ' . $this->name . '. Id = ' . $this->playlistId);

        $sql = 'UPDATE `playlist` SET `name` = :name WHERE `playlistId` = :playlistId';
        \PDOConnect::update($sql, array(
            'playlistId' => $this->playlistId,
            'name' => $this->name
        ));
    }

    /**
     * Link regions
     */
    private function linkRegions()
    {
        $order = 0;
        foreach ($this->regionIds as $regionId) {
            $order++;
            \PDOConnect::insert('INSERT INTO `lkregionplaylist` (regionId, playlistId, displayOrder) VALUES (:regionId, :playlistId, :displayOrder) ON DUPLICATE KEY UPDATE regionId = :regionId2', array(
                'regionId' => $regionId,
                'regionId2' => $regionId,
                'playlistId' => $this->playlistId,
                'displayOrder' => $order
            ));
        }
    }
}