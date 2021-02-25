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
use Xibo\Exception\XiboException;
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

    /**
     * @var Playlist|null
     * @SWG\Property(
     *   description="This Regions Playlist - null if getPlaylist() has not been called."
     * )
     */
    public $regionPlaylist = null;

    //<editor-fold desc="Factories and Dependencies">
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
    //</editor-fold>

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

    /**
     * Clone this object
     */
    public function __clone()
    {
        // Clear the regionId, clone the Playlist
        $this->regionId = null;
        $this->hash = null;
        $this->permissions = [];

        $this->regionPlaylist = clone $this->regionPlaylist;

        $this->regionOptions = array_map(function ($object) { return clone $object; }, $this->regionOptions);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('Region %s - %d x %d (%d, %d). RegionId = %d, LayoutId = %d. OwnerId = %d. Duration = %d', $this->name, $this->width, $this->height, $this->top, $this->left, $this->regionId, $this->layoutId, $this->ownerId, $this->duration);
    }

    /**
     * @return string
     */
    private function hash()
    {
        return md5($this->name . $this->ownerId . $this->width . $this->height . $this->top . $this->left . $this->regionId . $this->zIndex . $this->duration);
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
     * @throws XiboException
     */
    public function setOwner($ownerId, $cascade = false)
    {
        $this->load();

        $this->ownerId = $ownerId;

        if ($cascade) {
            $playlist = $this->getPlaylist();
            $playlist->setOwner($ownerId);
        }
    }

    /**
     * Get Option
     * @param string $option
     * @return RegionOption
     * @throws XiboException
     */
    public function getOption($option)
    {
        $this->load();

        foreach ($this->regionOptions as $regionOption) {
            /* @var RegionOption $regionOption */
            if ($regionOption->option == $option)
                return $regionOption;
        }

        $this->getLog()->debug('RegionOption ' . $option . ' not found');

        throw new NotFoundException('Region Option not found');
    }

    /**
     * Get Region Option Value
     * @param string $option
     * @param mixed $default
     * @return mixed
     * @throws XiboException
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
     * @throws XiboException
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
     * @param array $options
     * @return Playlist
     * @throws NotFoundException
     */
    public function getPlaylist($options = [])
    {
        if ($this->regionPlaylist === null) {
            $this->regionPlaylist = $this->playlistFactory->getByRegionId($this->regionId)->load($options);
        }

        return $this->regionPlaylist;
    }

    /**
     * Load
     * @param array $options
     * @throws XiboException
     */
    public function load($options = [])
    {
        if ($this->loaded || $this->regionId == 0)
            return;

        $options = array_merge([
            'loadPlaylists' => false
        ], $options);

        $this->getLog()->debug('Load Region with ' . json_encode($options));

        // Load permissions
        $this->permissions = $this->permissionFactory->getByObjectId(get_class(), $this->regionId);

        // Get region options
        $this->regionOptions = $this->regionOptionFactory->getByRegionId($this->regionId);

        // Load the Playlist?
        if ($options['loadPlaylists']) {
            $this->getPlaylist($options);
        }

        $this->hash = $this->hash();
        $this->loaded = true;
    }

    /**
     * Validate the region
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if ($this->width <= 0 || $this->height <= 0)
            throw new InvalidArgumentException(__('The Region dimensions cannot be empty or negative'), 'width/height');

        // Check zindex is positive
        if ($this->zIndex < 0)
            throw new InvalidArgumentException(__('Layer must be 0 or a positive number'), 'zIndex');
    }

    /**
     * Save
     * @param array $options
     * @throws XiboException
     */
    public function save($options = [])
    {
        $options = array_merge([
            'saveRegionOptions' => true,
            'validate' => true,
            'audit' => true,
            'notify' => true
        ], $options);

        $this->getLog()->debug('Saving ' . $this . '. Options = ' . json_encode($options, JSON_PRETTY_PRINT));

        if ($options['validate'])
            $this->validate();

        if ($options['audit']) {
            // get the layout specific campaignId
            $campaignId = 0;
            $sql = 'SELECT campaign.campaignId FROM layout INNER JOIN lkcampaignlayout on layout.layoutId = lkcampaignlayout.layoutId INNER JOIN campaign ON campaign.campaignId = lkcampaignlayout.campaignId WHERE campaign.isLayoutSpecific = 1 AND layout.layoutId = :layoutId';
            $params = ['layoutId' => $this->layoutId];
            $results = $this->store->select($sql, $params);
            foreach ($results as $row) {
                $campaignId = $row['campaignId'];
            }
        }

        if ($this->regionId == null || $this->regionId == 0) {
            // We are adding
            $this->add();

            // Add and save a region specific playlist
            if ($this->regionPlaylist === null) {
                $this->regionPlaylist = $this->playlistFactory->create($this->name, $this->ownerId, $this->regionId);
            } else {
                // assert the region id
                $this->regionPlaylist->regionId = $this->regionId;
                $this->regionPlaylist->setOwner($this->ownerId);
            }
            $this->regionPlaylist->save();

            // Audit
            if ($options['audit']) {
                $this->audit($this->regionId, 'Added', ['regionId' => $this->regionId, 'campaignId' => $campaignId, 'details' => (string)$this]);
            }
        }
        else if ($this->hash != $this->hash()) {
            $this->update();

            // There are 3 cases that we need to consider
            // 1 - Saving direct edit of region properties, $this->regionPlaylist will be null, as such we load it from database.
            // 2 - Saving whole Layout without changing ownership, $this->regionPlaylist will be populated including widgets property, we do not need to save widgets, load from database,
            // 3 - Saving whole Layout and changing the ownership (reassignAll or setOwner on Layout), in this case, we need to save widgets to cascade the ownerId change, don't load from database
            // case 3 due to - https://github.com/xibosignage/xibo/issues/2061
            $regionPlaylist = $this->playlistFactory->getByRegionId($this->regionId);

            if ($this->regionPlaylist == null || $this->ownerId == $regionPlaylist->ownerId) {
                $this->regionPlaylist = $regionPlaylist;
            }

            $this->regionPlaylist->name = $this->name;
            $this->regionPlaylist->save();

            if ($options['audit'] && count($this->getChangedProperties()) > 0) {
                $change = $this->getChangedProperties();
                $change['campaignId'][] = $campaignId;
                $this->audit($this->regionId, 'Saved', $change);
            }
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
    }

    /**
     * Delete Region
     * @param array $options
     * @throws XiboException
     */
    public function delete($options = [])
    {
        $options = array_merge([
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

        // Delete the region specific playlist
        $this->getPlaylist()->delete(['regionDelete' => true]);

        // Delete this region
        $this->getStore()->update('DELETE FROM `region` WHERE regionId = :regionId', array('regionId' => $this->regionId));

        $this->getLog()->audit('Region', $this->regionId, 'Region Deleted', ['regionId' => $this->regionId, 'layoutId' => $this->layoutId]);

        // Notify Layout
        if ($options['notify'])
            $this->notifyLayout();
    }

    /**
     * Add
     */
    private function add()
    {
        $this->getLog()->debug('Adding region to LayoutId ' . $this->layoutId);

        $sql = '
            INSERT INTO `region` (`layoutId`, `ownerId`, `name`, `width`, `height`, `top`, `left`, `zIndex`) 
              VALUES (:layoutId, :ownerId, :name, :width, :height, :top, :left, :zIndex)
        ';

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
     * Update
     */
    private function update()
    {
        $this->getLog()->debug('Editing ' . $this);

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
     * Notify the Layout (set to building)
     */
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