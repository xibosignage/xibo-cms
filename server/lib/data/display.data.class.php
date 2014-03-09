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
        Debug::LogEntry('audit', 'IN', 'Display', 'Add');
        
        try {
            $dbh = PDOConnect::init();

            // Create the SQL
            $SQL  = "";
            $SQL .= "INSERT INTO display (display, isAuditing, defaultlayoutid, license, licensed, inc_schedule, email_alert, alert_timeout) ";
            $SQL .= " VALUES (:display, :isauditing, :defaultlayoutid, :license, :licensed, :inc_schedule, :email_alert, :alert_timeout) ";
            
            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'display' => $display,
                    'isauditing' => 0,
                    'defaultlayoutid' => 1,
                    'license' => $license,
                    'licensed' => 0,
                    'inc_schedule' => 0,
                    'email_alert' => 0,
                    'alert_timeout' => 0
                ));
		
            // Get the ID of the inserted record
            $displayId = $dbh->lastInsertId();

            // Also want to add the DisplayGroup associated with this Display.
            $displayGroupObject = new DisplayGroup($this->db);

            if (!$displayGroupId = $displayGroupObject->Add($display, 1, ''))
                $this->ThrowError(25001, __('Could not add a display group for the new display.'));

            // Link the Two together
            if (!$displayGroupObject->Link($displayGroupId, $displayId))
                $this->ThrowError(25001, __('Could not link the new display with its group.'));
            
            Debug::LogEntry('audit', 'OUT', 'Display', 'Add');

            return $displayId;
        }
        catch (Exception $e) {

            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25000, __('Could not add display'));

            return false;
        }
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
	public function Edit($displayID, $display, $isAuditing, $defaultLayoutID, $licensed, $incSchedule, $email_alert, $alert_timeout, $wakeOnLanEnabled, $wakeOnLanTime, $broadCastAddress, $secureOn, $cidr, $latitude, $longitude)
    {
		Debug::LogEntry('audit', 'IN', 'Display', 'Edit');
        
        try {
            $dbh = PDOConnect::init();
        
            // Check the number of licensed displays
            $maxDisplays = Config::GetSetting('MAX_LICENSED_DISPLAYS');

            if ($maxDisplays > 0)
            {
                // See if this is a license switch
                $sth = $dbh->prepare('SELECT licensed FROM display WHERE DisplayID = :displayid');
                $sth->execute(array(
                        'displayid' => $displayID
                    ));

                if (!$row = $sth->fetch())
                    $this->ThrowError(25004, __('Cannot find display record'));

                // Has the licence flag toggled?
                if (Kit::ValidateParam($row['licensed'], _INT) != $licensed && $licensed == 1)
                {
                    // License change - test number of licensed displays.
                    $sth = $dbh->prepare('SELECT COUNT(DisplayID) AS CountLicensed FROM display WHERE licensed = 1');
                    $sth->execute();

                    if (!$row = $sth->fetch())
                        $this->ThrowError(1, __('Unable to get count of licensed displays.'));

                    $count = Kit::ValidateParam($row['CountLicensed'], _INT);

                    $sth->closeCursor();

                    if ($count + 1 > $maxDisplays)
                        $this->ThrowError(25000, sprintf(__('You have exceeded your maximum number of licensed displays. %d'), $maxDisplays));
                }
            }

            // Validate some parameters

            // Fill $addr with client's IP address, if $addr is empty
            if ($broadCastAddress != '')
            {
                // Resolve broadcast address
                // same as (but easier than):  preg_match("/\b(([01]?\d?\d|2[0-4]\d|25[0-5])\.){3}([01]?\d?\d|2[0-4]\d|25[0-5])\b/",$addr)
                if (!filter_var($broadCastAddress, FILTER_VALIDATE_IP))
                    $this->ThrowError(25015, __('BroadCast Address is not a valid IP Address'));
            }

            // Check whether $cidr is valid
            if ($cidr != '')
            {
                if ((!is_numeric($cidr)) || ($cidr < 0) || ($cidr > 32))
                    $this->ThrowError(25015, __('CIDR subnet mask is not a number within the range of 0 to 32.'));
            }

            // Check whether $secureOn is valid
            if ($secureOn != '')
            {
                $secureOn = strtoupper($secureOn);
                $secureOn = str_replace(":", "-", $secureOn);

                if ((!preg_match("/([A-F0-9]{2}[-]){5}([0-9A-F]){2}/", $secureOn)) || (strlen($secureOn) != 17))
                    $this->ThrowError(25015, __('Pattern of secureOn-password is not "xx-xx-xx-xx-xx-xx" (x = digit or CAPITAL letter)'));
            }

            Debug::LogEntry('audit', 'Validation Complete and Passed', 'Display', 'Edit');

    		// Update the display record
    		$SQL  = "UPDATE display SET display = :display, ";
    		$SQL .= "		defaultlayoutid = :defaultlayoutid, ";
    		$SQL .= "		inc_schedule = :incschedule, ";
    		$SQL .= " 		licensed = :licensed, ";
    		$SQL .= "		isAuditing = :isauditing, ";
            $SQL .= "       email_alert = :emailalert, ";
            $SQL .= "       alert_timeout = :alerttimeout, ";
            $SQL .= "       WakeOnLan = :wakeonlan, ";
            $SQL .= "       WakeOnLanTime = :wakeonlantime, ";
            $SQL .= "       BroadCastAddress = :broadcastaddress, ";
            $SQL .= "       SecureOn = :secureon, ";
            $SQL .= "       Cidr = :cidr, ";
            $SQL .= "       GeoLocation = POINT(:latitude, :longitude) ";
    		$SQL .= " WHERE displayid = :displayid ";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'display' => $display,
                    'defaultlayoutid' => $defaultLayoutID,
                    'incschedule' => $incSchedule,
                    'licensed' => $licensed,
                    'isauditing' => $isAuditing,
                    'emailalert' => $email_alert,
                    'alerttimeout' => $alert_timeout,
                    'wakeonlan' => $wakeOnLanEnabled,
                    'wakeonlantime' => $wakeOnLanTime,
                    'broadcastaddress' => $broadCastAddress,
                    'secureon' => $secureOn,
                    'cidr' => $cidr,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'displayid' => $displayID
                ));

            Debug::LogEntry('audit', 'Display Edited', 'Display', 'Edit');
        
    		// Use a DisplayGroup to handle the default layout and displaygroup name for this display
    		$displayGroupObject = new DisplayGroup($this->db);
    		
    		// Do we also want to update the linked Display Groups name (seeing as that is what we will be presenting to everyone)
    		if (!$displayGroupObject->EditDisplayGroup($displayID, $display)) {
                $this->ThrowError(25002, __('Could not update this display with a new name.'));
            }

    		Debug::LogEntry('audit', 'OUT', 'DisplayGroup', 'Edit');
    		
    		return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25000, __('Could not update display'));

            return false;
        }
	}
	
	/**
	 * Deletes a Display
	 * @return 
	 * @param $displayID Object
	 */
	public function Delete($displayID)
	{
        Debug::LogEntry('audit', 'IN', 'Display', 'Delete');
        
        try {
            $dbh = PDOConnect::init();

            // Pass over to the DisplayGroup data class so that it can try and delete the
            // display specific group first (it is that group which is linked to schedules)
            $displayGroupObject = new DisplayGroup($this->db);

            // Do we also want to update the linked Display Groups name (seeing as that is what we will be presenting to everyone)
            if (!$displayGroupObject->DeleteDisplay($displayID))
                $this->ThrowError($displayGroupObject->GetErrorMessage(), $displayGroupObject->GetErrorMessage());

            // Delete the blacklist
            $sth = $dbh->prepare('DELETE FROM blacklist WHERE DisplayID = :displayid');
            $sth->execute(array(
                    'displayid' => $displayID
                ));

            // Now we know the Display Group is gone - and so are any links
            // delete the display
            $sth = $dbh->prepare('DELETE FROM display WHERE DisplayID = :displayid');
            $sth->execute(array(
                    'displayid' => $displayID
                ));

            Debug::LogEntry('audit', 'OUT', 'Display', 'Delete');

            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25015,__('Unable to delete display record.'));

            return false;
        }
	}
	
	/**
	 * Edits a Displays Name
	 * @return 
	 * @param $license Object
	 * @param $display Object
	 */
	public function EditDisplayName($license, $display) 
	{
		Debug::LogEntry('audit', 'IN', 'DisplayGroup', 'EditDisplayName');

        try {
            $dbh = PDOConnect::init();

            // Update the display with its new name (using the licence as the key)
            $sth = $dbh->prepare('UPDATE display SET display = :display WHERE license = :license');
            $sth->execute(array(
                    'display' => $display,
                    'license' => $license
                ));
			
    		// Also need to update the display group name here.
    		$displayGroupObject = new DisplayGroup($this->db);
    		
    		// Do we also want to update the linked Display Groups name (seeing as that is what we will be presenting to everyone)
    		if (!$displayGroupObject->EditDisplayGroup($displayID, $display))
    			$this->ThrowError(25015, __('Could not update this display with a new name.'));
    		
    		Debug::LogEntry('audit', 'OUT', 'DisplayGroup', 'EditDisplayName');
    		
    		return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25015,__('Unable to edit display record.'));

            return false;
        }
	}
	
	/**
     * Sets the information required on the display to indicate that it is still logged in
     * @param string  $license                The display licence key
     * @param string  $clientAddress          The client IP address
     * @param integer $mediaInventoryComplete The Media Inventory Status
     * @param string  $mediaInventoryXml      The Media Inventory XML
     * @param string  $macAddress             The Client Mac Address
     * @param string  $clientType             The Client Type
     * @param string  $clientVersion          The Client Version
     * @param integer $clientCode             The Client Version Code
     */
	public function Touch($license, $clientAddress = '', $mediaInventoryComplete = 0, $mediaInventoryXml = '', $macAddress = '', $clientType = '', $clientVersion = '', $clientCode = 0)
	{
		Debug::LogEntry('audit', 'IN', 'DisplayGroup', 'Touch');
        
        try {
            $dbh = PDOConnect::init();

            // Set the last accessed flag on the display
            $SQL  = "";
            $SQL .= "UPDATE display SET lastaccessed = :lastaccessed, loggedin = :loggedin ";

            $params = array();
            $params['lastaccessed'] = time();
            $params['loggedin'] = 1;
            $params['license'] = $license;

            // We will want to update the client Address if it is given
            if ($clientAddress != '') {
                $SQL .= " , ClientAddress = :clientaddress ";
                $params['clientaddress'] = $clientAddress;
            }

            // Media Inventory Settings (if appropriate)
            if ($mediaInventoryComplete != 0) {
                $SQL .= " , MediaInventoryStatus = :mediainventorystatus ";
                $params['mediainventorystatus'] = $mediaInventoryComplete;
            }

            if ($mediaInventoryXml != '') {
                $SQL .= " , MediaInventoryXml = :mediainventoryxml ";
                $params['mediainventoryxml'] = $mediaInventoryXml;
            }

            // Client information if present
            if ($clientType != '') {
                $SQL .= " , client_type = :client_type ";
                $params['client_type'] = $clientType;
            }

            if ($clientVersion != '') {
                $SQL .= " , client_version = :client_version ";
                $params['client_version'] = $clientVersion;
            }

            if ($clientCode != '') {
                $SQL .= " , client_code = :client_code ";
                $params['client_code'] = $clientCode;
            }

            // Mac address storage
            if ($macAddress != '')
            {
                // Address changed.
                $sth = $dbh->prepare('SELECT MacAddress FROM display WHERE license = :license');
                $sth->execute(array(
                        'license' => $license
                    ));

                if (!$row = $sth->fetch())
                    $currentAddress = '';
                else
                    $currentAddress = $row['MacAddress'];

                if ($macAddress != $currentAddress)
                {
                    $SQL .= " , MacAddress = :macaddress, LastChanged = :lastchanged, NumberOfMacAddressChanges = NumberOfMacAddressChanges + 1 ";
                    $params['macaddress'] = $macAddress;
                    $params['lastchanged'] = time();
                }
            }

            // Restrict to the display license
            $SQL .= " WHERE license = :license";

            // Update the display with its new name (using the license as the key)
            $sth = $dbh->prepare($SQL);
            $sth->execute($params);
    		
    		Debug::LogEntry('audit', 'OUT', 'DisplayGroup', 'Touch');
    		
    		return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25002, __("Error updating this displays last accessed information."));
        }
	}

    /**
     * Flags a display as being incomplete
     * @param <type> $displayId
     */
    public function FlagIncomplete($displayId)
    {
        Debug::LogEntry('audit', sprintf('Flag DisplayID %d incomplete.', $displayId), 'display', 'NotifyDisplays');

        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('UPDATE display SET MediaInventoryStatus = 3 WHERE displayID = :displayid');
            $sth->execute(array(
                    'displayid' => $displayId
                ));

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25004, 'Unable to Flag Display as incomplete');
        }
    }

    /**
     * Notify displays of this campaign change
     * @param <type> $layoutId
     */
    public function NotifyDisplays($campaignId)
    {
        Debug::LogEntry('audit', sprintf('Checking for Displays to refresh on Layout %d', $campaignId), 'display', 'NotifyDisplays');

        try {
            $dbh = PDOConnect::init();

            $currentdate = time();
            $rfLookahead = Kit::ValidateParam(Config::GetSetting('REQUIRED_FILES_LOOKAHEAD'), _INT);
            $rfLookahead = $currentdate + $rfLookahead;

            // Which displays does a change to this layout effect?
            $SQL  = " SELECT DISTINCT display.DisplayID ";
            $SQL .= "   FROM schedule_detail ";
            $SQL .= " 	INNER JOIN lkdisplaydg ";
            $SQL .= "	ON lkdisplaydg.DisplayGroupID = schedule_detail.DisplayGroupID ";
            $SQL .= " 	INNER JOIN display ";
            $SQL .= "	ON lkdisplaydg.DisplayID = display.displayID ";
            $SQL .= " WHERE schedule_detail.CampaignID = :campaignid ";
            $SQL .= " AND schedule_detail.FromDT < :fromdt AND schedule_detail.ToDT > :todt ";
            $SQL .= " UNION ";
            $SQL .= " SELECT DISTINCT display.DisplayID ";
            $SQL .= "   FROM display ";
            $SQL .= "       INNER JOIN lkcampaignlayout ";
            $SQL .= "       ON lkcampaignlayout.LayoutID = display.DefaultLayoutID ";
            $SQL .= " WHERE lkcampaignlayout.CampaignID = :campaignid";

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'campaignid' => $campaignId,
                    'fromdt' => $rfLookahead,
                    'todt' => $currentdate - 3600
                ));

            while ($row = $sth->fetch()) {
                // Notify each display in turn
                $displayId = Kit::ValidateParam($row['DisplayID'], _INT);
                $this->FlagIncomplete($displayId);
            }
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25004, 'Unable to Flag Display as incomplete');

            return false;
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
        Debug::LogEntry('audit', 'IN', 'Display', 'EditDefaultLayout');

        try {
            $dbh = PDOConnect::init();

            $sth = $dbh->prepare('UPDATE display SET defaultLayoutId = :defaultlayoutid WHERE displayID = :displayid');
            $sth->execute(array(
                    'defaultlayoutid' => $defaultLayoutId,
                    'displayid' => $displayId
                ));
            
            // Flag this display as not having all the content
            $this->FlagIncomplete($displayId);

            Debug::LogEntry('audit', 'OUT', 'Display', 'EditDefaultLayout');

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25012, __('Error updating this displays default layout.'));
        }
    }

    public function SetVersionInstructions($displayId, $mediaId, $storedAs) {
        Debug::LogEntry('audit', 'IN', get_class(), __FUNCTION__);

        try {
            $dbh = PDOConnect::init();

            // Set the instructions
            $version_instructions = array();
            $version_instructions['id'] = $mediaId;
            $version_instructions['file'] = $storedAs;
        
            $sth = $dbh->prepare('UPDATE `display` SET version_instructions = :version_instructions WHERE displayid = :displayid');
            $sth->execute(array(
                    'displayid' => $displayId,
                    'version_instructions' => json_encode($version_instructions)
                ));

            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            if (!$this->IsError())
                $this->SetError(1, __('Unknown Error'));
        
            return false;
        }

    }

    /**
     * Wake this display using a WOL command
     * @param <int> $displayId
     * @return <bool>
     */
    public function WakeOnLan($displayId)
    {
        Debug::LogEntry('audit', 'IN', 'Display', 'WakeOnLan');

        try {
            $dbh = PDOConnect::init();

            // Get the Client Address and the Mac Address
            $sth = $dbh->prepare('SELECT MacAddress, BroadCastAddress, SecureOn, Cidr FROM `display` WHERE DisplayID = :displayid');
            $sth->execute(array(
                    'displayid' => $displayId
                ));

            if (!$row = $sth->fetch())
                $this->ThrowError(25013, __('Unable to get the Mac or Client Address'));

            // Check they are populated
            if ($row['MacAddress'] == '' || $row['BroadCastAddress'] == '')
                $this->SetError(25014, __('This display has no mac address recorded against it yet. Make sure the display is running.'));

            Debug::LogEntry('audit', 'About to send WOL packet to ' . $row['BroadCastAddress'] . ' with Mac Address ' . $row['MacAddress'], 'display', 'WakeOnLan');

            if (!$this->TransmitWakeOnLan($row['MacAddress'], $row['SecureOn'], $row['BroadCastAddress'], $row['Cidr'], "9"))
                throw new Exception('Error in TransmitWakeOnLan');

            // If we succeeded then update this display with the last WOL time
            $sth = $dbh->prepare('UPDATE `display` SET LastWakeOnLanCommandSent = :lastaccessed WHERE DisplayID = :displayid');
            $sth->execute(array(
                    'displayid' => $displayId,
                    'lastaccessed' => time()
                ));

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());

            if (!$this->IsError())
                $this->SetError(25012, __('Unknown Error.'));

            return false;
        }
    }

    /**
     * Wake On Lan Script
     *  // Version: 2
     *  // Author of this application:
     *  //	DS508_customer (http://www.synology.com/enu/forum/memberlist.php?mode=viewprofile&u=12636)
     *  //	Please inform the author of any suggestions on (the functionality, graphical design, ... of) this application.
     *  //	More info: http://wolviaphp.sourceforge.net
     *  // License: GPLv2.0
     *
     * Modified for use with the Xibo project by Dan Garner.
     */
    function TransmitWakeOnLan($mac_address, $secureon, $addr, $cidr, $port)
    {
        // Prepare magic packet: part 1/3 (defined constant)
        $buf = "";
        
        // the defined constant as represented in hexadecimal: FF FF FF FF FF FF (i.e., 6 bytes of hexadecimal FF)
        for ($a=0; $a<6; $a++) $buf .= chr(255); 
        
        // Check whether $mac_address is valid
        $mac_address = strtoupper($mac_address);
        $mac_address = str_replace(":", "-", $mac_address);
        
        if ((!preg_match("/([A-F0-9]{2}[-]){5}([0-9A-F]){2}/",$mac_address)) || (strlen($mac_address) != 17))
        {
            return $this->SetError(25015, __('Pattern of MAC-address is not "xx-xx-xx-xx-xx-xx" (x = digit or letter)'));
        }
        else
        {
            // Prepare magic packet: part 2/3 (16 times MAC-address)
            // Split MAC-address into an array of (six) bytes
            $addr_byte = explode('-', $mac_address); 
            $hw_addr = "";
            
            // Convert MAC-address from bytes to hexadecimal to decimal
            for ($a=0; $a<6; $a++) $hw_addr .= chr(hexdec($addr_byte[$a])); 
            
            $hw_addr_string = "";
            
            for ($a=0; $a<16; $a++) $hw_addr_string .= $hw_addr;
            $buf .= $hw_addr_string;
        }
        
        if ($secureon != "")
        {
            // Check whether $secureon is valid
            $secureon = strtoupper($secureon);
            $secureon = str_replace(":", "-", $secureon);
            
            if ((!preg_match("/([A-F0-9]{2}[-]){5}([0-9A-F]){2}/", $secureon)) || (strlen($secureon) != 17))
            {
                return $this->SetError(25015, __('Pattern of SecureOn-password is not "xx-xx-xx-xx-xx-xx" (x = digit or CAPITAL letter)'));
            }
            else
            {
                // Prepare magic packet: part 3/3 (Secureon password)
                // Split MAC-address into an array of (six) bytes
                $addr_byte = explode('-', $secureon); 
                $hw_addr = "";
                
                // Convert MAC address from hexadecimal to decimal
                for ($a=0; $a<6; $a++) $hw_addr .= chr(hexdec($addr_byte[$a])); 
                $buf .= $hw_addr;
            }
        }
        
        // Fill $addr with client's IP address, if $addr is empty
        if ($addr == "")
            return $this->SetError(25000, __('No IP Address Specified'));
        
        // Resolve broadcast address
        if (filter_var ($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) // same as (but easier than):  preg_match("/\b(([01]?\d?\d|2[0-4]\d|25[0-5])\.){3}([01]?\d?\d|2[0-4]\d|25[0-5])\b/",$addr)
        {
            // $addr has an IP-adres format
        }
        else
        {
            return $this->SetError(25000, __('IP Address Incorrectly Formed'));
        }
        
        // If $cidr is set, replace $addr for its broadcast address
        if ($cidr != "")
        {
            // Check whether $cidr is valid
            if ((!ctype_digit($cidr)) || ($cidr < 0) || ($cidr > 32))
            {
                return $this->SetError(25015, __('CIDR subnet mask is not a number within the range of 0 till 32.'));
            }
            
            // Convert $cidr from one decimal to one inverted binary array
            $inverted_binary_cidr = "";
            
            // Build $inverted_binary_cidr by $cidr * zeros (this is the mask)
            for ($a=0; $a<$cidr; $a++) $inverted_binary_cidr .= "0"; 
            
            // Invert the mask (by postfixing ones to $inverted_binary_cidr untill 32 bits are filled/ complete)
            $inverted_binary_cidr = $inverted_binary_cidr.substr("11111111111111111111111111111111", 0, 32 - strlen($inverted_binary_cidr));
            
            // Convert $inverted_binary_cidr to an array of bits
            $inverted_binary_cidr_array = str_split($inverted_binary_cidr); 
            
            // Convert IP address from four decimals to one binary array
            // Split IP address into an array of (four) decimals
            $addr_byte = explode('.', $addr);
            $binary_addr = "";
            
            for ($a=0; $a<4; $a++)
            {
                // Prefix zeros
                $pre = substr("00000000",0,8-strlen(decbin($addr_byte[$a])));
                
                // Postfix binary decimal
                $post = decbin($addr_byte[$a]); 
                $binary_addr .= $pre.$post;
            }
            
            // Convert $binary_addr to an array of bits
            $binary_addr_array = str_split($binary_addr); 
                    
            // Perform a bitwise OR operation on arrays ($binary_addr_array & $inverted_binary_cidr_array)
            $binary_broadcast_addr_array="";
            
            // binary array of 32 bit variables ('|' = logical operator 'or')
            for ($a=0; $a<32; $a++) $binary_broadcast_addr_array[$a] = ($binary_addr_array[$a] | $inverted_binary_cidr_array[$a]);
            
            // build binary address of four bundles of 8 bits (= 1 byte)
            $binary_broadcast_addr = chunk_split(implode("", $binary_broadcast_addr_array), 8, "."); 
            
            // chop off last dot ('.')
            $binary_broadcast_addr = substr($binary_broadcast_addr,0,strlen($binary_broadcast_addr)-1); 
            
            // binary array of 4 byte variables
            $binary_broadcast_addr_array = explode(".", $binary_broadcast_addr); 
            $broadcast_addr_array = "";
            
            // decimal array of 4 byte variables
            for ($a=0; $a<4; $a++) $broadcast_addr_array[$a] = bindec($binary_broadcast_addr_array[$a]); 
            
            // broadcast address
            $addr = implode(".", $broadcast_addr_array); 
        }
        
        // Check whether $port is valid
        if ((!ctype_digit($port)) || ($port < 0) || ($port > 65536))
            return $this->SetError(25000, __('Port is not a number within the range of 0 till 65536. Port Provided: ' . $port));
           
        // Check whether UDP is supported
        if (!array_search('udp', stream_get_transports()))
            return $this->SetError(25000, __('No magic packet can been sent, since UDP is unsupported (not a registered socket transport)'));
        
        // Ready to send the packet
        if (function_exists('fsockopen'))
        {
            // Try fsockopen function - To do: handle error 'Permission denied'
            $socket = fsockopen("udp://" . $addr, $port, $errno, $errstr);

            if ($socket)
            {
                $socket_data = fwrite($socket, $buf);

                if ($socket_data)
                {
                    $function = "fwrite";
                    $sent_fsockopen = "A magic packet of ".$socket_data." bytes has been sent via UDP to IP address: ".$addr.":".$port.", using the '".$function."()' function.";
                    $content = bin2hex($buf);

                    $sent_fsockopen = $sent_fsockopen."Contents of magic packet:".strlen($content)." ".$content;
                    fclose($socket);
                    
                    unset($socket);
                    
                    Debug::LogEntry('audit', $sent_fsockopen, 'display', 'WakeOnLan');
                    return true;
                }
                else
                {
                    unset($socket);
                    
                    return $this->SetError(25015, __('Using "fwrite()" failed, due to error: ' . $errstr.  ' ("' . $errno . '")'));
                }
            }
            else
            {
                unset($socket);
                
                Debug::LogEntry('audit', __('Using fsockopen() failed, due to denied permission'));
            }
        }

        // Try socket_create function
        if (function_exists('socket_create'))
        {
            // create socket based on IPv4, datagram and UDP
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP); 

            if ($socket)
            {
                // to enable manipulation of options at the socket level (you may have to change this to 1)
                $level = SOL_SOCKET; 
                
                // to enable permission to transmit broadcast datagrams on the socket (you may have to change this to 6)
                $optname = SO_BROADCAST; 
                
                $optval = true;
                $opt_returnvalue = socket_set_option($socket, $level, $optname, $optval);

                if ($opt_returnvalue < 0)
                {
                    return $this->SetError(25015, __('Using "socket_set_option()" failed, due to error: ' . socket_strerror($opt_returnvalue)));
                }

                $flags = 0;

                // To do: handle error 'Operation not permitted'
                $socket_data = socket_sendto($socket, $buf, strlen($buf), $flags, $addr, $port);

                if ($socket_data)
                {
                    $function = "socket_sendto";
                    $socket_create = "A magic packet of ". $socket_data . " bytes has been sent via UDP to IP address: ".$addr.":".$port.", using the '".$function."()' function.<br>";

                    $content = bin2hex($buf);
                    $socket_create = $socket_create . "Contents of magic packet:" . strlen($content) ." " . $content;

                    socket_close($socket);
                    unset($socket);

                    Debug::LogEntry('audit', $socket_create, 'display', 'WakeOnLan');
                    return true;
                }
                else
                {
                    $error = "Using 'socket_sendto()' failed, due to error: '".socket_strerror(socket_last_error($socket))."' (".socket_last_error($socket).")<br>\n";
                    socket_close($socket);
                    unset($socket);

                    return $this->SetError(25015, __('Using "socket_sendto()" failed, due to error: ' . socket_strerror(socket_last_error($socket)) . ' (' . socket_last_error($socket) . ')'));
                }
            }
            else
            {
                return $this->SetError(25015, __('Using "socket_sendto()" failed, due to error: ' . socket_strerror(socket_last_error($socket)) . ' (' . socket_last_error($socket) . ')'));
            }
        }
        else
        {
            return $this->SetError(25015, __('Wake On Lan Failed as there are no functions available to transmit it'));
        }
    }
}
?>
