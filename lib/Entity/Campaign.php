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

    public $layoutIds = [];
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
        $this->permissions = PermissionFactory::getByObjectId('Campaign', $this->campaignId);

        // Layouts
        foreach (LayoutFactory::getByCampaignId($this->campaignId) as $layout) {
            /* @var Layout $layout */
            $this->layoutIds[] = $layout->layoutId;
        }

        // Events
        $this->events = ScheduleFactory::getByCampaignId($this->campaignId);
    }

    public function save()
    {
        if ($this->campaignId == null || $this->campaignId == 0)
            $this->add();
        else
            $this->update();

        $this->linkLayouts();
    }

    public function delete()
    {
        $this->load();

        // Remove all layouts
        $this->layoutIds = [];
        $this->unlinkLayouts();

        // Delete things this group can own
        foreach ($this->permissions as $permission) {
            /* @var Permission $permission */
            $permission->delete();
        }

        foreach ($this->events as $event) {
            /* @var Schedule $event */
            $event->delete();
        }

        PDOConnect::update('DELETE FROM `campaign` WHERE CampaignID = :campaignId', ['campaignId' => $this->campaignId]);
    }

    /**
     * Assign Layout
     * @param int $layoutId
     */
    public function assignLayout($layoutId)
    {
        if (!in_array($layoutId, $this->layoutIds))
            $this->layoutIds[] = $layoutId;
    }

    /**
     * Unassign Layout
     * @param int $layoutId
     */
    public function unassignLayouts($layoutId)
    {
        unset($this->layoutIds[$layoutId]);
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
     * Link Layout
     */
    private function linkLayouts()
    {
        // TODO: Make this more efficient by storing the prepared SQL statement
        $sql = 'INSERT INTO `lkcampaignlayout` (CampaignID, LayoutID, DisplayOrder) VALUES (:campaignId, :layoutId, :displayOrder) ON DUPLICATE KEY UPDATE layoutId = :layoutId2';

        $i = 0;
        foreach ($this->layoutIds as $layoutId) {
            $i++;

            PDOConnect::insert($sql, array(
                'campaignId' => $this->campaignId,
                'displayOrder' => $i,
                'layoutId' => $layoutId,
                'layoutId2' => $layoutId
            ));
        }
    }

    /**
     * Unlink Layout
     */
    private function unlinkLayouts()
    {
        // Unlink any layouts that are NOT in the collection
        if (count($this->layoutIds) <= 0)
            $this->layoutIds = [0];

        $params = ['campaignId' => $this->campaignId];

        $sql = 'DELETE FROM `lkcampaignlayout` WHERE campaignId = :campaignId AND layoutId NOT IN (0';

        $i = 0;
        foreach ($this->layoutIds as $layoutId) {
            $i++;
            $sql .= ',:layoutId' . $i;
            $params['layoutId' . $i] = $layoutId;
        }

        $sql .= ')';

        PDOConnect::update($sql, $params);
    }
}