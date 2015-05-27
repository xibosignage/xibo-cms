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
                    'todt' => $toDT,
                    'displayorder' => $displayOrder
                );
            
            $SQL  = "";
            $SQL .= "INSERT INTO `schedule` (CampaignId, DisplayGroupIDs, userID, is_priority, FromDT, ToDT, DisplayOrder ";
            
            // Columns for Recurrence
            if ($recType != '' && $recType != 'null') {
                $SQL .= ", recurrence_type, recurrence_detail, recurrence_range ";
            }
            
            $SQL .= ") ";
            $SQL .= " VALUES ( :campaignid, :displaygroupids, :userid, :is_priority, :fromdt, :todt, :displayorder ";
            
            // Values for Recurrence
            if ($recType != '' && $recType != 'null')
            {
                // Check that we have an end date
                if ($recToDT == '' || $recToDT == 0)
                    $this->ThrowError(__('Please provide an until date or set repeats to None'));

                // Check that we are non-negative
                if ($recDetail < 0)
                    $this->ThrowError(__('Repeat every cannot be a negative number.'));

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
                if (!$this->AddDetail($displayGroupID, $fromDT, $toDT, $userID, $eventID))
                    throw new Exception("Error Processing Request", 1);
                    
                // Is there any recurrance to take care of?
                if ($recType != '' && $recType != 'null') {
                    // Set the temp starts
                    $t_start_temp   = $fromDT;
                    $t_end_temp     = $toDT;
                    
                    Debug::LogEntry('audit', sprintf('Recurrence detected until %d. Recurrence period is %s and interval is %s.', $recToDT, $recDetail, $recType), 'Schedule', 'Add');
                    
                    //loop until we have added the recurring events for the schedule
                    while ($t_start_temp < $recToDT) 
                    {
                        // add the appropriate time to the start and end
                        switch ($recType) 
                        {
                            case 'Minute':
                                $t_start_temp   = mktime(date("H", $t_start_temp), date("i", $t_start_temp) + $recDetail, date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp), date("Y", $t_start_temp));
                                $t_end_temp     = mktime(date("H", $t_end_temp), date("i", $t_end_temp) + $recDetail, date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp), date("Y", $t_end_temp));
                                break;
                                
                            case 'Hour':
                                $t_start_temp   = mktime(date("H", $t_start_temp) + $recDetail, date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp), date("Y", $t_start_temp));
                                $t_end_temp     = mktime(date("H", $t_end_temp) + $recDetail, date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp), date("Y", $t_end_temp));
                                break;
                                
                            case 'Day':
                                $t_start_temp   = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp)+$recDetail, date("Y", $t_start_temp));
                                $t_end_temp     = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp)+$recDetail, date("Y", $t_end_temp));
                                break;
                                
                            case 'Week':
                                $t_start_temp   = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp) + ($recDetail * 7), date("Y", $t_start_temp));
                                $t_end_temp     = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp) + ($recDetail * 7), date("Y", $t_end_temp));
                                break;
                                
                            case 'Month':
                                $t_start_temp   = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp)+$recDetail ,date("d", $t_start_temp), date("Y", $t_start_temp));
                                $t_end_temp     = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp)+$recDetail ,date("d", $t_end_temp), date("Y", $t_end_temp));
                                break;
                                
                            case 'Year':
                                $t_start_temp   = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp), date("Y", $t_start_temp)+$recDetail);
                                $t_end_temp     = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp), date("Y", $t_end_temp)+$recDetail);
                                break;
                        }
                        
                        // after we have added the appropriate amount, are we still valid
                        if ($t_start_temp > $recToDT) break;
                        
                        if (!$this->AddDetail($displayGroupID, $t_start_temp, $t_end_temp, $userID, $eventID))
                            throw new Exception("Error Processing Request", 1);     
                    }
                }
            }
    
            // Notify (dont error)
            $displayObject = new Display();
            $displayObject->NotifyDisplays($campaignId);

            \Xibo\Helper\Log::audit('Schedule', $eventID, 'New Scheduled Event', $params);
            
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
    public function Edit($eventID, $displayGroupIDs, $fromDT, $toDT, $campaignId, $rec_type, $rec_detail, $recToDT, $isPriority, $userid, $displayOrder)
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
        
        return true;
    }
    
    /**
     * Deletes a scheduled event
     * @return 
     * @param $eventID int
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

            \Xibo\Helper\Log::audit('Schedule', $eventID, 'Schedule Event Deleted', array('eventId' => $eventID));
            
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
    public function AddDetail($displayGroupID, $fromDT, $toDT, $userID, $eventID)
    {
        Debug::LogEntry('audit', 'IN', 'Schedule', 'AddDetail');
        
        try {
            $dbh = PDOConnect::init();

            // The parameters for the INSERT
            $params = array(
                    'displaygroupid' => $displayGroupID,
                    'fromdt' => $fromDT,
                    'todt' => $toDT,
                    'userid' => $userID
                );
        
            // Insert statement
            $SQL = "INSERT INTO schedule_detail (DisplayGroupID, FromDT, ToDT, userID";
            
            // Extras for Event ID
            if ($eventID != '')
            {
                $SQL .= ", eventID";
                $params['eventid'] = $eventID;
            }

            $SQL .= ") ";
            
            // Values
            $SQL .= "VALUES (:displaygroupid, :fromdt, :todt, :userid";
            
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
     * Deletes all the Schedule records for a display group
     * @return 
     * @param $displayGroupID Object
     */
    public static function DeleteScheduleForCampaign($campaignId)
    {
        try {
            $dbh = PDOConnect::init();

            // Delete all Schedule Detail records for this campaignId
            $sth = $dbh->prepare('DELETE FROM schedule_detail WHERE EventID IN (SELECT EventID FROM `schedule` WHERE CampaignID = :campaignId)');
            $sth->execute(array(
                    'campaignId' => $campaignId
                ));

            // Delete all Schedule records for this campaignId
            $sth = $dbh->prepare('DELETE FROM schedule WHERE CampaignId = :campaignId');
            $sth->execute(array(
                    'campaignId' => $campaignId
                ));
            
            return true;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);

            throw new Exception(__('Unable to delete schedule records for Campaign.'), 25015);
        }
    }

    /**
     * Removes any orphaned records from the Schedule Table
     * Usually called as a result of an open-ended delete (such as deleting an entire display group)
     */
    private static function TidyScheduleTable() 
    {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM `schedule` WHERE EventID NOT IN (SELECT EventID FROM `schedule_detail`)');
            $sth->execute();
        
            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
            return false;
        }
    }

    /**
     * Removes any orphaned records from the Schedule Table
     * Usually called as a result of an open-ended delete (such as deleting an entire display group)
     */
    private static function TidyScheduleDetailTable() 
    {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('DELETE FROM `schedule_detail` WHERE EventID NOT IN (SELECT EventID FROM `schedule`)');
            $sth->execute();
        
            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());
        
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

    /**
     * Delete all events for a user
     * @param int $userId
     * @return bool
     */
    public function deleteAllForUser($userId)
    {
        // Get all events
        try {
            $dbh = PDOConnect::init();
            $sth = $dbh-> prepare('SELECT eventId FROM `schedule` WHERE userId = :userId');
            $sth->execute(array('userId' => $userId));

            $events = $sth->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (Exception $e) {
            return $this->SetError(__('Cannot get events for User'));
        }

        $eventIds = array_map(function ($element) { return $element['eventId']; }, $events);

        foreach ($eventIds as $eventId) {
            if (!$this->Delete($eventId))
                return false;
        }

        return true;
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