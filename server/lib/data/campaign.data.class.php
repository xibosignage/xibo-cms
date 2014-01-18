<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012-2013 Daniel Garner
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

class Campaign extends Data {
    /**
     * Add Campaign
     * @param <string> $campaign
     * @param <int> $isLayoutSpecific
     * @param <int> $userId
     * @return <type>
     */
    public function Add($campaign, $isLayoutSpecific, $userId) {
        Debug::LogEntry('audit', 'IN', 'Campaign', 'Add');
        
        if ($campaign == '')
            return $this->SetError(25000, __('Campaign name cannot be empty'));

        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('INSERT INTO `campaign` (Campaign, IsLayoutSpecific, UserId) VALUES (:campaign, :islayoutspecific, :userid)');
            $sth->execute(array(
                    'campaign' => $campaign,
                    'islayoutspecific' => $isLayoutSpecific,
                    'userid' => $userId
                ));

            return $dbh->lastInsertId();
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25500, __('Unable to add Campaign, Step 1'));
        }
    }

    /**
     * Edit Campaign
     * @param <type> $campaignId
     * @param <type> $campaign
     * @return <type>
     */
    public function Edit($campaignId, $campaign) {
        Debug::LogEntry('audit', 'IN', 'Campaign', 'Edit');

        if ($campaign == '')
            return $this->SetError(25000, __('Campaign name cannot be empty'));

        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('UPDATE `campaign` SET Campaign = :campaign WHERE CampaignID = :campaignid');
            $sth->execute(array(
                    'campaign' => $campaign,
                    'campaignid' => $campaignId
                ));

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25500, __('Unable to edit Campaign, Step 1'));
        }
    }

    /**
     * Delete Campaign
     * @param <type> $campaignId
     */
    public function Delete($campaignId) {
        Debug::LogEntry('audit', 'IN', 'Campaign', 'Delete');

        // Delete the Campaign record
        try {
            $dbh = PDOConnect::init();

            // Unlink all Layouts
            if (!$this->UnlinkAll($campaignId))
                throw new Exception(__('Unable to Unlink'));

            // Remove all permissions
            Kit::ClassLoader('campaignsecurity');
            $security = new CampaignSecurity($this->db);

            if (!$security->UnlinkAll($campaignId))
                throw new Exception(__('Unable to set permissions'));

            // Delete from the Campaign
            $sth = $dbh->prepare('DELETE FROM `campaign` WHERE CampaignID = :campaignid');
            $sth->execute(array(
                    'campaignid' => $campaignId
                ));

            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25500, __('Unable to delete Campaign'));

            return false;
        }
    }

    /**
     * Link a Campaign to a Layout
     * @param <type> $campaignId
     * @param <type> $layoutId
     * @param <type> $displayOrder
     * @return <type>
     */
    public function Link($campaignId, $layoutId, $displayOrder) {
        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('INSERT INTO `lkcampaignlayout` (CampaignID, LayoutID, DisplayOrder) VALUES (:campaignid, :layoutid, :displayorder)');
            $sth->execute(array(
                    'layoutid' => $layoutId,
                    'displayorder' => $displayOrder,
                    'campaignid' => $campaignId
                ));

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25500, __('Unable to link Campaign to Layout'));
        }
    }

    /**
     * Unlink a Layout from a Campaign
     * @param <type> $campaignId
     * @param <type> $layoutId
     * @param <type> $displayOrder
     * @return <type>
     */
    public function Unlink($campaignId, $layoutId, $displayOrder) {
        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('DELETE FROM `lkcampaignlayout` WHERE CampaignID = :campaignid AND LayoutID = :layoutid AND DisplayOrder = :displayorder');
            $sth->execute(array(
                    'layoutid' => $layoutId,
                    'displayorder' => $displayOrder,
                    'campaignid' => $campaignId
                ));

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25500, __('Unable to unlink Campaign from Layout'));
        }
    }

    /**
     * Unlink all
     * @param <type> $campaignId
     * @return <type>
     */
    public function UnlinkAll($campaignId) {
        try {
            $dbh = PDOConnect::init();

            // Delete from the Campaign
            $sth = $dbh->prepare('DELETE FROM `lkcampaignlayout` WHERE CampaignID = :campaignid');
            $sth->execute(array(
                    'campaignid' => $campaignId
                ));

            return true;
        }       
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25500, __('Unable to unlink all Layouts'));
        }
    }

    /**
     * Gets the CampaignId for a layoutspecfic campaign
     * @param <type> $layoutId
     */
    public function GetCampaignId($layoutId) {
        try {
            $dbh = PDOConnect::init();

            // Get the Campaign ID
            $SQL  = "SELECT campaign.CampaignID ";
            $SQL .= "  FROM `lkcampaignlayout` ";
            $SQL .= "   INNER JOIN `campaign` ";
            $SQL .= "   ON lkcampaignlayout.CampaignID = campaign.CampaignID ";
            $SQL .= " WHERE lkcampaignlayout.LayoutID = :layoutid ";
            $SQL .= "   AND campaign.IsLayoutSpecific = 1";

            // Delete from the Campaign
            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'layoutid' => $layoutId
                ));

            if (!$row = $sth->fetch())
                throw new Exception('No Campaign returned');

            // Return the Campaign ID
            return Kit::ValidateParam($row['CampaignID'], _INT);
        }       
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25000, __('Layout has no associated Campaign, corrupted Layout'));
        }
    }
}
?>
