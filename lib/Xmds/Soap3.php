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

use Xibo\Entity\Bandwidth;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\RequiredFileFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;

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
     */
    public function RegisterDisplay($serverKey, $hardwareKey, $displayName, $version)
    {
        $this->logProcessor->setRoute('RegisterDisplay');

        // Sanitize
        $serverKey = Sanitize::string($serverKey);
        $hardwareKey = Sanitize::string($hardwareKey);

        // Check the serverKey matches the one we have
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Check the Length of the hardwareKey
        if (strlen($hardwareKey) > 40)
            throw new \SoapFault('Sender', 'The Hardware Key you sent was too long. Only 40 characters are allowed (SHA1).');

        // Check in the database for this hardwareKey
        try {
            $display = DisplayFactory::getByLicence($hardwareKey);

            $this->logProcessor->setDisplay($this->display->displayId);

            if ($display->licensed == 0) {
                $active = 'Display is awaiting licensing approval from an Administrator.';
            } else {
                $active = 'Display is active and ready to start.';
            }

            // Touch
            $display->lastAccessed = time();
            $display->loggedIn = 1;
            $display->save(['validate' => false, 'audit' => false]);

            // Log Bandwidth
            $this->logBandwidth($display->displayId, Bandwidth::$REGISTER, strlen($active));

            Log::debug($active, $display->displayId);

            return $active;

        } catch (NotFoundException $e) {
            Log::error('Attempt to register a Version 3 Display with key %s.', $hardwareKey);

            throw new \SoapFault('Sender', 'You cannot register an old display against this CMS.');
        }
    }

    /**
     * Returns a string containing the required files xml for the requesting display
     * @param string $serverKey
     * @param string $hardwareKey Display Hardware Key
     * @param string $version
     * @return string $requiredXml Xml Formatted
     * @throws \SoapFault
     */
    function RequiredFiles($serverKey, $hardwareKey, $version)
    {
        $httpDownloads = false;
        return $this->doRequiredFiles($serverKey, $hardwareKey, $httpDownloads);
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
     * @throws \SoapFault
     */
    function GetFile($serverKey, $hardwareKey, $filePath, $fileType, $chunkOffset, $chunkSize, $version)
    {
        // Sanitize
        $serverKey = Sanitize::string($serverKey);
        $hardwareKey = Sanitize::string($hardwareKey);
        $filePath = Sanitize::string($filePath);
        $fileType = Sanitize::string($fileType);
        $chunkOffset = Sanitize::int($chunkOffset);
        $chunkSize = Sanitize::int($chunkSize);

        $libraryLocation = Config::GetSetting("LIBRARY_LOCATION");

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->checkBandwidth())
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Authenticate this request...
        if (!$this->authDisplay($hardwareKey))
            throw new \SoapFault('Receiver', "This display client is not licensed");

        if ($this->display->isAuditing == 1)
            Log::debug("[IN] Params: [$hardwareKey] [$filePath] [$fileType] [$chunkOffset] [$chunkSize]");

        $file = null;

        try {
            // Handle fetching the file
            if ($fileType == "layout") {
                $fileId = Sanitize::int($filePath);

                // Validate the nonce
                $requiredFile = RequiredFileFactory::getByDisplayAndLayout($this->display->displayId, $fileId);

                // Load the layout
                $layout = LayoutFactory::getById($fileId);
                $path = $layout->xlfToDisk();

                $file = file_get_contents($path);
                $chunkSize = filesize($path);

                $requiredFile->bytesRequested = $requiredFile->bytesRequested + $chunkSize;
                $requiredFile->markUsed();

            } else if ($fileType == "media") {
                // Get the ID
                if (strstr($filePath, '/') || strstr($filePath, '\\'))
                    throw new NotFoundException("Invalid file path.");

                $fileId = explode('.', $filePath);

                // Validate the nonce
                $requiredFile = RequiredFileFactory::getByDisplayAndMedia($this->display->displayId, $fileId[0]);

                // Return the Chunk size specified
                $f = fopen($libraryLocation . $filePath, 'r');

                fseek($f, $chunkOffset);

                $file = fread($f, $chunkSize);

                // Store file size for bandwidth log
                $chunkSize = strlen($file);

                $requiredFile->bytesRequested = $requiredFile->bytesRequested + $chunkSize;
                $requiredFile->markUsed();

            } else {
                throw new NotFoundException('Unknown FileType Requested.');
            }
        }
        catch (NotFoundException $e) {
            Log::error($e->getMessage());
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
     * @param string $version
     * @throws \SoapFault
     */
    function Schedule($serverKey, $hardwareKey, $version)
    {
        return $this->doSchedule($serverKey, $hardwareKey);
    }

    /**
     * BlackList
     * @return bool
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $mediaId
     * @param string $type
     * @param string $reason
     * @param string $version
     * @throws \SoapFault
     */
    function BlackList($serverKey, $hardwareKey, $mediaId, $type, $reason, $version)
    {
        return $this->doBlackList($serverKey, $hardwareKey, $mediaId, $type, $reason);
    }

    /**
     * Submit client logging
     * @return bool
     * @param string $version
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $logXml
     * @throws \SoapFault
     */
    function SubmitLog($version, $serverKey, $hardwareKey, $logXml)
    {
        return $this->doSubmitLog($serverKey, $hardwareKey, $logXml);
    }

    /**
     * Submit display statistics to the server
     * @return bool
     * @param string $version
     * @param string $serverKey
     * @param string $hardwareKey
     * @param string $statXml
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
     * @throws \SoapFault
     */
    function GetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId, $version)
    {
        return $this->doGetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId);
    }
}
