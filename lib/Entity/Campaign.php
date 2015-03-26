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


use Xibo\Storage\PDOConnect;

class Campaign
{
    public $campaignId;
    public $ownerId;

    public $campaign;

    public $isLayout;
    public $retired;

    public $numberLayouts;

    public $layoutIds = array();

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


        $this->unlinkLayouts();
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
        $this->campaignId = PDOConnect::insert('INSERT INTO `campaign` (Campaign, IsLayoutSpecific, UserId) VALUES (:campaign, :islayoutspecific, :userid)', array(
            'campaign' => $this->campaign,
            'islayoutspecific' => $this->isLayout,
            'userid' => $this->ownerId
        ));
    }

    private function update()
    {

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
        $i = 0;

        foreach ($this->layoutIds as $layoutId) {
            $i++;

            PDOConnect::update('DELETE FROM `lkcampaignlayout` WHERE CampaignID = :campaignid AND LayoutID = :layoutid AND DisplayOrder = :displayorder', array(
                'campaignId' => $this->campaignId,
                'displayOrder' => $i,
                'layoutId' => $layoutId,
            ));
        }
    }
}