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


use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\TagFactory;
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
     * @SWG\Property(description="The RegionId if this Playlist is specific to a Region")
     * @var int
     */
    public $regionId;

    /**
     * @var string
     * @SWG\Property(
     *  description="The datetime the Layout was created"
     * )
     */
    public $createdDt;

    /**
     * @var string
     * @SWG\Property(
     *  description="The datetime the Layout was last modified"
     * )
     */
    public $modifiedDt;

    /**
     * @var int
     * @SWG\Property(
     *  description="A read-only estimate of this Layout's total duration in seconds. This is equal to the longest region duration and is valid when the layout status is 1 or 2."
     * )
     */
    public $duration = 0;

    /**
     * @SWG\Property(description="An array of Tags")
     * @var Tag[]
     */
    public $tags = [];

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

    // Read only properties
    public $owner;
    public $groupsWithPermissions;
    private $unassignTags = [];

    //<editor-fold desc="Factories and Dependencies">
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
     * @var TagFactory
     */
    private $tagFactory;
    //</editor-fold>

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param DateServiceInterface $date
     * @param PermissionFactory $permissionFactory
     * @param WidgetFactory $widgetFactory
     * @param TagFactory $tagFactory
     */
    public function __construct($store, $log, $date, $permissionFactory, $widgetFactory, $tagFactory)
    {
        $this->setCommonDependencies($store, $log);

        $this->dateService = $date;
        $this->permissionFactory = $permissionFactory;
        $this->widgetFactory = $widgetFactory;
        $this->tagFactory = $tagFactory;
    }

    /**
     * Clone this Playlist
     */
    public function __clone()
    {
        $this->hash = null;
        $this->playlistId = null;
        $this->regionId = null;
        $this->permissions = [];
        $this->tags = [];

        $this->widgets = array_map(function ($object) { return clone $object; }, $this->widgets);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('Playlist %s. Widgets = %d. PlaylistId = %d. RegionId = %d', $this->name, count($this->widgets), $this->playlistId, $this->regionId);
    }

    /**
     * @return string
     */
    private function hash()
    {
        return md5($this->regionId . $this->playlistId . $this->ownerId . $this->name);
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
     * @param Tag[] $tags
     */
    public function replaceTags($tags = [])
    {
        if (!is_array($this->tags) || count($this->tags) <= 0)
            $this->tags = $this->tagFactory->loadByPlaylistId($this->playlistId);

        $this->unassignTags = array_udiff($this->tags, $tags, function($a, $b) {
            /* @var Tag $a */
            /* @var Tag $b */
            return $a->tagId - $b->tagId;
        });

        $this->getLog()->debug('Tags to be removed: ' . json_encode($this->unassignTags));

        // Replace the arrays
        $this->tags = $tags;

        $this->getLog()->debug('Tags remaining: ' . json_encode($this->tags));
    }

    /**
     * Load
     * @param array $loadOptions
     * @return $this
     */
    public function load($loadOptions = [])
    {
        if ($this->playlistId == null || $this->loaded)
            return $this;

        // Options
        $options = array_merge([
            'loadPermissions' => true,
            'loadWidgets' => true,
            'loadTags' => true
        ], $loadOptions);

        $this->getLog()->debug('Load Playlist with ' . json_encode($options));

        // Load permissions
        if ($options['loadPermissions'])
            $this->permissions = $this->permissionFactory->getByObjectId(get_class(), $this->playlistId);

        // Load all tags
        if ($options['loadTags'])
            $this->tags = $this->tagFactory->loadByPlaylistId($this->playlistId);

        // Load the widgets
        if ($options['loadWidgets']) {
            foreach ($this->widgetFactory->getByPlaylistId($this->playlistId) as $widget) {
                /* @var Widget $widget */
                $widget->load();
                $this->widgets[] = $widget;
            }
        }

        $this->hash = $this->hash();
        $this->loaded = true;

        return $this;
    }

    /**
     * Save
     * @param array $options
     */
    public function save($options = [])
    {
        // Default options
        $options = array_merge([
            'saveTags' => true
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
            $widget->save();
        }

        if ($options['saveTags']) {
            $this->getLog()->debug('Saving tags on ' . $this);

            // Save the tags
            if (is_array($this->tags)) {
                foreach ($this->tags as $tag) {
                    /* @var Tag $tag */

                    $this->getLog()->debug('Assigning tag ' . $tag->tag);

                    $tag->assignPlaylist($this->playlistId);
                    $tag->save();
                }
            }

            // Remove unwanted ones
            if (is_array($this->unassignTags)) {
                foreach ($this->unassignTags as $tag) {
                    /* @var Tag $tag */
                    $this->getLog()->debug('Unassigning tag ' . $tag->tag);

                    $tag->unassignPlaylist($this->playlistId);
                    $tag->save();
                }
            }
        }
    }

    /**
     * Delete
     * @throws InvalidArgumentException
     */
    public function delete()
    {
        // We must ensure everything is loaded before we delete
        if (!$this->loaded)
            $this->load();

        $this->getLog()->debug('Deleting ' . $this);

        if ($this->regionId != 0)
            throw new InvalidArgumentException(__('This Playlist belongs to a Region, please delete the Region instead.'), 'regionId');

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

        // Delete this playlist
        $this->getStore()->update('DELETE FROM `playlist` WHERE playlistId = :playlistId', array('playlistId' => $this->playlistId));
    }

    /**
     * Add
     */
    private function add()
    {
        $this->getLog()->debug('Adding Playlist ' . $this->name);

        $time = date('Y-m-d H:i:s');

        $sql = 'INSERT INTO `playlist` (`name`, `ownerId`, `regionId`, `createdDt`, `modifiedDt`) VALUES (:name, :ownerId, :regionId, :createdDt, :modifiedDt)';
        $this->playlistId = $this->getStore()->insert($sql, array(
            'name' => $this->name,
            'ownerId' => $this->ownerId,
            'regionId' => $this->regionId == 0 ? null : $this->regionId,
            'createdDt' => $time,
            'modifiedDt' => $time,
        ));
    }

    /**
     * Update
     */
    private function update()
    {
        $this->getLog()->debug('Updating Playlist ' . $this->name . '. Id = ' . $this->playlistId);

        $sql = '
            UPDATE `playlist` SET 
                `name` = :name, 
                `regionId` = :regionId, 
                `modifiedDt` = :modifiedDt, 
                `duration` = :duration 
             WHERE `playlistId` = :playlistId
        ';

        $this->getStore()->update($sql, array(
            'playlistId' => $this->playlistId,
            'name' => $this->name,
            'regionId' => $this->regionId == 0 ? null : $this->regionId,
            'duration' => $this->duration,
            'modifiedDt' => date('Y-m-d H:i:s')
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
        //TODO: this will need to change to assess as if we were a sub-playlist
        $this->getStore()->update('
            UPDATE `layout` SET `status` = 3, `modifiedDT` = :modifiedDt WHERE layoutId IN (
              SELECT `region`.layoutId
                FROM `region`
               WHERE `region`.playlistId = :playlistId
            )
        ', [
            'playlistId' => $this->playlistId,
            'modifiedDt' => $this->dateService->getLocalDate()
        ]);
    }
}