<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012-13 Daniel Garner
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
    public function GetPermissions($objectId)
    {
        $userGroup = new UserGroup();
        if (!$result = $userGroup->GetPermissionsForObject('lkcampaigngroup', 'CampaignID', $objectId))
            return $this->SetError($userGroup->GetErrorMessage());

        return $result;
    }

    /**
     * Links a Campaign to a Group
     * @return
     * @param $campaignId Object
     * @param $groupID Object
     */
    public function Link($campaignId, $groupId, $view, $edit, $del)
    {
        Debug::LogEntry('audit', 'IN', 'CampaignGroupSecurity', 'Link');

        try {
            $dbh = PDOConnect::init();

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
            $SQL .= " VALUES (:campaignid, :groupId, :view, :edit, :del)";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'campaignid' => $campaignId,
                    'groupId' => $groupId,
                    'view' => $view,
                    'edit' => $edit,
                    'del' => $del
                ));

            Debug::LogEntry('audit', 'OUT', 'CampaignGroupSecurity', 'Link');

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25024, __('Could not Link Campaign to Group'));
        }
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
        Debug::LogEntry('audit', 'IN', 'CampaignGroupSecurity', 'LinkEveryone');
        
        try {
            $dbh = PDOConnect::init();

            // Get the Group ID for Everyone
            $sth = $dbh->prepare('SELECT GroupID FROM `group` WHERE IsEveryone = 1');
            $sth->execute();

            if (!$row = $sth->fetch())
                throw new Exception('Missing Everyone group');

            // Link
            return $this->Link($campaignId, Kit::ValidateParam($row['GroupID'], _INT), $view, $edit, $del);
        }       
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25000, __('Layout has no associated Campaign, corrupted Layout'));
        }
    }

    /**
     * Unlinks a campaign from a group
     * @return
     * @param $campaignId Object
     * @param $groupID Object
     */
    public function Unlink($campaignId, $groupId)
    {
        Debug::LogEntry('audit', 'IN', 'CampaignGroupSecurity', 'Unlink');

        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('DELETE FROM `lkcampaigngroup` WHERE CampaignID = :campaignid AND GroupID = :groupid');
            $sth->execute(array(
                    'campaignid' => $campaignId,
                    'groupId' => $groupId
                ));

            Debug::LogEntry('audit', 'OUT', 'CampaignGroupSecurity', 'Unlink');

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25025, __('Could not Unlink Campaign from Group'));
        }
    }

        /**
     * Unlinks a campaign from a group
     * @return
     * @param $campaignId Object
     * @param $groupID Object
     */
    public function UnlinkAll($campaignId)
    {
        Debug::LogEntry('audit', 'IN', 'CampaignGroupSecurity', 'UnlinkAll');

        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('DELETE FROM `lkcampaigngroup` WHERE CampaignID = :campaignid');
            $sth->execute(array(
                    'campaignid' => $campaignId
                ));

            Debug::LogEntry('audit', 'OUT', 'CampaignGroupSecurity', 'UnlinkAll');

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25025, __('Could not Unlink Campaign from Group'));
        }
    }

    /**
     * Copys all security for a Campaign
     * @param <type> $campaignId
     * @param <type> $newCampaignId
     * @return <type>
     */
    public function CopyAll($campaignId, $newCampaignId)
    {
        Debug::LogEntry('audit', 'IN', 'CampaignGroupSecurity', 'Copy');

        try {
            $dbh = PDOConnect::init();

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
            $SQL .= " SELECT :newcampaignid, GroupID, View, Edit, Del ";
            $SQL .= "   FROM lkcampaigngroup ";
            $SQL .= "  WHERE CampaignID = :campaignid ";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'newcampaignid' => $newCampaignId,
                    'campaignid' => $campaignId
                ));

            Debug::LogEntry('audit', 'OUT', 'CampaignGroupSecurity', 'Copy');

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25028, __('Could not Copy All Campaign Security'));
        }
    }
}
?>