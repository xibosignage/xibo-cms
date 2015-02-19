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
use Xibo\Factory\RegionFactory;
use Xibo\Factory\TagFactory;

class Layout
{
    public $layoutId;
    public $ownerId;
    public $campaignId;
    public $backgroundImageId;
    public $schemaVersion;

    public $retired;
    public $layout;

    public $description;
    public $backgroundColor;
    public $status;
    public $legacyXml;

    public $width;
    public $height;

    public $regions;
    public $tags;

    // Track which bits of the layout have been loaded
    public $basicInfoLoaded = false;

    public function __construct()
    {
        $this->regions = array();
        $this->tags = array();
    }

    public function __clone()
    {
        // Clear the layout id
        $this->layoutId = null;

        // Clone the regions
        $this->regions = array_map(function ($object) { return clone $object; }, $this->regions);
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
     * Load this Layout
     */
    public function load()
    {
        // Load all regions
        $this->regions = RegionFactory::loadByLayoutId($this->layoutId);
        foreach ($this->regions as $region) {
            /* @var Region $region */
            $region->load();
        }

        // Load all tags
        $this->tags = TagFactory::loadByLayoutId($this->layoutId);
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
        else {
            $this->update();
        }

        // Update the regions
        foreach ($this->regions as $region) {
            /* @var Region $region */
            $region->save();
        }

        // Save the tags
        foreach ($this->tags as $tag) {
            /* @var Tag $tag */
            $tag->save();
        }
    }

    public function delete()
    {
        // TODO: Delete the Layout
    }

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
        $duplicates = LayoutFactory::query(null, array('userId' => $this->ownerId, 'layout' => $this->layout, 'notLayoutId' => $this->layoutId));

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
        $sql  = 'INSERT INTO layout (layout, description, userID, createdDT, modifiedDT, status, width, height, schemaVersion)';
        $sql .= ' VALUES (:layout, :description, :userid, :createddt, :modifieddt, :status, :width, :height, 3)';

        $time = \DateManager::getSystemDate(null, 'Y-m-d h:i:s');

        try {
            $this->layoutId = \PDOConnect::insert($sql, array(
                'layout' => $this->layout,
                'description' => $this->description,
                'userid' => $this->ownerId,
                'createddt' => $time,
                'modifieddt' => $time,
                'status' => 3,
                'width' => $this->width,
                'height' => $this->height
            ));
        }
        catch (\PDOException $e) {
            throw new \Exception(__('Could not add Layout'));
        }

        // Add a Campaign
        $campaign = new \Campaign();
        $this->campaignId = $campaign->Add($this->layout, 1, $this->ownerId);

        // Link them
        $campaign->Link($this->campaignId, $this->layoutId, 0);

        // TODO: Set the default permissions on the regions
    }

    /**
     * Update
     * NOTE: We set the XML to NULL during this operation as we will always convert old layouts to the new structure
     */
    private function update()
    {
        $sql = 'UPDATE layout SET layout = :layout, description = :description, modifiedDT = :modifieddt, retired = :retired, width = :width, height = :height, xml = NULL WHERE layoutID = :layoutid';

        $time = \DateManager::getSystemDate(null, 'Y-m-d h:i:s');

        \PDOConnect::execute($sql, array(
            'layoutid' => $this->layoutId,
            'layout' => $this->layout,
            'description' => $this->description,
            'modifieddt' => $time,
            'retired' => $this->retired,
            'width' => $this->width,
            'height' => $this->height
        ));

        if (!$this->basicInfoLoaded)
            throw new NotFoundException(__('Layout not loaded'));

        // TODO: Update the Campaign
    }
}