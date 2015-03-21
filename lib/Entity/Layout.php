<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file is part of Xibo.
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
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\RegionFactory;
use Xibo\Factory\TagFactory;

class Layout
{
    private $hash;
    public $layoutId;
    public $ownerId;
    public $campaignId;
    public $backgroundImageId;
    public $schemaVersion;

    public $layout;
    public $description;
    public $backgroundColor;
    public $legacyXml;

    public $status;
    public $retired;
    public $backgroundzIndex;

    public $width;
    public $height;

    // Child items
    public $regions;
    public $tags;
    public $permissions;

    // Read only properties
    public $owner;
    public $groupsWithPermissions;

    public function __construct()
    {
        $this->hash = null;
        $this->regions = array();
        $this->tags = array();
    }

    public function __clone()
    {
        // Clear the layout id
        $this->layoutId = null;
        $this->hash = null;

        // Clone the regions
        $this->regions = array_map(function ($object) { return clone $object; }, $this->regions);
    }

    public function __toString()
    {
        return sprintf('Layout %s - %d x %d. Regions = %d, Tags = %d. layoutId = %d', $this->layout, $this->width, $this->height, count($this->regions), count($this->tags), $this->layoutId);
    }

    private function hash()
    {
        return md5($this->layoutId . $this->ownerId . $this->campaignId . $this->backgroundImageId . $this->backgroundColor . $this->width . $this->height . $this->status . $this->description);
    }

    /**
     * Get the Id
     * @return int
     */
    public function getId()
    {
        return $this->campaignId;
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
     * Sets the Owner of the Layout (including children)
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->ownerId = $ownerId;

        foreach ($this->regions as $region) {
            /* @var Region $region */
            $region->setOwner($ownerId);
        }
    }

    /**
     * Load Regions from a Layout
     * @param int $regionId
     * @return Region
     * @throws NotFoundException
     */
    public function getRegion($regionId)
    {
        foreach ($this->regions as $region) {
            /* @var Region $region */
            if ($region->regionId == $regionId)
                return $region;
        }

        throw new NotFoundException(__('Cannot find region'));
    }

    /**
     * Load this Layout
     */
    public function load()
    {
        // Load permissions
        $this->permissions = PermissionFactory::getByObjectId('campaign', $this->campaignId);

        // Load all regions
        $this->regions = RegionFactory::getByLayoutId($this->layoutId);
        foreach ($this->regions as $region) {
            /* @var Region $region */
            $region->load();
        }

        // Load all tags
        $this->tags = TagFactory::loadByLayoutId($this->layoutId);

        // Set the hash
        $this->hash = $this->hash();
    }

    /**
     * Save this Layout
     */
    public function save()
    {
        // New or existing layout
        if ($this->layoutId == null || $this->layoutId == 0) {
            $this->add();
        }
        else if ($this->hash() != $this->hash) {
            $this->update();
        }

        // Update the regions
        foreach ($this->regions as $region) {
            /* @var Region $region */

            // Assert the Layout Id
            $region->layoutId = $this->layoutId;
            $region->save();
        }

        // Save the tags
        foreach ($this->tags as $tag) {
            /* @var Tag $tag */

            $tag->assignLayout($this->layoutId);
            $tag->save();
        }
    }

    /**
     * Delete Layout
     * @throws \Exception
     */
    public function delete()
    {
        // We must ensure everything is loaded before we delete
        if ($this->hash == null)
            $this->load();

        \Debug::Audit('Deleting ' . $this);

        // Delete Permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->deleteAll();
        }

        // Unassign all Tags
        foreach ($this->tags as $tag) {
            /* @var Tag $tag */

            $tag->assignLayout($this->layoutId);
            $tag->removeAssignments();
        }

        // Delete Regions
        foreach ($this->regions as $region) {
            /* @var Region $region */

            // Assert the Layout Id
            $region->layoutId = $this->layoutId;
            $region->delete();
        }

        // Delete Campaign
        $campaign = new \Campaign();
        if (!$campaign->Delete($this->campaignId))
            throw new \Exception(__('Problem deleting Campaign'));

        // Remove the Layout from any display defaults
        \Xibo\Storage\PDOConnect::update('UPDATE `display` SET defaultlayoutid = 4 WHERE defaultlayoutid = :layoutid', array('layoutid' => $this->layoutId));

        // Remove the Layout (now it is orphaned it can be deleted safely)
        \Xibo\Storage\PDOConnect::update('DELETE FROM layout WHERE layoutid = :layoutid', array('layoutid' => $this->layoutId));
    }

    /**
     * Validate this layout
     * @throws NotFoundException
     */
    public function validate()
    {
        // We must provide either a template or a resolution
        if ($this->width == 0 || $this->height == 0)
            throw new \InvalidArgumentException(__('The layout dimensions cannot be empty'));

        // Validation
        if (strlen($this->layout) > 50 || strlen($this->layout) < 1)
            throw new \InvalidArgumentException(__("Layout Name must be between 1 and 50 characters"));

        if (strlen($this->description) > 254)
            throw new \InvalidArgumentException(__("Description can not be longer than 254 characters"));

        // Check for duplicates
        $duplicates = LayoutFactory::query(null, array('userId' => $this->ownerId, 'layoutExact' => $this->layout, 'notLayoutId' => $this->layoutId));

        if (count($duplicates) > 0)
            throw new \InvalidArgumentException(sprintf(__("You already own a layout called '%s'. Please choose another name."), $this->layout));
    }

    /**
     * Export the Layout as its XLF
     * @return string
     */
    public function toXlf()
    {
        // TODO: Represent this Layout in XML
        return '<xml></xml>';
    }

    /**
     * Export the Layout as a ZipArchive
     * @return \ZipArchive
     */
    public function toZip()
    {
        return new \ZipArchive();
    }

    //
    // Add / Update
    //

    /**
     * Add
     */
    private function add()
    {
        \Debug::Audit('Adding Layout ' . $this->layout);

        $sql  = 'INSERT INTO layout (layout, description, userID, createdDT, modifiedDT, status, width, height, schemaVersion, backgroundImageId, backgroundColor, backgroundzIndex)
                  VALUES (:layout, :description, :userid, :createddt, :modifieddt, :status, :width, :height, 3, :backgroundImageId, :backgroundColor, :backgroundzIndex)';

        $time = \Xibo\Helper\Date::getSystemDate(null, 'Y-m-d h:i:s');

        $this->layoutId = \Xibo\Storage\PDOConnect::insert($sql, array(
            'layout' => $this->layout,
            'description' => $this->description,
            'userid' => $this->ownerId,
            'createddt' => $time,
            'modifieddt' => $time,
            'status' => 3,
            'width' => $this->width,
            'height' => $this->height,
            'backgroundImageId' => $this->backgroundImageId,
            'backgroundColor' => $this->backgroundColor,
            'backgroundzIndex' => $this->backgroundzIndex,
        ));

        // Add a Campaign
        $campaign = new \Campaign();
        $this->campaignId = $campaign->Add($this->layout, 1, $this->ownerId);

        // Link them
        $campaign->Link($this->campaignId, $this->layoutId, 0);
    }

    /**
     * Update
     * NOTE: We set the XML to NULL during this operation as we will always convert old layouts to the new structure
     */
    private function update()
    {
        \Debug::Audit('Editing Layout ' . $this->layout . '. Id = ' . $this->layoutId);

        $sql = '
        UPDATE layout SET layout = :layout, description = :description, modifiedDT = :modifieddt, retired = :retired, width = :width, height = :height, backgroundImageId = :backgroundImageId, backgroundColor = :backgroundColor, backgroundzIndex = :backgroundzIndex, xml = NULL
         WHERE layoutID = :layoutid
        ';

        $time = \Xibo\Helper\Date::getSystemDate(null, 'Y-m-d h:i:s');

        \Xibo\Storage\PDOConnect::update($sql, array(
            'layoutid' => $this->layoutId,
            'layout' => $this->layout,
            'description' => $this->description,
            'modifieddt' => $time,
            'retired' => $this->retired,
            'width' => $this->width,
            'height' => $this->height,
            'backgroundImageId' => $this->backgroundImageId,
            'backgroundColor' => $this->backgroundColor,
            'backgroundzIndex' => $this->backgroundzIndex,
        ));

        // Update the Campaign
        $campaign = new \Campaign();
        $campaign->Edit($this->campaignId, $this->layout);
    }
}