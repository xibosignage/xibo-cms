<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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
	 * @param $displayGroup string
	 * @param $isDisplaySpecific int
	 * @param $description string[optional]
	 */
	public function Add($displayGroup, $isDisplaySpecific, $description = '')
	{
		Debug::LogEntry('audit', 'IN', 'DisplayGroup', 'Add');

		try {
            $dbh = PDOConnect::init();

			// Validation
			if ($displayGroup == '')
				$this->ThrowError(__('Please enter a display group name'));
			
			if (strlen($description) > 254) 
				$this->ThrowError(__("Description can not be longer than 254 characters"));

			$sth = $dbh->prepare('SELECT DisplayGroup FROM displaygroup WHERE DisplayGroup = :displaygroup AND IsDisplaySpecific = 0');
            $sth->execute(array(
                    'displaygroup' => $displayGroup
                ));

            if ($row = $sth->fetch())
                $this->ThrowError(25004, sprintf(__('You already own a display group called "%s". Please choose another name.'), $displayGroup));

			// End Validation
			
			// Insert the display group
			$sth = $dbh->prepare('INSERT INTO displaygroup (DisplayGroup, IsDisplaySpecific, Description) VALUES (:displaygroup, :isdisplayspecific, :description)');
            $sth->execute(array(
                    'displaygroup' => $displayGroup,
                    'isdisplayspecific' => $isDisplaySpecific,
                    'description' => $description
                ));

            $displayGroupID = $dbh->lastInsertId();
			
			Debug::LogEntry('audit', 'OUT', 'DisplayGroup', 'Add');

			return $displayGroupID;
		}
        catch (Exception $e) {

            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25000, __('Could not add Display Group'));

            return false;
        }
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
		Debug::LogEntry('audit', 'IN', 'DisplayGroup', 'Edit');

		try {
            $dbh = PDOConnect::init();

			// Validation
			if ($displayGroupID == 0) 
				$this->ThrowError(__('No Display Group Selected'));
			
			if ($displayGroup == '')
				$this->ThrowError(__('Please enter a display group name'));
			
			if (strlen($description) > 254) 
				$this->ThrowError(__("Description can not be longer than 254 characters"));

			$sth = $dbh->prepare('SELECT DisplayGroup FROM displaygroup WHERE DisplayGroup = :displaygroup AND IsDisplaySpecific = 0 AND DisplayGroupID <> :displaygroupid');
            $sth->execute(array(
                    'displaygroup' => $displayGroup,
                    'displaygroupid' => $displayGroupID
                ));

            if ($row = $sth->fetch())
                $this->ThrowError(25004, sprintf(__('You already own a display group called "%s". Please choose another name.'), $displayGroup));
			
			// End Validation
			 
			// Update the DisplayGroup
			$sth = $dbh->prepare('UPDATE displaygroup SET DisplayGroup = :displaygroup, Description = :description WHERE DisplayGroupID = :displaygroupid');
            $sth->execute(array(
                    'displaygroup' => $displayGroup,
                    'description' => $description,
                    'displaygroupid' => $displayGroupID
                ));
			
			Debug::LogEntry('audit', 'OUT', 'DisplayGroup', 'Edit');		
						
			return true;
		}
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25000, __('Could not add Display Group'));

            return false;
        }
	}
	
	/**
	 * Deletes an Xibo Display Group
	 * @return 
	 * @param $displayGroupID Object
	 */
	public function Delete($displayGroupID)
	{
		Debug::LogEntry('audit', 'IN', 'DisplayGroup', 'Delete');

        try {
            $dbh = PDOConnect::init();

            // Tidy up the schedule detail records.
            $schedule = new Schedule($this->db);
	
            if (!$schedule->DeleteScheduleForDisplayGroup($displayGroupID))
                throw new Exception('Unable to DeleteScheduleForDisplayGroup');

            // Delete the Display Group
           	$sth = $dbh->prepare('DELETE FROM displaygroup WHERE DisplayGroupID = :displaygroupid');
            $sth->execute(array(
                    'displaygroupid' => $displayGroupID
                ));

			Debug::LogEntry('audit', 'OUT', 'DisplayGroup', 'Delete');
						
			return true;
		}
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25015,__('Unable to delete Display Group.'));

            return false;
        }
	}
	
	/**
	 * Deletes all Display Group records associated with a display.
	 * @return 
	 * @param $displayID Object
	 */
	public function DeleteDisplay($displayID)
	{
		try {
            $dbh = PDOConnect::init();
		
			// Get the DisplaySpecific Group for this Display
			$SQL  = "";
			$SQL .= "SELECT displaygroup.DisplayGroupID ";
			$SQL .= "FROM   displaygroup ";
			$SQL .= "       INNER JOIN lkdisplaydg ";
			$SQL .= "       ON     lkdisplaydg.DisplayGroupID = displaygroup.DisplayGroupID ";
			$SQL .= "WHERE  displaygroup.IsDisplaySpecific = 1 ";
			$SQL .= "	AND lkdisplaydg.DisplayID = :displayid";

			$sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'displayid' => $displayID
                ));

            if (!$row = $sth->fetch())
                $this->ThrowError(25005, __('Unable to get the DisplayGroup for this Display'));
			
			// Get the Display Group ID
			$displayGroupID	= Kit::ValidateParam($row['DisplayGroupID'], _INT);
		
			// If there is no region specific display record... what do we do?
            if ($displayGroupID == 0)
            	$this->ThrowError(25005, __('Unable to get the DisplayGroup for this Display'));
		
            // Delete the Schedule for this Display Group
            $scheduleObject = new Schedule($this->db);

            if (!$scheduleObject->DeleteScheduleForDisplayGroup($displayGroupID))
            	$this->ThrowError(25006, __('Unable to delete Schedule records for this DisplayGroup.'));

            // Unlink all Display Groups from this Display
            $sth = $dbh->prepare('DELETE FROM lkdisplaydg WHERE DisplayID = :displayid');
            $sth->execute(array(
                    'displayid' => $displayID
                ));

            // Delete this display groups link to any groups
            $sth = $dbh->prepare('DELETE FROM lkdisplaygroupgroup WHERE DisplayGroupId = :displaygroupid');
            $sth->execute(array(
                    'displaygroupid' => $displayGroupID
                ));
            
            // Delete the Display Group Itself
            if (!$this->Delete($displayGroupID))
                // An error will already be set - so just drop out
                throw new Exception('Unable to delete');

			return true;
		}
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25015,__('Unable to delete Display Group.'));

            return false;
        }
	}
	
	/**
	 * Links a Display to a Display Group
	 * @return 
	 * @param $displayGroupID Object
	 * @param $displayID Object
	 */
	public function Link($displayGroupID, $displayID)
	{
		Debug::LogEntry('audit', 'IN', 'DisplayGroup', 'Link');
		
		try {
            $dbh = PDOConnect::init();
		
			$sth = $dbh->prepare('INSERT INTO lkdisplaydg (DisplayGroupID, DisplayID) VALUES (:displaygroupid, :displayid)');
            $sth->execute(array(
                    'displaygroupid' => $displayGroupID,
                    'displayid' => $displayID
                ));

			Debug::LogEntry('audit', 'OUT', 'DisplayGroup', 'Link');
		
			return true;
		}
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25005, __('Could not Link Display Group to Display'));
        }
	}
	
	/**
	 * Unlinks a Display from a Display Group
	 * @return 
	 * @param $displayGroupID Object
	 * @param $displayID Object
	 */
	public function Unlink($displayGroupID, $displayID)
	{
		Debug::LogEntry('audit', 'IN', 'DisplayGroup', 'Unlink');
		
		try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('DELETE FROM lkdisplaydg WHERE DisplayGroupID = :displaygroupid AND DisplayID = :displayid');
            $sth->execute(array(
                    'displaygroupid' => $displayGroupID,
                    'displayid' => $displayID
                ));

			Debug::LogEntry('audit', 'OUT', 'DisplayGroup', 'Unlink');
		
			return true;
		}
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25007, __('Could not Unlink Display Group from Display'));
        }
	}
	
	/**
	 * Edits the Display Group associated with a Display
	 * @return 
	 * @param $displayID Object
	 * @param $display Object
	 */
	public function EditDisplayGroup($displayID, $display)
	{
		Debug::LogEntry('audit', 'IN', 'DisplayGroup', 'EditDisplayGroup');
		
		try {
            $dbh = PDOConnect::init();

			// Get the DisplayGroupID for this DisplayID
			$SQL  = "";
			$SQL .= "SELECT displaygroup.DisplayGroupID ";
			$SQL .= "FROM   displaygroup ";
			$SQL .= "       INNER JOIN lkdisplaydg ";
			$SQL .= "       ON     lkdisplaydg.DisplayGroupID = displaygroup.DisplayGroupID ";
			$SQL .= "WHERE  displaygroup.IsDisplaySpecific = 1 ";
			$SQL .= "   AND lkdisplaydg.DisplayID = :displayid";
			
			$sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'displayid' => $displayID
                ));

            if (!$row = $sth->fetch())
                $this->ThrowError(25005, __('Unable to get the DisplayGroup for this Display'));
			
			// Get the Display Group ID
			$displayGroupID	= Kit::ValidateParam($row['DisplayGroupID'], _INT);
		
			// If there is no region specific display record... what do we do?
            if ($displayGroupID == 0) {
				// We should always have 1 display specific DisplayGroup for a display.
				// Do we a) Error here and give up?
				//		 b) Create one and link it up?
				// $this->SetError(25006, __('Unable to get the DisplayGroup for this Display'));
				
				if (!$displayGroupID = $this->Add($display, 1))
					$this->ThrowError(25001, __('Could not add a display group for the new display.'));
				
				// Link the Two together
				if (!$this->Link($displayGroupID, $displayID))
					$this->ThrowError(25001, __('Could not link the new display with its group.'));
			}
			
			// Update the Display group name
			$sth = $dbh->prepare('UPDATE displaygroup SET DisplayGroup = :displaygroup WHERE  DisplayGroupID = :displaygroupid');
            $sth->execute(array(
                    'displaygroupid' => $displayGroupID,
                    'displaygroup' => $display
                ));
			
			Debug::LogEntry('audit', 'OUT', 'DisplayGroup', 'EditDisplayGroup');
			
			return true;
		}
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25005, __('Unable to update the DisplayGroup for this Display'));

            return false;
        }
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

        Debug::LogEntry('audit', 'Depricated method called.', 'DisplayGroup', 'SetDefaultLayout');
        return true;
    }
}
?>