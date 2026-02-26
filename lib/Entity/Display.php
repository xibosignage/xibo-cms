<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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

use Carbon\Carbon;
use OpenApi\Attributes as OA;
use Respect\Validation\Validator as v;
use Stash\Interfaces\PoolInterface;
use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Event\TriggerTaskEvent;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\FolderFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\DeadlockException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Display
 * @package Xibo\Entity
 */
#[OA\Schema]
class Display implements \JsonSerializable
{
    public static $STATUS_DONE = 1;
    public static $STATUS_DOWNLOADING = 2;
    public static $STATUS_PENDING = 3;

    use EntityTrait;
    use TagLinkTrait;

    /**
     * @var int
     */
    #[OA\Property(description: "The ID of this Display")]
    public $displayId;

    /**
     * @var int
     */
    #[OA\Property(description: "The Display Type ID of this Display")]
    public $displayTypeId;

    /**
     * @var int
     */
    #[OA\Property(description: "The Venue ID of this Display")]
    public $venueId;

    /**
     * @var string
     */
    #[OA\Property(description: "The Location Address of this Display")]
    public $address;

    /**
     * @var int
     */
    #[OA\Property(description: "Is this Display mobile?")]
    public $isMobile;

    /**
     * @var string
     */
    #[OA\Property(description: "The Languages supported in this display location")]
    public $languages;

    /**
     * @var string
     */
    #[OA\Property(description: "The type of this Display")]
    public $displayType;

    /**
     * @var int
     */
    #[OA\Property(description: "The screen size of this Display")]
    public $screenSize;

    /**
     * @var int
     */
    #[OA\Property(description: "Is this Display Outdoor?")]
    public $isOutdoor;

    /**
     * @var string
     */
    #[OA\Property(description: "The custom ID (an Id of any external system) of this Display")]
    public $customId;

    /**
     * @var double
     */
    #[OA\Property(description: "The Cost Per Play of this Display")]
    public $costPerPlay;

    /**
     * @var double
     */
    #[OA\Property(description: "The Impressions Per Play of this Display")]
    public $impressionsPerPlay;

    /**
     * @var string
     */
    #[OA\Property(description: "Optional Reference 1")]
    public $ref1;

    /**
     * @var string
     */
    #[OA\Property(description: "Optional Reference 2")]
    public $ref2;

    /**
     * @var string
     */
    #[OA\Property(description: "Optional Reference 3")]
    public $ref3;

    /**
     * @var string
     */
    #[OA\Property(description: "Optional Reference 4")]
    public $ref4;

    /**
     * @var string
     */
    #[OA\Property(description: "Optional Reference 5")]
    public $ref5;

    /**
     * @var int
     */
    #[OA\Property(description: "Flag indicating whether this Display is recording Auditing Information from XMDS")]
    public $auditingUntil;

    /**
     * @var string
     */
    #[OA\Property(description: "The Name of this Display")]
    public $display;

    /**
     * @var string
     */
    #[OA\Property(description: "The Description of this Display")]
    public $description;

    /**
     * @var int
     */
    #[OA\Property(description: "The ID of the Default Layout")]
    public $defaultLayoutId = 4;

    /**
     * @var string
     */
    #[OA\Property(description: "The Display Unique Identifier also called hardware key")]
    public $license;

    /**
     * @var int
     */
    #[OA\Property(description: "A flag indicating whether this Display is licensed or not")]
    public $licensed;
    private $currentlyLicensed;

    /**
     * @var int
     */
    #[OA\Property(description: "A flag indicating whether this Display is currently logged in")]
    public $loggedIn;

    /**
     * @var int
     */
    #[OA\Property(description: "A timestamp in CMS time for the last time the Display accessed XMDS")]
    public $lastAccessed;

    /**
     * @var int
     */
    #[OA\Property(description: "A flag indicating whether the default layout is interleaved with the Schedule")]
    public $incSchedule;

    /**
     * @var int
     */
    #[OA\Property(description: "A flag indicating whether the Display will send email alerts.")]
    public $emailAlert;

    /**
     * @var int
     */
    #[OA\Property(description: "A timeout in seconds for the Display to send email alerts.")]
    public $alertTimeout;

    /**
     * @var string
     */
    #[OA\Property(description: "The MAC Address of the Display")]
    public $clientAddress;

    /**
     * @var int
     */
    #[OA\Property(description: "The media inventory status of the Display")]
    public $mediaInventoryStatus;

    /**
     * @var string
     */
    #[OA\Property(description: "The current Mac Address of the Player")]
    public $macAddress;

    /**
     * @var int
     */
    #[OA\Property(description: "A timestamp indicating the last time the Mac Address changed")]
    public $lastChanged;

    /**
     * @var int
     */
    #[OA\Property(description: "A count of Mac Address changes")]
    public $numberOfMacAddressChanges;

    /**
     * @var int
     */
    #[OA\Property(description: "A timestamp indicating the last time a WOL command was sent")]
    public $lastWakeOnLanCommandSent;

    /**
     * @var int
     */
    #[OA\Property(description: "A flag indicating whether Wake On Lan is enabled")]
    public $wakeOnLanEnabled;

    /**
     * @var string
     */
    #[OA\Property(description: "A h:i string indicating the time to send a WOL command")]
    public $wakeOnLanTime;

    /**
     * @var string
     */
    #[OA\Property(description: "The broad cast address for this Display")]
    public $broadCastAddress;

    /**
     * @var string
     */
    #[OA\Property(description: "The secureOn WOL settings for this display.")]
    public $secureOn;

    /**
     * @var string
     */
    #[OA\Property(description: "The CIDR WOL settings for this display")]
    public $cidr;

    /**
     * @var double
     */
    #[OA\Property(description: "The display Latitude")]
    public $latitude;

    /**
     * @var double
     */
    #[OA\Property(description: "The display longitude")]
    public $longitude;

    /**
     * @var string
     */
    #[OA\Property(description: "A string representing the player type")]
    public $clientType;

    /**
     * @var string
     */
    #[OA\Property(description: "A string representing the player version")]
    public $clientVersion;

    /**
     * @var int
     */
    #[OA\Property(description: "A number representing the Player version code")]
    public $clientCode;

    /**
     * @var int
     */
    #[OA\Property(description: "The display settings profile ID for this Display")]
    public $displayProfileId;

    /**
     * @var int
     */
    #[OA\Property(description: "The current layout ID reported via XMDS")]
    public $currentLayoutId;

    /**
     * @var int
     */
    #[OA\Property(description: "A flag indicating that a screen shot should be taken by the Player")]
    public $screenShotRequested;

    /**
     * @var int
     */
    #[OA\Property(description: "The number of bytes of storage available on the device.")]
    public $storageAvailableSpace;

    /**
     * @var int
     */
    #[OA\Property(description: "The number of bytes of storage in total on the device")]
    public $storageTotalSpace;

    /**
     * @var int
     */
    #[OA\Property(description: "The ID of the Display Group for this Device")]
    public $displayGroupId;

    /**
     * @var string
     */
    #[OA\Property(description: "The current layout")]
    public $currentLayout;

    /**
     * @var string
     */
    #[OA\Property(description: "The default layout")]
    public $defaultLayout;

    /**
     * @var DisplayGroup[]
     */
    #[OA\Property(description: "The Display Groups this Display belongs to")]
    public $displayGroups = [];

    /**
     * @var string
     */
    #[OA\Property(description: "The Player Subscription Channel")]
    public $xmrChannel;

    /**
     * @var string
     */
    #[OA\Property(description: "The Player Public Key")]
    public $xmrPubKey;

    /**
     * @var int
     */
    #[OA\Property(description: "The last command success, 0 = failure, 1 = success, 2 = unknown")]
    public $lastCommandSuccess = 0;

    /**
     * @var string
     */
    #[OA\Property(description: "The Device Name for the device hardware associated with this Display")]
    public $deviceName;

    /**
     * @var string
     */
    #[OA\Property(description: "The Display Timezone, or empty to use the CMS timezone")]
    public $timeZone;

    /**
     * @var TagLink[]
     */
    #[OA\Property(description: "Tags associated with this Display, array of TagLink objects")]
    public $tags = [];

    /**
     * @var string|array
     */
    #[OA\Property(description: "The configuration options that will overwrite Display Profile Config")]
    public $overrideConfig = [];

    /**
     * @var int
     */
    #[OA\Property(description: "The display bandwidth limit")]
    public $bandwidthLimit;

    /**
     * @var string
     */
    #[OA\Property(description: "The new CMS Address")]
    public $newCmsAddress;

    /**
     * @var string
     */
    #[OA\Property(description: "The new CMS Key")]
    public $newCmsKey;

    /**
     * @var string
     */
    #[OA\Property(description: "The orientation of the Display, either landscape or portrait")]
    public $orientation;

    /**
     * @var string
     */
    #[OA\Property(description: "The resolution of the Display expressed as a string in the format WxH")]
    public $resolution;

    /**
     * @var int
     */
    #[OA\Property(description: "Status of the commercial licence for this Display. 0 - Not licensed, 1 - licensed, 2 - trial licence, 3 - not applicable")]
    public $commercialLicence;

    /**
     * @var string
     */
    #[OA\Property(description: "The TeamViewer serial number for this Display")]
    public $teamViewerSerial;

    /**
     * @var string
     */
    #[OA\Property(description: "The Webkey serial number for this Display")]
    public $webkeySerial;

    /**
     * @var string
     */
    #[OA\Property(description: "A comma separated list of groups/users with permissions to this Display")]
    public $groupsWithPermissions;

    /**
     * @var string
     */
    #[OA\Property(description: "The datetime this entity was created")]
    public $createdDt;

    /**
     * @var string
     */
    #[OA\Property(description: "The datetime this entity was last modified")]
    public $modifiedDt;

    /**
     * @var int
     */
    #[OA\Property(description: "The id of the Folder this Display belongs to")]
    public $folderId;

    /**
     * @var int
     */
    #[OA\Property(description: "The id of the Folder responsible for providing permissions for this Display")]
    public $permissionsFolderId;

    /**
     * @var int
     */
    #[OA\Property(description: "The count of Player reported faults")]
    public $countFaults;

    /**
     * @var string
     */
    #[OA\Property(description: "LAN IP Address, if available on the Player")]
    public $lanIpAddress;

    /**
     * @var int
     */
    #[OA\Property(description: "The Display Group ID this Display is synced to")]
    public $syncGroupId;

    /**
     * @var string
     */
    #[OA\Property(description: "The OS version of the Display")]
    public $osVersion;

    /**
     * @var string
     */
    #[OA\Property(description: "The SDK version of the Display")]
    public $osSdk;

    /**
     * @var string
     */
    #[OA\Property(description: "The manufacturer of the Display")]
    public $manufacturer;

    /**
     * @var string
     */
    #[OA\Property(description: "The brand of the Display")]
    public $brand;

    /**
     * @var string
     */
    #[OA\Property(description: "The model of the Display")]
    public $model;

    /** @var array The configuration from the Display Profile  */
    private $profileConfig;

    /** @var array Combined config */
    private $combinedConfig;

    /** @var \Xibo\Entity\DisplayProfile the resolved DisplayProfile for this Display */
    private $_displayProfile;

    private $datesToFormat = ['auditingUntil'];

    /**
     * Commands
     * @var array[Command]
     */
    private $commands = null;

    public static $saveOptionsMinimum = ['validate' => false, 'audit' => false, 'setModifiedDt' => false];

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

    /** @var FolderFactory */
    private $folderFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param ConfigServiceInterface $config
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DisplayProfileFactory $displayProfileFactory
     * @param DisplayFactory $displayFactory
     */
    public function __construct($store, $log, $dispatcher, $config, $displayGroupFactory, $displayProfileFactory, $displayFactory, $folderFactory)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
        $this->excludeProperty('mediaInventoryXml');
        $this->setPermissionsClass('Xibo\Entity\DisplayGroup');
        $this->setCanChangeOwner(false);

        $this->config = $config;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->displayProfileFactory = $displayProfileFactory;
        $this->displayFactory = $displayFactory;
        $this->folderFactory = $folderFactory;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->displayGroupId;
    }

    public function getPermissionFolderId()
    {
        return $this->permissionsFolderId;
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
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getDisplayProfile(): DisplayProfile
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
            } catch (NotFoundException) {
                $this->getLog()->error('getDisplayProfile: Cannot get display profile, '
                    . $this->clientType . ' not found.');

                $displayProfile = $this->displayProfileFactory->getUnknownProfile($this->clientType);
            }

            // Set our display profile
            $this->_displayProfile = $displayProfile;
        }

        return $this->_displayProfile;
    }

    /**
     * @return array
     */
    public function getLanguages()
    {
        return empty($this->languages) ? [] : explode(',', $this->languages);
    }

    /**
     * @return bool true is this display is a PWA
     */
    public function isPwa(): bool
    {
        return $this->clientType === 'chromeOS';
    }

    /**
     * @return bool true is this display supports WebSocket XMR
     */
    public function isWebSocketXmrSupported(): bool
    {
        return $this->clientType === 'chromeOS'
            || ($this->clientType === 'windows' && $this->clientCode >= 406)
            || ($this->clientType === 'android' && $this->clientCode >= 408);
    }

    /**
     * Is this display auditing?
     * return bool
     */
    public function isAuditing(): bool
    {
        $this->getLog()->debug(sprintf(
            'Testing whether this display is auditing. %d vs %d.',
            $this->auditingUntil,
            Carbon::now()->format('U')
        ));

        // Test $this->auditingUntil against the current date.
        return (!empty($this->auditingUntil) && $this->auditingUntil >= Carbon::now()->format('U'));
    }

    /**
     * Does this display has elevated log level?
     * @return bool
     * @throws NotFoundException
     */
    public function isElevatedLogging(): bool
    {
        $elevatedUntil = $this->getSetting('elevateLogsUntil', 0);

        $this->getLog()->debug(sprintf(
            'Testing whether this display has elevated log level. %d vs %d.',
            $elevatedUntil,
            Carbon::now()->format('U')
        ));

        return (!empty($elevatedUntil) && $elevatedUntil >= Carbon::now()->format('U'));
    }

    /**
     * Get current log level for this Display
     * @return string
     * @throws NotFoundException
     */
    public function getLogLevel(): string
    {
        $restingLogLevel = $this->getSetting('logLevel', 'error');
        $isElevated = $this->isElevatedLogging();

        return $isElevated ? 'audit' : $restingLogLevel;
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
        if (!v::stringType()->notEmpty()->validate($this->display)) {
            throw new InvalidArgumentException(__('Can not have a display without a name'), 'name');
        }

        if (!v::stringType()->notEmpty()->validate($this->license)) {
            throw new InvalidArgumentException(__('Can not have a display without a hardware key'), 'license');
        }

        if ($this->wakeOnLanEnabled == 1 && $this->wakeOnLanTime == '') {
            throw new InvalidArgumentException(
                __('Wake on Lan is enabled, but you have not specified a time to wake the display'),
                'wakeonlan'
            );
        }
        // Broadcast Address
        if ($this->broadCastAddress != '' && !v::ip()->validate($this->broadCastAddress)) {
            throw new InvalidArgumentException(
                __('BroadCast Address is not a valid IP Address'),
                'broadCastAddress'
            );
        }

        // CIDR
        if (!empty($this->cidr) && !v::numeric()->between(0, 32)->validate($this->cidr)) {
            throw new InvalidArgumentException(
                __('CIDR subnet mask is not a number within the range of 0 to 32.'),
                'cidr'
            );
        }

        // secureOn
        if ($this->secureOn != '') {
            $this->secureOn = strtoupper($this->secureOn);
            $this->secureOn = str_replace(':', '-', $this->secureOn);

            if ((!preg_match('/([A-F0-9]{2}[-]){5}([0-9A-F]){2}/', $this->secureOn))
                || (strlen($this->secureOn) != 17)
            ) {
                throw new InvalidArgumentException(
                    __('Pattern of secureOn-password is not "xx-xx-xx-xx-xx-xx" (x = digit or CAPITAL letter)'),
                    'secureOn'
                );
            }
        }

        // Mac Address Changes
        if ($this->hasPropertyChanged('macAddress')) {
            // Mac address change detected
            $this->numberOfMacAddressChanges++;
            $this->lastChanged = Carbon::now()->format('U');
        }

        // Lat/Long
        if (!empty($this->longitude) && !v::longitude()->validate($this->longitude)) {
            throw new InvalidArgumentException(__('The longitude entered is not valid.'), 'longitude');
        }

        if (!empty($this->latitude) && !v::latitude()->validate($this->latitude)) {
            throw new InvalidArgumentException(__('The latitude entered is not valid.'), 'latitude');
        }

        if ($this->bandwidthLimit !== null && !v::intType()->min(0)->validate($this->bandwidthLimit)) {
            throw new InvalidArgumentException(
                __('Bandwidth limit must be a whole number greater than 0.'),
                'bandwidthLimit'
            );
        }

        // do we have default Layout set?
        if (empty($this->defaultLayoutId)) {
            // do we have global default Layout ?
            $globalDefaultLayoutId = $this->config->getSetting('DEFAULT_LAYOUT');
            if (!empty($globalDefaultLayoutId)) {
                $this->getLog()->debug(
                    'No default Layout set on Display ID '
                    . $this->displayId
                    . ' falling back to global default Layout.'
                );
                $this->defaultLayoutId = $globalDefaultLayoutId;
                $this->notify();
            } else {
                // we have no Default Layout and no global Default Layout
                $this->getLog()->error(
                    'No global default Layout set and no default Layout set for Display ID ' . $this->displayId
                );
                throw new InvalidArgumentException(
                    __('Please set a Default Layout directly on this Display or in CMS Administrator Settings'),
                    'defaultLayoutId'
                );
            }
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
            $this->getLog()->debug(sprintf('Testing authorised displays against %d maximum. Currently authorised = %d, authorised = %d.', $maxDisplays, $this->currentlyLicensed, $this->licensed));

            if ($this->currentlyLicensed != $this->licensed && $this->licensed == 1) {
                $countLicensed = $this->getStore()->select('SELECT COUNT(DisplayID) AS CountLicensed FROM display WHERE licensed = 1', []);

                $this->getLog()->debug(sprintf('There are %d authorised displays and we the maximum is %d', $countLicensed[0]['CountLicensed'], $maxDisplays));

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
     * @throws GeneralException
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true,
            'audit' => true,
            'checkDisplaySlotAvailability' => true,
            'setModifiedDt' => true,
        ], $options);

        if ($options['validate']) {
            $this->validate();
        }

        if ($options['checkDisplaySlotAvailability']) {
            // Check if there are display slots available
            $maxDisplays = $this->config->GetSetting('MAX_LICENSED_DISPLAYS');

            if (!$this->isDisplaySlotAvailable()) {
                throw new InvalidArgumentException(sprintf(
                    __('You have exceeded your maximum number of authorised displays. %d'),
                    $maxDisplays
                ), 'maxDisplays');
            }
        }

        if ($this->displayId == null || $this->displayId == 0) {
            $this->add();
        } else {
            $this->edit($options);
        }

        if ($options['audit'] && $this->getChangedProperties() != []) {
            $this->getLog()->audit('Display', $this->displayId, 'Display Saved', $this->getChangedProperties());
        }

        // Trigger an update of all dynamic DisplayGroups?
        if ($this->hasPropertyChanged('display') || $this->hasPropertyChanged('tags')) {
            // Background update.
            $this->getDispatcher()->dispatch(
                new TriggerTaskEvent('\Xibo\XTR\MaintenanceRegularTask', 'DYNAMIC_DISPLAY_GROUP_ASSESSED'),
                TriggerTaskEvent::$NAME
            );
        }
    }

    /**
     * Delete
     * @throws GeneralException
     */
    public function delete()
    {
        $this->load();

        // Delete references
        $this->getStore()->update('DELETE FROM `display_media` WHERE displayId = :displayId', [
            'displayId' => $this->displayId
        ]);
        $this->getStore()->update('DELETE FROM `requiredfile` WHERE displayId = :displayId', [
            'displayId' => $this->displayId
        ]);
        $this->getStore()->update('DELETE FROM `player_faults` WHERE displayId = :displayId', [
            'displayId' => $this->displayId
        ]);
        $this->getStore()->update('DELETE FROM `schedule_sync` WHERE displayId = :displayId', [
            'displayId' => $this->displayId
        ]);

        // Remove our display from any groups it is assigned to
        foreach ($this->displayGroups as $displayGroup) {
            $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);
            $displayGroup->load();
            $displayGroup->unassignDisplay($this);
            $displayGroup->save(['validate' => false, 'manageDynamicDisplayLinks' => false, 'allowNotify' => false]);
        }

        // Delete our display specific group
        $displayGroup = $this->displayGroupFactory->getById($this->displayGroupId);
        $this->getDispatcher()->dispatch(new DisplayGroupLoadEvent($displayGroup), DisplayGroupLoadEvent::$NAME);
        $displayGroup->delete();

        // Delete the display
        $this->getStore()->update('DELETE FROM `display` WHERE displayId = :displayId', [
            'displayId' => $this->displayId
        ]);

        $this->getLog()->audit('Display', $this->displayId, 'Display Deleted', [
            'displayId' => $this->displayId,
            'display' => $this->display,
        ]);
    }

    /**
     * @throws GeneralException
     * @throws NotFoundException
     */
    private function add()
    {
        $this->displayId = $this->getStore()->insert('
            INSERT INTO display (display, auditingUntil, defaultlayoutid, license, licensed, lastAccessed, inc_schedule, email_alert, alert_timeout, clientAddress, xmrChannel, xmrPubKey, lastCommandSuccess, macAddress, lastChanged, lastWakeOnLanCommandSent, client_type, client_version, client_code, overrideConfig, newCmsAddress, newCmsKey, commercialLicence, lanIpAddress, syncGroupId, osVersion, osSdk, manufacturer, brand, model)
              VALUES (:display, :auditingUntil, :defaultlayoutid, :license, :licensed, :lastAccessed, :inc_schedule, :email_alert, :alert_timeout, :clientAddress, :xmrChannel, :xmrPubKey, :lastCommandSuccess, :macAddress, :lastChanged, :lastWakeOnLanCommandSent, :clientType, :clientVersion, :clientCode, :overrideConfig, :newCmsAddress, :newCmsKey, :commercialLicence, :lanIpAddress, :syncGroupId, :osVersion, :osSdk, :manufacturer, :brand, :model)
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
            'commercialLicence' => $this->commercialLicence,
            'lanIpAddress' => empty($this->lanIpAddress) ? null : $this->lanIpAddress,
            'syncGroupId' => empty($this->syncGroupId) ? null : $this->syncGroupId,
            'osVersion' => $this->osVersion,
            'osSdk' => $this->osSdk,
            'manufacturer' => $this->manufacturer,
            'brand' => $this->brand,
            'model' => $this->model,
        ]);


        $displayGroup = $this->displayGroupFactory->create();
        $displayGroup->displayGroup = $this->display;
        $displayGroup->tags = $this->tags;

        // this is added from xmds, by default new displays will end up in root folder.
        // Can be overridden per DISPLAY_DEFAULT_FOLDER setting
        $folderId = $this->folderId ?? 1;

        // If folderId is not set to Root Folder
        // We need to check what permissionsFolderId should be set on the Display Group
        if ($folderId !== 1) {
            // just in case protect against no longer existing Folder.
            try {
                $folder = $this->folderFactory->getById($folderId, 0);

                $displayGroup->folderId = $folder->getId();
                $displayGroup->permissionsFolderId = $folder->getPermissionFolderIdOrThis();
            } catch (NotFoundException $e) {
                $this->getLog()->error('Display Default Folder no longer exists');

                // if the Folder from settings no longer exists, default to Root Folder.
                $displayGroup->folderId = 1;
                $displayGroup->permissionsFolderId = 1;
            }
        } else {
            $displayGroup->folderId = 1;
            $displayGroup->permissionsFolderId = 1;
        }

        $displayGroup->setDisplaySpecificDisplay($this);

        $this->getLog()->debug('Creating display specific group with userId ' . $displayGroup->userId);

        $displayGroup->save();
    }


    /**
     * @throws GeneralException
     * @throws NotFoundException
     */
    private function edit($options = [])
    {
        $this->getStore()->update('
            UPDATE display
                SET display = :display,
                    defaultlayoutid = :defaultLayoutId,
                    displayTypeId = :displayTypeId,
                    venueId = :venueId,
                    address = :address,
                    isMobile = :isMobile,
                    languages = :languages,
                    screenSize = :screenSize,
                    isOutdoor = :isOutdoor,
                    `customId` = :customId,
                    costPerPlay = :costPerPlay,
                    impressionsPerPlay = :impressionsPerPlay,
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
                    osVersion = :osVersion,
                    osSdk = :osSdk,
                    manufacturer = :manufacturer,
                    brand = :brand,
                    model = :model,
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
                    `commercialLicence` = :commercialLicence,
                    `teamViewerSerial` = :teamViewerSerial,
                    `webkeySerial` = :webkeySerial,
                    `lanIpAddress` = :lanIpAddress,
                    `syncGroupId` = :syncGroupId
             WHERE displayid = :displayId
        ', [
            'display' => $this->display,
            'defaultLayoutId' => $this->defaultLayoutId,
            'displayTypeId' => $this->displayTypeId === 0 ? null : $this->displayTypeId,
            'venueId' => $this->venueId === 0 ? null : $this->venueId,
            'address' => $this->address,
            'isMobile' => $this->isMobile,
            'languages' => $this->languages,
            'screenSize' => $this->screenSize,
            'isOutdoor' => $this->isOutdoor,
            'customId' => $this->customId,
            'costPerPlay' => $this->costPerPlay,
            'impressionsPerPlay' => $this->impressionsPerPlay,
            'incSchedule' => ($this->incSchedule == null) ? 0 : $this->incSchedule,
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
            'teamViewerSerial' => empty($this->teamViewerSerial) ? null : $this->teamViewerSerial,
            'webkeySerial' => empty($this->webkeySerial) ? null : $this->webkeySerial,
            'lanIpAddress' => empty($this->lanIpAddress) ? null : $this->lanIpAddress,
            'syncGroupId' => empty($this->syncGroupId) ? null : $this->syncGroupId,
            'displayId' => $this->displayId,
            'osVersion' => $this->osVersion,
            'osSdk' => $this->osSdk,
            'manufacturer' => $this->manufacturer,
            'brand' => $this->brand,
            'model' => $this->model,
        ]);

        // Maintain the Display Group
        if ($this->hasPropertyChanged('display')
            || $this->hasPropertyChanged('description')
            || $this->hasPropertyChanged('tags')
            || $this->hasPropertyChanged('bandwidthLimit')
            || $this->hasPropertyChanged('folderId')
            || $this->hasPropertyChanged('ref1')
            || $this->hasPropertyChanged('ref2')
            || $this->hasPropertyChanged('ref3')
            || $this->hasPropertyChanged('ref4')
            || $this->hasPropertyChanged('ref5')
        ) {
            $this->getLog()->debug('Display specific DisplayGroup properties need updating');

            $displayGroup = $this->displayGroupFactory->getById($this->displayGroupId);
            $displayGroup->load();
            $displayGroup->displayGroup = $this->display;
            $displayGroup->description = $this->description;
            $displayGroup->bandwidthLimit = $this->bandwidthLimit;
            $displayGroup->ref1 = $this->ref1;
            $displayGroup->ref2 = $this->ref2;
            $displayGroup->ref3 = $this->ref3;
            $displayGroup->ref4 = $this->ref4;
            $displayGroup->ref5 = $this->ref5;

            // Tags
            $saveTags = false;
            if ($this->hasPropertyChanged('tags')) {
                $saveTags = true;
                $displayGroup->updateTagLinks($this->tags);
            }

            // If the folderId has changed, we should check this user has permissions to the new folderId
            // it shouldn't ever be null, but just in case.
            $displayGroup->folderId = ($this->folderId == null) ? 1 : $this->folderId;
            if ($this->hasPropertyChanged('folderId')) {
                $folder = $this->folderFactory->getById($displayGroup->folderId, 0);
                // We have permission, so assert the new folder's permission id
                $displayGroup->permissionsFolderId = $folder->getPermissionFolderIdOrThis();
            }

            // manageDisplayLinks is false because we never update a display specific display group's display links
            $displayGroup->save([
                'validate' => false,
                'saveGroup' => true,
                'manageLinks' => false,
                'manageDisplayLinks' => false,
                'manageDynamicDisplayLinks' => false,
                'allowNotify' => true,
                'saveTags' => $saveTags,
                'setModifiedDt' => $options['setModifiedDt'],
            ]);
        } else if ($options['setModifiedDt']) {
            // Bump the modified date.
            $this->store->update('
                UPDATE displaygroup 
                    SET `modifiedDt` = :modifiedDt
                 WHERE displayGroupId = :displayGroupId
            ', [
                'modifiedDt' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
                'displayGroupId' => $this->displayGroupId
            ]);
        }
    }

    /**
     * Get the Settings Profile for this Display
     * @param array $options
     * @return array
     * @throws GeneralException
     */
    public function getSettings($options = [])
    {
        $options = array_merge([
            'displayOverride' => false
        ], $options);

        return $this->setConfig($options);
    }

    /**
     * @return Command[]
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
    private function setConfig(array $options = []): array
    {
        $options = array_merge([
            'displayOverride' => false
        ], $options);

        if ($this->profileConfig == null) {
            $this->load();

            // Get the display profile
            try {
                $displayProfile = $this->getDisplayProfile();
            } catch (NotFoundException) {
                $displayProfile = $this->displayProfileFactory->getUnknownProfile($this->clientType);
            }

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
    private function mergeConfigs($default, $override): array
    {
        // No overrides, then nothing to do.
        if (empty($override) || !is_array($override)) {
            return $default;
        }

        // Merge the settings together
        foreach ($default as &$defaultItem) {
            for ($i = 0; $i < count($override); $i++) {
                if ($defaultItem['name'] == $override[$i]['name']) {
                    // For special json fields, we need to decode, merge, encode and save instead
                    if (in_array($defaultItem['name'], ['timers', 'pictureOptions', 'lockOptions'])
                        && isset($defaultItem['value']) && isset($override[$i]['value'])
                    ) {
                        // Decode values
                        $defaultItemValueDecoded = json_decode($defaultItem['value'], true);
                        $overrideValueDecoded = json_decode($override[$i]['value'], true);

                        // Merge values, encode and save
                        $defaultItem['value'] = json_encode(array_merge(
                            $defaultItemValueDecoded,
                            $overrideValueDecoded
                        ));
                    } else {
                        // merge
                        $defaultItem = array_merge($defaultItem, $override[$i]);
                    }
                    break;
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
    public function getCurrentLayoutId($pool, LayoutFactory $layoutFactory)
    {
        $item = $pool->getItem('/currentLayoutId/' . $this->displayId);

        $data = $item->get();

        if ($item->isHit()) {
            $this->currentLayoutId = $data;

            try {
                $this->currentLayout = $layoutFactory->getById($this->currentLayoutId)->layout;
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

    /**
     * @param PoolInterface $pool
     * @return array
     */
    public function getStatusWindow($pool)
    {
        $item = $pool->getItem('/statusWindow/' . $this->displayId);

        if ($item->isMiss()) {
            return [];
        } else {
            // special handling for Android
            if ($this->clientType === 'android') {
                return nl2br($item->get());
            } else {
                return $item->get();
            }
        }
    }

    /**
     * @param PoolInterface $pool
     * @param array $status
     * @return $this
     */
    public function setStatusWindow($pool, $status)
    {
        // Cache it
        $this->getLog()->debug('Caching statusWindow with Pool');

        $item = $pool->getItem('/statusWindow/' . $this->displayId);
        $item->set($status);
        $item->expiresAfter(new \DateInterval('P1D'));

        $pool->saveDeferred($item);

        return $this;
    }

    /**
     * Check if this Display is set as Lead Display on any Sync Group
     * @return bool
     */
    public function isLead(): bool
    {
        $syncGroups = $this->getStore()->select(
            'SELECT syncGroupId FROM `syncgroup` WHERE `syncgroup`.leadDisplayId = :displayId',
            ['displayId' => $this->displayId]
        );

        return count($syncGroups) > 0;
    }
}
