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

class LayoutMediaGroupSecurity extends Data
{
    /**
     * Links a Display Group to a Group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Link($layoutId, $regionId, $mediaId, $groupId, $view, $edit, $del)
    {
        Debug::LogEntry('audit', 'IN', 'LayoutMediaGroupSecurity', 'Link');
        
        try {
            $dbh = PDOConnect::init();
        
            $SQL  = "INSERT INTO lklayoutmediagroup (LayoutID, RegionID, MediaID, GroupID, View, Edit, Del) ";
            $SQL .= " VALUES (:layoutid, :regionid, :mediaid, :groupid, :view, :edit, :del) ";
                
            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'layoutid' => $layoutId,
                    'regionid' => $regionId,
                    'mediaid' => $mediaId,
                    'groupid' => $groupId,
                    'view' => $view,
                    'edit' => $edit,
                    'del' => $del
                ));
        
            Debug::LogEntry('audit', 'OUT', 'LayoutMediaGroupSecurity', 'Link');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25026, __('Could not Link Layout Media to Group'));
        
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
    public function LinkEveryone($layoutId, $regionId, $mediaId, $view, $edit, $del)
    {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT GroupID FROM `group` WHERE IsEveryone = 1');
            $sth->execute();

            if (!$row = $sth->fetch())
                throw new Exception("Error Processing Request", 1);

            $groupId = Kit::ValidateParam($row['GroupID'], _INT);
        
            if (!$this->Link($layoutId, $regionId, $mediaId, $groupId, $view, $edit, $del))
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
    public function Unlink($layoutId, $regionId, $mediaId, $groupId)
    {
        Debug::LogEntry('audit', 'IN', 'LayoutMediaGroupSecurity', 'Unlink');

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM lklayoutmediagroup  WHERE LayoutID = :layoutid AND RegionID = :regionid AND MediaID = :mediaid AND GroupID = :groupid');
            $sth->execute(array(
                    'layoutid' => $layoutId,
                    'regionid' => $regionId,
                    'mediaid' => $mediaId,
                    'groupid' => $groupId
                ));

            Debug::LogEntry('audit', 'OUT', 'LayoutMediaGroupSecurity', 'Unlink');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25027, __('Could not Unlink Layout Media from Group'));
        
            return false;
        }
    }

    /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function UnlinkAll($layoutId, $regionId, $mediaId)
    {
        Debug::LogEntry('audit', 'IN', 'LayoutMediaGroupSecurity', 'Unlink');
        
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM lklayoutmediagroup WHERE LayoutID = :layoutid AND RegionID = :regionid AND MediaID = :mediaid');
            $sth->execute(array(
                    'layoutid' => $layoutId,
                    'regionid' => $regionId,
                    'mediaid' => $mediaId
                ));
        
            Debug::LogEntry('audit', 'OUT', 'LayoutMediaGroupSecurity', 'Unlink');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25028, __('Could not Unlink Layout Media from Group'));
        
            return false;
        }
    }

    /**
     * Copies a media items permissions
     * @param <type> $layoutId
     * @param <type> $regionId
     * @param <type> $mediaId
     * @param <type> $newMediaId
     * @return <type>
     */
    public function Copy($layoutId, $regionId, $mediaId, $newMediaId)
    {
        Debug::LogEntry('audit', 'IN', 'LayoutMediaGroupSecurity', 'Copy');

        try {
            $dbh = PDOConnect::init();
        
            $SQL  = "";
            $SQL .= "INSERT ";
            $SQL .= "INTO   lklayoutmediagroup ";
            $SQL .= "       ( ";
            $SQL .= "              LayoutID, ";
            $SQL .= "              RegionID, ";
            $SQL .= "              MediaID, ";
            $SQL .= "              GroupID, ";
            $SQL .= "              View, ";
            $SQL .= "              Edit, ";
            $SQL .= "              Del ";
            $SQL .= "       ) ";
            $SQL .= " SELECT LayoutID, RegionID, :mediaid, GroupID, View, Edit, Del ";
            $SQL .= "   FROM lklayoutmediagroup ";
            $SQL .= "  WHERE LayoutID = :layoutid AND RegionID = :regionid AND MediaID = :oldmediaid ";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'layoutid' => $layoutId,
                    'regionid' => $regionId,
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

    /**
     * Copys all media security for a layout
     * @param <type> $layoutId
     * @param <type> $newLayoutId
     * @return <type>
     */
    public function CopyAll($layoutId, $newLayoutId)
    {
        Debug::LogEntry('audit', 'IN', 'LayoutMediaGroupSecurity', 'Copy');

        try {
            $dbh = PDOConnect::init();

            $SQL  = "";
            $SQL .= "INSERT ";
            $SQL .= "INTO   lklayoutmediagroup ";
            $SQL .= "       ( ";
            $SQL .= "              LayoutID, ";
            $SQL .= "              RegionID, ";
            $SQL .= "              MediaID, ";
            $SQL .= "              GroupID, ";
            $SQL .= "              View, ";
            $SQL .= "              Edit, ";
            $SQL .= "              Del ";
            $SQL .= "       ) ";
            $SQL .= " SELECT :layoutid, RegionID, MediaID, GroupID, View, Edit, Del ";
            $SQL .= "   FROM lklayoutmediagroup ";
            $SQL .= "  WHERE LayoutID = :oldlayoutid ";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'layoutid' => $newLayoutId,
                    'oldlayoutid' => $layoutId
                ));
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25028, __('Could not Copy All Layout Media Security'));
        
            return false;
        }
    }

    /**
     * Copys all security for specific media on a layout
     * @param int $layoutId
     * @param int $newLayoutId
     * @param string $oldMediaId
     * @param string $newMediaId
     * @return bool
     */
    public function CopyAllForMedia($layoutId, $newLayoutId, $oldMediaId, $newMediaId)
    {
        Debug::LogEntry('audit', 'IN', 'LayoutMediaGroupSecurity', 'Copy');

        try {
            $dbh = PDOConnect::init();
        
            $SQL  = "";
            $SQL .= "INSERT ";
            $SQL .= "INTO   lklayoutmediagroup ";
            $SQL .= "       ( ";
            $SQL .= "              LayoutID, ";
            $SQL .= "              RegionID, ";
            $SQL .= "              MediaID, ";
            $SQL .= "              GroupID, ";
            $SQL .= "              View, ";
            $SQL .= "              Edit, ";
            $SQL .= "              Del ";
            $SQL .= "       ) ";
            $SQL .= " SELECT :layoutid, RegionID, :mediaid, GroupID, View, Edit, Del ";
            $SQL .= "   FROM lklayoutmediagroup ";
            $SQL .= "  WHERE LayoutID = :oldlayoutid AND MediaID = :oldmediaid ";
    
            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'layoutid' => $newLayoutId,
                    'oldlayoutid' => $layoutId,
                    'mediaid' => $newMediaId,
                    'oldmediaid' => $oldMediaId
                ));
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25028, __('Could not Copy All Layout Media Security'));
        
            return false;
        }
    }
}
?>