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
	public function __construct(database $db)
	{
		include_once('lib/data/displaygroup.data.class.php');
		
		parent::__construct($db);
	}
	
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

            Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroup', 'Add');

            // Create the SQL
            $SQL  = "";
            $SQL .= "INSERT ";
            $SQL .= "INTO   display ";
            $SQL .= "       ( ";
            $SQL .= "              display        , ";
            $SQL .= "              isAuditing     , ";
            $SQL .= "              defaultlayoutid, ";
            $SQL .= "              license        , ";
            $SQL .= "              licensed       , ";
            $SQL .= "              inc_schedule   , ";
            $SQL .= "              email_alert    , ";
            $SQL .= "              alert_timeout    ";
            $SQL .= "       ) ";
            $SQL .= "       VALUES ";
            $SQL .= "       ( ";
            $SQL .= sprintf("      '%s', ", $db->escape_string($display));
            $SQL .= "              0   , ";
            $SQL .= "              1   , ";
            $SQL .= sprintf("      '%s', ", $db->escape_string($license));
            $SQL .= "              0   , ";
            $SQL .= "              0   , ";
            $SQL .= "              1   , ";
            $SQL .= "              0 ";
            $SQL .= "       )";
		
            if (!$displayID = $db->insert_query($SQL))
            {
                trigger_error($db->error());
                $this->SetError(25000, __('Could not add display'));

                return false;
            }

            // Also want to add the DisplayGroup associated with this Display.
            $displayGroupObject = new DisplayGroup($db);

            if (!$displayGroupID = $displayGroupObject->Add($display, 1, ''))
            {
                $this->SetError(25001, __('Could not add a display group for the new display.'));

                return false;
            }

            // Link the Two together
            if (!$displayGroupObject->Link($displayGroupID, $displayID))
            {
                $this->SetError(25001, __('Could not link the new display with its group.'));

                return false;
            }

            Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroup', 'Add');

            return $displayID;
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
	public function Edit($displayID, $display, $isAuditing, $defaultLayoutID, $licensed, $incSchedule, $email_alert, $alert_timeout)
	{
		$db	=& $this->db;
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroup', 'Edit');
		
		// Update the display record
		$SQL  = "UPDATE display SET display = '%s', ";
		$SQL .= "		defaultlayoutid = %d, ";
		$SQL .= "		inc_schedule = %d, ";
		$SQL .= " 		licensed = %d, ";
		$SQL .= "		isAuditing = %d, ";
                $SQL .= "       email_alert = %d, ";
                $SQL .= "       alert_timeout = %d ";
		$SQL .= "WHERE displayid = %d ";
		
		$SQL = sprintf($SQL, $db->escape_string($display), $defaultLayoutID, $incSchedule, $licensed, $isAuditing, $email_alert, $alert_timeout, $displayID);
		
		Debug::LogEntry($db, 'audit', $SQL);
		
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25000, __('Could not update display') . '-' . __('Stage 1'));
			
			return false;
		}
		
		// Use a DisplayGroup to handle the default layout and displaygroup name for this display
		$displayGroupObject = new DisplayGroup($db);
		
		// Do we also want to update the linked Display Groups name (seeing as that is what we will be presenting to everyone)
		if (!$displayGroupObject->EditDisplayGroup($displayID, $display))
		{
			$this->SetError(25002, __('Could not update this display with a new name.'));
			
			return false;
		}
		
		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroup', 'Edit');
		
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
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroup', 'Delete');

                // Pass over to the DisplayGroup data class so that it can try and delete the
		// display specific group first (it is that group which is linked to schedules)
		$displayGroupObject = new DisplayGroup($db);
		
		// Do we also want to update the linked Display Groups name (seeing as that is what we will be presenting to everyone)
		if (!$displayGroupObject->DeleteDisplay($displayID))
		{
			$this->SetError(25002, __('Could not delete this display.'));
			
			return false;
		}

            // Delete the blacklist
            $SQL = sprintf("DELETE FROM blacklist WHERE DisplayID = %d", $displayID);

            Debug::LogEntry($db, 'audit', $SQL);

            if (!$db->query($SQL))
                return $this->SetError(25016,__('Unable to delete blacklist records.'));
		
		// Now we know the Display Group is gone - and so are any links
		// delete the display
		$SQL = " ";
		$SQL .= "DELETE FROM display ";
		$SQL .= sprintf(" WHERE displayid = %d", $displayID);
		
		Debug::LogEntry($db, 'audit', $SQL);

		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25015,__('Unable to delete display record. However it is no longer usable.'));
			
			return false;
		}

		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroup', 'Delete');
		
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
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroup', 'EditDisplayName');
	
		$SQL = sprintf("UPDATE display SET display = '%s' WHERE license = '%s' ", $display, $license);
				
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25010, __("Error updating this displays last accessed information."));
			
			return false;
		}
		
		// Also need to update the display group name here.
		$displayGroupObject = new DisplayGroup($db);
		
		// Do we also want to update the linked Display Groups name (seeing as that is what we will be presenting to everyone)
		if (!$displayGroupObject->EditDisplayGroup($displayID, $display))
		{
			$this->SetError(25015, __('Could not update this display with a new name.'));
			
			return false;
		}
		
		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroup', 'EditDisplayName');
		
		return true;
	}
	
	/**
	 * Sets the information required on the display to indicate
	 * that it is still logged in
	 * @return 
	 * @param $license Object
	 */
	public function Touch($license, $clientAddress = '')
	{
		$db		=& $this->db;
		$time 	= time();
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroup', 'Touch');
			
		// Set the last accessed flag on the display
		$SQL  = "";
                $SQL .= "UPDATE display SET lastaccessed = %d, loggedin = 1 ";

                // We will want to update the client Address if it is given
                if ($clientAddress != '')
                    $SQL .= sprintf(" , ClientAddress = '%s' ", $db->escape_string($clientAddress));

                // Restrict to the display license
                $SQL .= " WHERE license = '%s'";
                $SQL = sprintf($SQL, $time, $license);

                if (!$result = $db->query($SQL))
		{
                    trigger_error($db->error());
                    $this->SetError(25002, __("Error updating this displays last accessed information."));

                    return false;
		}
		
		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroup', 'Touch');
		
		return true;
	}
}
?>
