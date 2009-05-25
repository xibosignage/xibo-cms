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

class Display extends Data
{
	/**
	 * Adds a Display
	 * @return 
	 * @param $display Object
	 * @param $isAuditing Object
	 * @param $defaultLayoutID Object
	 * @param $license Object
	 * @param $licensed Object
	 * @param $incSchedule Object
	 */
	public function Add($display, $isAuditing, $defaultLayoutID, $license, $licensed, $incSchedule)
	{
		$db	=& $this->db;
	}
	
	/**
	 * Edits a Display 
	 * @return 
	 * @param $displayID Object
	 * @param $isAuditing Object
	 * @param $defaultLayoutID Object
	 * @param $licensed Object
	 * @param $incSchedule Object
	 */
	public function Edit($displayID, $isAuditing, $defaultLayoutID, $licensed, $incSchedule)
	{
		$db	=& $this->db;
		
		// Update the display record
		$SQL  = "UPDATE display SET display = '%s', ";
		$SQL .= "		defaultlayoutid = %d, ";
		$SQL .= "		inc_schedule = %d, ";
		$SQL .= " 		licensed = %d, ";
		$SQL .= "		isAuditing = %d ";
		$SQL .= "WHERE displayid = ".$displayid;
		
		$SQL = sprintf($SQL, $db->escape_string($display), $layoutid, $inc_schedule, $licensed, $auditing, $displayid);
		
		Debug::LogEntry($db, 'audit', $SQL);
		
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25000, __('Could not update display') . '-' . __('Stage 1'));
			
			return false;
		}
		
		// Use a DisplayGroup to handle the default layout and displaygroup name for this display
		include_once('lib/data/displaygroup.data.class.php');
		$displayGroupObject = new DisplayGroup($db);
		
		// Set the default layout
		if (!$displayGroupObject->SetDefaultLayout($displayID, $defaultLayoutID))
		{
			$this->SetError(25000, __('Could not update display with default layout.'));
			
			return false;
		}
		
		// Do we also want to update the linked Display Groups name (seeing as that is what we will be presenting to everyone)
		
		return true;
	}
	
	/**
	 * Deletes a Display
	 * @return 
	 * @param $displayID Object
	 */
	public function Delete($displayID)
	{
		$db	=& $this->db;
		
		return true;
	}
	
	/**
	 * Edits a Displays Name
	 * @return 
	 * @param $license Object
	 * @param $display Object
	 */
	public function EditDisplayName($license, $display) 
	{
		$db	=& $this->db;
		
		return true;
	}
	
	/**
	 * Sets the information required on the display to indicate
	 * that it is still logged in
	 * @return 
	 * @param $license Object
	 */
	public function Touch($license)
	{
		$db		=& $this->db;
		$time 	= time();
			
		// Set the last accessed flag on the display
		$SQL 	= sprintf("UPDATE display SET lastaccessed = %d, loggedin = 1 WHERE license = '%s' ", $time, $license);

		if (!$result = $db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25002, __("Error updating this displays last accessed information."));
			
			return false;
		}
		
		return true;
	}
}
?>