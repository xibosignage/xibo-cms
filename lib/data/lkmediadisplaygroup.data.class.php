<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class LkMediaDisplayGroup extends Data {
    
    /**
     * Link display group and media item
     * @param int $displaygroupid The Display Group ID
     * @param int $mediaid        The Media ID
     */
    public function Link($displaygroupid, $mediaid) {
        Debug::LogEntry('audit', 'IN', get_class(), __FUNCTION__);

        try {
            $dbh = PDOConnect::init();

            $displaygroupid = Kit::ValidateParam($displaygroupid, _INT, false);
            $mediaid = Kit::ValidateParam($mediaid, _INT, false);
        
            $sth = $dbh->prepare('INSERT INTO `lkmediadisplaygroup` (mediaid, displaygroupid) VALUES (:mediaid, :displaygroupid)');
            $sth->execute(array(
                    'mediaid' => $mediaid,
                    'displaygroupid' => $displaygroupid
                ));

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
     * Unlink all media from the provided display group
     * @param int $displaygroupid The display group to unlink from
     */
    public function UnlinkAllFromDisplayGroup($displaygroupid) {
        Debug::LogEntry('audit', 'IN', get_class(), __FUNCTION__);

        try {
            $dbh = PDOConnect::init();

            $displaygroupid = Kit::ValidateParam($displaygroupid, _INT, false);
        
            $sth = $dbh->prepare('DELETE FROM `lkmediadisplaygroup` WHERE displaygroupid = :displaygroupid');
            $sth->execute(array('displaygroupid' => $displaygroupid));

            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }

    /**
     * Unlink all media from the provided media item
     * @param int $mediaid The media item to unlink from
     */
    public function UnlinkAllFromMedia($mediaid) {
        Debug::LogEntry('audit', 'IN', get_class(), __FUNCTION__);

        try {
            $dbh = PDOConnect::init();

            $mediaid = Kit::ValidateParam($mediaid, _INT, false);
        
            $sth = $dbh->prepare('DELETE FROM `lkmediadisplaygroup` WHERE mediaid = :mediaid');
            $sth->execute(array('mediaid' => $mediaid));

            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }
    }
}
