<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009 Daniel Garner
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
 
class Schedule extends Data
{
	/**
	 * Adds a Schedule Detail record. This can optionally be linked to a Schedule Event record.
	 * @return 
	 * @param $displayGroupID Object
	 * @param $layoutID Object
	 * @param $fromDT Object
	 * @param $toDT Object
	 * @param $userID Object
	 * @param $isPriority Object
	 * @param $eventID Object[optional]
	 */
	public function AddDetail($displayGroupID, $layoutID, $fromDT, $toDT, $userID = 1, $isPriority = 0, $eventID = '')
	{
		$db	=& $this->db;
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroup', 'AddDetail');
		
		$SQL  = "";
		$SQL .= "INSERT ";
		$SQL .= "INTO   schedule_detail ";
		$SQL .= "       ( ";
		$SQL .= "              DisplayGroupID, ";
		$SQL .= "              layoutID      , ";
		$SQL .= "              FromDT        , ";
		$SQL .= "              ToDT          , ";
		$SQL .= "              userID        , ";
		if ($eventID != '')
		{
			$SQL .= "              eventID       , ";
		}
		$SQL .= "              is_priority ";
		$SQL .= "       ) ";
		$SQL .= "       VALUES ";
		$SQL .= "       ( ";
		$SQL .= sprintf("      %d, ", $displayGroupID);
		$SQL .= sprintf("      %d, ", $layoutID);
		$SQL .= sprintf("      %d, ", $fromDT);
		$SQL .= sprintf("      %d, ", $toDT);
		$SQL .= sprintf("      %d, ", $userID);
		if ($eventID != '')
		{
			$SQL .= sprintf("      %d, ", $eventID);
		}
		$SQL .= sprintf("      %d  ", $isPriority);
		$SQL .= "       )";
		
		Debug::LogEntry($db, 'audit', $SQL);
			
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25002, __('Could not update Layout on Schedule'));
			
			return false;
		}
		
		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroup', 'AddDetail');
		
		return true;	
	}
	
	/**
	 * Edits the LayoutID of a Schedule Detail record
	 * @return 
	 * @param $scheduleDetailID Object
	 * @param $layoutID Object
	 */
	public function EditDetailLayoutID($scheduleDetailID, $layoutID)
	{
		$db	=& $this->db;
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroup', 'EditDetail');
		
		//Update the default layoutdisplay record
		$SQL = " UPDATE schedule_detail SET layoutid = %d ";
		$SQL .= " WHERE schedule_detailID = %d ";
		
		$SQL = sprintf($SQL, $layoutID, $displayGroupID, $scheduleID);
		
		Debug::LogEntry($db, 'audit', $SQL);
			
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25002, __('Could not update Layout on Schedule'));
			
			return false;
		}

		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroup', 'EditDetail');
		
		return false;
	}
	
	public function DeleteScheduleForDisplayGroup($displayGroupID)
	{
		$db	=& $this->db;
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroup', 'DeleteScheduleForDisplayGroup');
		
		// Delete all Schedule records for this DisplayGroupID
		$SQL = sprintf("DELETE FROM schedule_detail WHERE DisplayGroupID = %d", $displayGroupID);
		
		Debug::LogEntry($db, 'audit', $SQL);

		if (!$db->query($SQL)) 
		{
			$this->SetError(25015,__('Unable to delete schedule records for this Display Group.'));
			
			return false;
		}
		
		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroup', 'DeleteScheduleForDisplayGroup');
		
		return true;
	}
}
?>