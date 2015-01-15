<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2013 Daniel Garner
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

class LayoutRegionGroupSecurity extends Data
{
    /**
     * Links a Display Group to a Group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Link($layoutId, $regionId, $groupId, $view, $edit, $del)
    {
        Debug::LogEntry('audit', 'IN', 'LayoutRegionGroupSecurity', 'Link');

        try {
            $dbh = PDOConnect::init();
        
            $SQL  = "";
            $SQL .= "INSERT INTO lklayoutregiongroup (LayoutID, RegionID, GroupID, View, Edit, Del) ";
            $SQL .= " VALUES (:layoutid, :regionid, :groupid, :view, :edit, :del) ";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'layoutid' => $layoutId,
                    'regionid' => $regionId,
                    'groupid' => $groupId,
                    'view' => $view,
                    'edit' => $edit,
                    'del' => $del
                ));
    
            Debug::LogEntry('audit', 'OUT', 'LayoutRegionGroupSecurity', 'Link');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25026, __('Could not Link Layout Region to Group'));
        
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
    public function LinkEveryone($layoutId, $regionId, $view, $edit, $del)
    {
        Debug::LogEntry('audit', 'IN', 'LayoutGroupSecurity', 'LinkEveryone');

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('SELECT GroupID FROM `group` WHERE IsEveryone = 1');
            $sth->execute();

            if (!$row = $sth->fetch())
                throw new Exception("Error Processing Request", 1);

            $groupId = Kit::ValidateParam($row['GroupID'], _INT);
        
            if (!$this->Link($layoutId, $regionId, $groupId, $view, $edit, $del))
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
    public function Unlink($layoutId, $regionId, $groupId)
    {
        Debug::LogEntry('audit', 'IN', 'LayoutRegionGroupSecurity', 'Unlink');

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM lklayoutregiongroup WHERE LayoutID = :layoutid AND RegionID = :regionid AND GroupID = :groupid');
            $sth->execute(array(
                    'layoutid' => $layoutId,
                    'regionid' => $regionId,
                    'groupid' => $groupId
                ));

            Debug::LogEntry('audit', 'OUT', 'LayoutRegionGroupSecurity', 'Unlink');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25027, __('Could not Unlink Layout Region from Group'));
        
            return false;
        }
    }

        /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function UnlinkAll($layoutId, $regionId)
    {
        Debug::LogEntry('audit', 'IN', 'LayoutRegionGroupSecurity', 'UnlinkAll');
        
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM lklayoutregiongroup WHERE LayoutID = :layoutid AND RegionID = :regionid');
            $sth->execute(array(
                    'layoutid' => $layoutId,
                    'regionid' => $regionId
                ));
    
            Debug::LogEntry('audit', 'OUT', 'LayoutRegionGroupSecurity', 'UnlinkAll');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25027, __('Could not Unlink Layout Region from Groups'));
        
            return false;
        }
    }

    /**
     * Copys all region security for a layout
     * @param <type> $layoutId
     * @param <type> $newLayoutId
     * @return <type>
     */
    public function CopyAll($layoutId, $newLayoutId)
    {
        Debug::LogEntry('audit', 'IN', 'LayoutRegionGroupSecurity', 'Copy');

        try {
            $dbh = PDOConnect::init();
        
            $SQL  = "";
            $SQL .= "INSERT ";
            $SQL .= "INTO   lklayoutregiongroup ";
            $SQL .= "       ( ";
            $SQL .= "              LayoutID, ";
            $SQL .= "              RegionID, ";
            $SQL .= "              GroupID, ";
            $SQL .= "              View, ";
            $SQL .= "              Edit, ";
            $SQL .= "              Del ";
            $SQL .= "       ) ";
            $SQL .= " SELECT :layoutid, RegionID, GroupID, View, Edit, Del ";
            $SQL .= "   FROM lklayoutregiongroup ";
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
                $this->SetError(25028, __('Could not Copy All Layout Region Security'));
        
            return false;
        }
    }
}
?>