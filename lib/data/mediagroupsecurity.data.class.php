<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2011-13 Daniel Garner
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

class MediaGroupSecurity extends Data
{
    /**
     * Links a Display Group to a Group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Link($mediaId, $groupId, $view, $edit, $del)
    {
        Debug::LogEntry('audit', 'IN', 'MediaGroupSecurity', 'Link');
        
        try {
            $dbh = PDOConnect::init();
        
            $SQL  = "INSERT INTO lkmediagroup (MediaID, GroupID, View, Edit, Del) ";
            $SQL .= " VALUES (:mediaid, :groupid, :view, :edit, :del) ";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'mediaid' => $mediaId,
                    'groupid' => $groupId,
                    'view' => $view,
                    'edit' => $edit,
                    'del' => $del
                ));
    
            Debug::LogEntry('audit', 'OUT', 'MediaGroupSecurity', 'Link');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25026, __('Could not Link Media to Group'));
        
            return false;
        }
    }

    /**
     * Links everyone to the layout specified
     * @param <type> $layoutId
     * @param <type> $view
     * @param <type> $edit
     * @param <type> $del
     * @return <type>
     */
    public function LinkEveryone($mediaId, $view, $edit, $del)
    {
        Debug::LogEntry('audit', 'IN', 'MediaGroupSecurity', 'LinkEveryone');
        
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT GroupID FROM `group` WHERE IsEveryone = 1');
            $sth->execute();

            if (!$row = $sth->fetch())
                throw new Exception("Error Processing Request", 1);

            $groupId = Kit::ValidateParam($row['GroupID'], _INT);
        
            if (!$this->Link($mediaId, $groupId, $view, $edit, $del))
                throw new Exception("Error Processing Request", 1);

            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Unlink($mediaId, $groupId)
    {
        Debug::LogEntry('audit', 'IN', 'MediaGroupSecurity', 'Unlink');

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM lkmediagroup WHERE MediaID = :mediaid AND GroupID = :groupid');
            $sth->execute(array(
                    'groupid' => $groupId,
                    'mediaid' => $mediaId
                ));
    
            Debug::LogEntry('audit', 'OUT', 'MediaGroupSecurity', 'Unlink');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25027, __('Could not Unlink Media from Group'));
        
            return false;
        }
    }

        /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function UnlinkAll($mediaId)
    {
        Debug::LogEntry('audit', 'IN', 'MediaGroupSecurity', 'UnlinkAll');

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM lkmediagroup WHERE MediaID = :mediaid');
            $sth->execute(array(
                    'mediaid' => $mediaId
                ));
    
            Debug::LogEntry('audit', 'OUT', 'MediaGroupSecurity', 'Unlink');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25027, __('Could not Unlink Media from Groups'));
        
            return false;
        }
    }

    /**
     * Copies a media items permissions
     * @param <type> $mediaId
     * @param <type> $newMediaId
     * @return <type>
     */
    public function Copy($mediaId, $newMediaId)
    {
        Debug::LogEntry('audit', 'IN', 'MediaGroupSecurity', 'Copy');

        try {
            $dbh = PDOConnect::init();
        
            $SQL  = "";
            $SQL .= "INSERT ";
            $SQL .= "INTO   lkmediagroup ";
            $SQL .= "       ( ";
            $SQL .= "              MediaID, ";
            $SQL .= "              GroupID, ";
            $SQL .= "              View, ";
            $SQL .= "              Edit, ";
            $SQL .= "              Del ";
            $SQL .= "       ) ";
            $SQL .= " SELECT :mediaid, GroupID, View, Edit, Del ";
            $SQL .= "   FROM lkmediagroup ";
            $SQL .= "  WHERE MediaID = :oldmediaid ";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'mediaid' => $newMediaId,
                    'oldmediaid' => $mediaId
                ));
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25028, __('Could not Copy Layout Media Security'));
        
            return false;
        }
    }
}
?>