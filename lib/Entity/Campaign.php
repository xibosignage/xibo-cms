<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Campaign.php) is part of Xibo.
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


use Xibo\Factory\LayoutFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Storage\PDOConnect;

class Campaign
{
    use EntityTrait;
    public $campaignId;
    public $ownerId;

    public $campaign;

    public $isLayoutSpecific = 0;
    public $retired;

    public $numberLayouts;

    private $layouts = [];
    private $permissions = [];
    private $events = [];

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

    public function load()
    {
        // If we are already loaded, then don't do it again
        if ($this->loaded)
            return;

        // Permissions
        $this->permissions = PermissionFactory::getByObjectId('Campaign', $this->campaignId);

        // Layouts
        $this->layouts = LayoutFactory::getByCampaignId($this->campaignId);

        // Events
        $this->events = ScheduleFactory::getByCampaignId($this->campaignId);

        $this->loaded = true;
    }

    public function save()
    {
        if ($this->campaignId == null || $this->campaignId == 0)
            $this->add();
        else
            $this->update();

        if ($this->loaded) {
            // Manage assignments
            $this->manageAssignments();
        }
    }

    public function delete()
    {
        $this->load();

        // Unassign all Layouts
        $this->layouts = [];
        $this->unlinkLayouts();

        // Delete all permissions
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->delete();
        }

        // Delete all events
        foreach ($this->events as $event) {
            /* @var Schedule $event */
            $event->delete();
        }

        // Delete the Actual Campaign
        PDOConnect::update('DELETE FROM `campaign` WHERE CampaignID = :campaignId', ['campaignId' => $this->campaignId]);
    }

    /**
     * Assign Layout
     * @param Layout $layout
     */
    public function assignLayout($layout)
    {
        $this->load();

        if (!in_array($layout, $this->layouts))
            $this->layouts[] = $layout;
    }

    /**
     * Unassign Layout
     * @param Layout $layout
     */
    public function unassignLayouts($layout)
    {
        $this->load();

        $this->layouts = array_udiff($this->layouts, [$layout], function ($a, $b) {
            return $a->getId() - $b->getId();
        });
    }

    private function add()
    {
        $this->campaignId = PDOConnect::insert('INSERT INTO `campaign` (Campaign, IsLayoutSpecific, UserId) VALUES (:campaign, :isLayoutSpecific, :userId)', array(
            'campaign' => $this->campaign,
            'isLayoutSpecific' => $this->isLayoutSpecific,
            'userId' => $this->ownerId
        ));
    }

    private function update()
    {
        PDOConnect::update('UPDATE `campaign` SET campaign = :campaign WHERE CampaignID = :campaignId', [
            'campaignId' => $this->campaignId,
            'campaign' => $this->campaign
        ]);
    }

    /**
     * Manage the assignments
     */
    private function manageAssignments()
    {
        $this->linkLayouts();
        $this->unlinkLayouts();
    }

    /**
     * Link Layout
     */
    private function linkLayouts()
    {
        // TODO: Make this more efficient by storing the prepared SQL statement
        $sql = 'INSERT INTO `lkcampaignlayout` (CampaignID, LayoutID, DisplayOrder) VALUES (:campaignId, :layoutId, :displayOrder) ON DUPLICATE KEY UPDATE layoutId = :layoutId2';

        $i = 0;
        foreach ($this->layouts as $layout) {
            $i++;

            PDOConnect::insert($sql, array(
                'campaignId' => $this->campaignId,
                'displayOrder' => $i,
                'layoutId' => $layout->layoutId,
                'layoutId2' => $layout->layoutId
            ));
        }
    }

    /**
     * Unlink Layout
     */
    private function unlinkLayouts()
    {
        // Unlink any layouts that are NOT in the collection
        $params = ['campaignId' => $this->campaignId];

        $sql = 'DELETE FROM `lkcampaignlayout` WHERE campaignId = :campaignId AND layoutId NOT IN (0';

        $i = 0;
        foreach ($this->layouts as $layout) {
            $i++;
            $sql .= ',:layoutId' . $i;
            $params['layoutId' . $i] = $layout->layoutId;
        }

        $sql .= ')';

        PDOConnect::update($sql, $params);
    }
}