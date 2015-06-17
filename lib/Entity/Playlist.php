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


use Xibo\Exception\NotFoundException;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\Log;
use Xibo\Storage\PDOConnect;

class Playlist implements \JsonSerializable
{
    use EntityTrait;
    public $playlistId;
    public $ownerId;

    public $name;

    public $tags = [];
    public $regions = [];
    public $widgets = [];
    public $permissions = [];

    // Display Order when assigned to a Playlist
    public $displayOrder;

    public function __construct()
    {
        // Exclude properties that will cause recursion
        $this->excludeProperty('regions');
    }

    public function __clone()
    {
        $this->hash = null;
        $this->playlistId = null;
        $this->regions = [];

        $this->widgets = array_map(function ($object) { return clone $object; }, $this->widgets);
    }

    public function __toString()
    {
        return sprintf('Playlist %s. Widgets = %d. PlaylistId = %d', $this->name, count($this->widgets), $this->playlistId);
    }

    private function hash()
    {
        return md5($this->playlistId . $this->ownerId . $this->name);
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->playlistId;
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

        foreach ($this->widgets as $widget) {
            /* @var Widget $widget */
            $widget->setOwner($ownerId);
        }
    }

    /**
     * Get Widget at Index
     * @param int $index
     * @return Widget
     * @throws NotFoundException
     */
    public function getWidgetAt($index)
    {
        foreach ($this->widgets as $widget) {
            /* @var Widget $widget */
            if ($widget->displayOrder == $index)
                return $widget;
        }

        throw new NotFoundException(sprintf(__('Widget not found at sequence %d'), $index));
    }

    /**
     * Assign this Playlist to a Region
     * @param Region $region
     */
    public function assignRegion($region)
    {
        $this->load();

        $region->displayOrder = ($region->displayOrder == null || $region->displayOrder == 0) ? count($this->regions) : $region->displayOrder ;
        $this->regions[] = $region;
    }

    /**
     * Unassign a region
     * @param $region
     */
    public function unassignRegion($region)
    {
        $this->load();

        $this->regions = array_udiff($this->regions, [$region], function($a, $b) {
            /**
             * @var Region $a
             * @var Region $b
             */
            return $a->getId() - $b->getId() + $a->displayOrder - $b->displayOrder;
        });
    }

    /**
     * Load
     * @param array $loadOptions
     */
    public function load($loadOptions = [])
    {
        if ($this->playlistId == null || $this->loaded)
            return;

        $options = array_merge(['playlistIncludeRegionAssignments' => true], $loadOptions);

        Log::debug('Load Playlist with %s', json_encode($options));

        // Load permissions
        $this->permissions = PermissionFactory::getByObjectId(get_class(), $this->playlistId);

        // Load the widgets
        foreach (WidgetFactory::getByPlaylistId($this->playlistId) as $widget) {
            /* @var Widget $widget */
            $widget->load();
            $this->widgets[] = $widget;
        }

        if ($options['playlistIncludeRegionAssignments']) {
            // Load the region assignments
            foreach (RegionFactory::getByPlaylistId($this->playlistId) as $region) {
                /* @var Region $region */
                $this->regions[] = $region;
            }
        }

        $this->hash = $this->hash();
        $this->loaded = true;
    }

    /**
     * Save
     */
    public function save()
    {
        if ($this->playlistId == null || $this->playlistId == 0)
            $this->add();
        else if ($this->hash != $this->hash())
            $this->update();

        foreach ($this->widgets as $widget) {
            /* @var Widget $widget */

            // Assert the playlistId
            $widget->playlistId = $this->playlistId;
            $widget->save();
        }

        // Manage the assignments to regions
        $this->manageAssignments();
    }

    /**
     * Delete
     */
    public function delete()
    {
        // We must ensure everything is loaded before we delete
        if ($this->hash == null)
            $this->load();

        Log::debug('Deleting ' . $this);

        // Delete Permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->deleteAll();
        }

        // Delete widgets
        foreach ($this->widgets as $widget) {
            /* @var Widget $widget */

            // Assert the playlistId
            $widget->playlistId = $this->playlistId;
            $widget->delete();
        }

        // Unlink regions
        $this->unlinkRegions();

        // Delete this playlist
        PDOConnect::update('DELETE FROM `playlist` WHERE playlistId = :playlistId', array('playlistId' => $this->playlistId));
    }

    private function add()
    {
        Log::debug('Adding Playlist ' . $this->name);

        $sql = 'INSERT INTO `playlist` (`name`, `ownerId`) VALUES (:name, :ownerId)';
        $this->playlistId = PDOConnect::insert($sql, array(
            'name' => $this->name,
            'ownerId' => $this->ownerId
        ));
    }

    private function update()
    {
        Log::debug('Updating Playlist ' . $this->name . '. Id = ' . $this->playlistId);

        $sql = 'UPDATE `playlist` SET `name` = :name WHERE `playlistId` = :playlistId';
        PDOConnect::update($sql, array(
            'playlistId' => $this->playlistId,
            'name' => $this->name
        ));
    }

    private function manageAssignments()
    {
        $this->linkRegions();
        $this->unlinkRegions();
    }

    /**
     * Link regions
     */
    private function linkRegions()
    {
        foreach ($this->regions as $region) {
            /* @var Region $region */
            PDOConnect::insert('INSERT INTO `lkregionplaylist` (regionId, playlistId, displayOrder) VALUES (:regionId, :playlistId, :displayOrder) ON DUPLICATE KEY UPDATE regionId = regionId', array(
                'regionId' => $region->regionId,
                'playlistId' => $this->playlistId,
                'displayOrder' => $region->displayOrder
            ));
        }
    }

    /**
     * Unlink all Regions
     */
    private function unlinkRegions()
    {
        // Unlink any media that is NOT in the collection
        $params = ['playlistId' => $this->playlistId];

        $sql = '
          DELETE FROM `lkregionplaylist` WHERE playlistId = :playlistId
        ';

        $i = 0;
        foreach ($this->regions as $region) {
            /* @var \Xibo\Entity\Region $region */

            $sql .= ' AND ( ';

            $i++;
            $sql .= ' (regionId <> :regionId' . $i . ' AND displayOrder <> :displayOrder' . $i . '))';
            $params['regionId' . $i] = $region->regionId;
            $params['displayOrder' . $i] = $region->displayOrder;
        }

        PDOConnect::update($sql, $params);
    }
}