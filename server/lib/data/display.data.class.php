<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2012 Daniel Garner
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
	public function Edit($displayID, $display, $isAuditing, $defaultLayoutID, $licensed, $incSchedule, $email_alert, $alert_timeout, $wakeOnLanEnabled, $wakeOnLanTime)
	{
		$db	=& $this->db;
		
		Debug::LogEntry($db, 'audit', 'IN', 'Display', 'Edit');

                // Check the number of licensed displays
                $maxDisplays = Config::GetSetting($db, 'MAX_LICENSED_DISPLAYS');

                if ($maxDisplays > 0)
                {
                    // See if this is a license switch
                    $currentLicense = $db->GetSingleValue(sprintf('SELECT licensed FROM display WHERE DisplayID = %d', $displayID), 'licensed', _INT);

                    if ($currentLicense != $licensed && $licensed == 1)
                    {
                        // License change - test number of licensed displays.
                        $licensedDisplays = $db->GetSingleValue('SELECT COUNT(DisplayID) AS CountLicensed FROM display WHERE licensed =1 ', 'CountLicensed', _INT);

                        if ($licensedDisplays + 1 > $maxDisplays)
                            return $this->SetError(25000, sprintf(__('You have exceeded your maximum number of licensed displays. %d'), $maxDisplays));
                    }
                }

		// Update the display record
		$SQL  = "UPDATE display SET display = '%s', ";
		$SQL .= "		defaultlayoutid = %d, ";
		$SQL .= "		inc_schedule = %d, ";
		$SQL .= " 		licensed = %d, ";
		$SQL .= "		isAuditing = %d, ";
                $SQL .= "       email_alert = %d, ";
                $SQL .= "       alert_timeout = %d, ";
                $SQL .= "       WakeOnLan = %d, ";
                $SQL .= "       WakeOnLanTime = '%s' ";
		$SQL .= "WHERE displayid = %d ";
		
		$SQL = sprintf($SQL, $db->escape_string($display), $defaultLayoutID, $incSchedule, $licensed, $isAuditing, $email_alert, $alert_timeout, $wakeOnLanEnabled, $wakeOnLanTime, $displayID);
		
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
	public function Touch($license, $clientAddress = '', $mediaInventoryComplete = 0, $mediaInventoryXml = '', $macAddress = '')
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

                // Media Inventory Settings (if appropriate)
                if ($mediaInventoryComplete != 0)
                    $SQL .= sprintf(" , MediaInventoryStatus = %d ", $mediaInventoryComplete);

                if ($mediaInventoryXml != '')
                    $SQL .= sprintf(" , MediaInventoryXml = '%s' ", $mediaInventoryXml);

                // Mac address storage
                if ($macAddress != '')
                {
                    // Address changed.
                    $currentAddress = $db->GetSingleValue(sprintf("SELECT MacAddress FROM display WHERE license = '%s'", $license), 'MacAddress', _STRING);

                    if ($macAddress != $currentAddress)
                    {
                        $SQL .= sprintf(" , MacAddress = '%s', LastChanged = %d, NumberOfMacAddressChanges = NumberOfMacAddressChanges + 1 ", $macAddress, time());
                    }
                }

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

    /**
     * Flags a display as being incomplete
     * @param <type> $displayId
     */
    private function FlagIncomplete($displayId)
    {
        $db =& $this->db;

        Debug::LogEntry($db, 'audit', sprintf('Flag DisplayID %d incomplete.', $displayId), 'display', 'NotifyDisplays');

        $SQL = sprintf("UPDATE display SET MediaInventoryStatus = 3 WHERE displayID = %d", $displayId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(25004, 'Unable to Flag Display as incomplete');
        }

        return true;
    }

    /**
     * Notify displays of this layout change
     * @param <type> $layoutId
     */
    public function NotifyDisplays($layoutId)
    {
        $db =& $this->db;
        $currentdate 	= time();
        $rfLookahead    = Kit::ValidateParam(Config::GetSetting($db,'REQUIRED_FILES_LOOKAHEAD'), _INT);

        $rfLookahead 	= $currentdate + $rfLookahead;

        Debug::LogEntry($db, 'audit', sprintf('Checking for Displays to refresh on Layout %d', $layoutId), 'display', 'NotifyDisplays');

        // Which displays does a change to this layout effect?
        $SQL  = " SELECT DISTINCT display.DisplayID ";
        $SQL .= "   FROM schedule_detail ";
        $SQL .= " 	INNER JOIN lkdisplaydg ";
        $SQL .= "	ON lkdisplaydg.DisplayGroupID = schedule_detail.DisplayGroupID ";
        $SQL .= " 	INNER JOIN display ";
        $SQL .= "	ON lkdisplaydg.DisplayID = display.displayID ";
        $SQL .= " WHERE schedule_detail.layoutID = %d ";
        $SQL .= " AND schedule_detail.FromDT < %d AND schedule_detail.ToDT > %d ";
        $SQL .= " UNION ";
        $SQL .= " SELECT DisplayID FROM display WHERE DefaultLayoutID = %d";

        $SQL = sprintf($SQL, $layoutId, $rfLookahead, $currentdate - 3600, $layoutId);

        Debug::LogEntry($db, 'audit', $SQL, 'display', 'NotifyDisplays');

        if (!$result = $db->query($SQL))
        {
            trigger_error($db->error());
            return $this->SetError(25037, __('Unable to get layouts for Notify'));
        }

        while ($row = $db->get_assoc_row($result))
        {
            // Notify each display in turn
            $displayId = Kit::ValidateParam($row['DisplayID'], _INT);
            $this->FlagIncomplete($displayId);
        }
    }

        /**
     * Edits the default layout for a display
     * @param <type> $displayId
     * @param <type> $defaultLayoutId
     * @return <type>
     */
    public function EditDefaultLayout($displayId, $defaultLayoutId)
    {
        $db	=& $this->db;

        Debug::LogEntry($db, 'audit', 'IN', 'Display', 'EditDefaultLayout');

        $SQL = sprintf('UPDATE display SET defaultLayoutId = %d WHERE displayID = %d ', $defaultLayoutId, $displayId);

        if (!$db->query($SQL))
        {
            trigger_error($db->error());
            $this->SetError(25012, __('Error updating this displays default layout.'));

            return false;
        }

        // Flag this display as not having all the content
        $this->FlagIncomplete($displayId);

        Debug::LogEntry($db, 'audit', 'OUT', 'Display', 'EditDefaultLayout');

        return true;
    }

    /**
     * Wake this display using a WOL command
     * @param <int> $displayId
     * @return <bool>
     */
    public function WakeOnLan($displayId)
    {
        $db =& $this->db;

        // Get the Client Address and the Mac Address
        if (!$row = $db->GetSingleRow(sprintf("SELECT MacAddress, ClientAddress FROM `display` WHERE DisplayID = %d", $displayId)))
            $this->SetError(25013, __('Unable to get the Mac or Client Address'));

        // Check they are populated
        if ($row['MacAddress'] == '' || $row['ClientAddress'] == '')
            $this->SetError(25014, __('This display has no mac address recorded against it yet. Make sure the display is running.'));

        // Wake on Lan command via a socket
        $socketNumber = "7";

        Debug::LogEntry($db, 'audit', 'About to send WOL packet to ' . $row['ClientAddress'] . ' with Mac Address ' . $row['MacAddress'], 'display', 'WakeOnLan');

        if (!$this->AllanBarizoWol($row['ClientAddress'], $row['MacAddress'], $socketNumber))
            $this->SetError(25015, __('Unable to generate the WOL Command'));

        // If we succeeded then update this display with the last WOL time
        $db->query(sprintf("UPDATE `display` SET LastWakeOnLanCommandSent = %d", time()));

        return true;
    }

    # Wake on LAN - (c) HotKey@spr.at, upgraded by Murzik
    # Modified by Allan Barizo http://www.hackernotcracker.com
    private function AllanBarizoWol($addr, $mac, $socket_number)
    {
        $addr_byte = explode(':', $mac);
        $hw_addr = '';

        for ($a=0; $a <6; $a++) $hw_addr .= chr(hexdec($addr_byte[$a]));
        $msg = chr(255).chr(255).chr(255).chr(255).chr(255).chr(255);

        for ($a = 1; $a <= 16; $a++) $msg .= $hw_addr;

        // send it to the broadcast address using UDP
        // SQL_BROADCAST option isn't help!!
        $s = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if ($s == false)
        {
            return $this->SetError(25000, 'Error Creating the Socket. Error code is: ' . socket_last_error($s) . ' - ' . socket_strerror(socket_last_error($s)));
        }
        else
        {
            // setting a broadcast option to socket:
            $opt_ret = socket_set_option($s, 1, 6, TRUE);

            if($opt_ret <0)
            {
                return $this->SetError(25000, 'setsockopt() failed, error: ' . strerror($opt_ret));
            }

            if(socket_sendto($s, $msg, strlen($msg), 0, $addr, $socket_number))
            {
                socket_close($s);
                return TRUE;
            }
            else
            {
                return $this->SetError(25000, 'Send Failed');
            }
        }
    }
}
?>
