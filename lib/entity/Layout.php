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

class Layout
{
    public $layoutId;
    public $ownerId;
    public $campaignId;
    public $backgroundImageId;

    public $retired;

    public $layout;
    public $description;
    public $status;

    public $width;
    public $height;

    /**
     * @var array[Region]
     */
    public $regions;
    public $tags;

    private $compiledXlf;
    private $compiledMediaList;

    // Track which bits of the layout have been loaded
    public $basicInfoLoaded = false;

    /**
     * Load this Layout
     */
    public function load()
    {
        if ($this->basicInfoLoaded)
            return;

        // Load the Layout's basic data
        if ($this->layoutId)
            throw new \InvalidArgumentException(__('Missing Argument LayoutId'));

        $layout = LayoutFactory::loadById($this->layoutId);
        $this->layout = $layout->layout;
        $this->description = $layout->description;
        $this->status = $layout->status;
        $this->campaignId = $layout->campaignId;
        $this->backgroundImageId = $layout->backgroundImageId;
        $this->ownerId = $layout->ownerId;
        $this->basicInfoLoaded = true;
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
        if ($this->regions != null) {
            foreach ($this->regions as $region) {
                /* @var Region $region */
                $region->save();
            }
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
            throw new \InvalidArgumentException(__('The layout dimensions cannot be 0'));

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

    public function toXlf()
    {
        // TODO: Represent this Layout in XML
        return '<xml></xml>';
    }

    //
    // Add / Update
    //

    /**
     * Add
     */
    private function add()
    {
        $sql  = 'INSERT INTO layout (layout, description, userID, createdDT, modifiedDT, status)';
        $sql .= ' VALUES (:layout, :description, :userid, :createddt, :modifieddt, :status)';

        $time = \DateManager::getSystemDate(null, 'Y-m-d h:i:s');

        $this->layoutId = \PDOConnect::insert($sql, array(
            'layout' => $this->layout,
            'description' => $this->description,
            'userid' => $this->ownerId,
            'createddt' => $time,
            'modifieddt' => $time,
            'status' => 3
        ));

        // Add a Campaign
        $campaign = new \Campaign();
        $this->campaignId = $campaign->Add($this->layout, 1, $this->ownerId);

        // Link them
        $campaign->Link($this->campaignId, $this->layoutId, 0);

        // TODO: Set the default permissions on the regions
    }

    /**
     * Update
     */
    private function update()
    {
        $sql = 'UPDATE layout SET layout = :layout, description = :description, modifiedDT = :modifieddt, retired = :retired WHERE layoutID = :layoutid';

        $time = \DateManager::getSystemDate(null, 'Y-m-d h:i:s');

        \PDOConnect::execute($sql, array(
            'layoutid' => $this->layoutId,
            'layout' => $this->layout,
            'description' => $this->description,
            'modifieddt' => $time,
            'retired' => $this->retired,
        ));

        if (!$this->basicInfoLoaded)
            throw new NotFoundException(__('Layout not loaded'));

        // TODO: Update the Campaign
    }
}