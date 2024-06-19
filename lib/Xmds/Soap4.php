<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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
namespace Xibo\Xmds;

use Carbon\Carbon;
use Intervention\Image\ImageManagerStatic as Img;
use Xibo\Entity\Bandwidth;
use Xibo\Entity\Display;
use Xibo\Event\XmdsDependencyRequestEvent;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Random;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Soap4
 * @package Xibo\Xmds
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
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
     * @param null $xmrChannel
     * @param null $xmrPubKey
     * @return string
     * @throws GeneralException
     * @throws NotFoundException
     * @throws \SoapFault
     */
    public function RegisterDisplay($serverKey, $hardwareKey, $displayName, $clientType, $clientVersion, $clientCode, $operatingSystem, $macAddress, $xmrChannel = null, $xmrPubKey = null)
    {
        $this->logProcessor->setRoute('RegisterDisplay');

        $sanitized = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey,
            'displayName' => $displayName,
            'clientType' => $clientType,
            'clientVersion' => $clientVersion,
            'clientCode' => $clientCode,
            'operatingSystem' => $operatingSystem,
            'macAddress' => $macAddress,
            'xmrChannel' => $xmrChannel,
            'xmrPubKey' => $xmrPubKey,
        ]);

        // Sanitize
        $serverKey = $sanitized->getString('serverKey');
        $hardwareKey = $sanitized->getString('hardwareKey');
        $displayName = $sanitized->getString('displayName');
        $clientType = $sanitized->getString('clientType');
        $clientVersion = $sanitized->getString('clientVersion');
        $clientCode = $sanitized->getInt('clientCode');
        $macAddress = $sanitized->getString('macAddress');
        $clientAddress = $this->getIp();
        $operatingSystem = $sanitized->getString('operatingSystem');

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');
        }

        // Check the Length of the hardwareKey
        if (strlen($hardwareKey) > 40) {
            throw new \SoapFault('Sender', 'The Hardware Key you sent was too long. Only 40 characters are allowed (SHA1).');
        }

        // Return an XML formatted string
        $return = new \DOMDocument('1.0');
        $displayElement = $return->createElement('display');
        $return->appendChild($displayElement);

        // Check in the database for this hardwareKey
        try {
            $display = $this->displayFactory->getByLicence($hardwareKey);
            $this->display = $display;

            $this->logProcessor->setDisplay($display->displayId, $display->isAuditing());

            // Audit in
            $this->getLog()->debug(
                'serverKey: ' . $serverKey . ', hardwareKey: ' . $hardwareKey .
                ', displayName: ' . $displayName . ', macAddress: ' . $macAddress
            );

            // Now
            $dateNow = Carbon::now();

            // Append the time
            $displayElement->setAttribute('date', $dateNow->format(DateFormatHelper::getSystemFormat()));
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
                            $arrayItem['value'] = Carbon::now()->setTime(intval($timeParts[0]), intval($timeParts[1]));
                        }
                    }

                    $node = $return->createElement($arrayItem['name'], (isset($arrayItem['value']) ? $arrayItem['value'] : $arrayItem['default']));
                    $node->setAttribute('type', $arrayItem['type']);
                    $displayElement->appendChild($node);
                }

                // Player upgrades
                $version = '';
                try {
                    $versionId = $this->display->getSetting('versionMediaId', null, ['displayOverride' => true]);

                    if ($clientType != 'windows' && $versionId != null) {
                        $version = $this->playerVersionFactory->getById($versionId);

                        if ($clientType == 'android') {
                            $version = json_encode([
                                'id' => $versionId,
                                'file' => $version->fileName,
                                'code' => $version->code
                            ]);
                        } elseif ($clientType == 'lg') {
                            $version = json_encode([
                                'id' => $versionId,
                                'file' => $version->fileName,
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
                                'id' => $versionId,
                                'file' => $version->fileName,
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
                    $displayElement->setAttribute('localDate', $dateNow->format(DateFormatHelper::getSystemFormat()));
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
            } catch (\InvalidArgumentException $e) {
                throw new \SoapFault('Sender', $e->getMessage());
            }

            $displayElement->setAttribute('status', 1);
            $displayElement->setAttribute('code', 'ADDED');
            if ($display->licensed == 0) {
                $displayElement->setAttribute('message', 'Display added and is awaiting licensing approval from an Administrator.');
            } else {
                $displayElement->setAttribute('message', 'Display is active and ready to start.');
            }
        }

        // Send Notification if required
        $this->alertDisplayUp();

        $display->lastAccessed = Carbon::now()->format('U');
        $display->loggedIn = 1;
        $display->clientAddress = $clientAddress;
        $display->macAddress = $macAddress;
        $display->clientType = $clientType;
        $display->clientVersion = $clientVersion;
        $display->clientCode = $clientCode;

        // Parse operatingSystem JSON data
        $operatingSystemJson = json_decode($operatingSystem, false);

        // Newer version of players will return a JSON value, but for older version, it will return a string.
        // In case the json decode fails, use the operatingSystem string value as the default value for the osVersion.
        $display->osVersion = $operatingSystemJson->version ?? $operatingSystem;
        $display->osSdk = $operatingSystemJson->sdk ?? null;
        $display->manufacturer = $operatingSystemJson->manufacturer ?? null;
        $display->brand = $operatingSystemJson->brand ?? null;
        $display->model = $operatingSystemJson->model ?? null;

        $display->save(['validate' => false, 'audit' => false]);

        // Log Bandwidth
        $returnXml = $return->saveXML();
        $this->logBandwidth($display->displayId, Bandwidth::$REGISTER, strlen($returnXml));

        // Audit our return
        $this->getLog()->debug($returnXml);

        // Phone Home?
        $this->phoneHome();

        return $returnXml;
    }

    /**
     * Returns a string containing the required files xml for the requesting display
     * @param string $serverKey The Server Key
     * @param string $hardwareKey Display Hardware Key
     * @return string $requiredXml Xml Formatted String
     * @throws \SoapFault
     * @throws \Xibo\Support\Exception\NotFoundException
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
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    function GetFile($serverKey, $hardwareKey, $fileId, $fileType, $chunkOffset, $chunkSize, $isDependency = false)
    {
        $this->logProcessor->setRoute('GetFile');

        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey,
            'fileId' => $fileId,
            'fileType' => $fileType,
            'chunkOffset' => $chunkOffset,
            'chunkSize' => $chunkSize
        ]);

        // Sanitize
        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');
        if ($isDependency) {
            $fileId = $sanitizer->getString('fileId');
        } else {
            $fileId = $sanitizer->getInt('fileId');
        }
        $fileType = $sanitizer->getString('fileType');
        $chunkOffset = $sanitizer->getDouble('chunkOffset');
        $chunkSize = $sanitizer->getDouble('chunkSize');

        $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault(
                'Sender',
                'The Server key you entered does not match with the server key at this address'
            );
        }

        // Authenticate this request...
        if (!$this->authDisplay($hardwareKey)) {
            throw new \SoapFault('Receiver', 'This Display is not authorised.');
        }

        // Now that we authenticated the Display, make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth($this->display->displayId)) {
            throw new \SoapFault('Receiver', 'Bandwidth Limit exceeded');
        }


        $this->getLog()->debug(
            'hardwareKey: ' . $hardwareKey . ', fileId: ' . $fileId . ', fileType: ' . $fileType .
            ', chunkOffset: ' . $chunkOffset . ', chunkSize: ' . $chunkSize
        );


        try {
            if ($isDependency || ($fileType == 'media' && $fileId < 0)) {
                // Validate the nonce
                // If we are an older player downloading as media using a faux fileId, then this lookup
                // should be performed against the `itemId`
                $requiredFile = $this->requiredFileFactory->getByDisplayAndDependency(
                    $this->display->displayId,
                    $fileType,
                    $fileId,
                    !($fileType == 'media' && $fileId < 0)
                );

                // File is valid, see if we can return it.
                $event = new XmdsDependencyRequestEvent($requiredFile);
                $this->getDispatcher()->dispatch($event, 'xmds.dependency.request');

                // Get the path
                $path = $event->getRelativePath();
                if (empty($path)) {
                    throw new NotFoundException(__('File not found'));
                }

                $path = $libraryLocation . $path;

                $f = fopen($path, 'r');
                if (!$f) {
                    throw new NotFoundException(__('Unable to get file pointer'));
                }

                fseek($f, $chunkOffset);
                $file = fread($f, $chunkSize);

                // Store file size for bandwidth log
                $chunkSize = strlen($file);

                if ($chunkSize === 0) {
                    throw new NotFoundException(__('Empty file'));
                }

                $requiredFile->bytesRequested = $requiredFile->bytesRequested + $chunkSize;
                $requiredFile->save();
            } else if ($fileType == 'layout') {
                // Validate the nonce
                $requiredFile = $this->requiredFileFactory->getByDisplayAndLayout($this->display->displayId, $fileId);

                // Load the layout
                $layout = $this->layoutFactory->concurrentRequestLock($this->layoutFactory->getById($fileId));
                try {
                    $path = $layout->xlfToDisk();
                } finally {
                    $this->layoutFactory->concurrentRequestRelease($layout);
                }

                $file = file_get_contents($path);
                $chunkSize = filesize($path);

                $requiredFile->bytesRequested = $requiredFile->bytesRequested + $chunkSize;
                $requiredFile->save();
            } else if ($fileType == 'media') {
                // A normal media file.
                $requiredFile = $this->requiredFileFactory->getByDisplayAndMedia(
                    $this->display->displayId,
                    $fileId
                );

                $media = $this->mediaFactory->getById($fileId);
                $this->getLog()->debug(json_encode($media));

                if (!file_exists($libraryLocation . $media->storedAs)) {
                    throw new NotFoundException(__('Media exists but file missing from library.'));
                }

                // Return the Chunk size specified
                $f = fopen($libraryLocation . $media->storedAs, 'r');

                if (!$f) {
                    throw new NotFoundException(__('Unable to get file pointer'));
                }

                fseek($f, $chunkOffset);

                $file = fread($f, $chunkSize);

                // Store file size for bandwidth log
                $chunkSize = strlen($file);

                if ($chunkSize === 0) {
                    throw new NotFoundException(__('Empty file'));
                }

                $requiredFile->bytesRequested = $requiredFile->bytesRequested + $chunkSize;
                $requiredFile->save();
            } else {
                throw new NotFoundException(__('Unknown FileType Requested.'));
            }
        } catch (NotFoundException $e) {
            $this->getLog()->error('Not found FileId: ' . $fileId . '. FileType: '
                . $fileType . '. ' . $e->getMessage());
            throw new \SoapFault('Receiver', 'Requested an invalid file.');
        }

        // Log Bandwidth
        if ($isDependency) {
            $this->logBandwidth($this->display->displayId, Bandwidth::$GET_DEPENDENCY, $chunkSize);
        } else {
            $this->logBandwidth($this->display->displayId, Bandwidth::$GETFILE, $chunkSize);
        }

        return $file;
    }

    /**
     * Returns the schedule for the hardware key specified
     * @param string $serverKey
     * @param string $hardwareKey
     * @return string
     * @throws \SoapFault
     * @throws \Xibo\Support\Exception\NotFoundException
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
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    function BlackList($serverKey, $hardwareKey, $mediaId, $type, $reason)
    {
        return $this->doBlackList($serverKey, $hardwareKey, $mediaId, $type, $reason);
    }

    /**
     * Submit client logging
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $logXml
     * @return bool
     * @throws \SoapFault
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    function SubmitLog($serverKey, $hardwareKey, $logXml)
    {
        return $this->doSubmitLog($serverKey, $hardwareKey, $logXml);
    }

    /**
     * Submit display statistics to the server
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $statXml
     * @return bool
     * @throws \SoapFault
     * @throws \Xibo\Support\Exception\NotFoundException
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
     * @throws \Xibo\Support\Exception\NotFoundException
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
     * @throws \Xibo\Support\Exception\NotFoundException
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
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function NotifyStatus($serverKey, $hardwareKey, $status)
    {
        $this->logProcessor->setRoute('NotifyStatus');

        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey
        ]);
        // Sanitize
        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');

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
        $this->getLog()->debug($status);

        $this->logBandwidth($this->display->displayId, Bandwidth::$NOTIFYSTATUS, strlen($status));

        $status = json_decode($status, true);
        $sanitizedStatus = $this->getSanitizer($status);

        $this->display->storageAvailableSpace = $sanitizedStatus->getInt('availableSpace', ['default' => $this->display->storageAvailableSpace]);
        $this->display->storageTotalSpace = $sanitizedStatus->getInt('totalSpace', ['default' => $this->display->storageTotalSpace]);
        $this->display->lastCommandSuccess = $sanitizedStatus->getCheckbox('lastCommandSuccess');
        $this->display->deviceName = $sanitizedStatus->getString('deviceName', ['default' => $this->display->deviceName]);
        $this->display->lanIpAddress = $sanitizedStatus->getString('lanIpAddress', ['default' => $this->display->lanIpAddress]);
        $commercialLicenceString = $sanitizedStatus->getString('licenceResult', ['default' => null]);

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
        $timeZone = $sanitizedStatus->getString('timeZone');

        if (!empty($timeZone)) {
            // Validate the provided data and log/ignore if not well formatted
            if (array_key_exists($timeZone, DateFormatHelper::timezoneList())) {
                $this->display->timeZone = $timeZone;
            } else {
                $this->getLog()->info('Ignoring Incorrect timezone string: ' . $timeZone);
            }
        }

        // Current Layout
        // don't fail: xibosignage/xibo#2517
        try {
            $currentLayoutId = $sanitizedStatus->getInt('currentLayoutId');

            if ($currentLayoutId !== null) {
                $this->display->setCurrentLayoutId($this->getPool(), $currentLayoutId);
            }
        } catch (\Exception $exception) {
            $this->getLog()->debug('Ignoring currentLayout due to a validation error.');
        }

        // Status Dialog
        $statusDialog = $sanitizedStatus->getString('statusDialog', ['default' => null]);

        if ($statusDialog !== null) {
            // special handling for Android Players (Other Players send status as json already)
            if ($this->display->clientType == 'android') {
                $statusDialog = json_encode($statusDialog);
            }

            // Log in as an alert
            $this->getLog()->alert($statusDialog);

            // Cache on the display as transient data
            try {
                $this->display->setStatusWindow($this->getPool(), json_decode($statusDialog, true));
            } catch (\Exception $exception) {
                $this->getLog()->error('Unable to cache display status. e = ' . $exception->getMessage());
            }
        }

        // Resolution
        $width = $sanitizedStatus->getInt('width');
        $height = $sanitizedStatus->getInt('height');

        if ($width != null && $height != null) {
            // Determine the orientation
            $this->display->orientation = ($width >= $height) ? 'landscape' : 'portrait';
            $this->display->resolution = $width . 'x' . $height;
        }

        // Lat/Long
        $latitude = $sanitizedStatus->getDouble('latitude', ['default' => null]);
        $longitude = $sanitizedStatus->getDouble('longitude', ['default' => null]);

        if ($latitude != null && $longitude != null) {
            $this->display->latitude = $latitude;
            $this->display->longitude = $longitude;
        }

        // Touch the display record
        try {
            if (count($this->display->getChangedProperties()) > 0) {
                $this->display->save(Display::$saveOptionsMinimum);
            }
        } catch (GeneralException $xiboException) {
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
     * @throws GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function SubmitScreenShot($serverKey, $hardwareKey, $screenShot)
    {
        $this->logProcessor->setRoute('SubmitScreenShot');

        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey,
        ]);
        // Sanitize
        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');

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

        $this->getLog()->debug('Received Screen shot');

        // Open this displays screen shot file and save this.
        $location = $this->getConfig()->getSetting('LIBRARY_LOCATION') . 'screenshots/' . $this->display->displayId . '_screenshot.' . $screenShotFmt;

        foreach (array('imagick', 'gd') as $imgDriver) {
            Img::configure(array('driver' => $imgDriver));
            try {
                $screenShotImg = Img::make($screenShot);
            } catch (\Exception $e) {
                $this->getLog()->debug($imgDriver . ' - ' . $e->getMessage());
            }
            if ($screenShotImg !== false) {
                $this->getLog()->debug('Use ' . $imgDriver);
                break;
            }
        }

        if ($screenShotImg !== false) {
            $imgMime = $screenShotImg->mime();

            if ($imgMime != $screenShotMime) {
                $needConversion = true;
                try {
                    $this->getLog()->debug("converting: '" . $imgMime . "' to '" . $screenShotMime . "'");
                    $screenShot = (string) $screenShotImg->encode($screenShotFmt);
                    $converted = true;
                } catch (\Exception $e) {
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
        $this->display->setCurrentScreenShotTime($this->getPool(), Carbon::now()->format(DateFormatHelper::getSystemFormat()));

        $this->logBandwidth($this->display->displayId, Bandwidth::$SCREENSHOT, filesize($location));

        return true;
    }
}
