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
use Xibo\Entity\Bandwidth;
use Xibo\Entity\Display;
use Xibo\Exception\NotFoundException;

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
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
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
            $displayElement->setAttribute('timezone', $this->getConfig()->GetSetting('defaultTimezone'));

            // Determine if we are licensed or not
            if ($display->licensed == 0) {
                // It is not licensed
                $displayElement->setAttribute('status', 2);
                $displayElement->setAttribute('code', 'WAITING');
                $displayElement->setAttribute('message', 'Display is awaiting licensing approval from an Administrator.');

            } else {
                // It is licensed
                $displayElement->setAttribute('status', 0);
                $displayElement->setAttribute('code', 'READY');
                $displayElement->setAttribute('message', 'Display is active and ready to start.');
                $displayElement->setAttribute('version_instructions', $display->versionInstructions);

                // Display Settings
                $settings = $display->getSettings();

                // Create the XML nodes
                foreach ($settings as $arrayItem) {

                    $node = $return->createElement($arrayItem['name'], (isset($arrayItem['value']) ? $arrayItem['value'] : $arrayItem['default']));
                    $node->setAttribute('type', $arrayItem['type']);
                    $displayElement->appendChild($node);
                }

                // Add some special settings
                $nodeName = ($clientType == 'windows') ? 'DisplayName' : 'displayName';
                $node = $return->createElement($nodeName, $display->display);
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

                // Send Notification if required
                $this->alertDisplayUp();
            }

        } catch (NotFoundException $e) {

            // Add a new display
            try {
                $display = $this->displayFactory->createEmpty();
                $this->display = $display;
                $display->display = $displayName;
                $display->auditingUntil = 0;
                $display->defaultLayoutId = 4;
                $display->license = $hardwareKey;
                $display->licensed = 0;
                $display->incSchedule = 0;
                $display->clientAddress = $this->getIp();
            }
            catch (\InvalidArgumentException $e) {
                throw new \SoapFault('Sender', $e->getMessage());
            }

            $displayElement->setAttribute('status', 1);
            $displayElement->setAttribute('code', 'ADDED');
            $displayElement->setAttribute('message', 'Display added and is awaiting licensing approval from an Administrator.');
        }


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
        $httpDownloads = ($this->getConfig()->GetSetting('SENDFILE_MODE') != 'Off');
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

        $libraryLocation = $this->getConfig()->GetSetting("LIBRARY_LOCATION");

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Authenticate this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \SoapFault('Receiver', "This display client is not licensed");

        if ($this->display->isAuditing())
            $this->getLog()->debug('hardwareKey: ' . $hardwareKey . ', fileId: ' . $fileId . ', fileType: ' . $fileType . ', chunkOffset: ' . $chunkOffset . ', chunkSize: ' . $chunkSize);

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
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \SoapFault('Receiver', 'This display client is not licensed');

        // Important to keep this logging in place (status screen notification gets logged)
        if ($this->display->isAuditing())
            $this->getLog()->debug($status);

        $this->logBandwidth($this->display->displayId, Bandwidth::$NOTIFYSTATUS, strlen($status));

        $status = json_decode($status, true);

        $this->display->currentLayoutId = $this->getSanitizer()->getInt('currentLayoutId', $this->display->currentLayoutId, $status);
        $this->display->storageAvailableSpace = $this->getSanitizer()->getInt('availableSpace', $this->display->storageAvailableSpace, $status);
        $this->display->storageTotalSpace = $this->getSanitizer()->getInt('totalSpace', $this->display->storageTotalSpace, $status);
        $this->display->lastCommandSuccess = $this->getSanitizer()->getCheckbox('lastCommandSuccess', $this->display->lastCommandSuccess, $status);
        $this->display->deviceName = $this->getSanitizer()->getString('deviceName', $this->display->deviceName, $status);

        // Touch the display record
        $this->display->save(Display::$saveOptionsMinimum);

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
        if ($serverKey != $this->getConfig()->GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \SoapFault('Receiver', 'This display client is not licensed');

        if ($this->display->isAuditing())
            $this->getLog()->debug('Received Screen shot');

        // Open this displays screen shot file and save this.
        $location = $this->getConfig()->GetSetting('LIBRARY_LOCATION') . 'screenshots/' . $this->display->displayId . '_screenshot.' . $screenShotFmt;

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

        $this->logBandwidth($this->display->displayId, Bandwidth::$SCREENSHOT, filesize($location));

        return true;
    }
}
