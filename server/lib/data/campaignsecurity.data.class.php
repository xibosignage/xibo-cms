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

class CampaignSecurity extends Data
{
    public function __construct(database $db)
    {
        parent::__construct($db);
    }

    /**
     * Links a Campaign to a Group
     * @return
     * @param $campaignId Object
     * @param $groupID Object
     */
    public function Link($campaignId, $groupId, $view, $edit, $del)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'CampaignGroupSecurity', 'Link');

        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   lkcampaigngroup ";
        $SQL .= "       ( ";
        $SQL .= "              CampaignID, ";
        $SQL .= "              GroupID, ";
        $SQL .= "              View, ";
        $SQL .= "              Edit, ";
        $SQL .= "              Del ";
        $SQL .= "       ) ";
        $SQL .= "       VALUES ";
        $SQL .= "       ( ";
        $SQL .= sprintf("  %d, %d, %d, %d, %d ", $campaignId, $groupId, $view, $edit, $del);
        $SQL .= "       )";

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25024, __('Could not Link Campaign to Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'CampaignGroupSecurity', 'Link');

        return true;
    }

    /**
     * Links everyone to the Campaign specified
     * @param <type> $campaignId
     * @param <type> $view
     * @param <type> $edit
     * @param <type> $del
     * @return <type>
     */
    public function LinkEveryone($campaignId, $view, $edit, $del)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'CampaignGroupSecurity', 'LinkEveryone');

        $groupId = $db->GetSingleValue("SELECT GroupID FROM `group` WHERE IsEveryone = 1", 'GroupID', _INT);

        return $this->Link($campaignId, $groupId, $view, $edit, $del);
    }

    /**
     * Unlinks a campaign from a group
     * @return
     * @param $campaignId Object
     * @param $groupID Object
     */
    public function Unlink($campaignId, $groupId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'CampaignGroupSecurity', 'Unlink');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lkcampaigngroup ";
        $SQL .= sprintf("  WHERE CampaignID = %d AND GroupID = %d ", $campaignId, $groupId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25025, __('Could not Unlink Campaign from Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'CampaignGroupSecurity', 'Unlink');

        return true;
    }

        /**
     * Unlinks a campaign from a group
     * @return
     * @param $campaignId Object
     * @param $groupID Object
     */
    public function UnlinkAll($campaignId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'CampaignGroupSecurity', 'Unlink');

        $SQL  = "";
        $SQL .= "DELETE FROM ";
        $SQL .= "   lkcampaigngroup ";
        $SQL .= sprintf("  WHERE CampaignID = %d ", $campaignId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25025, __('Could not Unlink Campaign from Group'));

            return false;
        }

        Debug::LogEntry($db, 'audit', 'OUT', 'CampaignGroupSecurity', 'Unlink');

        return true;
    }

    /**
     * Copys all security for a Campaign
     * @param <type> $campaignId
     * @param <type> $newCampaignId
     * @return <type>
     */
    public function CopyAll($campaignId, $newCampaignId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'CampaignGroupSecurity', 'Copy');

        $SQL  = "";
        $SQL .= "INSERT ";
        $SQL .= "INTO   lkcampaigngroup ";
        $SQL .= "       ( ";
        $SQL .= "              CampaignID, ";
        $SQL .= "              GroupID, ";
        $SQL .= "              View, ";
        $SQL .= "              Edit, ";
        $SQL .= "              Del ";
        $SQL .= "       ) ";
        $SQL .= " SELECT '%s', GroupID, View, Edit, Del ";
        $SQL .= "   FROM lkcampaigngroup ";
        $SQL .= "  WHERE CampaignID = %d ";

        $SQL = sprintf($SQL, $newCampaignId, $campaignId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25028, __('Could not Copy All Campaign Security'));

            return false;
        }

        return true;
    }
}
?>