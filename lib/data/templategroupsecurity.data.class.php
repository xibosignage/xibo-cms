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

class TemplateGroupSecurity extends Data
{
    /**
     * Links a Display Group to a Group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Link($templateId, $groupId, $view, $edit, $del)
    {
        Debug::LogEntry('audit', 'IN', 'TemplateGroupSecurity', 'Link');

        try {
            $dbh = PDOConnect::init();

            $SQL  = "INSERT INTO lktemplategroup (TemplateID, GroupID, View, Edit, Del) ";
            $SQL .= " VALUES (:templateid, :groupid, :view, :edit, :del)";
        
            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'templateid' => $templateId,
                    'groupid' => $groupId,
                    'view' => $view,
                    'edit' => $edit,
                    'del' => $del
                ));

            Debug::LogEntry('audit', 'OUT', 'TemplateGroupSecurity', 'Link');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25024, __('Could not Link Template to Group'));
        
            return false;
        }
    }

    /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function Unlink($templateId, $groupId)
    {
        Debug::LogEntry('audit', 'IN', 'TemplateGroupSecurity', 'Unlink');
        
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM lktemplategroup WHERE TemplateID = :templateid AND GroupID = :groupid');
            $sth->execute(array(
                    'templateid' => $templateId,
                    'groupid' => $groupId
                ));
    
            Debug::LogEntry('audit', 'OUT', 'TemplateGroupSecurity', 'Unlink');
    
            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25025, __('Could not Unlink Template from Group'));
        
            return false;
        }
    }

        /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function UnlinkAll($templateId)
    {
        Debug::LogEntry('audit', 'IN', 'TemplateGroupSecurity', 'UnlinkAll');

        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM lktemplategroup WHERE TemplateID = :templateid');
            $sth->execute(array(
                    'templateid' => $templateId
                ));
        
            Debug::LogEntry('audit', 'OUT', 'TemplateGroupSecurity', 'UnlinkAll');
    
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25025, __('Could not Unlink Template from Groups'));
        
            return false;
        }
    }
}
?>