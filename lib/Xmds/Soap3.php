<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */
namespace Xibo\Xmds;

use Carbon\Carbon;
use Xibo\Entity\Bandwidth;
use Xibo\Event\XmdsDependencyRequestEvent;
use Xibo\Service\LibraryAsset;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Soap3
 * @package Xibo\Xmds
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class Soap3 extends Soap
{
    /**
     * Registers a new display
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $displayName
     * @param string $version
     * @return string
     * @throws \SoapFault
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function RegisterDisplay($serverKey, $hardwareKey, $displayName, $version)
    {
        $this->logProcessor->setRoute('RegisterDisplay');

        // Sanitize
        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey
        ]);

        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');

        // Check the serverKey matches the one we have
        if (!$this->isValidServerKey($serverKey))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Check the Length of the hardwareKey
        if (strlen($hardwareKey) > 40)
            throw new \SoapFault('Sender', 'The Hardware Key you sent was too long. Only 40 characters are allowed (SHA1).');

        // Check in the database for this hardwareKey
        try {
            $display = $this->displayFactory->getByLicence($hardwareKey);

            if (!$display->isDisplaySlotAvailable()) {
                $display->licensed = 0;
            }

            $this->logProcessor->setDisplay($display->displayId, $display->isAuditing());

            if ($display->licensed == 0) {
                $active = 'Display is awaiting licensing approval from an Administrator.';
            } else {
                $active = 'Display is active and ready to start.';
            }

            // Touch
            $display->lastAccessed = Carbon::now()->format('U');
            $display->loggedIn = 1;
            $display->save(['validate' => false, 'audit' => false]);

            // Log Bandwidth
            $this->logBandwidth($display->displayId, Bandwidth::$REGISTER, strlen($active));

            $this->getLog()->debug($active, $display->displayId);

            return $active;
        } catch (NotFoundException $e) {
            $this->getLog()->error('Attempt to register a Version 3 Display with key %s.', $hardwareKey);

            throw new \SoapFault('Sender', 'You cannot register an old display against this CMS.');
        }
    }

    /**
     * Returns a string containing the required files xml for the requesting display
     * @param string $serverKey
     * @param string $hardwareKey Display Hardware Key
     * @param string $version
     * @return string $requiredXml Xml Formatted
     * @throws NotFoundException
     * @throws \SoapFault
     */
    function RequiredFiles($serverKey, $hardwareKey, $version)
    {
        return $this->doRequiredFiles($serverKey, $hardwareKey, false);
    }

    /**
     * Get File
     * @param string $serverKey The ServerKey for this CMS
     * @param string $hardwareKey The HardwareKey for this Display
     * @param string $filePath
     * @param string $fileType The File Type
     * @param int $chunkOffset The Offset of the Chunk Requested
     * @param string $chunkSize The Size of the Chunk Requested
     * @param string $version
     * @return string
     * @throws NotFoundException
     * @throws \SoapFault
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function GetFile($serverKey, $hardwareKey, $filePath, $fileType, $chunkOffset, $chunkSize, $version)
    {
        $this->logProcessor->setRoute('GetFile');

        // Sanitize
        $sanitizer = $this->getSanitizer([
            'serverKey' => $serverKey,
            'hardwareKey' => $hardwareKey,
            'filePath' => $filePath,
            'fileType' => $fileType,
            'chunkOffset' => $chunkOffset,
            'chunkSize' => $chunkSize
        ]);

        $serverKey = $sanitizer->getString('serverKey');
        $hardwareKey = $sanitizer->getString('hardwareKey');
        $filePath = $sanitizer->getString('filePath');
        $fileType = $sanitizer->getString('fileType');
        $chunkOffset = $sanitizer->getDouble('chunkOffset');
        $chunkSize = $sanitizer->getDouble('chunkSize');

        $libraryLocation = $this->getConfig()->getSetting('LIBRARY_LOCATION');

        // Check the serverKey matches
        if (!$this->isValidServerKey($serverKey)) {
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
            '[IN] Params: ['. $hardwareKey .'] ['. $filePath . '] 
            ['. $fileType.'] ['.$chunkOffset.'] ['.$chunkSize.']'
        );

        $file = null;

        if (empty($filePath)) {
            $this->getLog()->error('Soap3 GetFile request without a file path. Maybe a player missing ?v= parameter');
            throw new \SoapFault(
                'Receiver',
                'GetFile request is missing file path - is this version compatible with this CMS?'
            );
        }

        try {
            // Handle fetching the file
            if ($fileType == 'layout') {
                $fileId = (int) $filePath;

                // Validate the nonce
                $requiredFile = $this->requiredFileFactory->getByDisplayAndLayout($this->display->displayId, $fileId);

                // Load the layout
                $file = $this->layoutFactory->getBuiltXlf($this->layoutFactory->getById($fileId));
                $chunkSize = strlen($file);

                $requiredFile->bytesRequested = $requiredFile->bytesRequested + $chunkSize;
                $requiredFile->save();
            } else if ($fileType == 'media') {
                // Get the ID
                if (strstr($filePath, '/') || strstr($filePath, '\\')) {
                    throw new NotFoundException('Invalid file path.');
                }

                $fileId = explode('.', $filePath);

                if (is_numeric($fileId[0])) {
                    // Validate the nonce
                    $requiredFile = $this->requiredFileFactory->getByDisplayAndMedia(
                        $this->display->displayId,
                        $fileId[0]
                    );

                    // Only the media's own file, so another sharing its id (such as {layoutId}.xlf) isn't served.
                    $media = $this->getStore()->select(
                        'SELECT `storedAs`, `storageEngine` FROM `media` WHERE `mediaId` = :mediaId',
                        ['mediaId' => (int)$fileId[0]]
                    )[0] ?? null;
                    if ($media === null || $media['storedAs'] !== $filePath) {
                        throw new NotFoundException('Invalid file path.');
                    }

                    // Only the requested chunk is read, from the local library while the media waits to be copied.
                    $storage = $this->libraryStorageRegistry->get(LibraryAsset::MEDIA, $media['storageEngine']);
                    $file = $storage->readRange(
                        $filePath,
                        (int) $chunkOffset,
                        (int) $chunkSize
                    )->getContents();
                } else {
                    // Non-numeric, so assume we're a dependency
                    $this->getLog()->debug('Assumed dependency with path: ' . $filePath);

                    $requiredFile = $this->requiredFileFactory->getByDisplayAndDependencyPath(
                        $this->display->displayId,
                        $filePath
                    );

                    $event = new XmdsDependencyRequestEvent($requiredFile);
                    $this->getDispatcher()->dispatch($event, 'xmds.dependency.request');

                    $file = $this->readDependencyChunk(
                        $event,
                        $libraryLocation,
                        (int)$chunkOffset,
                        (int)$chunkSize
                    );
                }

                // Store file size for bandwidth log
                $chunkSize = strlen($file);

                $requiredFile->bytesRequested = $requiredFile->bytesRequested + $chunkSize;
                $requiredFile->save();

            } else {
                throw new NotFoundException(__('Unknown FileType Requested.'));
            }
        }
        catch (NotFoundException $e) {
            $this->getLog()->error($e->getMessage());
            throw new \SoapFault('Receiver', 'Requested an invalid file.');
        }

        // Log Bandwidth
        $this->logBandwidth($this->display->displayId, Bandwidth::$GETFILE, $chunkSize);

        return $file;
    }

    /**
     * Returns the schedule for the hardware key specified
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $version
     * @return string
     * @throws NotFoundException
     * @throws \SoapFault
     */
    function Schedule($serverKey, $hardwareKey, $version)
    {
        return $this->doSchedule($serverKey, $hardwareKey);
    }

    /**
     * BlackList
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $mediaId
     * @param string $type
     * @param string $reason
     * @param string $version
     * @return bool
     * @throws NotFoundException
     * @throws \SoapFault
     */
    function BlackList($serverKey, $hardwareKey, $mediaId, $type, $reason, $version)
    {
        return $this->doBlackList($serverKey, $hardwareKey, $mediaId, $type, $reason);
    }

    /**
     * Submit client logging
     * @param string $version
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $logXml
     * @return bool
     * @throws NotFoundException
     * @throws \SoapFault
     */
    function SubmitLog($version, $serverKey, $hardwareKey, $logXml)
    {
        return $this->doSubmitLog($serverKey, $hardwareKey, $logXml);
    }

    /**
     * Submit display statistics to the server
     * @param string $version
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $statXml
     * @return bool
     * @throws NotFoundException
     * @throws \SoapFault
     */
    function SubmitStats($version, $serverKey, $hardwareKey, $statXml)
    {
        return $this->doSubmitStats($serverKey, $hardwareKey, $statXml);
    }

    /**
     * Store the media inventory for a client
     * @param string $version
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $inventory
     * @return bool
     * @throws NotFoundException
     * @throws \SoapFault
     */
    public function MediaInventory($version, $serverKey, $hardwareKey, $inventory)
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
     * @param string $version
     * @return string
     * @throws NotFoundException
     * @throws \SoapFault
     */
    function GetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId, $version)
    {
        return $this->doGetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId);
    }
}
