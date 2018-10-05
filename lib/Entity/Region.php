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


use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\RegionOptionFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Region
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Region implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID of this region")
     * @var int
     */
    public $regionId;

    /**
     * @SWG\Property(description="The Layout ID this region belongs to")
     * @var int
     */
    public $layoutId;

    /**
     * @SWG\Property(description="The userId of the User that owns this Region")
     * @var int
     */
    public $ownerId;

    /**
     * @SWG\Property(description="The name of this Region")
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(description="Width of the region")
     * @var double
     */
    public $width;

    /**
     * @SWG\Property(description="Height of the Region")
     * @var double
     */
    public $height;

    /**
     * @SWG\Property(description="The top coordinate of the Region")
     * @var double
     */
    public $top;

    /**
     * @SWG\Property(description="The left coordinate of the Region")
     * @var double
     */
    public $left;

    /**
     * @SWG\Property(description="The z-index of the Region to control Layering")
     * @var int
     */
    public $zIndex;

    /**
     * @SWG\Property(description="An array of Playlists assigned")
     * @var Playlist[]
     */
    public $playlists = [];

    /**
     * @SWG\Property(description="An array of Region Options")
     * @var RegionOption[]
     */
    public $regionOptions = [];

    /**
     * @SWG\Property(description="An array of Permissions")
     * @var Permission[]
     */
    public $permissions = [];

    /**
     * @SWG\Property(description="When linked from a Playlist, what is the display order of that link")
     * @var int
     */
    public $displayOrder;

    /**
     * @var int
     * @SWG\Property(
     *  description="A read-only estimate of this Regions's total duration in seconds. This is valid when the parent layout status is 1 or 2."
     * )
     */
    public $duration;

    /**
     * Temporary Id used during import/upgrade
     * @var string read only string
     */
    public $tempId = null;

    /**  @var DateServiceInterface */
    private $dateService;

    /**
     * @var RegionFactory
     */
    private $regionFactory;

    /**
     * @var RegionOptionFactory
     */
    private $regionOptionFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * @var PlaylistFactory
     */
    private $playlistFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param DateServiceInterface $date
     * @param RegionFactory $regionFactory
     * @param PermissionFactory $permissionFactory
     * @param RegionOptionFactory $regionOptionFactory
     * @param PlaylistFactory $playlistFactory
     */
    public function __construct($store, $log, $date, $regionFactory, $permissionFactory, $regionOptionFactory, $playlistFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->dateService = $date;
        $this->regionFactory = $regionFactory;
        $this->permissionFactory = $permissionFactory;
        $this->regionOptionFactory = $regionOptionFactory;
        $this->playlistFactory = $playlistFactory;
    }

    public function __clone()
    {
        // Clear the IDs and clone the playlist
        $this->regionId = null;
        $this->hash = null;
        $this->permissions = [];

        $this->playlists = array_map(function ($object) { return clone $object; }, $this->playlists);
        $this->regionOptions = array_map(function ($object) { return clone $object; }, $this->regionOptions);
    }

    public function __toString()
    {
        return sprintf('Region %s - %d x %d (%d, %d). RegionId = %d, LayoutId = %d. OwnerId = %d. Duration = %d', $this->name, $this->width, $this->height, $this->top, $this->left, $this->regionId, $this->layoutId, $this->ownerId, $this->duration);
    }

    private function hash()
    {
        return md5($this->name . $this->width . $this->height . $this->top . $this->left . $this->regionId . $this->zIndex . $this->duration);
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
     * @param bool $cascade Cascade ownership change down to Playlist records
     */
    public function setOwner($ownerId, $cascade = false)
    {
        $this->load();

        $this->ownerId = $ownerId;

        if ($cascade) {
            foreach ($this->playlists as $playlist) {
                /* @var Playlist $playlist */
                $playlist->setOwner($ownerId);
            }
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
        $this->load();

        foreach ($this->regionOptions as $regionOption) {
            /* @var RegionOption $regionOption */
            if ($regionOption->option == $option)
                return $regionOption;
        }

        $this->getLog()->debug('RegionOption %s not found', $option);

        throw new NotFoundException('Region Option not found');
    }

    /**
     * Get Region Option Value
     * @param string $option
     * @param mixed $default
     * @return mixed
     */
    public function getOptionValue($option, $default = null)
    {
        $this->load();

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
            $this->regionOptions[] = $this->regionOptionFactory->create($this->regionId, $option, $value);
        }
    }

    /**
     * Assign this Playlist to a Region
     * @param Playlist $playlist
     */
    public function assignPlaylist($playlist)
    {
        $this->load();

        $playlist->displayOrder = ($playlist->displayOrder == null || $playlist->displayOrder == 0) ? count($this->playlists) + 1 : $playlist->displayOrder ;
        $this->playlists[] = $playlist;
    }

    /**
     * Unassign a Playlist
     * @param $playlist
     */
    public function unassignPlaylist($playlist)
    {
        $this->load();

        $this->playlists = array_udiff($this->playlists, [$playlist], function($a, $b) {
            /**
             * @var Playlist $a
             * @var Playlist $b
             */
            return $a->getId() - $b->getId() + $a->displayOrder - $b->displayOrder;
        });
    }

    /**
     * Load
     * @param array $options
     */
    public function load($options = [])
    {
        if ($this->loaded || $this->regionId == 0)
            return;

        $options = array_merge(['regionIncludePlaylists' => true], $options);

        $this->getLog()->debug('Load Region with %s', json_encode($options));

        // Load permissions
        $this->permissions = $this->permissionFactory->getByObjectId(get_class(), $this->regionId);

        // Load all playlists
        if ($options['regionIncludePlaylists']) {
            $this->playlists = $this->playlistFactory->getByRegionId($this->regionId);

            foreach ($this->playlists as $playlist) {
                /* @var Playlist $playlist */
                $playlist->setChildObjectDependencies($this->regionFactory);
                $playlist->load($options);
            }
        }

        // Get region options
        $this->regionOptions = $this->regionOptionFactory->getByRegionId($this->regionId);

        $this->hash = $this->hash();
        $this->loaded = true;
    }

    /**
     * Validate the region
     */
    public function validate()
    {
        if ($this->width <= 0 || $this->height <= 0)
            throw new \InvalidArgumentException(__('The Region dimensions cannot be empty or negative'));

        // Check zindex is positive
        if ($this->zIndex < 0)
            throw new InvalidArgumentException(__('Layer must be 0 or a positive number'), 'zIndex');
    }

    /**
     * Save
     * @param array $options
     */
    public function save($options = [])
    {
        $options = array_merge([
            'saveRegionOptions' => true,
            'manageRegionAssignments' => true,
            'validate' => true,
            'audit' => true,
            'notify' => true
        ], $options);

        $this->getLog()->debug('Saving %s. Options = %s', $this, json_encode($options, JSON_PRETTY_PRINT));

        if ($options['validate'])
            $this->validate();

        if ($this->regionId == null || $this->regionId == 0) {
            $this->add();

            if ($options['audit'])
                $this->audit($this->regionId, 'Added', ['regionId' => $this->regionId, 'details' => (string)$this]);
        }
        else if ($this->hash != $this->hash()) {
            $this->update();

            if ($options['audit'])
                $this->audit($this->regionId, 'Saved');
        }

        if ($options['saveRegionOptions']) {
            // Save all Options
            foreach ($this->regionOptions as $regionOption) {
                /* @var RegionOption $regionOption */
                // Assert the regionId
                $regionOption->regionId = $this->regionId;
                $regionOption->save();
            }
        }

        if ($options['manageRegionAssignments']) {
            // Manage the assignments to regions
            $this->manageAssignments($options);
        }
    }

    /**
     * Delete Region
     * @param array $options
     */
    public function delete($options = [])
    {
        $options = array_merge([
            'deleteOrphanedPlaylists' => true,
            'notify' => true
        ], $options);

        // We must ensure everything is loaded before we delete
        if ($this->hash == null)
            $this->load();

        $this->getLog()->debug('Deleting ' . $this);

        // Delete Permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->deleteAll();
        }

        // Delete all region options
        foreach ($this->regionOptions as $regionOption) {
            /* @var RegionOption $regionOption */
            $regionOption->delete();
        }

        // Store the playlists locally for use after unlink
        $playlists = $this->playlists;

        // Unlink playlists
        $this->playlists = [];
        $this->unlinkPlaylists();

        // Should we delete orphaned playlists?
        if ($options['deleteOrphanedPlaylists']) {
            $this->getLog()->debug('We should delete orphaned playlists, checking %d playlists.', count($playlists));

            // Delete
            foreach ($playlists as $playlist) {
                /* @var Playlist $playlist */
                if (!$playlist->hasLayouts()) {
                    $this->getLog()->debug('Deleting orphaned playlist: %d', $playlist->playlistId);
                    $playlist->delete();
                }
                else {
                    $this->getLog()->debug('Playlist still linked to Layouts, skipping playlist delete');
                }
            }
        }

        // Delete this region
        $this->getStore()->update('DELETE FROM `region` WHERE regionId = :regionId', array('regionId' => $this->regionId));

        $this->getLog()->audit('Region', $this->regionId, 'Region Deleted', ['regionId' => $this->regionId]);

        // Notify Layout
        if ($options['notify'])
            $this->notifyLayout();
    }

    // Add / Update
    /**
     * Add
     */
    private function add()
    {
        $this->getLog()->debug('Adding region to LayoutId ' . $this->layoutId);

        $sql = 'INSERT INTO `region` (`layoutId`, `ownerId`, `name`, `width`, `height`, `top`, `left`, `zIndex`) VALUES (:layoutId, :ownerId, :name, :width, :height, :top, :left, :zIndex)';

        $this->regionId = $this->getStore()->insert($sql, array(
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
        $this->getLog()->debug('Editing %s', $this);

        $sql = '
          UPDATE `region` SET
            `ownerId` = :ownerId,
            `name` = :name,
            `width` = :width,
            `height` = :height,
            `top` = :top,
            `left` = :left,
            `zIndex` = :zIndex,
            `duration` = :duration
           WHERE `regionId` = :regionId
        ';

        $this->getStore()->update($sql, array(
            'ownerId' => $this->ownerId,
            'name' => $this->name,
            'width' => $this->width,
            'height' => $this->height,
            'top' => $this->top,
            'left' => $this->left,
            'zIndex' => $this->zIndex,
            'duration' => $this->duration,
            'regionId' => $this->regionId
        ));
    }

    /**
     * @param array $options
     */
    private function manageAssignments($options)
    {
        $this->linkPlaylists($options);
        $this->unlinkPlaylists();
    }

    /**
     * Link regions
     * @param array $options
     */
    private function linkPlaylists($options)
    {
        foreach ($this->playlists as $playlist) {
            /* @var Playlist $playlist */

            // The playlist might be new
            if ($playlist->playlistId == 0)
                $playlist->save($options);

            $this->getStore()->insert('INSERT INTO `lkregionplaylist` (regionId, playlistId, displayOrder) VALUES (:regionId, :playlistId, :displayOrder) ON DUPLICATE KEY UPDATE regionId = regionId', array(
                'regionId' => $this->regionId,
                'playlistId' => $playlist->playlistId,
                'displayOrder' => $playlist->displayOrder
            ));
        }
    }

    /**
     * Unlink all Regions
     */
    private function unlinkPlaylists()
    {
        // Unlink any media that is NOT in the collection
        $params = ['regionId' => $this->regionId];

        $sql = '
          DELETE FROM `lkregionplaylist` WHERE regionId = :regionId
        ';

        $i = 0;
        foreach ($this->playlists as $playlist) {
            /* @var Playlist $playlist */

            $sql .= ' AND ( ';

            $i++;
            $sql .= ' (playlistId <> :playlistId' . $i . ' AND displayOrder <> :displayOrder' . $i . '))';
            $params['playlistId' . $i] = $playlist->playlistId;
            $params['displayOrder' . $i] = $playlist->displayOrder;
        }



        $this->getStore()->update($sql, $params);
    }

    public function notifyLayout()
    {
        $this->getStore()->update('
            UPDATE `layout` SET `status` = 3, `modifiedDT` = :modifiedDt WHERE layoutId = :layoutId
        ', [
            'layoutId' => $this->layoutId,
            'modifiedDt' => $this->dateService->getLocalDate()
        ]);
    }
}