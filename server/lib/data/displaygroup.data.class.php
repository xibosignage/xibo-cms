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
	public function __construct(database $db)
	{
		include_once('lib/data/schedule.data.class.php');
		include_once('lib/data/displaygroupsecurity.data.class.php');
		
		parent::__construct($db);
	}
	
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
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroup', 'Add');
		
		// Create the SQL
		$SQL  = "";
		$SQL .= "INSERT ";
		$SQL .= "INTO   displaygroup ";
		$SQL .= "       ( ";
		$SQL .= "              DisplayGroup     , ";
		$SQL .= "              IsDisplaySpecific, ";
		$SQL .= "              Description ";
		$SQL .= "       ) ";
		$SQL .= "       VALUES ";
		$SQL .= "       ( ";
		$SQL .= sprintf("              '%s', ", $db->escape_string($displayGroup));
		$SQL .= sprintf("              %d  , ", $isDisplaySpecific);
		$SQL .= sprintf("              '%s'  ", $db->escape_string($description));
		$SQL .= "       )";
				
		if (!$displayGroupID = $db->insert_query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25000, __('Could not add Display Group'));
			
			return false;
		}
		
		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroup', 'Add');
		
		return $displayGroupID;
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
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroup', 'Edit');
		
		// Create the SQL
		$SQL  = "";
		$SQL .= "UPDATE displaygroup ";
		$SQL .= sprintf("SET    DisplayGroup   = '%s', ", $db->escape_string($displayGroup));
		$SQL .= sprintf("       Description    = '%s' ", $db->escape_string($description));
		$SQL .= sprintf("WHERE  DisplayGroupID = %d", $displayGroupID);
		
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25005, __('Could not edit Display Group'));
			
			return false;
		}
		
		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroup', 'Edit');		
		
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
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroup', 'Delete');
		
		$SQL = sprintf("DELETE FROM displaygroup WHERE DisplayGroupID = %d", $displayGroupID);
		
		Debug::LogEntry($db, 'audit', $SQL);

		if (!$db->query($SQL)) 
		{
			$this->SetError(25015,__('Unable to delete Display Group.'));
			
			return false;
		}

		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroup', 'Delete');
		
		return true;
	}
	
	/**
	 * Deletes all Display Group records associated with a display.
	 * @return 
	 * @param $displayID Object
	 */
	public function DeleteDisplay($displayID)
	{
		$db	=& $this->db;
		
		// Get the DisplaySpecific Group for this Display
		$SQL  = "";
		$SQL .= "SELECT displaygroup.DisplayGroupID ";
		$SQL .= "FROM   displaygroup ";
		$SQL .= "       INNER JOIN lkdisplaydg ";
		$SQL .= "       ON     lkdisplaydg.DisplayGroupID = displaygroup.DisplayGroupID ";
		$SQL .= "WHERE  displaygroup.IsDisplaySpecific    = 1 ";
		$SQL .= sprintf("   AND lkdisplaydg.DisplayID             = %d", $displayID);
		
		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error());
			$this->SetError(25005, __('Unable to get the DisplayGroup for this Display'));
			
			return false;
		}
		
		$row 			= $db->get_assoc_row($result);
		$displayGroupID	= $row['DisplayGroupID'];
		
		if ($displayGroupID == '')
		{
			// If there is no region specific display record... what do we do?
			$this->SetError(25006, __('Unable to get the DisplayGroup for this Display'));
			
			return false;
		}
		
		// Delete the Schedule for this Display Group
		$scheduleObject = new Schedule($db);
		
		if (!$scheduleObject->DeleteScheduleForDisplayGroup($displayGroupID))
		{
			$this->SetError(25006, __('Unable to delete Schedule records for this DisplayGroup.'));
			
			return false;
		}
		
		// Unlink all Display Groups from this Display
		$SQL = sprintf("DELETE FROM lkdisplaydg WHERE DisplayID = %d", $displayID);
		
		Debug::LogEntry($db, 'audit', $SQL);

		if (!$db->query($SQL)) 
		{
			$this->SetError(25015,__('Unable to delete Display Group Links.'));
			
			return false;
		}

            // Delete this display groups link to any groups
            $SQL = sprintf("DELETE FROM lkgroupdg WHERE DisplayGroupId = %d", $displayGroupID);
		
            Debug::LogEntry($db, 'audit', $SQL);

            if (!$db->query($SQL))
                return $this->SetError(25016,__('Unable to delete Display Group Links.'));
		
		// Delete the Display Group Itself
		if (!$this->Delete($displayGroupID))
		{
			// An error will already be set - so just drop out
			return false;
		}
		
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
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroup', 'Link');
		
		$SQL  = "";
		$SQL .= "INSERT ";
		$SQL .= "INTO   lkdisplaydg ";
		$SQL .= "       ( ";
		$SQL .= "              DisplayGroupID, ";
		$SQL .= "              DisplayID ";
		$SQL .= "       ) ";
		$SQL .= "       VALUES ";
		$SQL .= "       ( ";
		$SQL .= sprintf("              %d, %d ", $displayGroupID, $displayID);
		$SQL .= "       )";
		
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25005, __('Could not Link Display Group to Display'));
			
			return false;
		}
		
		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroup', 'Link');
		
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
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroup', 'Unlink');
		
		$SQL  = "";
		$SQL .= "DELETE FROM ";
		$SQL .= "   lkdisplaydg ";
		$SQL .= sprintf("  WHERE DisplayGroupID = %d AND DisplayID = %d ", $displayGroupID, $displayID);
		
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25007, __('Could not Unlink Display Group from Display'));
			
			return false;
		}
		
		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroup', 'Unlink');
		
		return true;
	}
	
	/**
	 * Edits the Display Group associated with a Display
	 * @return 
	 * @param $displayID Object
	 * @param $display Object
	 */
	public function EditDisplayGroup($displayID, $display)
	{
		$db	=& $this->db;
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroup', 'EditDisplayGroup');
		
		// Get the DisplayGroupID for this DisplayID
		$SQL  = "";
		$SQL .= "SELECT displaygroup.DisplayGroupID ";
		$SQL .= "FROM   displaygroup ";
		$SQL .= "       INNER JOIN lkdisplaydg ";
		$SQL .= "       ON     lkdisplaydg.DisplayGroupID = displaygroup.DisplayGroupID ";
		$SQL .= "WHERE  displaygroup.IsDisplaySpecific    = 1 ";
		$SQL .= sprintf("   AND lkdisplaydg.DisplayID             = %d", $displayID);
		
		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error());
			$this->SetError(25005, __('Unable to get the DisplayGroup for this Display'));
			
			return false;
		}
		
		$row 			= $db->get_assoc_row($result);
		$displayGroupID	= $row['DisplayGroupID'];
		
		if ($displayGroupID == '')
		{
			// We should always have 1 display specific DisplayGroup for a display.
			// Do we a) Error here and give up?
			//		 b) Create one and link it up?
			// $this->SetError(25006, __('Unable to get the DisplayGroup for this Display'));
			
			if (!$displayGroupID = $this->Add($display, 1))
			{
				$this->SetError(25001, __('Could not add a display group for the new display.'));
				
				return false;
			}
			
			// Link the Two together
			if (!$this->Link($displayGroupID, $displayID))
			{
				$this->SetError(25001, __('Could not link the new display with its group.'));
				
				return false;
			}
		}
		
		// Update SQL
		$SQL  = "";
		$SQL .= "UPDATE displaygroup ";
		$SQL .= sprintf("SET    DisplayGroup   = '%s' ", $db->escape_string($display));
		$SQL .= sprintf("WHERE  DisplayGroupID = %d", $displayGroupID);
		
		if (!$db->query($SQL))
		{
			trigger_error($db->error());
			$this->SetError(25005, __('Unable to update the DisplayGroup for this Display'));
			
			return false;
		}
		
		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroup', 'EditDisplayGroup');
		
		return true;
	}
	
    /**
     * DEPRICATED: Removed in 1.2.0. Sets the Default Layout on display linked groups
     * @return
     * @param $displayID Object
     * @param $layoutID Object
     */
    public function SetDefaultLayout($displayID, $layoutID)
    {
        $db	=& $this->db;

        Debug::LogEntry($db, 'audit', 'Depricated method called.', 'DisplayGroup', 'SetDefaultLayout');
        return true;
    }
}
?>