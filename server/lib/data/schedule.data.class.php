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
	public function Add($displayGroupIDs, $fromDT, $toDT, $layoutID, $recType, $recDetail, $recToDT, $isPriority, $userID)
	{
		$db	=& $this->db;
		
		Debug::LogEntry($db, 'audit', 'IN', 'Schedule', 'Add');
		
		// make the displayid_list from the selected displays.
		$displayGroupIDList = implode(",", $displayGroupIDs); 
		
		$SQL  = "";
		$SQL .= "INSERT ";
		$SQL .= "INTO   `schedule` ";
		$SQL .= "       ( ";
		$SQL .= "              layoutid         , ";
		$SQL .= "              DisplayGroupIDs  , ";
		$SQL .= "              userID           , ";
		$SQL .= "              is_priority      , ";
		if ($recType != '')
		{
			$SQL .= "              recurrence_type  , ";
			$SQL .= "              recurrence_detail, ";
			$SQL .= "              recurrence_range , ";
		}
		$SQL .= "              FromDT           , ";
		$SQL .= "              ToDT ";
		$SQL .= "       ) ";
		$SQL .= "       VALUES ";
		$SQL .= "       ( ";
		$SQL .= sprintf("              %d              , ", $layoutID);
		$SQL .= sprintf("              '%s'            , ", $db->escape_string($displayGroupIDList));
		$SQL .= sprintf("              %d              , ", $userID);
		$SQL .= sprintf("              %d              , ", $isPriority);
		if ($recType != '')
		{
			$SQL .= sprintf("              '%s'            , ", $db->escape_string($recType));
			$SQL .= sprintf("              '%s'            , ", $db->escape_string($recDetail));
			$SQL .= sprintf("              %d              , ", $recToDT);
		}
		$SQL .= sprintf("              %d              , ", $fromDT);
		$SQL .= sprintf("              %d                ", $toDT);
		$SQL .= "       )";
		
		Debug::LogEntry($db, 'audit', $SQL);
		
		if (!$eventID = $db->insert_query($SQL)) 
		{
			trigger_error($db->error());
			$this->SetError(25001, __('Could not INSERT a new Schedule'));
			
			return false;
		}
		
		// Make sure we dont just have one...
		if (!is_array($displayGroupIDs)) $displayGroupIDs = array($displayGroupIDs);
		
		// Create a detail record for each display group
		foreach($displayGroupIDs as $displayGroupID)
		{
			// Add the parent detail record for this event
			Debug::LogEntry($db, 'audit', 'Calling AddDetail for new Schedule record', 'Schedule', 'Add');
			
			if (!$this->AddDetail($displayGroupID, $layoutID, $fromDT, $toDT, $userID, $isPriority, $eventID))
			{
				Debug::LogEntry($db, 'audit', 'Failure in AddDetail - aborting partially done', 'Schedule', 'Add');
				return false;
			}

			Debug::LogEntry($db, 'audit', 'Success Calling AddDetail for new Schedule record', 'Schedule', 'Add');
			
			// Is there any recurrance to take care of?
			if ($recType != '') 
			{
				// Set the temp starts
				$t_start_temp 	= $fromDT;
				$t_end_temp 	= $toDT;
				
				Debug::LogEntry($db, 'audit', sprintf('Recurrence detected until %d', $recToDT), 'Schedule', 'Add');
				
				//loop until we have added the recurring events for the schedule
				while ($t_start_temp < $recToDT) 
				{
					// add the appropriate time to the start and end
					switch ($recType) 
					{
						case 'Hour':
							$t_start_temp 	= mktime(date("H", $t_start_temp) + $recDetail, date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp), date("Y", $t_start_temp));
							$t_end_temp 	= mktime(date("H", $t_end_temp) + $recDetail, date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp), date("Y", $t_end_temp));
							break;
							
						case 'Day':
							$t_start_temp 	= mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp)+$recDetail, date("Y", $t_start_temp));
							$t_end_temp 	= mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp)+$recDetail, date("Y", $t_end_temp));
							break;
							
						case 'Week':
							$t_start_temp 	= mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp)+($recDetail*7), date("Y", $t_start_temp));
							$t_end_temp 	= mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp)+($recDetail*7), date("Y", $t_end_temp));
							break;
							
						case 'Month':
							$t_start_temp 	= mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp)+$recDetail ,date("d", $t_start_temp), date("Y", $t_start_temp));
							$t_end_temp 	= mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp)+$recDetail ,date("d", $t_end_temp), date("Y", $t_end_temp));
							break;
							
						case 'Year':
							$t_start_temp 	= mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp), date("Y", $t_start_temp)+$recDetail);
							$t_end_temp 	= mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp), date("Y", $t_end_temp)+$recDetail);
							break;
					}
					
					// after we have added the appropriate amount, are we still valid
					if ($t_start_temp > $recToDT) break;
					
					if (!$this->AddDetail($displayGroupID, $layoutID, $t_start_temp, $t_end_temp, $userID, $isPriority, $eventID))
					{
						Debug::LogEntry($db, 'audit', 'Failure in AddDetail - aborting partially done', 'Schedule', 'Add');
						return false;
					}
				}
			}
		}
		
		Debug::LogEntry($db, 'audit', 'OUT', 'Schedule', 'Add');
		
		return true;
	}
	
	public function Edit()
	{
		$db	=& $this->db;
		
		Debug::LogEntry($db, 'audit', 'IN', 'Schedule', 'Edit');
		
		Debug::LogEntry($db, 'audit', 'OUT', 'Schedule', 'Edit');
		
		return true;
	}
	
	public function Delete()
	{
		$db	=& $this->db;
		
		Debug::LogEntry($db, 'audit', 'IN', 'Schedule', 'Delete');
		
		Debug::LogEntry($db, 'audit', 'OUT', 'Schedule', 'Delete');
		
		return true;
	}
	
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
		
		Debug::LogEntry($db, 'audit', 'IN', 'Schedule', 'AddDetail');
		
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
		
		Debug::LogEntry($db, 'audit', 'OUT', 'Schedule', 'AddDetail');
		
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
	
	/**
	 * Deletes all the Schedule records for a display group
	 * @return 
	 * @param $displayGroupID Object
	 */
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
	
	/**
	 * Deletes all the Schedule records for an EventID
	 * @return 
	 * @param $displayGroupID Object
	 */
	public function DeleteScheduleForEvent($eventID)
	{
		$db	=& $this->db;
		
		Debug::LogEntry($db, 'audit', 'IN', 'DisplayGroup', 'DeleteScheduleForEvent');
		
		// Delete all Schedule records for this DisplayGroupID
		$SQL = sprintf("DELETE FROM schedule_detail WHERE EventID = %d", $eventID);
		
		Debug::LogEntry($db, 'audit', $SQL);

		if (!$db->query($SQL)) 
		{
			$this->SetError(25016,__('Unable to delete schedule records for this Event.'));
			
			return false;
		}
		
		Debug::LogEntry($db, 'audit', 'OUT', 'DisplayGroup', 'DeleteScheduleForEvent');
		
		return true;
	}
}
?>