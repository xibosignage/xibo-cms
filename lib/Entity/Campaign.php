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

use Respect\Validation\Validator as v;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Helper\Log;
use Xibo\Storage\PDOConnect;

class Campaign implements \JsonSerializable
{
    use EntityTrait;
    public $campaignId;
    public $ownerId;

    public $campaign;

    public $isLayoutSpecific = 0;

    public $numberLayouts;

    private $layouts = [];
    private $permissions = [];
    private $events = [];

    public function __toString()
    {
        return sprintf('CampaignId %d, Campaign %s, LayoutSpecific %d', $this->campaignId, $this->campaign, $this->isLayoutSpecific);
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

    public function load()
    {
        // If we are already loaded, then don't do it again
        if ($this->campaignId == null || $this->loaded)
            return;

        // Permissions
        $this->permissions = PermissionFactory::getByObjectId('Campaign', $this->campaignId);

        // Layouts
        $this->layouts = LayoutFactory::getByCampaignId($this->campaignId);

        // Events
        $this->events = ScheduleFactory::getByCampaignId($this->campaignId);

        $this->loaded = true;
    }

    public function validate()
    {
        if (!v::string()->notEmpty()->validate($this->campaign))
            throw new \InvalidArgumentException(__('Name cannot be empty'));
    }

    public function save($validate = true)
    {
        Log::debug('Saving %s', $this);

        if ($validate)
            $this->validate();

        if ($this->campaignId == null || $this->campaignId == 0) {
            $this->add();
            $this->loaded = true;
        }
        else
            $this->update();

        if ($this->loaded) {
            // Manage assignments
            $this->manageAssignments();
        }

        // Notify anyone interested of the changes
        $this->notify();
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
    public function unassignLayout($layout)
    {
        Log::debug('Unassigning Layout %s from Campaign %s', $layout, $this);

        $this->load();

        $this->layouts = array_udiff($this->layouts, [$layout], function ($a, $b) {
            /**
             * @var Layout $a
             * @var Layout $b
             */
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
        Log::debug('Managing Assignments on %s', $this);
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

    /**
     * Notify displays of this campaign change
     */
    private function notify()
    {
        Log::debug('Checking for Displays to refresh on Campaign %d', $this->campaignId);

        foreach (DisplayFactory::getByActiveCampaignId($this->campaignId) as $display) {
            /* @var \Xibo\Entity\Display $display */
            $display->setMediaIncomplete();
            $display->save(false);
        }
    }
}