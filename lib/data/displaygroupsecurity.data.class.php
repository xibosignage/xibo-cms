<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-13 Daniel Garner
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

include_once('lib/data/schedule.data.class.php');

class DisplayGroupSecurity extends Data {	
	/**
	 * Links a Display Group to a Group
	 * @return 
	 * @param $displayGroupID Object
	 * @param $groupID Object
	 */
	public function Link($displayGroupId, $groupId, $view, $edit, $del)
	{
		Debug::LogEntry('audit', 'IN', 'DisplayGroupSecurity', 'Link');
		
		try {
            $dbh = PDOConnect::init();
		
			$sth = $dbh->prepare('INSERT INTO lkdisplaygroupgroup (DisplayGroupID, GroupID, View, Edit, Del) VALUES (:displaygroupid, :groupid, :view, :edit, :del)');
			$sth->execute(array(
					'displaygroupid' => $displayGroupId,
					'groupid' => $groupId,
					'view' => $view,
					'edit' => $edit,
					'del' => $del
				));
			
			Debug::LogEntry('audit', 'OUT', 'DisplayGroupSecurity', 'Link');
			
			return true;
		}
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25005, __('Could not Link Display Group to User Group'));

            return false;
        }
	}
	
	/**
	 * Unlinks a display group from a group
	 * @return 
	 * @param $displayGroupID Object
	 * @param $groupID Object
	 */
	public function Unlink($displayGroupId, $groupId)
	{
		Debug::LogEntry('audit', 'IN', 'DisplayGroupSecurity', 'Unlink');
		
		try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('DELETE FROM lkdisplaygroupgroup WHERE DisplayGroupID = :displaygroupid AND GroupID = :groupid');
			$sth->execute(array(
					'displaygroupid' => $displayGroupId,
					'groupid' => $groupId
				));
				
			Debug::LogEntry('audit', 'OUT', 'DisplayGroupSecurity', 'Unlink');
			
			return true;
		}
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25007, __('Could not Unlink Display Group from User Group'));

            return false;
        }
	}

   /**
     * Unlinks a display group from a group
     * @return
     * @param $displayGroupID Object
     * @param $groupID Object
     */
    public function UnlinkAll($displayGroupId)
    {
        Debug::LogEntry('audit', 'IN', 'DataSetGroupSecurity', 'Unlink');
        
        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('DELETE FROM lkdisplaygroupgroup WHERE DisplayGroupID = :displaygroupid');
			$sth->execute(array(
					'displaygroupid' => $displayGroupId
				));

	        Debug::LogEntry('audit', 'OUT', 'DataSetGroupSecurity', 'Unlink');

	        return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25007, __('Could not Unlink All Display Groups from User Group'));

            return false;
        }
    }
} 
?>