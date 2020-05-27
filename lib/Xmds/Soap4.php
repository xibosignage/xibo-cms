<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2014 Daniel Garner
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
namespace Xibo\Xmds;

use Intervention\Image\ImageManagerStatic as Img;
use Jenssegers\Date\Date;
use Xibo\Entity\Bandwidth;
use Xibo\Entity\Display;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Helper\Random;

/**
 * Class Soap4
 * @package Xibo\Xmds
 */
class Soap4 extends Soap
{
    /**
     * Registers a new display
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $displayName
     * @param string $clientType
     * @param string $clientVersion
     * @param int $clientCode
     * @param string $operatingSystem
     * @param string $macAddress
     * @return string
     * @throws \SoapFault
     */
    public function RegisterDisplay($serverKey, $hardwareKey, $displayName, $clientType, $clientVersion, $clientCode, $operatingSystem, $macAddress, $xmrChannel = null, $xmrPubKey = null)
    {
        $this->logProcessor->setRoute('RegisterDisplay');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);
        $displayName = $this->getSanitizer()->string($displayName);
        $clientType = $this->getSanitizer()->string($clientType);
        $clientVersion = $this->getSanitizer()->string($clientVersion);
        $clientCode = $this->getSanitizer()->int($clientCode);
        $macAddress = $this->getSanitizer()->string($macAddress);
        $clientAddress = $this->getIp();

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Check the Length of the hardwareKey
        if (strlen($hardwareKey) > 40)
            throw new \SoapFault('Sender', 'The Hardware Key you sent was too long. Only 40 characters are allowed (SHA1).');

        // Return an XML formatted string
        $return = new \DOMDocument('1.0');
        $displayElement = $return->createElement('display');
        $return->appendChild($displayElement);

        // Check in the database for this hardwareKey
        try {
            $display = $this->displayFactory->getByLicence($hardwareKey);
            $this->display = $display;

            $this->logProcessor->setDisplay($display->displayId, ($display->isAuditing()));

            // Audit in
            $this->getLog()->debug('serverKey: ' . $serverKey . ', hardwareKey: ' . $hardwareKey . ', displayName: ' . $displayName . ', macAddress: ' . $macAddress);

            // Now
            $dateNow = $this->getDate()->parse();

            // Append the time
            $displayElement->setAttribute('date', $this->getDate()->getLocalDate($dateNow));
            $displayElement->setAttribute('timezone', $this->getConfig()->getSetting('defaultTimezone'));

            // Determine if we are licensed or not
            if ($display->licensed == 0) {
                // It is not authorised
                $displayElement->setAttribute('status', 2);
                $displayElement->setAttribute('code', 'WAITING');
                $displayElement->setAttribute('message', 'Display is awaiting licensing approval from an Administrator.');

            } else {
                // It is licensed
                $displayElement->setAttribute('status', 0);
                $displayElement->setAttribute('code', 'READY');
                $displayElement->setAttribute('message', 'Display is active and ready to start.');

                // Display Settings
                $settings = $this->display->getSettings(['displayOverride' => true]);

                // Create the XML nodes
                foreach ($settings as $arrayItem) {
                    // Upper case the setting name for windows
                    $settingName = ($clientType == 'windows') ? ucfirst($arrayItem['name']) : $arrayItem['name'];

                    $node = $return->createElement($settingName, (isset($arrayItem['value']) ? $arrayItem['value'] : $arrayItem['default']));

                    if (isset($arrayItem['type'])) {
                        $node->setAttribute('type', $arrayItem['type']);
                    }

                    // Patch download and update windows to make sure they are unix time stamps
                    // XMDS schema 4 sent down unix time
                    // https://github.com/xibosignage/xibo/issues/1791
                    if (strtolower($arrayItem['name']) == 'downloadstartwindow'
                        || strtolower($arrayItem['name']) == 'downloadendwindow'
                        || strtolower($arrayItem['name']) == 'updatestartwindow'
                        || strtolower($arrayItem['name']) == 'updateendwindow'
                    ) {
                        // Split by :
                        $timeParts = explode(':', $arrayItem['value']);
                        if ($timeParts[0] == '00' && $timeParts[1] == '00') {
                            $arrayItem['value'] = 0;
                        } else {
                            $arrayItem['value'] = Date::now()->setTime(intval($timeParts[0]), intval($timeParts[1]));
                        }
                    }

                    $node = $return->createElement($arrayItem['name'], (isset($arrayItem['value']) ? $arrayItem['value'] : $arrayItem['default']));
                    $node->setAttribute('type', $arrayItem['type']);
                    $displayElement->appendChild($node);
                }

                // Player upgrades
                $version = '';
                try {
                    $upgradeMediaId = $this->display->getSetting('versionMediaId', null, ['displayOverride' => true]);

                    if ($clientType != 'windows' && $upgradeMediaId != null) {
                        $version = $this->playerVersionFactory->getByMediaId($upgradeMediaId);

                        if ($clientType == 'android') {
                            $version = json_encode([
                                'id' => $upgradeMediaId,
                                'file' => $version->storedAs,
                                'code' => $version->code
                            ]);
                        } elseif ($clientType == 'lg') {
                            $version = json_encode([
                                'id' => $upgradeMediaId,
                                'file' => $version->storedAs,
                                'code' => $version->code
                            ]);
                        } elseif ($clientType == 'sssp') {
                            // Create a nonce and store it in the cache for this display.
                            $nonce = Random::generateString();
                            $cache = $this->getPool()->getItem('/playerVersion/' . $nonce);
                            $cache->set($this->display->displayId);
                            $cache->expiresAfter(86400);
                            $this->getPool()->saveDeferred($cache);

                            $version = json_encode([
                                'id' => $upgradeMediaId,
                                'file' => $version->storedAs,
                                'code' => $version->code,
                                'url' => str_replace('/xmds.php', '', Wsdl::getRoot()) . '/playersoftware/' . $nonce
                            ]);
                        }
                    }
                } catch (NotFoundException $notFoundException) {
                    $this->getLog()->error('Non-existing version set on displayId ' . $this->display->displayId);
                }

                $displayElement->setAttribute('version_instructions', $version);

                // Add some special settings
                $nodeName = ($clientType == 'windows') ? 'DisplayName' : 'displayName';
                $node = $return->createElement($nodeName);
                $node->appendChild($return->createTextNode($display->display));
                $node->setAttribute('type', 'string');
                $displayElement->appendChild($node);

                $nodeName = ($clientType == 'windows') ? 'ScreenShotRequested' : 'screenShotRequested';
                $node = $return->createElement($nodeName, $display->screenShotRequested);
                $node->setAttribute('type', 'checkbox');
                $displayElement->appendChild($node);

                $nodeName = ($clientType == 'windows') ? 'DisplayTimeZone' : 'displayTimeZone';
                $node = $return->createElement($nodeName, (!empty($display->timeZone)) ? $display->timeZone : '');
                $node->setAttribute('type', 'string');
                $displayElement->appendChild($node);

                if (!empty($display->timeZone)) {
                    // Calculate local time
                    $dateNow->timezone($display->timeZone);

                    // Append Local Time
                    $displayElement->setAttribute('localDate', $this->getDate()->getLocalDate($dateNow));
                }
            }

        } catch (NotFoundException $e) {

            // Add a new display
            try {
                $display = $this->displayFactory->createEmpty();
                $this->display = $display;
                $display->display = $displayName;
                $display->auditingUntil = 0;
                $display->defaultLayoutId = $this->getConfig()->getSetting('DEFAULT_LAYOUT');
                $display->license = $hardwareKey;
                $display->licensed = $this->getConfig()->getSetting('DISPLAY_AUTO_AUTH', 0);
                $display->incSchedule = 0;
                $display->clientAddress = $this->getIp();

                if (!$display->isDisplaySlotAvailable()) {
                    $display->licensed = 0;
                }
            }
            catch (\InvalidArgumentException $e) {
                throw new \SoapFault('Sender', $e->getMessage());
            }

            $displayElement->setAttribute('status', 1);
            $displayElement->setAttribute('code', 'ADDED');
            if ($display->licensed == 0)
                $displayElement->setAttribute('message', 'Display added and is awaiting licensing approval from an Administrator.');
            else
                $displayElement->setAttribute('message', 'Display is active and ready to start.');
        }

        // Send Notification if required
        $this->alertDisplayUp();

        $display->lastAccessed = time();
        $display->loggedIn = 1;
        $display->clientAddress = $clientAddress;
        $display->macAddress = $macAddress;
        $display->clientType = $clientType;
        $display->clientVersion = $clientVersion;
        $display->clientCode = $clientCode;
        //$display->operatingSystem = $operatingSystem;
        $display->save(['validate' => false, 'audit' => false]);

        // Log Bandwidth
        $returnXml = $return->saveXML();
        $this->logBandwidth($display->displayId, Bandwidth::$REGISTER, strlen($returnXml));

        // Audit our return
        $this->getLog()->debug($returnXml);

        return $returnXml;
    }

    /**
     * Returns a string containing the required files xml for the requesting display
     * @param string $serverKey The Server Key
     * @param string $hardwareKey Display Hardware Key
     * @return string $requiredXml Xml Formatted String
     * @throws \SoapFault
     */
    function RequiredFiles($serverKey, $hardwareKey)
    {
        $httpDownloads = ($this->getConfig()->getSetting('SENDFILE_MODE') != 'Off');
        return $this->doRequiredFiles($serverKey, $hardwareKey, $httpDownloads);
    }

    /**
     * Get File
     * @param string $serverKey The ServerKey for this CMS
     * @param string $hardwareKey The HardwareKey for this Display
     * @param int $fileId The ID
     * @param string $fileType The File Type
     * @param int $chunkOffset The Offset of the Chunk Requested
     * @param string $chunkSize The Size of the Chunk Requested
     * @return mixed
     * @throws \SoapFault
     */
    function GetFile($serverKey, $hardwareKey, $fileId, $fileType, $chunkOffset, $chunkSize)
    {
        $this->logProcessor->setRoute('GetFile');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);
        $fileId = $this->getSanitizer()->int($fileId);
        $fileType = $this->getSanitizer()->string($fileType);
        $chunkOffset = $this->getSanitizer()->int($chunkOffset);
        $chunkSize = $this->getSanitizer()->int($chunkSize);

        $libraryLocation = $this->getConfig()->getSetting("LIBRARY_LOCATION");

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');
        }

        // Authenticate this request...
        if (!$this->authDisplay($hardwareKey)) {
            throw new \SoapFault('Receiver', "This Display is not authorised.");
        }

        // Now that we authenticated the Display, make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth($this->display->displayId)) {
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");
        }

        if ($this->display->isAuditing()) {
            $this->getLog()->debug('hardwareKey: ' . $hardwareKey . ', fileId: ' . $fileId . ', fileType: ' . $fileType . ', chunkOffset: ' . $chunkOffset . ', chunkSize: ' . $chunkSize);
        }

        try {
            if ($fileType == "layout") {
                $fileId = $this->getSanitizer()->int($fileId);

                // Validate the nonce
                $requiredFile = $this->requiredFileFactory->getByDisplayAndLayout($this->display->displayId, $fileId);

                // Load the layout
                $layout = $this->layoutFactory->getById($fileId);
                $path = $layout->xlfToDisk();

                $file = file_get_contents($path);
                $chunkSize = filesize($path);

                $requiredFile->bytesRequested = $requiredFile->bytesRequested + $chunkSize;
                $requiredFile->save();

            } else if ($fileType == "media") {
                // Validate the nonce
                $requiredFile = $this->requiredFileFactory->getByDisplayAndMedia($this->display->displayId, $fileId);

                $media = $this->mediaFactory->getById($fileId);
                $this->getLog()->debug(json_encode($media));

                if (!file_exists($libraryLocation . $media->storedAs))
                    throw new NotFoundException('Media exists but file missing from library. ' . $libraryLocation);

                // Return the Chunk size specified
                if (!$f = fopen($libraryLocation . $media->storedAs, 'r'))
                    throw new NotFoundException('Unable to get file pointer');

                fseek($f, $chunkOffset);

                $file = fread($f, $chunkSize);

                // Store file size for bandwidth log
                $chunkSize = strlen($file);

                if ($chunkSize === 0)
                    throw new NotFoundException('Empty file');

                $requiredFile->bytesRequested = $requiredFile->bytesRequested + $chunkSize;
                $requiredFile->save();

            } else {
                throw new NotFoundException('Unknown FileType Requested.');
            }
        }
        catch (NotFoundException $e) {
            $this->getLog()->error('Not found FileId: ' . $fileId . '. FileType: ' . $fileType . '. ' . $e->getMessage());
            throw new \SoapFault('Receiver', 'Requested an invalid file.');
        }

        // Log Bandwidth
        $this->logBandwidth($this->display->displayId, Bandwidth::$GETFILE, $chunkSize);

        return $file;
    }

    /**
     * Returns the schedule for the hardware key specified
     * @return string
     * @param string $serverKey
     * @param string $hardwareKey
     * @throws \SoapFault
     */
    function Schedule($serverKey, $hardwareKey)
    {
        return $this->doSchedule($serverKey, $hardwareKey);
    }

    /**
     * Black List
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $mediaId
     * @param string $type
     * @param string $reason
     * @return bool
     * @throws \SoapFault
     */
    function BlackList($serverKey, $hardwareKey, $mediaId, $type, $reason)
    {
        return $this->doBlackList($serverKey, $hardwareKey, $mediaId, $type, $reason);
    }

    /**
     * Submit client logging
     * @return bool
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $logXml
     * @throws \SoapFault
     */
    function SubmitLog($serverKey, $hardwareKey, $logXml)
    {
        return $this->doSubmitLog($serverKey, $hardwareKey, $logXml);
    }

    /**
     * Submit display statistics to the server
     * @return bool
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $statXml
     * @throws \SoapFault
     */
    function SubmitStats($serverKey, $hardwareKey, $statXml)
    {
        return $this->doSubmitStats($serverKey, $hardwareKey, $statXml);
    }

    /**
     * Store the media inventory for a client
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $inventory
     * @return bool
     * @throws \SoapFault
     */
    public function MediaInventory($serverKey, $hardwareKey, $inventory)
    {
        return $this->doMediaInventory($serverKey, $hardwareKey, $inventory);
    }

    /**
     * Gets additional resources for assigned media
     * @param string $serverKey
     * @param string $hardwareKey
     * @param int $layoutId
     * @param string $regionId
     * @param string $mediaId
     * @return mixed
     * @throws \SoapFault
     */
    function GetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId)
    {
        return $this->doGetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId);
    }

    /**
     * Notify Status
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $status
     * @return bool
     * @throws \SoapFault
     */
    public function NotifyStatus($serverKey, $hardwareKey, $status)
    {
        $this->logProcessor->setRoute('NotifyStatus');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');
        }

        // Auth this request...
        if (!$this->authDisplay($hardwareKey)) {
            throw new \SoapFault('Receiver', 'This Display is not authorised.');
        }

        // Now that we authenticated the Display, make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth($this->display->displayId)) {
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");
        }

        // Important to keep this logging in place (status screen notification gets logged)
        if ($this->display->isAuditing()) {
            $this->getLog()->debug($status);
        }

        $this->logBandwidth($this->display->displayId, Bandwidth::$NOTIFYSTATUS, strlen($status));

        $status = json_decode($status, true);

        $this->display->storageAvailableSpace = $this->getSanitizer()->getInt('availableSpace', $this->display->storageAvailableSpace, $status);
        $this->display->storageTotalSpace = $this->getSanitizer()->getInt('totalSpace', $this->display->storageTotalSpace, $status);
        $this->display->lastCommandSuccess = $this->getSanitizer()->getCheckbox('lastCommandSuccess', $this->display->lastCommandSuccess, $status);
        $this->display->deviceName = $this->getSanitizer()->getString('deviceName', $this->display->deviceName, $status);
        $commercialLicenceString = $this->getSanitizer()->getString('licenceResult', null, $status);

        // Commercial Licence Check,  0 - Not licensed, 1 - licensed, 2 - trial licence, 3 - not applicable
        if (!empty($commercialLicenceString)) {
            if ($commercialLicenceString === 'Licensed fully') {
                $commercialLicence = 1;
            } elseif ($commercialLicenceString === 'Trial') {
                $commercialLicence = 2;
            } else {
                $commercialLicence = 0;
            }

            $this->display->commercialLicence = $commercialLicence;
        }

        // commercial licence not applicable for Windows and Linux players.
        if (in_array($this->display->clientType, ['windows', 'linux'])) {
            $this->display->commercialLicence = 3;
        }

        if ($this->getConfig()->getSetting('DISPLAY_LOCK_NAME_TO_DEVICENAME') == 1 && $this->display->hasPropertyChanged('deviceName')) {
            $this->display->display = $this->display->deviceName;
        }

        // Timezone
        $timeZone = $this->getSanitizer()->getString('timeZone', $status);

        if (!empty($timeZone)) {
            // Validate the provided data and log/ignore if not well formatted
            if (array_key_exists($timeZone, $this->getDate()->timezoneList())) {
                $this->display->timeZone = $timeZone;
            } else {
                $this->getLog()->info('Ignoring Incorrect timezone string: ' . $timeZone);
            }
        }

        // Current Layout
        $currentLayoutId = $this->getSanitizer()->getInt('currentLayoutId', $status);

        if ($currentLayoutId !== null) {
            $this->display->setCurrentLayoutId($this->getPool(), $currentLayoutId);
        }

        // Status Dialog
        $statusDialog = $this->getSanitizer()->getString('statusDialog', null, $status);

        if ($statusDialog !== null) {
            $this->getLog()->alert($statusDialog);
        }

        // Resolution
        $width = $this->getSanitizer()->getInt('width', null, $status);
        $height = $this->getSanitizer()->getInt('height', null, $status);

        if ($width != null && $height != null) {
            // Determine the orientation
            $this->display->orientation = ($width >= $height) ? 'landscape' : 'portrait';
            $this->display->resolution = $width . 'x' . $height;
        }

        // Lat/Long
        $latitude = $this->getSanitizer()->getDouble('latitude', null, $status);
        $longitude = $this->getSanitizer()->getDouble('longitude', null, $status);

        if ($latitude != null && $longitude != null) {
            $this->display->latitude = $latitude;
            $this->display->longitude = $longitude;
        }

        // Touch the display record
        try {
            if (count($this->display->getChangedProperties()) > 0)
                $this->display->save(Display::$saveOptionsMinimum);
        } catch (XiboException $xiboException) {
            $this->getLog()->error($xiboException->getMessage());
            throw new \SoapFault('Receiver', 'Unable to save status update');
        }

        return true;
    }

    /**
     * Submit ScreenShot
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $screenShot
     * @return bool
     * @throws \SoapFault
     */
    public function SubmitScreenShot($serverKey, $hardwareKey, $screenShot)
    {
        $this->logProcessor->setRoute('SubmitScreenShot');

        // Sanitize
        $serverKey = $this->getSanitizer()->string($serverKey);
        $hardwareKey = $this->getSanitizer()->string($hardwareKey);

        $screenShotFmt = "jpg";
        $screenShotMime = "image/jpeg";
        $screenShotImg = false;

        $converted = false;
        $needConversion = false;

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');
        }

        // Auth this request...
        if (!$this->authDisplay($hardwareKey)) {
            throw new \SoapFault('Receiver', 'This Display is not authorised.');
        }

        // Now that we authenticated the Display, make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth($this->display->displayId)) {
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");
        }

        if ($this->display->isAuditing()) {
            $this->getLog()->debug('Received Screen shot');
        }

        // Open this displays screen shot file and save this.
        $location = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'screenshots/' . $this->display->displayId . '_screenshot.' . $screenShotFmt;

        foreach(array('imagick', 'gd') as $imgDriver) {
            Img::configure(array('driver' => $imgDriver));
            try {
                $screenShotImg = Img::make($screenShot);
            } catch (\Exception $e) {
                if ($this->display->isAuditing())
                    $this->getLog()->debug($imgDriver . " - " . $e->getMessage());
            }
            if($screenShotImg !== false) {
                if ($this->display->isAuditing())
                    $this->getLog()->debug("Use " . $imgDriver);
                break;
            }
        }

        if ($screenShotImg !== false) {
            $imgMime = $screenShotImg->mime(); 

            if($imgMime != $screenShotMime) {
                $needConversion = true;
                try {
                    if ($this->display->isAuditing())
                        $this->getLog()->debug("converting: '" . $imgMime . "' to '" . $screenShotMime . "'");
                    $screenShot = (string) $screenShotImg->encode($screenShotFmt);
                    $converted = true;
                } catch (\Exception $e) {
                    if ($this->display->isAuditing())
                        $this->getLog()->debug($e->getMessage());
                }
            }
        }

        // return early with false, keep screenShotRequested intact, let the Player retry.
        if ($needConversion && !$converted) {
            $this->logBandwidth($this->display->displayId, Bandwidth::$SCREENSHOT, filesize($location));
            throw new \SoapFault('Receiver', __('Incorrect Screen shot Format'));
        }

        $fp = fopen($location, 'wb');
        fwrite($fp, $screenShot);
        fclose($fp);

        // Touch the display record
        $this->display->screenShotRequested = 0;
        $this->display->save(Display::$saveOptionsMinimum);

        // Cache the current screen shot time
        $this->display->setCurrentScreenShotTime($this->getPool(), $this->getDate()->getLocalDate());

        $this->logBandwidth($this->display->displayId, Bandwidth::$SCREENSHOT, filesize($location));

        return true;
    }
}
