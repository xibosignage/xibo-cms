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

/**
 * Class Display
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Display
{
    private $_config;
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID of this Display")
     * @var int
     */
    public $displayId;

    /**
     * @SWG\Property(description="Flag indicating whether this Display is recording Auditing Information from XMDS")
     * @var int
     */
    public $isAuditing;

    /**
     * @SWG\Property(description="The Name of this Display")
     * @var string
     */
    public $display;

    /**
     * @SWG\Property(description="The Description of this Display")
     * @var string
     */
    public $description;

    /**
     * @SWG\Property(description="The ID of the Default Layout")
     * @var int
     */
    public $defaultLayoutId = 4;

    /**
     * @SWG\Property(description="The Display Unique Identifier also called hardware key")
     * @var string
     */
    public $license;

    /**
     * @SWG\Property(description="A flag indicating whether this Display is licensed or not")
     * @var int
     */
    public $licensed;
    private $currentlyLicensed;

    /**
     * @SWG\Property(description="A flag indicating whether this Display is currently logged in")
     * @var int
     */
    public $loggedIn;

    /**
     * @SWG\Property(description="A timestamp in CMS time for the last time the Display accessed XMDS")
     * @var int
     */
    public $lastAccessed;

    /**
     * @SWG\Property(description="A flag indicating whether the default layout is interleaved with the Schedule")
     * @var int
     */
    public $incSchedule;

    /**
     * @SWG\Property(description="A flag indicating whether the Display will send email alerts.")
     * @var int
     */
    public $emailAlert;

    /**
     * @SWG\Property(description="A timeout in seconds for the Display to send email alerts.")
     * @var int
     */
    public $alertTimeout;

    /**
     * @SWG\Property(description="The MAC Address of the Display")
     * @var string
     */
    public $clientAddress;

    /**
     * @SWG\Property(description="The media inventory status of the Display")
     * @var int
     */
    public $mediaInventoryStatus;

    private $currentMacAddress;
    /**
     * @SWG\Property(description="The current Mac Address of the Player")
     * @var string
     */
    public $macAddress;

    /**
     * @SWG\Property(description="A timestamp indicating the last time the Mac Address changed")
     * @var int
     */
    public $lastChanged;

    /**
     * @SWG\Property(description="A count of Mac Address changes")
     * @var int
     */
    public $numberOfMacAddressChanges;

    /**
     * @SWG\Property(description="A timestamp indicating the last time a WOL command was sent")
     * @var int
     */
    public $lastWakeOnLanCommandSent;

    /**
     * @SWG\Property(description="A flag indicating whether Wake On Lan is enabled")
     * @var int
     */
    public $wakeOnLanEnabled;

    /**
     * @SWG\Property(description="A h:i string indicating the time to send a WOL command")
     * @var string
     */
    public $wakeOnLanTime;

    /**
     * @SWG\Property(description="The broad cast address for this Display")
     * @var string
     */
    public $broadCastAddress;

    /**
     * @SWG\Property(description="The secureOn WOL settings for this display.")
     * @var string
     */
    public $secureOn;

    /**
     * @SWG\Property(description="The CIDR WOL settings for this display")
     * @var string
     */
    public $cidr;

    /**
     * @SWG\Property(description="The display Latitude")
     * @var double
     */
    public $latitude;

    /**
     * @SWG\Property(description="The display longitude")
     * @var double
     */
    public $longitude;

    /**
     * @SWG\Property(description="A JSON string representing the player installer that should be installed")
     * @var string
     */
    public $versionInstructions;

    /**
     * @SWG\Property(description="A string representing the player type")
     * @var string
     */
    public $clientType;

    /**
     * @SWG\Property(description="A string representing the player version")
     * @var string
     */
    public $clientVersion;

    /**
     * @SWG\Property(description="A number representing the Player version code")
     * @var int
     */
    public $clientCode;

    /**
     * @SWG\Property(description="The display settings profile ID for this Display")
     * @var int
     */
    public $displayProfileId;

    /**
     * @SWG\Property(description="The current layout ID reported via XMDS")
     * @var int
     */
    public $currentLayoutId;

    /**
     * @SWG\Property(description="A flag indicating that a screen shot should be taken by the Player")
     * @var int
     */
    public $screenShotRequested;

    /**
     * @SWG\Property(description="The number of bytes of storage available on the device.")
     * @var int
     */
    public $storageAvailableSpace;

    /**
     * @SWG\Property(description="The number of bytes of storage in total on the device")
     * @var int
     */
    public $storageTotalSpace;

    /**
     * @SWG\Property(description="The ID of the Display Group for this Device")
     * @var int
     */
    public $displayGroupId;

    /**
     * @SWG\Property(description="The current layout")
     * @var string
     */
    public $currentLayout;

    /**
     * @SWG\Property(description="The default layout")
     * @var string
     */
    public $defaultLayout;

    /**
     * @SWG\Property(description="The Display Groups this Display belongs to")
     * @var DisplayGroup[]
     */
    public $displayGroups = [];

    public function __construct()
    {
        $this->excludeProperty('mediaInventoryXml');
    }

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
        if ($this->broadCastAddress != '' && !v::ip()->validate($this->broadCastAddress))
            throw new \InvalidArgumentException(__('BroadCast Address is not a valid IP Address'));

        // CIDR
        if (!empty($this->cidr) && !v::numeric()->between(0, 32)->validate($this->cidr))
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
     * @param array $options
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true,
            'audit' => true
        ], $options);

        if ($options['validate'])
            $this->validate();

        if ($this->displayId == null || $this->displayId == 0)
            $this->add();
        else
            $this->edit();

        if ($options['audit'])
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
            $displayGroup->unassignDisplay($this);
            $displayGroup->save(false);
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
            'defaultlayoutid' => $this->defaultLayoutId,
            'license' => $this->license,
            'licensed' => 0,
            'inc_schedule' => 0,
            'email_alert' => 0,
            'alert_timeout' => 0
        ]);

        $displayGroup = new DisplayGroup();
        $displayGroup->displayGroup = $this->display;
        $displayGroup->setOwner($this);
        $displayGroup->save();
    }

    private function edit()
    {
        PDOConnect::update('
            UPDATE display
                SET display = :display,
                    defaultlayoutid = :defaultLayoutId,
                    inc_schedule = :incSchedule,
                    license = :license,
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
            'license' => $this->license,
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
        $displayGroup->save(false);
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
