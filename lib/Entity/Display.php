<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (Display.php) is part of Xibo.
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


namespace Xibo\Entity;


use Respect\Validation\Validator as v;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Log;
use Xibo\Storage\PDOConnect;

class Display
{
    private $_config;
    use EntityTrait;

    public $displayId;
    public $isAuditing;
    public $display;
    public $description;
    public $defaultLayoutId;
    public $license;
    public $licensed;
    public $currentlyLicensed;
    public $loggedIn;
    public $lastAccessed;
    public $incSchedule;
    public $emailAlert;
    public $alertTimeout;
    public $clientAddress;
    public $mediaInventoryStatus;
    public $mediaInventoryXml;
    public $currentMacAddress;
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
    public $currentLayout;

    public $displayGroups = [];

    protected $jsonExclude = ['mediaInventoryXml'];

    public function getId()
    {
        return $this->displayId;
    }

    public function getOwnerId()
    {
        return 1;
    }

    /**
     * Set the Media Status to Incomplete
     */
    public function setMediaIncomplete()
    {
        $this->mediaInventoryStatus = 3;
    }

    /**
     * Validate the Object as it stands
     */
    public function validate()
    {
        if (!v::string()->notEmpty()->validate($this->display))
            throw new \InvalidArgumentException(__('Can not have a display without a name'));

        if ($this->wakeOnLanEnabled == 1 && $this->wakeOnLanTime == '')
            throw new \InvalidArgumentException(__('Wake on Lan is enabled, but you have not specified a time to wake the display'));

        // Check the number of licensed displays
        $maxDisplays = Config::GetSetting('MAX_LICENSED_DISPLAYS');

        if ($maxDisplays > 0 && $this->currentlyLicensed != $this->licensed && $this->licensed == 1) {
            $countLicensed = PDOConnect::select('SELECT COUNT(DisplayID) AS CountLicensed FROM display WHERE licensed = 1', []);

            if ($countLicensed[0] + 1 > $maxDisplays)
                throw new \InvalidArgumentException(sprintf(__('You have exceeded your maximum number of licensed displays. %d'), $maxDisplays));
        }

        // Broadcast Address
        if (!v::ip()->validate($this->broadCastAddress))
            throw new \InvalidArgumentException(__('BroadCast Address is not a valid IP Address'));

        // CIDR
        if (!v::numeric()->between(0, 32)->validate($this->cidr))
            throw new \InvalidArgumentException(__('CIDR subnet mask is not a number within the range of 0 to 32.'));

        // secureOn
        if ($this->secureOn != '') {
            $this->secureOn = strtoupper($this->secureOn);
            $this->secureOn = str_replace(":", "-", $this->secureOn);

            if ((!preg_match("/([A-F0-9]{2}[-]){5}([0-9A-F]){2}/", $this->secureOn)) || (strlen($this->secureOn) != 17))
                throw new \InvalidArgumentException(__('Pattern of secureOn-password is not "xx-xx-xx-xx-xx-xx" (x = digit or CAPITAL letter)'));
        }

        // Mac Address Changes
        if ($this->macAddress != $this->currentMacAddress) {
            // Mac address change detected
            $this->numberOfMacAddressChanges++;
            $this->lastChanged = time();
        }
    }

    /**
     * Load
     */
    public function load()
    {
        // Load this displays group membership
        $this->displayGroups = DisplayGroupFactory::getByDisplayId($this->displayId);
    }

    /**
     * Save
     * @param bool $validate
     */
    public function save($validate = true)
    {
        if ($validate)
            $this->validate();

        if ($this->displayId == null || $this->displayId == 0)
            $this->add();
        else
            $this->edit();

        Log::audit('Display', $this->displayId, 'Display Saved', $this->jsonSerialize());
    }

    /**
     * Delete
     * @throws \Xibo\Exception\NotFoundException
     */
    public function delete()
    {
        $this->load();

        // Remove our display from any groups it is assigned to
        foreach ($this->displayGroups as $displayGroup) {
            /* @var DisplayGroup $displayGroup */
            $displayGroup->removeAssignments();
        }

        // Delete our display specific group
        $displayGroup = DisplayGroupFactory::getById($this->displayGroupId);
        $displayGroup->delete();

        // Delete the display
        PDOConnect::update('DELETE FROM `blacklist` WHERE displayId = :displayId', ['displayId' => $this->displayId]);
        PDOConnect::update('DELETE FROM `display` WHERE displayId = :displayId', ['displayId' => $this->displayId]);

        Log::audit('Display', $this->displayId, 'Display Deleted', ['displayId' => $this->displayId]);
    }

    private function add()
    {
        $this->displayId = PDOConnect::insert('
            INSERT INTO display (display, isAuditing, defaultlayoutid, license, licensed, inc_schedule, email_alert, alert_timeout)
              VALUES (:display, :isauditing, :defaultlayoutid, :license, :licensed, :inc_schedule, :email_alert, :alert_timeout)
        ', [
            'display' => $this->display,
            'isauditing' => 0,
            'defaultlayoutid' => 1,
            'license' => $this->license,
            'licensed' => 0,
            'inc_schedule' => 0,
            'email_alert' => 0,
            'alert_timeout' => 0
        ]);

        $displayGroup = new DisplayGroup();
        $displayGroup->displayGroup = $this->display;
        $displayGroup->setOwner($this->displayId);
        $displayGroup->save();
    }

    private function edit()
    {
        PDOConnect::update('
            UPDATE display
                SET display = :display,
                    defaultlayoutid = :defaultLayoutId,
                    inc_schedule = :incSchedule,
                    licensed = :licensed,
                    isAuditing = :isAuditing,
                    email_alert = :emailAlert,
                    alert_timeout = :alertTimeout,
                    WakeOnLan = :wakeOnLanEnabled,
                    WakeOnLanTime = :wakeOnLanTime,
                    BroadCastAddress = :broadCastAddress,
                    SecureOn = :secureOn,
                    Cidr = :cidr,
                    GeoLocation = POINT(:latitude, :longitude),
                    displayprofileid = :displayProfileId,
                    lastaccessed = :lastAccessed,
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
             WHERE displayid = :displayId
        ', [
            'display' => $this->display,
            'defaultLayoutId' => $this->defaultLayoutId,
            'incSchedule' => $this->incSchedule,
            'licensed' => $this->licensed,
            'isAuditing' => $this->isAuditing,
            'emailAlert' => $this->emailAlert,
            'alertTimeout' => $this->alertTimeout,
            'wakeOnLanEnabled' => $this->wakeOnLanEnabled,
            'wakeOnLanTime' => $this->wakeOnLanTime,
            'broadCastAddress' => $this->broadCastAddress,
            'secureOn' => $this->secureOn,
            'cidr' => $this->cidr,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'displayProfileId' => $this->displayProfileId,
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
            'storageTotalSpace' => $this->storageTotalSpace,
            'displayId' => $this->displayId
        ]);

        // Maintain the Display Group
        $displayGroup = DisplayGroupFactory::getById($this->displayGroupId);
        $displayGroup->displayGroup = $this->display;
        $displayGroup->description = $this->description;
        $displayGroup->save();
    }

    /**
     * Get the Settings Profile for this Display
     * @return array
     */
    public function getSettings()
    {
        return $this->setConfig();
    }

    /**
     * Get a particular setting
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSetting($key, $default)
    {
        $this->setConfig();

        // Find
        $return = $default;
        foreach($this->_config as $row) {
            if ($row['name'] == $key || $row['name'] == ucfirst($key)) {
                $return = $row['value'];
                break;
            }
        }

        return $return;
    }

    /**
     * Set the config array
     * @return array
     */
    private function setConfig()
    {
        if ($this->_config == null) {

            if ($this->displayProfileId == 0) {
                // Load the default profile
                $displayProfile = DisplayProfileFactory::getDefaultByType($this->clientType);
            }
            else {
                // Load the specified profile
                $displayProfile = DisplayProfileFactory::getById($this->displayProfileId);
            }

            $this->_config = $displayProfile->getConfig();
        }

        return $this->_config;
    }
}
