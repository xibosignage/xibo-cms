<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2013 Daniel Garner
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
	 * Add
	 * @return 
	 * @param $displayGroupIDs Object
	 * @param $fromDT Object
	 * @param $toDT Object
	 * @param $layoutID Object
	 * @param $recType Object
	 * @param $recDetail Object
	 * @param $recToDT Object
	 * @param $isPriority Object
	 * @param $userID Object
	 * @param $displayOrder Object
	 */
	public function Add($displayGroupIDs, $fromDT, $toDT, $campaignId, $recType, $recDetail, $recToDT, $isPriority, $userID, $displayOrder = 0)
	{
		Debug::LogEntry('audit', 'IN', 'Schedule', 'Add');
		
        try {
            $dbh = PDOConnect::init();
        
            // Validation
        	if (count($displayGroupIDs) == 0)
                return $this->SetError(25001, __('No display groups selected'));
    
            if ($userID == 0)
                return $this->SetError(25001, __('No User Id Present'));
    
            // Cant have a 0 increment as it creates a loop
            if ($recDetail == 0)
                $recDetail = 1;
    
    		// make the displayid_list from the selected displays.
    		$displayGroupIDList = implode(",", $displayGroupIDs);

    		// Parameters for the query
    		$params = array(
					'campaignid' => $campaignId,
					'displaygroupids' => $displayGroupIDList,
					'userid' => $userID,
					'is_priority' => $isPriority,
					'fromdt' => $fromDT,
					'todt' => $toDT
				);
    		
    		$SQL  = "";
    		$SQL .= "INSERT INTO `schedule` (CampaignId, DisplayGroupIDs, userID, is_priority, FromDT, ToDT ";
    		
    		// Columns for Recurrence
    		if ($recType != '' && $recType != 'null') {
    			$SQL .= ", recurrence_type, recurrence_detail, recurrence_range ";
    		}
    		
    		$SQL .= ") ";
    		$SQL .= " VALUES ( :campaignid, :displaygroupids, :userid, :is_priority, :fromdt, :todt ";
    		
    		// Values for Recurrence
    		if ($recType != '' && $recType != 'null')
    		{
    			$SQL .= ", :recurrence_type, :recurrence_detail, :recurrence_range ";
    			$params['recurrence_type'] = $recType;
    			$params['recurrence_detail'] = $recDetail;
    			$params['recurrence_range'] = $recToDT;
    		}

    		$SQL .= ")";

    		$sth = $dbh->prepare($SQL);
            $sth->execute($params);
    		
    		// Get the event id
    		$eventID = $dbh->lastInsertId();
    		
    		// Make sure we dont just have one...
    		if (!is_array($displayGroupIDs)) 
    			$displayGroupIDs = array($displayGroupIDs);
    		
    		// Create a detail record for each display group
    		foreach ($displayGroupIDs as $displayGroupID)
    		{
    			// Add the parent detail record for this event
    			if (!$this->AddDetail($displayGroupID, $campaignId, $fromDT, $toDT, $userID, $isPriority, $eventID, $displayOrder))
    				throw new Exception("Error Processing Request", 1);
    			    
    			// Is there any recurrance to take care of?
    			if ($recType != '' && $recType != 'null') {
    				// Set the temp starts
    				$t_start_temp 	= $fromDT;
    				$t_end_temp 	= $toDT;
    				
    				Debug::LogEntry('audit', sprintf('Recurrence detected until %d. Recurrence period is %s and interval is %s.', $recToDT, $recDetail, $recType), 'Schedule', 'Add');
    				
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
    							$t_start_temp 	= mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp) + ($recDetail * 7), date("Y", $t_start_temp));
    							$t_end_temp 	= mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp) + ($recDetail * 7), date("Y", $t_end_temp));
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
    					
    					if (!$this->AddDetail($displayGroupID, $campaignId, $t_start_temp, $t_end_temp, $userID, $isPriority, $eventID, $displayOrder))
    						throw new Exception("Error Processing Request", 1);		
    				}
    			}
    		}
    
            // Notify (dont error)
            Kit::ClassLoader('Display');
            $displayObject = new Display($this->db);
            $displayObject->NotifyDisplays($campaignId);
    		
    		Debug::LogEntry('audit', 'OUT', 'Schedule', 'Add');
    		
    		return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            if (!$this->IsError())
                $this->SetError(25001, __('Could not INSERT a new Schedule'));
        
            return false;
        }
	}
	
    /**
     * Edits a Schedule
     * @param <type> $eventID
     * @param <type> $eventDetailID
     * @param <type> $displayGroupIDs
     * @param <type> $fromDT
     * @param <type> $toDT
     * @param <type> $campaignId
     * @param <type> $rec_type
     * @param <type> $rec_detail
     * @param <type> $recToDT
     * @param <type> $isPriority
     * @param <type> $userid
     * @param <int> $displayOrder
     * @return <type>
     */
	public function Edit($eventID, $eventDetailID, $displayGroupIDs, $fromDT, $toDT, $campaignId, $rec_type, $rec_detail, $recToDT, $isPriority, $userid, $displayOrder)
	{
		Debug::LogEntry('audit', 'IN', 'Schedule', 'Edit');

        // Cant have a 0 increment as it creates a loop
        if ($rec_detail == 0)
            $rec_detail = 1;
		
		// What we are really going to do here is delete and re-add... just because it is easier to get the logic right
		// and it means the same logic will be applied across both functions.
		
		// Delete the old schedule
		if (!$this->Delete($eventID))
			return false;
		
		// Add the new one
		if (!$this->Add($displayGroupIDs, $fromDT, $toDT, $campaignId, $rec_type, $rec_detail, $recToDT, $isPriority, $userid, $displayOrder))
			return false;
		
		Debug::LogEntry('audit', 'OUT', 'Schedule', 'Edit');
		
		return true;
	}
	
	/**
	 * Deletes a scheduled event
	 * @return 
	 * @param $eventID Object
	 */
	public function Delete($eventID)
	{
		Debug::LogEntry('audit', 'IN', 'Schedule', 'Delete');
		
		try {
		    $dbh = PDOConnect::init();
		
		    if (!$this->DeleteScheduleForEvent($eventID))
				throw new Exception("Error Processing Request", 1);
			
			// Delete all Schedule records for this DisplayGroupID
			$sth = $dbh->prepare('DELETE FROM schedule WHERE eventID = :eventid');
		    $sth->execute(array(
		            'eventid' => $eventID
		        ));
				
			Debug::LogEntry('audit', 'OUT', 'Schedule', 'Delete');
			
			return true;  
		}
		catch (Exception $e) {
		    
		    Debug::LogEntry('error', $e->getMessage());
		
		    if (!$this->IsError())
		        $this->SetError(25016,__('Unable to delete schedule record for this Event.'));
		
		    return false;
		}
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
	public function AddDetail($displayGroupID, $campaignId, $fromDT, $toDT, $userID = 1, $isPriority = 0, $eventID = '', $displayOrder = 0)
	{
		Debug::LogEntry('audit', 'IN', 'Schedule', 'AddDetail');
		
		try {
		    $dbh = PDOConnect::init();

		    // The parameters for the INSERT
		    $params = array(
					'displaygroupid' => $displayGroupID,
					'campaignid' => $campaignId,
					'fromdt' => $fromDT,
					'todt' => $toDT,
					'userid' => $userID,
					'is_priority' => $isPriority,
					'displayorder' => $displayOrder
				);
		
			// Insert statement
			$SQL = "INSERT INTO schedule_detail (DisplayGroupID, CampaignId, FromDT, ToDT, userID, is_priority, DisplayOrder";
			
			// Extras for Event ID
			if ($eventID != '')
			{
				$SQL .= ", eventID";
				$params['eventid'] = $eventID;
			}

			$SQL .= ") ";
			
			// Values
			$SQL .= "VALUES (:displaygroupid, :campaignid, :fromdt, :todt, :userid, :is_priority, :displayorder";
			
			if ($eventID != '')
				$SQL .= ", :eventid";

			$SQL .= ")";
			
			// Execute the SQL
		    $sth = $dbh->prepare($SQL);
		    $sth->execute($params);
			
			Debug::LogEntry('audit', 'OUT', 'Schedule', 'AddDetail');
			
			return true;  
		}
		catch (Exception $e) {
		    
		    Debug::LogEntry('error', $e->getMessage());
		
		    if (!$this->IsError())
		        $this->SetError(25002, __('Could not update Layout on Schedule'));
		
		    return false;
		}	
	}
	
	/**
	 * Edits the LayoutID of a Schedule Detail record
	 * @return 
	 * @param $scheduleDetailID Object
	 * @param $layoutID Object
	 */
	public function EditDetailLayoutID($scheduleDetailID, $campaignId)
	{
		Debug::LogEntry('audit', 'IN', 'Schedule', 'EditDetailLayoutID');
		
		try {
		    $dbh = PDOConnect::init();
		
			// Update the default layoutdisplay record
		    $sth = $dbh->prepare('UPDATE schedule_detail SET CampaignId = :campaignid WHERE schedule_detailID = :scheduledetailid');
		    $sth->execute(array(
		            'scheduledetailid' => $scheduleDetailID,
		            'campaignid' => $campaignId
		        ));

			Debug::LogEntry('audit', 'OUT', 'Schedule', 'EditDetailLayoutID');
			
			return true;  
		}
		catch (Exception $e) {
		    
		    Debug::LogEntry('error', $e->getMessage());
		
		    if (!$this->IsError())
		        $this->SetError(25002, __('Could not update Layout on Schedule'));
		
		    return false;
		}
	}
	
	/**
	 * Deletes all the Schedule records for a display group
	 * @return 
	 * @param $displayGroupID Object
	 */
	public function DeleteScheduleForDisplayGroup($displayGroupID)
	{
		Debug::LogEntry('audit', 'IN', 'DisplayGroup', 'DeleteScheduleForDisplayGroup');
		
		try {
		    $dbh = PDOConnect::init();

			// Delete all Schedule records for this DisplayGroupID
		    $sth = $dbh->prepare('DELETE FROM schedule_detail WHERE DisplayGroupID = :displaygroupid');
		    $sth->execute(array(
		            'displaygroupid' => $displayGroupID
		        ));

		    // Tidy up the schedule table. There might be orphaned records because of this delete
		    $this->TidyScheduleTable();

			Debug::LogEntry('audit', 'OUT', 'DisplayGroup', 'DeleteScheduleForDisplayGroup');
			
			return true;  
		}
		catch (Exception $e) {
		    
		    Debug::LogEntry('error', $e->getMessage());
		
		    if (!$this->IsError())
		        $this->SetError(25015,__('Unable to delete schedule records for this Display Group.'));
		
		    return false;
		}
	}

	/**
	 * Removes any orphaned records from the Schedule Table
	 * Usually called as a result of an open-ended delete (such as deleting an entire display group)
	 */
	private function TidyScheduleTable() {
		Debug::LogEntry('audit', 'IN', 'DisplayGroup', 'TidyScheduleTable');

		try {
		    $dbh = PDOConnect::init();
		
		    $sth = $dbh->prepare('DELETE FROM `schedule` WHERE EventID NOT IN (SELECT EventID FROM `schedule_detail`)');
		    $sth->execute();
		
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
	 * Deletes all the Schedule records for an EventID
	 * @return 
	 * @param $displayGroupID Object
	 */
	public function DeleteScheduleForEvent($eventID) {
		Debug::LogEntry('audit', 'IN', 'DisplayGroup', 'DeleteScheduleForEvent');
		
		try {
		    $dbh = PDOConnect::init();
		
			// Delete all Schedule records for this DisplayGroupID
		    $sth = $dbh->prepare('DELETE FROM schedule_detail WHERE EventID = :eventid');
		    $sth->execute(array(
		            'eventid' => $eventID
		        ));
			
			Debug::LogEntry('audit', 'OUT', 'DisplayGroup', 'DeleteScheduleForEvent');
			
			return true;  
		}
		catch (Exception $e) {
		    
		    Debug::LogEntry('error', $e->getMessage());
		
		    if (!$this->IsError())
		        $this->SetError(25016,__('Unable to delete schedule records for this Event.'));
		
		    return false;
		}
	}
	
	/**
	 * Deletes all the Schedule records for an EventID and DisplayGroupID
	 * @return 
	 * @param $displayGroupID Object
	 */
	public function DeleteScheduleForEventAndGroup($eventID, $displayGroupID) {
		Debug::LogEntry('audit', 'IN', 'DisplayGroup', 'DeleteScheduleForEventAndGroup');
		
		try {
		    $dbh = PDOConnect::init();
		
			// Delete all Schedule records for this DisplayGroupID
		    $sth = $dbh->prepare('DELETE FROM schedule_detail WHERE EventID = :eventid AND DisplayGroupID = :displaygroupid');
		    $sth->execute(array(
		            'displaygroupid' => $displayGroupID,
		            'eventid' => $eventID
		        ));

			Debug::LogEntry('audit', 'OUT', 'DisplayGroup', 'DeleteScheduleForEventAndGroup');
			
			return true;  
		}
		catch (Exception $e) {
		    
		    Debug::LogEntry('error', $e->getMessage());
		
		    if (!$this->IsError())
		        $this->SetError(25016,__('Unable to delete schedule records for this Event and DisplayGroup.'));
		
		    return false;
		}
	}
	
	/**
	 * Deletes the event detail record provided
	 * @return 
	 * @param $eventDetailID Object
	 */
	public function DeleteEventDetail($eventDetailID) {
		Debug::LogEntry('audit', 'IN', 'Schedule', 'DeleteEventDetail');
		
		try {
		    $dbh = PDOConnect::init();
		
			// Delete all Schedule records for this EventDetail
		    $sth = $dbh->prepare('DELETE FROM schedule_detail WHERE schedule_detailID = :schedule_detailid');
		    $sth->execute(array(
		            'schedule_detailid' => $eventDetailID
		        ));
				
			Debug::LogEntry('audit', 'OUT', 'Schedule', 'DeleteEventDetail');
			
			return true;  
		}
		catch (Exception $e) {
		    
		    Debug::LogEntry('error', $e->getMessage());
		
		    if (!$this->IsError())
		        $this->SetError(25016,__('Unable to delete schedule records for this Event.'));
		
		    return false;
		}
	}
	
	public function DeleteDisplayGroupFromEvent($eventID, $displayGroupID)
	{
		Debug::LogEntry('audit', 'IN', 'Schedule', 'EditDisplayGroupsForEvent');
		
		try {
		    $dbh = PDOConnect::init();
		
		    // Read the display groups from the event
			$SQL = sprintf('', $eventID);
			$sth = $dbh->prepare('SELECT DisplayGroupIDs FROM schedule WHERE EventID = :eventid');
		    $sth->execute(array(
		            'eventid' => $eventID
		        ));
			
			if (!$row = $sth->fetch())
				$this->ThrowError(25034,__('Error retriving information necessary to delete this event'));
							
			// Get the Display Group IDs
			$displayGroupIDs = Kit::ValidateParam($row['DisplayGroupIDs'], _STRING);
			
			// Load into an array and remove the one in $displayGroupID
			$displayGroupIDs = explode(',', $displayGroupIDs);
			$key = array_search($displayGroupID, $displayGroupIDs);
			
			if ($key !== true) {
				unset($displayGroupIDs[$key]);
			}
			else {
				Debug::LogEntry('audit', 'Display Group ID is already removed from the Event - this is strange.', 'Schedule', 'EditDisplayGroupsForEvent');
				return true;
			}
			
			// Save the list back to the event
			$displayGroupIDList = implode(',', $displayGroupIDs);
			
			// Delete all Schedule records for this EventDetail
			$sth = $dbh->prepare('UPDATE schedule SET DisplayGroupIDs = :displaygroupids WHERE EventID = :eventid');
		    $sth->execute(array(
		            'eventid' => $eventID,
		            'displaygroupids' => $displayGroupIDList
		        ));
			
			Debug::LogEntry('audit', 'OUT', 'Schedule', 'EditDisplayGroupsForEvent');
			
			return true;  
		}
		catch (Exception $e) {
		    
		    Debug::LogEntry('error', $e->getMessage());
		
		    if (!$this->IsError())
		        $this->SetError(25036,__('Unable to edit the display groups for this Event.'));
		
		    return false;
		}
	}

	/**
	 * Gets an array of display group ids for the given event
	 * @param [int] $eventId The Event ID
	 */
	public function DisplayGroupsForEvent($eventId) {
		$eventId = Kit::ValidateParam($eventId, _INT);

		try {
		    $dbh = PDOConnect::init();
		
		    $sth = $dbh->prepare('SELECT DISTINCT DisplayGroupID FROM `schedule_detail` WHERE EventID = :eventid');
		    $sth->execute(array(
		            'eventid' => $eventId
		        ));

		    $ids = array();
		  
		  	while ($row = $sth->fetch()) {
		  		$ids[] = Kit::ValidateParam($row['DisplayGroupID'], _INT);
		  	}

		  	return $ids;
		}
		catch (Exception $e) {
		    
		    Debug::LogEntry('error', $e->getMessage());
		
		    if (!$this->IsError())
		        $this->SetError(1, __('Unknown Error'));
		
		    return false;
		}
	}
}

class Event
{
	public $eventID;
	public $eventDetailID;
	public $fromDT;
	public $toDT;
	public $layout;
	public $layoutUri;
    public $deleteUri;
	public $spanningDays;
	public $startDayNo;
	public $displayGroup;
	public $editPermission;
    public $isdisplayspecific;
	
	public function __construct()
	{
		
	}
}
?>