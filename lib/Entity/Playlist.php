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
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Playlist
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Playlist implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID of this Playlist")
     * @var int
     */
    public $playlistId;

    /**
     * @SWG\Property(description="The userId of the User that owns this Playlist")
     * @var int
     */
    public $ownerId;

    /**
     * @SWG\Property(description="The Name of the Playlist")
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(description="An array of Tags")
     * @var Tag[]
     */
    public $tags = [];

    /**
     * @SWG\Property(description="An array of Regions this Playlist is assigned to")
     * @var Region[]
     */
    public $regions = [];

    /**
     * @SWG\Property(description="An array of Widgets assigned to this Playlist")
     * @var Widget[]
     */
    public $widgets = [];

    /**
     * @SWG\Property(description="An array of permissions")
     * @var Permission[]
     */
    public $permissions = [];

    /**
     * @SWG\Property(description="The display order of the Playlist when assigned to a Region")
     * @var int
     */
    public $displayOrder;

    /**
     * @var DateServiceInterface
     */
    private $dateService;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var WidgetFactory
     */
    private $widgetFactory;

    /**
     * @var RegionFactory
     */
    private $regionFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param DateServiceInterface $date
     * @param PermissionFactory $permissionFactory
     * @param WidgetFactory $widgetFactory
     */
    public function __construct($store, $log, $date, $permissionFactory, $widgetFactory)
    {
        $this->setCommonDependencies($store, $log);

        $this->dateService = $date;
        $this->permissionFactory = $permissionFactory;
        $this->widgetFactory = $widgetFactory;

        $this->excludeProperty('regions');
    }

    /**
     * @param $regionFactory
     * @return $this
     */
    public function setChildObjectDependencies($regionFactory)
    {
        $this->regionFactory = $regionFactory;
        return $this;
    }

    public function __clone()
    {
        $this->hash = null;
        $this->playlistId = null;
        $this->regions = [];
        $this->permissions = [];

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
        if ($index <= count($this->widgets)) {
            $zeroBased = $index - 1;
            if (isset($this->widgets[$zeroBased])) {
                return $this->widgets[$zeroBased];
            }
        }

        throw new NotFoundException(sprintf(__('Widget not found at index %d'), $index));
    }

    /**
     * @param Widget $widget
     */
    public function assignWidget($widget)
    {
        $this->load();

        $widget->displayOrder = count($this->widgets) + 1;
        $this->widgets[] = $widget;
    }

    /**
     * Load
     * @param array $loadOptions
     */
    public function load($loadOptions = [])
    {
        if ($this->playlistId == null || $this->loaded)
            return;

        // Options
        $options = array_merge([
            'playlistIncludeRegionAssignments' => true,
            'loadPermissions' => true,
            'loadWidgets' => true
        ], $loadOptions);

        $this->getLog()->debug('Load Playlist with %s', json_encode($options));

        // Load permissions
        if ($options['loadPermissions'])
            $this->permissions = $this->permissionFactory->getByObjectId(get_class(), $this->playlistId);

        // Load the widgets
        if ($options['loadWidgets']) {
            foreach ($this->widgetFactory->getByPlaylistId($this->playlistId) as $widget) {
                /* @var Widget $widget */
                $widget->load();
                $this->widgets[] = $widget;
            }
        }

        if ($options['playlistIncludeRegionAssignments']) {
            // Load the region assignments
            foreach ($this->regionFactory->getByPlaylistId($this->playlistId) as $region) {
                /* @var Region $region */
                $this->regions[] = $region;
            }
        }

        $this->hash = $this->hash();
        $this->loaded = true;
    }

    /**
     * Save
     * @param array $options
     */
    public function save($options = [])
    {
        // Default options
        $options = array_merge([
            'notify' => true
        ], $options);

        if ($this->playlistId == null || $this->playlistId == 0)
            $this->add();
        else if ($this->hash != $this->hash())
            $this->update();

        // Sort the widgets by their display order
        usort($this->widgets, function($a, $b) {
            /**
             * @var Widget $a
             * @var Widget$b
             */
            return $a->displayOrder - $b->displayOrder;
        });

        // Assert the Playlist on all widgets and apply a display order
        // this keeps the widgets in numerical order on each playlist
        $i = 0;
        foreach ($this->widgets as $widget) {
            /* @var Widget $widget */
            $i++;

            // Assert the playlistId
            $widget->playlistId = $this->playlistId;
            // Assert the displayOrder
            $widget->displayOrder = $i;
            $widget->save($options);
        }
    }

    /**
     * Delete
     */
    public function delete()
    {
        // We must ensure everything is loaded before we delete
        if (!$this->loaded)
            $this->load();

        $this->getLog()->debug('Deleting ' . $this);

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
        foreach ($this->regions as $region) {
            /* @var Region $region */
            $region->unassignPlaylist($this);
            $region->save();
        }

        // Delete this playlist
        $this->getStore()->update('DELETE FROM `playlist` WHERE playlistId = :playlistId', array('playlistId' => $this->playlistId));
    }

    /**
     * Add
     */
    private function add()
    {
        $this->getLog()->debug('Adding Playlist ' . $this->name);

        $sql = 'INSERT INTO `playlist` (`name`, `ownerId`) VALUES (:name, :ownerId)';
        $this->playlistId = $this->getStore()->insert($sql, array(
            'name' => $this->name,
            'ownerId' => $this->ownerId
        ));
    }

    /**
     * Update
     */
    private function update()
    {
        $this->getLog()->debug('Updating Playlist ' . $this->name . '. Id = ' . $this->playlistId);

        $sql = 'UPDATE `playlist` SET `name` = :name WHERE `playlistId` = :playlistId';
        $this->getStore()->update($sql, array(
            'playlistId' => $this->playlistId,
            'name' => $this->name
        ));
    }

    /**
     * Notify all Layouts of a change to this playlist
     *  This only sets the Layout Status to require a build and to update the layout modified date
     *  once the build is triggered, either from the UI or maintenance it will assess the layout
     *  and call save() if required.
     *  Layout->save() will ultimately notify the interested display groups.
     */
    public function notifyLayouts()
    {
        $this->getStore()->update('
            UPDATE `layout` SET `status` = 3, `modifiedDT` = :modifiedDt WHERE layoutId IN (
              SELECT `region`.layoutId
                FROM `lkregionplaylist`
                  INNER JOIN `region`
                  ON region.regionId = `lkregionplaylist`.regionId
               WHERE `lkregionplaylist`.playlistId = :playlistId
            )
        ', [
            'playlistId' => $this->playlistId,
            'modifiedDt' => $this->dateService->getLocalDate()
        ]);
    }

    /**
     * Has layouts
     * @return bool
     */
    public function hasLayouts()
    {
        $results = $this->getStore()->select('SELECT COUNT(*) AS qty FROM `lkregionplaylist` WHERE playlistId = :playlistId', ['playlistId' => $this->playlistId]);

        return ($results[0]['qty'] > 0);
    }
}