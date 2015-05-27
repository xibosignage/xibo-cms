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

class Display extends Data {

    private $loaded;

    public $displayId;
    public $isAuditing;
    public $display;
    public $description;
    public $defaultLayoutId;
    public $license;
    public $licensed;
    public $currentLicensed;
    public $loggedIn;
    public $lastAccessed;
    public $incSchedule;
    public $emailAlert;
    public $alertTimeout;
    public $clientAddress;
    public $mediaInventoryStatus;
    public $mediaInventoryXml;
    public $macAddress;
    public $lastChanged;
    public $numberOfMacAddressChanges;
    public $lastWakeOnLanCommandSent;
    public $wakeOnLanEnabled;
    public $wakeOnLanTime;
    public $broadCastAddress;
    public $secureOn;
    public $cidr;
    public $latitude;
    public $longitude;
    public $versionInstructions;
    public $clientType;
    public $clientVersion;
    public $clientCode;
    public $displayProfileId;
    public $currentLayoutId;
    public $screenShotRequested;
    public $storageAvailableSpace;
    public $storageTotalSpace;

    public $displayGroupId;
    private $_config;

    protected $jsonExclude = array('mediaInventoryXml');
    
    public function Load() {
        try {
            $dbh = PDOConnect::init();
            $params = array();

            $SQL = '
              SELECT display.*, displaygroup.displaygroupid, displaygroup.description, X(display.GeoLocation) AS Latitude, Y(display.GeoLocation) AS Longitude
                FROM `display`
                    INNER JOIN `lkdisplaydg`
                    ON lkdisplaydg.displayid = display.displayId
                    INNER JOIN `displaygroup`
                    ON displaygroup.displaygroupid = lkdisplaydg.displaygroupid
                        AND isdisplayspecific = 1 ';

            if ($this->displayId != null && $this->displayId != 0) {
                $SQL .= 'WHERE display.displayid = :displayId';
                $params['displayId'] = $this->displayId;
            }
            else if ($this->license != null && $this->license != '') {
                $SQL .= 'WHERE display.licence = :licence';
                $params['licence'] = $this->license;
            }
            else {
                throw new Exception('There aren\'t any valid filter criteria');
            }

            $sth = $dbh->prepare($SQL);
            
            $sth->execute($params);
          
            if (!$row = $sth->fetch())
                $this->ThrowError(25004, __('Cannot find display record'));

            $this->isAuditing = Kit::ValidateParam($row['isAuditing'], _INT);
            $this->display = Kit::ValidateParam($row['display'], _STRING);
            $this->description = Kit::ValidateParam($row['description'], _STRING);
            $this->defaultLayoutId = Kit::ValidateParam($row['defaultlayoutid'], _INT);
            $this->license = Kit::ValidateParam($row['license'], _STRING);
            $this->licensed = Kit::ValidateParam($row['licensed'], _INT);
            $this->loggedIn = Kit::ValidateParam($row['loggedin'], _INT);
            $this->lastAccessed = Kit::ValidateParam($row['lastaccessed'], _INT);
            $this->incSchedule = Kit::ValidateParam($row['inc_schedule'], _INT);
            $this->emailAlert = Kit::ValidateParam($row['email_alert'], _INT);
            $this->alertTimeout = Kit::ValidateParam($row['alert_timeout'], _INT);
            $this->clientAddress = Kit::ValidateParam($row['ClientAddress'], _STRING);
            $this->mediaInventoryStatus = Kit::ValidateParam($row['MediaInventoryStatus'], _INT);
            $this->mediaInventoryXml = Kit::ValidateParam($row['MediaInventoryXml'], _HTMLSTRING);
            $this->macAddress = Kit::ValidateParam($row['MacAddress'], _STRING);
            $this->lastChanged = Kit::ValidateParam($row['LastChanged'], _INT);
            $this->numberOfMacAddressChanges = Kit::ValidateParam($row['NumberOfMacAddressChanges'], _INT);
            $this->lastWakeOnLanCommandSent = Kit::ValidateParam($row['LastWakeOnLanCommandSent'], _INT);
            $this->wakeOnLanEnabled = Kit::ValidateParam($row['WakeOnLan'], _INT);
            $this->wakeOnLanTime = Kit::ValidateParam($row['WakeOnLanTime'], _STRING);
            $this->broadCastAddress = Kit::ValidateParam($row['BroadCastAddress'], _STRING);
            $this->secureOn = Kit::ValidateParam($row['SecureOn'], _STRING);
            $this->cidr = Kit::ValidateParam($row['Cidr'], _STRING);
            $this->latitude = Kit::ValidateParam($row['Latitude'], _DOUBLE);
            $this->longitude = Kit::ValidateParam($row['Longitude'], _DOUBLE);
            $this->versionInstructions = Kit::ValidateParam($row['version_instructions'], _STRING);
            $this->clientType = Kit::ValidateParam($row['client_type'], _STRING);
            $this->clientVersion = Kit::ValidateParam($row['client_version'], _STRING);
            $this->clientCode = Kit::ValidateParam($row['client_code'], _INT);
            $this->displayProfileId = Kit::ValidateParam($row['displayprofileid'], _INT);
            $this->currentLayoutId = Kit::ValidateParam($row['currentLayoutId'], _INT);
            $this->screenShotRequested = Kit::ValidateParam($row['screenShotRequested'], _INT);
            $this->storageAvailableSpace = Kit::ValidateParam($row['storageAvailableSpace'], _INT);
            $this->storageTotalSpace = Kit::ValidateParam($row['storageTotalSpace'], _INT);

            $this->displayGroupId = Kit::ValidateParam($row['displaygroupid'], _INT);

            // Store the current licensed flag, in case we are changing it and need to check it.
            $this->currentLicensed = $this->licensed;

            $this->loaded = true;

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
     * Adds a Display
     * @return 
     * @param $display Object
     * @param $isAuditing Object
     * @param $defaultLayoutID Object
     * @param $license Object
     * @param $licensed Object
     * @param $incSchedule Object
     */
    public function Add($display, $isAuditing, $defaultLayoutID, $license, $licensed, $incSchedule) {
        Debug::LogEntry('audit', 'IN', get_class(), __FUNCTION__);
        
        try {
            $dbh = PDOConnect::init();

            // Create the SQL
            $SQL  = "";
            $SQL .= "INSERT INTO display (display, isAuditing, defaultlayoutid, license, licensed, inc_schedule, email_alert, alert_timeout) ";
            $SQL .= " VALUES (:display, :isauditing, :defaultlayoutid, :license, :licensed, :inc_schedule, :email_alert, :alert_timeout) ";
            
            $sth = $dbh->prepare($SQL);

            $params = array(
                'display' => $display,
                'isauditing' => 0,
                'defaultlayoutid' => 1,
                'license' => $license,
                'licensed' => 0,
                'inc_schedule' => 0,
                'email_alert' => 0,
                'alert_timeout' => 0
            );

            $sth->execute($params);
        
            // Get the ID of the inserted record
            $displayId = $dbh->lastInsertId();

            // Also want to add the DisplayGroup associated with this Display.
            $displayGroupObject = new DisplayGroup($this->db);

            if (!$displayGroupId = $displayGroupObject->Add($display, 1, ''))
                $this->ThrowError(25001, __('Could not add a display group for the new display.'));

            // Link the Two together
            if (!$displayGroupObject->Link($displayGroupId, $displayId))
                $this->ThrowError(25001, __('Could not link the new display with its group.'));
            
            \Xibo\Helper\Log::audit('Display', $displayId, 'Display Added', $params);

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
    public function Edit() {
        Debug::LogEntry('audit', 'IN', get_class(), __FUNCTION__);

        // Validation
        if ($this->display == '')
            return $this->SetError(__("Can not have a display without a name"));

        if ($this->wakeOnLanEnabled == 1 && $this->wakeOnLanTime == '')
            return $this->SetError(__('Wake on Lan is enabled, but you have not specified a time to wake the display'));
        
        try {
            $dbh = PDOConnect::init();
        
            // Check the number of licensed displays
            $maxDisplays = Config::GetSetting('MAX_LICENSED_DISPLAYS');

            if ($maxDisplays > 0) {
                // See if this is a license switch
                // Has the licence flag toggled?
                if ($this->currentLicensed != $this->licensed && $this->licensed == 1)
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
            if ($this->broadCastAddress != '')
            {
                // Resolve broadcast address
                // same as (but easier than):  preg_match("/\b(([01]?\d?\d|2[0-4]\d|25[0-5])\.){3}([01]?\d?\d|2[0-4]\d|25[0-5])\b/",$addr)
                if (!filter_var($this->broadCastAddress, FILTER_VALIDATE_IP))
                    $this->ThrowError(25015, __('BroadCast Address is not a valid IP Address'));
            }

            // Check whether $cidr is valid
            if ($this->cidr != '')
            {
                if ((!is_numeric($this->cidr)) || ($this->cidr < 0) || ($this->cidr > 32))
                    $this->ThrowError(25015, __('CIDR subnet mask is not a number within the range of 0 to 32.'));
            }

            // Check whether $secureOn is valid
            if ($this->secureOn != '')
            {
                $this->secureOn = strtoupper($this->secureOn);
                $this->secureOn = str_replace(":", "-", $this->secureOn);

                if ((!preg_match("/([A-F0-9]{2}[-]){5}([0-9A-F]){2}/", $this->secureOn)) || (strlen($this->secureOn) != 17))
                    $this->ThrowError(25015, __('Pattern of secureOn-password is not "xx-xx-xx-xx-xx-xx" (x = digit or CAPITAL letter)'));
            }

            Debug::LogEntry('audit', 'Validation Complete and Passed', 'Display', 'Edit');

            // Update the display record
            $SQL  = '
                UPDATE display 
                    SET display = :display,
                        defaultlayoutid = :defaultlayoutid,
                        inc_schedule = :incschedule,
                        licensed = :licensed,
                        isAuditing = :isauditing,
                        email_alert = :emailalert,
                        alert_timeout = :alerttimeout,
                        WakeOnLan = :wakeonlan,
                        WakeOnLanTime = :wakeonlantime,
                        BroadCastAddress = :broadcastaddress,
                        SecureOn = :secureon,
                        Cidr = :cidr,
                        GeoLocation = POINT(:latitude, :longitude),
                        displayprofileid = :displayprofileid
             WHERE displayid = :displayid';

            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'display' => $this->display,
                    'defaultlayoutid' => $this->defaultLayoutId,
                    'incschedule' => $this->incSchedule,
                    'licensed' => $this->licensed,
                    'isauditing' => $this->isAuditing,
                    'emailalert' => $this->emailAlert,
                    'alerttimeout' => $this->alertTimeout,
                    'wakeonlan' => $this->wakeOnLanEnabled,
                    'wakeonlantime' => $this->wakeOnLanTime,
                    'broadcastaddress' => $this->broadCastAddress,
                    'secureon' => $this->secureOn,
                    'cidr' => $this->cidr,
                    'latitude' => $this->latitude,
                    'longitude' => $this->longitude,
                    'displayprofileid' => $this->displayProfileId,
                    'displayid' => $this->displayId
                ));

            Debug::LogEntry('audit', 'Display Edited', 'Display', 'Edit');

            // Use a DisplayGroup to handle the default layout and displaygroup name for this display
            Kit::ClassLoader('displaygroup');
            $displayGroupObject = new DisplayGroup();
            
            // Do we also want to update the linked Display Groups name (seeing as that is what we will be presenting to everyone)
            if (!$displayGroupObject->Edit($this->displayGroupId, $this->display, $this->description)) {
                $this->ThrowError(25002, __('Could not update this display with a new name.'));
            }

            \Xibo\Helper\Log::audit('Display', $this->displayId, 'Display Edited', $this->jsonSerialize());
            
            return true;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);

            if (!$this->IsError())
                $this->SetError(25000, __('Could not update display'));

            return false;
        }
    }
    
    /**
     * Deletes a Display
     * @return 
     * @param $displayID int
     */
    public function Delete($displayID)
    {
        Debug::LogEntry('audit', 'IN', get_class(), __FUNCTION__);
        
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

            \Xibo\Helper\Log::audit('Display', $displayID, 'Display Deleted', array('displayId' => $displayID));

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
     * Sets the information required on the display to indicate that it is still logged in
     * @param int $displayId The Display ID
     * @param array $status The Display Status
     * @return bool
     */
    public function Touch($displayId, $status = array())
    {
        Debug::LogEntry('audit', 'IN', get_class(), __FUNCTION__);

        try {
            $dbh = PDOConnect::init();

            $this->displayId = $displayId;
            $this->Load();

            // Update last accessed and set to be logged in
            if (Kit::GetParam('loggedIn', $status, _INT, 1) != 0) {
                $this->lastAccessed = time();
                $this->loggedIn = 1;
            }

            // Pull in any of the optional parameters from the status array
            $this->clientAddress = (Kit::GetParam('clientAddress', $status, _STRING) == '') ? $this->clientAddress : Kit::GetParam('clientAddress', $status, _STRING);
            $this->mediaInventoryStatus = (Kit::GetParam('mediaInventoryStatus', $status, _INT) == 0) ? $this->mediaInventoryStatus : Kit::GetParam('mediaInventoryStatus', $status, _INT);
            $this->mediaInventoryXml = (Kit::GetParam('mediaInventoryXml', $status, _HTMLSTRING) == '') ? $this->mediaInventoryXml : Kit::GetParam('mediaInventoryXml', $status, _HTMLSTRING);
            $this->clientType = (Kit::GetParam('clientType', $status, _STRING) == '') ? $this->clientType : Kit::GetParam('clientType', $status, _STRING);
            $this->clientVersion = (Kit::GetParam('clientVersion', $status, _STRING) == '') ? $this->clientVersion : Kit::GetParam('clientVersion', $status, _STRING);
            $this->clientCode = (Kit::GetParam('clientCode', $status, _INT) == 0) ? $this->clientCode : Kit::GetParam('clientCode', $status, _INT);
            $this->currentLayoutId = (Kit::GetParam('currentLayoutId', $status, _INT) == 0) ? $this->currentLayoutId : Kit::GetParam('currentLayoutId', $status, _INT);
            $this->screenShotRequested = (Kit::GetParam('screenShotRequested', $status, _INT, -1) == -1) ? $this->screenShotRequested : Kit::GetParam('screenShotRequested', $status, _INT);
            $this->storageAvailableSpace = (Kit::GetParam('availableSpace', $status, _INT, -1) == -1) ? $this->storageAvailableSpace : Kit::GetParam('availableSpace', $status, _INT);
            $this->storageTotalSpace = (Kit::GetParam('totalSpace', $status, _INT, -1) == -1) ? $this->storageTotalSpace : Kit::GetParam('totalSpace', $status, _INT);

            // Has the mac address changed
            if (Kit::GetParam('macAddress', $status, _STRING) != '') {
                if ($this->macAddress != Kit::GetParam('macAddress', $status, _STRING)) {
                    // Mac address change detected
                    $this->macAddress = Kit::GetParam('macAddress', $status, _STRING);
                    $this->numberOfMacAddressChanges++;
                    $this->lastChanged = time();
                }
            }

            // Save
            $SQL = '
                    UPDATE display SET lastaccessed = :lastAccessed,
                        loggedin = :loggedIn,
                        ClientAddress = :clientAddress,
                        MediaInventoryStatus = :mediaInventoryStatus,
                        MediaInventoryXml = :mediaInventoryXml,
                        client_type = :clientType,
                        client_version = :clientVersion,
                        client_code = :clientCode,
                        MacAddress = :macAddress,
                        LastChanged = :lastChanged,
                        NumberOfMacAddressChanges = :numberOfMacAddressChanges,
                        currentLayoutId = :currentLayoutId,
                        screenShotRequested = :screenShotRequested,
                        storageAvailableSpace = :storageAvailableSpace,
                        storageTotalSpace = :storageTotalSpace
                     WHERE displayId = :displayId
                ';

            // Update the display
            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'displayId' => $this->displayId,
                    'lastAccessed' => $this->lastAccessed,
                    'loggedIn' => $this->loggedIn,
                    'clientAddress' => $this->clientAddress,
                    'mediaInventoryStatus' => $this->mediaInventoryStatus,
                    'mediaInventoryXml' => $this->mediaInventoryXml,
                    'clientType' => $this->clientType,
                    'clientVersion' => $this->clientVersion,
                    'clientCode' => $this->clientCode,
                    'macAddress' => $this->macAddress,
                    'lastChanged' => $this->lastChanged,
                    'numberOfMacAddressChanges' => $this->numberOfMacAddressChanges,
                    'currentLayoutId' => $this->currentLayoutId,
                    'screenShotRequested' => $this->screenShotRequested,
                    'storageAvailableSpace' => $this->storageAvailableSpace,
                    'storageTotalSpace' => $this->storageTotalSpace
                ));

            return true;
        }
        catch (Exception $e) {
            Debug::LogEntry('error', $e->getMessage());
            return $this->SetError(25002, __("Error updating this displays last accessed information."));
        }
    }

    /**
     * Edits a Displays Name
     * @param string $license
     * @param string $display
     * @return bool
     */
    public function EditDisplayName($license, $display)
    {
        Debug::Audit($license);

        $this->license = $license;
        if (!$this->Load())
            return false;

        // Update the name
        $this->display = $display;
        $this->Edit();
    }

    public function RequestScreenShot($displayId) {
        return $this->Touch($displayId, array('screenShotRequested' => 1, 'loggedIn' => 0));
    }

    /**
     * Flags a display as being incomplete
     * @param int $displayId
     * @return bool
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
            $SQL .= "   FROM schedule ";
            $SQL .= "   INNER JOIN schedule_detail ";
            $SQL .= "   ON schedule_detail.eventid = schedule.eventid ";
            $SQL .= "   INNER JOIN lkdisplaydg ";
            $SQL .= "   ON lkdisplaydg.DisplayGroupID = schedule_detail.DisplayGroupID ";
            $SQL .= "   INNER JOIN display ";
            $SQL .= "   ON lkdisplaydg.DisplayID = display.displayID ";
            $SQL .= " WHERE schedule.CampaignID = :campaignid ";
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
        Debug::LogEntry('audit', 'IN', get_class(), __FUNCTION__);

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

    public function GetSetting($key, $default)
    {
        if (!$this->SetConfig())
            return false;

        // Find
        $return = $default;
        foreach($this->_config as $row) {
            if ($row['name'] == $key || $row['name'] == ucfirst($key)) {
                //Debug::Audit('Found ' . $key . '. value= ' . $row['value']);
                $return = $row['value'];
                break;
            }
        }

        return $return;
    }

    private function SetConfig()
    {
        if ($this->_config == null) {
            try {
                $displayProfile = new DisplayProfile();
                $displayProfile->displayProfileId = $this->displayProfileId;
            
                if ($displayProfile->displayProfileId == 0) {
                    // Load the default profile
                    $displayProfile->type = $this->clientType;
                    $displayProfile->LoadDefault();
                }
                else {
                    // Load the specified profile
                    $displayProfile->Load();
                }
        
                $this->_config = $displayProfile->config;
                
                return true;
            }
            catch (Exception $e) {
                
                Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
            
                if (!$this->IsError())
                    $this->SetError(1, __('Unknown Error'));
            
                return false;
            }
        }
    }

    /**
     * Get the Settings Profile for this Display
     * @return array|bool
     */
    public function getSettingsProfile()
    {
        if (!$this->SetConfig())
            return false;

        return $this->_config;
    }

    /**
     * Assess each Display to correctly set the logged in flag based on last accessed time
     * @return
     */
    public static function ValidateDisplays() {
        // Maintain an array of timed out displays
        $timedOutDisplays = array();

        try {
            $dbh = PDOConnect::init();
            $statObject = new Stat();
        
            // Get a list of all displays and there last accessed / alert time out value
            $sth = $dbh->prepare('SELECT displayid, display, lastaccessed, alert_timeout, client_type, displayprofileid, email_alert, loggedin FROM display');
            $sthUpdate = $dbh->prepare('UPDATE display SET loggedin = 0 WHERE displayid = :displayid');
            
            $sth->execute(array());

            // Get the global time out (overrides the alert time out on the display if 0)
            $globalTimeout = Config::GetSetting('MAINTENANCE_ALERT_TOUT') * 60;
        
            $displays = $sth->fetchAll();

            foreach ($displays as $row) {
                $displayid = Kit::ValidateParam($row['displayid'], _INT);
                $lastAccessed = Kit::ValidateParam($row['lastaccessed'], _INT);
                $alertTimeout = Kit::ValidateParam($row['alert_timeout'], _INT);
                $clientType = Kit::ValidateParam($row['client_type'], _WORD);
                $loggedIn = Kit::ValidateParam($row['loggedin'], _INT);

                // Get the config object
                if ($alertTimeout == 0 && $clientType != '') {
                    $displayProfileId = (empty($row['displayprofileid']) ? 0 : Kit::ValidateParam($row['displayprofileid'], _INT));

                    $display = new Display();
                    $display->displayId = $displayid;
                    $display->displayProfileId = $displayProfileId;
                    $display->clientType = $clientType;
                    $timeoutToTestAgainst = $display->GetSetting('collectInterval', $globalTimeout);
                }
                else {
                    $timeoutToTestAgainst = $globalTimeout;
                }

                // Store the time out to test against
                $row['timeout'] = $timeoutToTestAgainst;
                $timeOut = $lastAccessed + $timeoutToTestAgainst;
    
                // If the last time we accessed is less than now minus the time out
                if ($timeOut < time()) {
                    Debug::Audit('Timed out display. Last Accessed: ' . date('Y-m-d h:i:s', $lastAccessed) . '. Time out: ' . date('Y-m-d h:i:s', $timeOut));

                    // If this is the first switch (i.e. the row was logged in before)
                    if ($loggedIn == 1) {
                        
                        // Update the display and set it as logged out
                        $sthUpdate->execute(array('displayid' => $displayid));
                       
                        // Log the down event
                        $statObject->displayDown($displayid, $lastAccessed);
                    }

                    // Store this row
                    $timedOutDisplays[] = $row;
                }
            }

            return $timedOutDisplays;  
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
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
        Debug::LogEntry('audit', 'IN', get_class(), __FUNCTION__);

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
     * Get a list of users that have permission for the provided display
     * @param  int $displayId The Display
     * @param  string $authLevel The Auth Level (view|edit|delete)
     * @return array Users Array
     */
    public static function getUsers($displayId, $authLevel = 'view')
    {
        try {
            $dbh = PDOConnect::init();
        
            $sth = $dbh->prepare('
                    SELECT DISTINCT user.userId, user.userName, user.email 
                      FROM `user` 
                        INNER JOIN `lkusergroup` 
                        ON lkusergroup.userId = user.userId
                        INNER JOIN `group` 
                        ON group.groupId = lkusergroup.groupId
                        INNER JOIN `lkdisplaygroupgroup` 
                        ON lkdisplaygroupgroup.groupId = group.groupId
                        INNER JOIN `displaygroup` 
                        ON displaygroup.displayGroupId = lkdisplaygroupgroup.displayGroupId
                        INNER JOIN `lkdisplaydg` 
                        ON lkdisplaydg.displayGroupId = lkdisplaygroupgroup.displayGroupId
                     WHERE lkdisplaydg.displayId = :displayId
                ');

            $sth->execute(array(
                    'displayId' => $displayId
                ));
            
            // Return this list of users
            return $sth->fetchAll();
        }
        catch (Exception $e) {            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
            return false;
        }
    }

    /**
     * Wake On Lan Script
     *  // Version: 2
     *  // Author of this application:
     *  //  DS508_customer (http://www.synology.com/enu/forum/memberlist.php?mode=viewprofile&u=12636)
     *  //  Please inform the author of any suggestions on (the functionality, graphical design, ... of) this application.
     *  //  More info: http://wolviaphp.sourceforge.net
     *  // License: GPLv2.0
     *
     * Modified for use with the Xibo project by Dan Garner.
     */
    function TransmitWakeOnLan($mac_address, $secureon, $addr, $cidr, $port) {
        Debug::LogEntry('audit', 'IN', get_class(), __FUNCTION__);

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
                    $error = __('Using "socket_sendto()" failed, due to error: ' . socket_strerror(socket_last_error($socket)) . ' (' . socket_last_error($socket) . ')');
                    socket_close($socket);
                    unset($socket);

                    return $this->SetError(25015, $error);
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
