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

class DisplayGroup extends Data
{
	/**
	 * Adds a Display Group to Xibo
	 * @return 
	 * @param $displayGroup Object
	 * @param $isDisplaySpecific Object
	 * @param $description Object[optional]
	 */
	public function Add($displayGroup, $isDisplaySpecific, $description = '')
	{
		$db	=& $this->db;
		
		return true;
	}
	
	/**
	 * Edits an existing Xibo Display Group
	 * @return 
	 * @param $displayGroupID Object
	 * @param $displayGroup Object
	 * @param $description Object
	 */
	public function Edit($displayGroupID, $displayGroup, $description)
	{
		$db	=& $this->db;
		
		return true;
	}
	
	/**
	 * Deletes an Xibo Display Group
	 * @return 
	 * @param $displayGroupID Object
	 */
	public function Delete($displayGroupID)
	{
		$db	=& $this->db;
		
		return true;
	}
	
	/**
	 * Links a Display to a Display Group
	 * @return 
	 * @param $displayGroupID Object
	 * @param $displayID Object
	 */
	public function Link($displayGroupID, $displayID)
	{
		$db	=& $this->db;
		
		return true;
	}
	
	/**
	 * Unlinks a Display from a Display Group
	 * @return 
	 * @param $displayGroupID Object
	 * @param $displayID Object
	 */
	public function Unlink($displayGroupID, $displayID)
	{
		$db	=& $this->db;
		
		return true;
	}
	
	/**
	 * Sets the Default Layout on display linked groups
	 * @return 
	 * @param $displayID Object
	 * @param $layoutID Object
	 */
	public function SetDefaultLayout($displayID, $layoutID)
	{
		$db	=& $this->db;
		
		// Build some TimeStamps for the Default Schedule Record
		$fromDT		= mktime(0,0,0,1,1,2000);
		$toDT		= mktime(0,0,0,12,31,2050);
		
		// Get the DisplayGroupID for this DisplayID
		
		
		// check that we have a default layout display record to update
		// we might not and should be able to resolve it by editing the display
		$SQL = "SELECT schedule_detailID FROM schedule_detail ";
		$SQL .= " WHERE DisplayGroupID = %d ";
		$SQL .= "   AND FromDT = %d ";
		$SQL .= "   AND ToDT = %d ";
		
		$SQL = sprintf($SQL, $displayGroupID, $fromDT, $toDT);
		
		Debug::LogEntry($db, 'audit', $SQL);
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25001, __('Could not update display') . '-' . __('Stage 1'));
			
			return false;
		}
		
		if ($db->num_rows($results) == 0) 
		{
			// For some reason we do not have a default layout record for this display
			// We should add one now
			$SQL  = " INSERT INTO schedule_detail (DisplayGroupID, layoutid, starttime, endtime) ";
			$SQL .= " VALUES (%d, %d, %d, %d) ";
			
			$SQL = sprintf($SQL, $displayGroupID, $layoutid);
		}
		else 
		{
			$row		= $db->get_row($results);
			$scheduleID	= $row[0];
			
			//Update the default layoutdisplay record
			$SQL = " UPDATE schedule_detail SET layoutid = %d ";
			$SQL .= " WHERE DisplayGroupID = %d ";
			$SQL .= "   AND schedule_detailID = %d ";
			
			$SQL = sprintf($SQL, $layoutid, $displayGroupID, $scheduleID);
		}
		
		Debug::LogEntry($db, 'audit', $SQL);
			
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25002, __('Could not update display') . '-' . __('Stage 2'));
			
			return false;
		}
	}
}
?>