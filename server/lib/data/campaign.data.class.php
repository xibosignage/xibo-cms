<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2012 Daniel Garner
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
defined('XIBO') or die('Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.');

class Campaign extends Data
{
    public function __construct(database $db)
    {
        parent::__construct($db);
    }

    /**
     * Add Campaign
     * @param <string> $campaign
     * @param <int> $isLayoutSpecific
     * @param <int> $userId
     * @return <type>
     */
    public function Add($campaign, $isLayoutSpecific, $userId)
    {
        Debug::LogEntry('audit', 'IN', 'Campaign', 'Add');
        
        if ($campaign == '')
            return $this->SetError(25000, __('Campaign name cannot be empty'));

        $SQL = "INSERT INTO `campaign` (Campaign, IsLayoutSpecific, UserId) VALUES ('%s', %d, %d) ";
        $SQL = sprintf($SQL, $this->db->escape_string($campaign), $isLayoutSpecific, $userId);

        if (!$id = $this->db->insert_query($SQL))
        {
            trigger_error($this->db->error());
            return $this->SetError(25500, __('Unable to add Campaign, Step 1'));
        }

        return $id;
    }

    /**
     * Edit Campaign
     * @param <type> $campaignId
     * @param <type> $campaign
     * @return <type>
     */
    public function Edit($campaignId, $campaign)
    {
        Debug::LogEntry('audit', 'IN', 'Campaign', 'Edit');

        if ($campaign == '')
            return $this->SetError(25000, __('Campaign name cannot be empty'));

        $SQL = "UPDATE `campaign` SET Campaign = '%s' WHERE CampaignID = %d ";
        $SQL = sprintf($SQL, $this->db->escape_string($campaign), $campaignId);

        if (!$this->db->query($SQL))
        {
            trigger_error($this->db->error());
            return $this->SetError(25500, __('Unable to edit Campaign, Step 1'));
        }

        return true;
    }

    /**
     * Delete Campaign
     * @param <type> $campaignId
     */
    public function Delete($campaignId)
    {
        Debug::LogEntry('audit', 'IN', 'Campaign', 'Delete');

        // Unlink all Layouts
        if (!$this->UnlinkAll($campaignId))
            return false;

        // Remove all permissions
        Kit::ClassLoader('campaignsecurity');
        $security = new CampaignSecurity($this->db);

        if (!$security->UnlinkAll($campaignId))
            trigger_error(__('Unable to set permissions'));

        // Delete the Campaign record
        $SQL = "DELETE FROM `campaign` WHERE CampaignID = %d ";
        $SQL = sprintf($SQL, $campaignId);

        if (!$this->db->query($SQL))
        {
            trigger_error($this->db->error());
            return $this->SetError(25500, __('Unable to delete Campaign'));
        }

        return true;
    }

    /**
     * Link a Campaign to a Layout
     * @param <type> $campaignId
     * @param <type> $layoutId
     * @param <type> $displayOrder
     * @return <type>
     */
    public function Link($campaignId, $layoutId, $displayOrder)
    {
        $SQL = "INSERT INTO `lkcampaignlayout` (CampaignID, LayoutID, DisplayOrder) VALUES (%d, %d, %d)";
        $SQL = sprintf($SQL, $campaignId, $layoutId, $displayOrder);

        if (!$this->db->query($SQL))
        {
            trigger_error($this->db->error());
            return $this->SetError(25500, __('Unable to link Campaign to Layout'));
        }

        return true;
    }

    /**
     * Unlink a Layout from a Campaign
     * @param <type> $campaignId
     * @param <type> $layoutId
     * @param <type> $displayOrder
     * @return <type>
     */
    public function Unlink($campaignId, $layoutId, $displayOrder)
    {
        $SQL = "DELETE FROM `lkcampaignlayout` WHERE CampaignID = %d AND LayoutID = %d AND DisplayOrder = %d";
        $SQL = sprintf($SQL, $campaignId, $layoutId, $displayOrder);

        if (!$this->db->query($SQL))
        {
            trigger_error($this->db->error());
            return $this->SetError(25500, __('Unable to unlink Campaign from Layout'));
        }

        return true;
    }

    /**
     * Unlink all
     * @param <type> $campaignId
     * @return <type>
     */
    public function UnlinkAll($campaignId)
    {
        $SQL = "DELETE FROM `lkcampaignlayout` WHERE CampaignID = %d";
        $SQL = sprintf($SQL, $campaignId);

        if (!$this->db->query($SQL))
        {
            trigger_error($this->db->error());
            return $this->SetError(25500, __('Unable to unlink all Layouts'));
        }

        return true;
    }

    /**
     * Gets the CampaignId for a layoutspecfic campaign
     * @param <type> $layoutId
     */
    public function GetCampaignId($layoutId)
    {
        // Get the Campaign ID
        $SQL  = "SELECT campaign.CampaignID ";
        $SQL .= "  FROM `lkcampaignlayout` ";
        $SQL .= "   INNER JOIN `campaign` ";
        $SQL .= "   ON lkcampaignlayout.CampaignID = campaign.CampaignID ";
        $SQL .= " WHERE lkcampaignlayout.LayoutID = %d ";
        $SQL .= "   AND campaign.IsLayoutSpecific = 1";

        if (!$campaignId = $this->db->GetSingleValue(sprintf($SQL, $layoutId), 'CampaignID', _INT))
        {
            trigger_error(sprintf('LayoutId %d has no associated campaign', $layoutId));
            return $this->SetError(25000, __('Layout has no associated Campaign, corrupted Layout'));
        }

        return $campaignId;
    }
}
?>
