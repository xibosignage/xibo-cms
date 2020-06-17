<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
namespace Xibo\Entity;


use Respect\Validation\Validator as v;
use Stash\Interfaces\PoolInterface;
use Xibo\Exception\DeadlockException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Display
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Display implements \JsonSerializable
{
    public static $STATUS_DONE = 1;
    public static $STATUS_DOWNLOADING = 2;
    public static $STATUS_PENDING = 3;

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
    public $auditingUntil;

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

    /**
     * @SWG\Property(description="The Player Subscription Channel")
     * @var string
     */
    public $xmrChannel;

    /**
     * @SWG\Property(description="The Player Public Key")
     * @var string
     */
    public $xmrPubKey;

    /**
     * @SWG\Property(description="The last command success, 0 = failure, 1 = success, 2 = unknown")
     * @var int
     */
    public $lastCommandSuccess = 0;

    /**
     * @SWG\Property(description="The Device Name for the device hardware associated with this Display")
     * @var string
     */
    public $deviceName;

    /**
     * @SWG\Property(description="The Display Timezone, or empty to use the CMS timezone")
     * @var string
     */
    public $timeZone;

    /**
     * @SWG\Property(description="Tags associated with this Display")
     * @var Tag[]
     */
    public $tags;
    public $tagValues;

    /**
     * @SWG\Property(description="The configuration options that will overwrite Display Profile Config")
     * @var string|array
     */
    public $overrideConfig = [];

    /**
     * @SWG\Property(description="The display bandwidth limit")
     * @var int
     */
    public $bandwidthLimit;

    /**
     * @SWG\Property(description="The new CMS Address")
     * @var string
     */
    public $newCmsAddress;

    /**
     * @SWG\Property(description="The new CMS Key")
     * @var string
     */
    public $newCmsKey;

    /**
     * @SWG\Property(description="The orientation of the Display, either landscape or portrait")
     * @var string
     */
    public $orientation;

    /**
     * @SWG\Property(description="The resolution of the Display expressed as a string in the format WxH")
     * @var string
     */
    public $resolution;

    /**
     * @SWG\Property(description="Status of the commercial licence for this Display. 0 - Not licensed, 1 - licensed, 2 - trial licence, 3 - not applicable")
     * @var int
     */
    public $commercialLicence;

    /** @var array The configuration from the Display Profile  */
    private $profileConfig;

    /** @var array Combined config */
    private $combinedConfig;

    /** @var \Xibo\Entity\DisplayProfile the resolved DisplayProfile for this Display */
    private $_displayProfile;

    /**
     * Commands
     * @var array[Command]
     */
    private $commands = null;

    public static $saveOptionsMinimum = ['validate' => false, 'audit' => false];

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var DisplayProfileFactory
     */
    private $displayProfileFactory;

    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DisplayProfileFactory $displayProfileFactory
     * @param DisplayFactory $displayFactory
     */
    public function __construct($store, $log, $config, $displayGroupFactory, $displayProfileFactory, $displayFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->excludeProperty('mediaInventoryXml');
        $this->setPermissionsClass('Xibo\Entity\DisplayGroup');
        $this->setCanChangeOwner(false);

        $this->config = $config;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->displayProfileFactory = $displayProfileFactory;
        $this->displayFactory = $displayFactory;

        // Initialise extra validation rules
        v::with('Xibo\\Validation\\Rules\\');
    }

    /**
     * Set child object dependencies
     * @param LayoutFactory $layoutFactory
     * @param MediaFactory $mediaFactory
     * @param ScheduleFactory $scheduleFactory
     * @return $this
     */
    public function setChildObjectDependencies($layoutFactory, $mediaFactory, $scheduleFactory)
    {
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
        $this->scheduleFactory = $scheduleFactory;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->displayGroupId;
    }

    /**
     * @return int
     */
    public function getOwnerId()
    {
        // No owner
        return 0;
    }

    /**
     * Get the cache key
     * @return string
     */
    public static function getCachePrefix()
    {
        return 'display/';
    }

    /**
     * Get the cache key
     * @return string
     */
    public function getCacheKey()
    {
        return self::getCachePrefix() . $this->displayId;
    }

    /**
     * @return \Xibo\Entity\DisplayProfile
     */
    public function getDisplayProfile()
    {
        if ($this->_displayProfile === null) {

            try {
                if ($this->displayProfileId == 0) {
                    // Load the default profile
                    $displayProfile = $this->displayProfileFactory->getDefaultByType($this->clientType);
                } else {
                    // Load the specified profile
                    $displayProfile = $this->displayProfileFactory->getById($this->displayProfileId);
                }
            } catch (NotFoundException $e) {
                $this->getLog()->error('Cannot get display profile');
                $this->getLog()->debug($e->getTraceAsString());

                $displayProfile = $this->displayProfileFactory->getUnknownProfile($this->clientType);
            }

            // Set our display profile
            $this->_displayProfile = $displayProfile;

        }

        return $this->_displayProfile;
    }

    /**
     * Is this display auditing?
     * return bool
     */
    public function isAuditing()
    {
        $this->getLog()->debug('Testing whether this display is auditing. %d vs %d.', $this->auditingUntil, time());
        // Test $this->auditingUntil against the current date.
        return ($this->auditingUntil >= time());
    }

    /**
     * Set the Media Status to Incomplete
     */
    public function notify()
    {
        $this->getLog()->debug($this->display . ' requests notify');

        $this->displayFactory->getDisplayNotifyService()->collectNow()->notifyByDisplayId($this->displayId);
    }

    /**
     * Validate the Object as it stands
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (!v::stringType()->notEmpty()->validate($this->display))
            throw new InvalidArgumentException(__('Can not have a display without a name'), 'name');

        if (!v::stringType()->notEmpty()->validate($this->license))
            throw new InvalidArgumentException(__('Can not have a display without a hardware key'), 'license');

        if ($this->wakeOnLanEnabled == 1 && $this->wakeOnLanTime == '')
            throw new InvalidArgumentException(__('Wake on Lan is enabled, but you have not specified a time to wake the display'), 'wakeonlan');

        // Broadcast Address
        if ($this->broadCastAddress != '' && !v::ip()->validate($this->broadCastAddress))
            throw new InvalidArgumentException(__('BroadCast Address is not a valid IP Address'), 'broadCastAddress');

        // CIDR
        if (!empty($this->cidr) && !v::numeric()->between(0, 32)->validate($this->cidr))
            throw new InvalidArgumentException(__('CIDR subnet mask is not a number within the range of 0 to 32.'), 'cidr');

        // secureOn
        if ($this->secureOn != '') {
            $this->secureOn = strtoupper($this->secureOn);
            $this->secureOn = str_replace(":", "-", $this->secureOn);

            if ((!preg_match("/([A-F0-9]{2}[-]){5}([0-9A-F]){2}/", $this->secureOn)) || (strlen($this->secureOn) != 17))
                throw new InvalidArgumentException(__('Pattern of secureOn-password is not "xx-xx-xx-xx-xx-xx" (x = digit or CAPITAL letter)'), 'secureOn');
        }

        // Mac Address Changes
        if ($this->hasPropertyChanged('macAddress')) {
            // Mac address change detected
            $this->numberOfMacAddressChanges++;
            $this->lastChanged = time();
        }

        // Lat/Long
        if (!empty($this->longitude) && !v::longitude()->validate($this->longitude))
            throw new InvalidArgumentException(__('The longitude entered is not valid.'), 'longitude');

        if (!empty($this->latitude) && !v::latitude()->validate($this->latitude))
            throw new InvalidArgumentException(__('The latitude entered is not valid.'), 'latitude');

        if ($this->bandwidthLimit !== null && !v::intType()->min(0)->validate($this->bandwidthLimit)) {
            throw new InvalidArgumentException(__('Bandwidth limit must be a whole number greater than 0.'), 'bandwidthLimit');
        }
    }

    /**
     * Check if there is display slot available, returns true when there are display slots available, return false if there are no display slots available
     * @return boolean
     */
    public function isDisplaySlotAvailable()
    {
        $maxDisplays = $this->config->GetSetting('MAX_LICENSED_DISPLAYS');

        // Check the number of licensed displays
        if ($maxDisplays > 0) {
            $this->getLog()->debug('Testing authorised displays against %d maximum. Currently authorised = %d, authorised = %d.', $maxDisplays, $this->currentlyLicensed, $this->licensed);

            if ($this->currentlyLicensed != $this->licensed && $this->licensed == 1) {
                $countLicensed = $this->getStore()->select('SELECT COUNT(DisplayID) AS CountLicensed FROM display WHERE licensed = 1', []);

                $this->getLog()->debug('There are %d authorised displays and we the maximum is %d', $countLicensed[0]['CountLicensed'], $maxDisplays);

                if (intval($countLicensed[0]['CountLicensed']) + 1 > $maxDisplays) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Load
     * @throws NotFoundException
     */
    public function load()
    {
        if ($this->loaded)
            return;

        // Load this displays group membership
        $this->displayGroups = $this->displayGroupFactory->getByDisplayId($this->displayId);

        $this->loaded = true;
    }

    /**
     * Save the media inventory status
     */
    public function saveMediaInventoryStatus()
    {
        try {
            $this->getStore()->updateWithDeadlockLoop('UPDATE `display` SET mediaInventoryStatus = :mediaInventoryStatus WHERE displayId = :displayId', [
                'mediaInventoryStatus' => $this->mediaInventoryStatus,
                'displayId' => $this->displayId
            ]);
        } catch (DeadlockException $deadlockException) {
            $this->getLog()->error('Media Inventory Status save failed due to deadlock');
        }
    }

    /**
     * Save
     * @param array $options
     * @throws XiboException
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true,
            'audit' => true,
            'checkDisplaySlotAvailability' => true
        ], $options);

        $allowNotify = true;

        if ($options['validate'])
            $this->validate();

        if ($options['checkDisplaySlotAvailability']) {
            // Check if there are display slots available
            $maxDisplays = $this->config->GetSetting('MAX_LICENSED_DISPLAYS');

            if (!$this->isDisplaySlotAvailable()) {
                throw new InvalidArgumentException(sprintf(__('You have exceeded your maximum number of authorised displays. %d'),
                    $maxDisplays), 'maxDisplays');
            }
        }

        if ($this->displayId == null || $this->displayId == 0) {
            $this->add();

            // Never notify on add (there is little point, we've only just added).
            $allowNotify = false;
        }
        else {
            $this->edit();
        }

        if ($options['audit'])
            $this->getLog()->audit('Display', $this->displayId, 'Display Saved', $this->getChangedProperties());

        // Trigger an update of all dynamic DisplayGroups
        if ($this->hasPropertyChanged('display') || $this->hasPropertyChanged('tags')) {
            foreach ($this->displayGroupFactory->getByIsDynamic(1) as $group) {
                /* @var DisplayGroup $group */
                $group->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
                $group->save(['validate' => false, 'saveGroup' => false, 'manageDisplayLinks' => true, 'allowNotify' => $allowNotify]);
            }
        }
    }

    /**
     * Delete
     * @throws XiboException
     */
    public function delete()
    {
        $this->load();

        // Delete incidential references
        $this->getStore()->update('DELETE FROM `requiredfile` WHERE displayId = :displayId', ['displayId' => $this->displayId]);

        // Remove our display from any groups it is assigned to
        foreach ($this->displayGroups as $displayGroup) {
            /* @var DisplayGroup $displayGroup */
            $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
            $displayGroup->unassignDisplay($this);
            $displayGroup->save(['validate' => false, 'manageDynamicDisplayLinks' => false]);
        }

        // Delete our display specific group
        $displayGroup = $this->displayGroupFactory->getById($this->displayGroupId);
        $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
        $displayGroup->delete();

        // Delete the display
        $this->getStore()->update('DELETE FROM `blacklist` WHERE displayId = :displayId', ['displayId' => $this->displayId]);
        $this->getStore()->update('DELETE FROM `display` WHERE displayId = :displayId', ['displayId' => $this->displayId]);

        $this->getLog()->audit('Display', $this->displayId, 'Display Deleted', ['displayId' => $this->displayId]);
    }

    private function add()
    {
        $this->displayId = $this->getStore()->insert('
            INSERT INTO display (display, auditingUntil, defaultlayoutid, license, licensed, lastAccessed, inc_schedule, email_alert, alert_timeout, clientAddress, xmrChannel, xmrPubKey, lastCommandSuccess, macAddress, lastChanged, lastWakeOnLanCommandSent, client_type, client_version, client_code, overrideConfig, newCmsAddress, newCmsKey, commercialLicence)
              VALUES (:display, :auditingUntil, :defaultlayoutid, :license, :licensed, :lastAccessed, :inc_schedule, :email_alert, :alert_timeout, :clientAddress, :xmrChannel, :xmrPubKey, :lastCommandSuccess, :macAddress, :lastChanged, :lastWakeOnLanCommandSent, :clientType, :clientVersion, :clientCode, :overrideConfig, :newCmsAddress, :newCmsKey, :commercialLicence)
        ', [
            'display' => $this->display,
            'auditingUntil' => 0,
            'defaultlayoutid' => $this->defaultLayoutId,
            'license' => $this->license,
            'licensed' => $this->licensed,
            'lastAccessed' => $this->lastAccessed,
            'inc_schedule' => 0,
            'email_alert' => 0,
            'alert_timeout' => 0,
            'clientAddress' => $this->clientAddress,
            'xmrChannel' => $this->xmrChannel,
            'xmrPubKey' => ($this->xmrPubKey === null) ? '' : $this->xmrPubKey,
            'lastCommandSuccess' => $this->lastCommandSuccess,
            'macAddress' => $this->macAddress,
            'lastChanged' => ($this->lastChanged === null) ? 0 : $this->lastChanged,
            'lastWakeOnLanCommandSent' => ($this->lastWakeOnLanCommandSent === null) ? 0 : $this->lastWakeOnLanCommandSent,
            'clientType' => $this->clientType,
            'clientVersion' => $this->clientVersion,
            'clientCode' => $this->clientCode,
            'overrideConfig' => ($this->overrideConfig == '') ? null : json_encode($this->overrideConfig),
            'newCmsAddress' => null,
            'newCmsKey' => null,
            'commercialLicence' => $this->commercialLicence
        ]);


        $displayGroup = $this->displayGroupFactory->create();
        $displayGroup->displayGroup = $this->display;
        $displayGroup->tags = $this->tags;
        $displayGroup->setDisplaySpecificDisplay($this);

        $this->getLog()->debug('Creating display specific group with userId ' . $displayGroup->userId);

        $displayGroup->save();
    }


    private function edit()
    {
        $this->getStore()->update('
            UPDATE display
                SET display = :display,
                    defaultlayoutid = :defaultLayoutId,
                    inc_schedule = :incSchedule,
                    license = :license,
                    licensed = :licensed,
                    auditingUntil = :auditingUntil,
                    email_alert = :emailAlert,
                    alert_timeout = :alertTimeout,
                    WakeOnLan = :wakeOnLanEnabled,
                    WakeOnLanTime = :wakeOnLanTime,
                    lastWakeOnLanCommandSent = :lastWakeOnLanCommandSent,
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
                    screenShotRequested = :screenShotRequested,
                    storageAvailableSpace = :storageAvailableSpace,
                    storageTotalSpace = :storageTotalSpace,
                    xmrChannel = :xmrChannel,
                    xmrPubKey = :xmrPubKey,
                    `lastCommandSuccess` = :lastCommandSuccess,
                    `deviceName` = :deviceName,
                    `timeZone` = :timeZone,
                    `overrideConfig` = :overrideConfig,
                    `newCmsAddress` = :newCmsAddress,
                    `newCmsKey` = :newCmsKey,
                    `orientation` = :orientation,
                    `resolution` = :resolution,
                    `commercialLicence` = :commercialLicence
             WHERE displayid = :displayId
        ', [
            'display' => $this->display,
            'defaultLayoutId' => $this->defaultLayoutId,
            'incSchedule' => $this->incSchedule,
            'license' => $this->license,
            'licensed' => $this->licensed,
            'auditingUntil' => ($this->auditingUntil == null) ? 0 : $this->auditingUntil,
            'emailAlert' => $this->emailAlert,
            'alertTimeout' => $this->alertTimeout,
            'wakeOnLanEnabled' => $this->wakeOnLanEnabled,
            'wakeOnLanTime' => $this->wakeOnLanTime,
            'lastWakeOnLanCommandSent' => $this->lastWakeOnLanCommandSent,
            'broadCastAddress' => $this->broadCastAddress,
            'secureOn' => $this->secureOn,
            'cidr' => $this->cidr,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'displayProfileId' => ($this->displayProfileId == null) ? null : $this->displayProfileId,
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
            'screenShotRequested' => $this->screenShotRequested,
            'storageAvailableSpace' => $this->storageAvailableSpace,
            'storageTotalSpace' => $this->storageTotalSpace,
            'xmrChannel' => $this->xmrChannel,
            'xmrPubKey' => ($this->xmrPubKey === null) ? '' : $this->xmrPubKey,
            'lastCommandSuccess' => $this->lastCommandSuccess,
            'deviceName' => $this->deviceName,
            'timeZone' => $this->timeZone,
            'overrideConfig' => ($this->overrideConfig == '') ? null : json_encode($this->overrideConfig),
            'newCmsAddress' => $this->newCmsAddress,
            'newCmsKey' => $this->newCmsKey,
            'orientation' => $this->orientation,
            'resolution' => $this->resolution,
            'commercialLicence' => $this->commercialLicence,
            'displayId' => $this->displayId
        ]);

        // Maintain the Display Group
        if ($this->hasPropertyChanged('display') || $this->hasPropertyChanged('description') || $this->hasPropertyChanged('tags') || $this->hasPropertyChanged('bandwidthLimit')) {
            $this->getLog()->debug('Display specific DisplayGroup properties need updating');

            $displayGroup = $this->displayGroupFactory->getById($this->displayGroupId);
            $displayGroup->displayGroup = $this->display;
            $displayGroup->description = $this->description;
            $displayGroup->replaceTags($this->tags);
            $displayGroup->bandwidthLimit = $this->bandwidthLimit;
            $displayGroup->save(DisplayGroup::$saveOptionsMinimum);
        }
    }

    /**
     * Get the Settings Profile for this Display
     * @param array $options
     * @return array
     * @throws \Xibo\Exception\XiboException
     */
    public function getSettings($options = [])
    {
        $options = array_merge([
            'displayOverride' => false
        ], $options);

        return $this->setConfig($options);
    }

    /**
     * @return array
     */
    public function getCommands()
    {
        if ($this->commands == null) {
            $displayProfile = $this->getDisplayProfile();

            // Set any commands
            $this->commands = $displayProfile->commands;
        }

        return $this->commands;
    }

    /**
     * Get a particular setting
     * @param string $key
     * @param mixed $default
     * @param array $options
     * @return mixed
     * @throws NotFoundException
     */
    public function getSetting($key, $default = null, $options = [])
    {
        $options = array_merge([
            'displayOverride' => true,
            'displayOnly' => false
        ], $options);

        $this->setConfig($options);

        // Find
        $return = $default;
        if ($options['displayOnly']) {
            // Only get an option if set from the override config on this display
            foreach ($this->overrideConfig as $row) {
                if ($row['name'] == $key || $row['name'] == ucfirst($key)) {
                    $return = array_key_exists('value', $row) ? $row['value'] : ((array_key_exists('default', $row)) ? $row['default'] : $default);
                    break;
                }
            }
        } else if ($options['displayOverride']) {
            // Get the option from the combined array of config
            foreach ($this->combinedConfig as $row) {
                if ($row['name'] == $key || $row['name'] == ucfirst($key)) {
                    $return = array_key_exists('value', $row) ? $row['value'] : ((array_key_exists('default', $row)) ? $row['default'] : $default);
                    break;
                }
            }
        } else {
            // Get the option from the profile only
            foreach ($this->profileConfig as $row) {
                if ($row['name'] == $key || $row['name'] == ucfirst($key)) {
                    $return = array_key_exists('value', $row) ? $row['value'] : ((array_key_exists('default', $row)) ? $row['default'] : $default);
                    break;
                }
            }
        }

        return $return;
    }

    /**
     * Set the config array
     * @param array $options
     * @return array
     * @throws NotFoundException
     */
    private function setConfig($options = [])
    {
        $options = array_merge([
            'displayOverride' => false
        ], $options);

        if ($this->profileConfig == null) {
            $this->load();

            // Get the display profile
            $displayProfile = $this->getDisplayProfile();

            // Merge in any overrides we have on our display.
            $this->profileConfig = $displayProfile->getProfileConfig();
            $this->combinedConfig = $this->mergeConfigs($this->profileConfig, $this->overrideConfig);
        }

        return ($options['displayOverride']) ? $this->combinedConfig : $this->profileConfig;
    }

    /**
     * Merge two configs
     * @param $default
     * @param $override
     * @return array
     */
    private function mergeConfigs($default, $override)
    {

        foreach ($default as &$defaultItem) {
            for ($i = 0; $i < count($override); $i++) {
                if ($defaultItem['name'] == $override[$i]['name']) {

                    // For special json fields, we need to decode, merge, encode and save instead
                    if(in_array($defaultItem['name'], ['timers', 'pictureOptions', 'lockOptions']) && isset($defaultItem['value']) && isset($override[$i]['value'])) {

                        // Decode values
                        $defaultItemValueDecoded = json_decode($defaultItem['value'], true);
                        $overrideValueDecoded = json_decode($override[$i]['value'], true);

                        // Merge values, encode and save
                        $defaultItem['value'] = json_encode(array_merge($defaultItemValueDecoded, $overrideValueDecoded));
                        break;
                    } else {
                        // merge
                        $defaultItem = array_merge($defaultItem, $override[$i]);
                        break;
                    }
                    
                }
            }
        }

        // Merge the remainder
        return $default;
    }

    /**
     * @param PoolInterface $pool
     * @return int|null
     */
    public function getCurrentLayoutId($pool)
    {
        $item = $pool->getItem('/currentLayoutId/' . $this->displayId);

        $data = $item->get();

        if ($item->isHit()) {
            $this->currentLayoutId = $data;

            try {
                $this->currentLayout = $this->layoutFactory->getById($this->currentLayoutId)->layout;
            }
            catch (NotFoundException $notFoundException) {
                // This is ok
            }
        } else {
            $this->getLog()->debug('Cache miss for setCurrentLayoutId on display ' . $this->display);
        }

        return $this->currentLayoutId;
    }

    /**
     * @param PoolInterface $pool
     * @param int $currentLayoutId
     * @return $this
     * @throws \Exception
     */
    public function setCurrentLayoutId($pool, $currentLayoutId)
    {
        // Cache it
        $this->getLog()->debug('Caching currentLayoutId with Pool');

        $item = $pool->getItem('/currentLayoutId/' . $this->displayId);
        $item->set($currentLayoutId);
        $item->expiresAfter(new \DateInterval('P1W'));

        $pool->saveDeferred($item);

        return $this;
    }

    /**
     * @param PoolInterface $pool
     * @return int|null
     */
    public function getCurrentScreenShotTime($pool)
    {
        $item = $pool->getItem('/screenShotTime/' . $this->displayId);

        return $item->get();
    }

    /**
     * @param PoolInterface $pool
     * @param string $date
     * @return $this
     * @throws \Exception
     */
    public function setCurrentScreenShotTime($pool, $date)
    {
        // Cache it
        $this->getLog()->debug('Caching currentLayoutId with Pool');

        $item = $pool->getItem('/screenShotTime/' . $this->displayId);
        $item->set($date);
        $item->expiresAfter(new \DateInterval('P1W'));

        $pool->saveDeferred($item);

        return $this;
    }
}
