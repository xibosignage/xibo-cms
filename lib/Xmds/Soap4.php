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

use Xibo\Controller\Library;
use Xibo\Entity\Bandwidth;
use Xibo\Entity\Display;
use Xibo\Factory\XmdsNonceFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;


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
     * @throws SoapFault
     */
    public function RegisterDisplay($serverKey, $hardwareKey, $displayName, $clientType, $clientVersion, $clientCode, $operatingSystem, $macAddress)
    {
        // Sanitize
        $serverKey = Sanitize::string($serverKey);
        $hardwareKey = Sanitize::string($hardwareKey);
        $displayName = Sanitize::string($displayName);
        $clientType = Sanitize::string($clientType);
        $clientVersion = Sanitize::string($clientVersion);
        $clientCode = Sanitize::int($clientCode);
        $macAddress = Sanitize::string($macAddress);
        $clientAddress = Sanitize::getString('REMOTE_ADDR');

        // Audit in
        Log::debug('serverKey: ' . $serverKey . ', hardwareKey: ' . $hardwareKey . ', displayName: ' . $displayName);

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Check the Length of the hardwareKey
        if (strlen($hardwareKey) > 40)
            throw new \SoapFault('Sender', 'The Hardware Key you sent was too long. Only 40 characters are allowed (SHA1).');

        // Check in the database for this hardwareKey
        try {
            $dbh = \Xibo\Storage\PDOConnect::init();
            $sth = $dbh->prepare('
                SELECT licensed, display, displayid, displayprofileid, client_type, version_instructions, screenShotRequested, email_alert, loggedin, isAuditing
                  FROM display
                WHERE license = :hardwareKey');

            $sth->execute(array(
                'hardwareKey' => $hardwareKey
            ));

            $result = $sth->fetchAll();
        } catch (Exception $e) {
            Log::error('Error trying to check hardware key. ' . $e->getMessage());
            throw new \SoapFault('Sender', 'Cannot check client key.');
        }

        // Use a display object to Add or Edit the display
        $displayObject = new Display();

        // Return an XML formatted string
        $return = new DOMDocument('1.0');
        $displayElement = $return->createElement('display');
        $return->appendChild($displayElement);

        // Is it there?
        if (count($result) == 0) {

            // Get the default layout id
            $defaultLayoutId = 4;

            // Add this display record
            if (!$displayId = $displayObject->Add($displayName, 0, $defaultLayoutId, $hardwareKey, 0, 0))
                throw new \SoapFault('Sender', 'Error adding display');

            $displayElement->setAttribute('status', 1);
            $displayElement->setAttribute('code', 'ADDED');
            $displayElement->setAttribute('message', 'Display added and is awaiting licensing approval from an Administrator.');

            // New displays don't audit
            $isAuditing = 0;
        } else {
            // We have seen this display before, so check the licensed value
            $row = $result[0];

            $displayId = Sanitize::int($row['displayid']);
            $display = Sanitize::string($row['display']);
            $clientType = \Kit::ValidateParam($row['client_type'], _WORD);
            $versionInstructions = \Kit::ValidateParam($row['version_instructions'], _HTMLSTRING);
            $screenShotRequested = Sanitize::int($row['screenShotRequested']);
            $emailAlert = Sanitize::int($row['email_alert']);
            $loggedIn = Sanitize::int($row['loggedin']);
            $isAuditing = Sanitize::int($row['isAuditing']);

            // Determine if we are licensed or not
            if ($row['licensed'] == 0) {
                // It is not licensed
                $displayElement->setAttribute('status', 2);
                $displayElement->setAttribute('code', 'WAITING');
                $displayElement->setAttribute('message', 'Display is awaiting licensing approval from an Administrator.');
            } else {
                // It is licensed
                $displayElement->setAttribute('status', 0);
                $displayElement->setAttribute('code', 'READY');
                $displayElement->setAttribute('message', 'Display is active and ready to start.');
                $displayElement->setAttribute('version_instructions', $versionInstructions);

                // Use the display profile and type to get this clients settings
                try {
                    $displayProfile = new DisplayProfile();
                    $displayProfile->displayProfileId = (empty($row['displayprofileid']) ? 0 : Sanitize::int($row['displayprofileid']));

                    if ($displayProfile->displayProfileId == 0) {
                        // Load the default profile
                        $displayProfile->type = $clientType;
                        $displayProfile->LoadDefault();
                    } else {
                        // Load the specified profile
                        $displayProfile->Load();
                    }

                    // Load the config and inject the display name
                    if ($clientType == 'windows') {
                        $displayProfile->config[] = array(
                            'name' => 'DisplayName',
                            'value' => $display,
                            'type' => 'string'
                        );
                        $displayProfile->config[] = array(
                            'name' => 'ScreenShotRequested',
                            'value' => $screenShotRequested,
                            'type' => 'checkbox'
                        );
                    } else {
                        $displayProfile->config[] = array(
                            'name' => 'displayName',
                            'value' => $display,
                            'type' => 'string'
                        );
                        $displayProfile->config[] = array(
                            'name' => 'screenShotRequested',
                            'value' => $screenShotRequested,
                            'type' => 'checkbox'
                        );
                    }

                    // Create the XML nodes
                    foreach ($displayProfile->config as $arrayItem) {
                        $node = $return->createElement($arrayItem['name'], (isset($arrayItem['value']) ? $arrayItem['value'] : $arrayItem['default']));
                        $node->setAttribute('type', $arrayItem['type']);
                        $displayElement->appendChild($node);
                    }
                } catch (Exception $e) {
                    Log::error('Error loading display config. ' . $e->getMessage());
                    throw new \SoapFault('Sender', 'Error after display found');
                }

                // Send Notification if required
                $this->AlertDisplayUp($displayId, $display, $loggedIn, $emailAlert);
            }
        }

        // Touch the display record
        $displayObject->Touch($displayId, array(
            'clientAddress' => $clientAddress,
            'macAddress' => $macAddress,
            'clientType' => $clientType,
            'clientVersion' => $clientVersion,
            'clientCode' => $clientCode,
            'operatingSystem' => $operatingSystem
        ));

        // Log Bandwidth
        $returnXml = $return->saveXML();
        $this->LogBandwidth($displayId, Bandwidth::$REGISTER, strlen($returnXml));

        // Audit our return
        if ($isAuditing == 1)
            Log::debug($returnXml, $displayId);

        return $returnXml;
    }

    /**
     * Returns a string containing the required files xml for the requesting display
     * @param string $serverKey The Server Key
     * @param string $hardwareKey Display Hardware Key
     * @return string $requiredXml Xml Formatted String
     * @throws SoapFault
     */
    function RequiredFiles($serverKey, $hardwareKey)
    {
        $httpDownloads = (Config::GetSetting('SENDFILE_MODE') != 'Off');
        return $this->getRequiredFiles($serverKey, $hardwareKey, $httpDownloads);
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
     * @throws SoapFault
     */
    function GetFile($serverKey, $hardwareKey, $fileId, $fileType, $chunkOffset, $chunkSize)
    {
        // Sanitize
        $serverKey = Sanitize::string($serverKey);
        $hardwareKey = Sanitize::string($hardwareKey);
        $fileId = Sanitize::int($fileId);
        $fileType = \Kit::ValidateParam($fileType, _WORD);
        $chunkOffset = Sanitize::int($chunkOffset);
        $chunkSize = Sanitize::int($chunkSize);

        $libraryLocation = Config::GetSetting("LIBRARY_LOCATION");

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Authenticate this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new \SoapFault('Receiver', "This display client is not licensed");

        if ($this->display->isAuditing == 1)
            Log::debug('hardwareKey: ' . $hardwareKey . ', fileId: ' . $fileId . ', fileType: ' . $fileType . ', chunkOffset: ' . $chunkOffset . ', chunkSize: ' . $chunkSize, $this->display->displayId);

        if ($fileType == "layout") {
            $fileId = Sanitize::int($fileId);

            // Validate the nonce
            if (count(XmdsNonceFactory::getByDisplayAndLayout($this->display->displayId, $fileId)) <= 0)
                throw new \SoapFault('Receiver', 'Requested an invalid file.');

            try {
                $dbh = \Xibo\Storage\PDOConnect::init();

                $sth = $dbh->prepare('SELECT xml FROM layout WHERE layoutid = :layoutid');
                $sth->execute(array('layoutid' => $fileId));

                if (!$row = $sth->fetch())
                    throw new Exception('No file found with that ID');

                $file = $row['xml'];

                // Store file size for bandwidth log
                $chunkSize = strlen($file);
            } catch (Exception $e) {
                Log::error('Unable to find the layout to download. ' . $e->getMessage(), $this->display->displayId);
                return new \SoapFault('Receiver', 'Unable the find layout.');
            }
        } else if ($fileType == "media") {
            // Validate the nonce
            if (count(XmdsNonceFactory::getByDisplayAndMedia($this->display->displayId, $fileId)) <= 0)
                throw new \SoapFault('Receiver', 'Requested an invalid file.');

            try {
                $dbh = \Xibo\Storage\PDOConnect::init();

                $sth = $dbh->prepare('SELECT storedAs FROM `media` WHERE mediaid = :mediaid');
                $sth->execute(array('mediaid' => $fileId));

                if (!$row = $sth->fetch())
                    throw new Exception('No file found with that ID');

                // Return the Chunk size specified
                $f = fopen($libraryLocation . $row['storedAs'], 'r');

                fseek($f, $chunkOffset);

                $file = fread($f, $chunkSize);

                // Store file size for bandwidth log
                $chunkSize = strlen($file);
            } catch (Exception $e) {
                Log::error('Unable to find the media to download. ' . $e->getMessage(), $this->display->displayId);
                return new \SoapFault('Receiver', 'Unable the find media.');
            }
        } else {
            throw new \SoapFault('Receiver', 'Unknown FileType Requested.');
        }

        // Log Bandwidth
        $this->LogBandwidth($this->display->displayId, Bandwidth::$GETFILE, $chunkSize);

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
        return $this->getSchedule($serverKey, $hardwareKey);
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
        return $this->getBlackList($serverKey, $hardwareKey, $mediaId, $type, $reason);
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
        $this->doMediaInventory($serverKey, $hardwareKey, $inventory);
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
        $this->doGetResource($serverKey, $hardwareKey, $layoutId, $regionId, $mediaId);
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
        // Sanitize
        $serverKey = Sanitize::string($serverKey);
        $hardwareKey = Sanitize::string($hardwareKey);

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new \SoapFault('Receiver', 'This display client is not licensed');

        if ($this->display->isAuditing == 1)
            Log::debug($status, $this->display->displayId);

        $this->LogBandwidth($this->display->displayId, Bandwidth::$NOTIFYSTATUS, strlen($status));

        // Touch the display record
        $displayObject = new Display();
        $displayObject->Touch($this->display->displayId, json_decode($status, true));

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
        // Sanitize
        $serverKey = Sanitize::string($serverKey);
        $hardwareKey = Sanitize::string($hardwareKey);

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
            throw new \SoapFault('Sender', 'The Server key you entered does not match with the server key at this address');

        // Make sure we are sticking to our bandwidth limit
        if (!$this->CheckBandwidth())
            throw new \SoapFault('Receiver', "Bandwidth Limit exceeded");

        // Auth this request...
        if (!$this->AuthDisplay($hardwareKey))
            throw new \SoapFault('Receiver', 'This display client is not licensed');

        if ($this->display->isAuditing == 1)
            Log::debug('Received Screen shot', $this->display->displayId);

        // Open this displays screen shot file and save this.
        Library::ensureLibraryExists();
        $location = Config::GetSetting('LIBRARY_LOCATION') . 'screenshots/' . $this->display->displayId . '_screenshot.jpg';
        $fp = fopen($location, 'wb');
        fwrite($fp, $screenShot);
        fclose($fp);

        // Touch the display record
        $displayObject = new Display();
        $displayObject->Touch($this->display->displayId, array('screenShotRequested' => 0));

        $this->LogBandwidth($this->display->displayId, Bandwidth::$SCREENSHOT, filesize($location));

        return true;
    }
}
