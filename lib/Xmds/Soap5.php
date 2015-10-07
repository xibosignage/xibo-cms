<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Soap5.php)
 */


namespace Xibo\Xmds;


use Xibo\Entity\Bandwidth;
use Xibo\Entity\Display;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\DisplayFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Date;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;

class Soap5 extends Soap4
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
     * @param string $xmrChannel
     * @param string $xmrPubKey
     * @return string
     * @throws \SoapFault
     */
    public function RegisterDisplay($serverKey, $hardwareKey, $displayName, $clientType, $clientVersion, $clientCode, $operatingSystem, $macAddress, $xmrChannel, $xmrPubKey)
    {
        $this->logProcessor->setRoute('RegisterDisplay');

        // Sanitize
        $serverKey = Sanitize::string($serverKey);
        $hardwareKey = Sanitize::string($hardwareKey);
        $displayName = Sanitize::string($displayName);
        $clientType = Sanitize::string($clientType);
        $clientVersion = Sanitize::string($clientVersion);
        $clientCode = Sanitize::int($clientCode);
        $macAddress = Sanitize::string($macAddress);
        $clientAddress = Sanitize::getString('REMOTE_ADDR');
        $xmrChannel = Sanitize::string($xmrChannel);
        $xmrPubKey = trim(Sanitize::string($xmrPubKey));

        if ($xmrPubKey != '' && !str_contains($xmrPubKey, 'BEGIN PUBLIC KEY')) {
            $xmrPubKey = "-----BEGIN PUBLIC KEY-----\n" . $xmrPubKey . "\n-----END PUBLIC KEY-----\n";
        }

            // Audit in
        Log::debug('serverKey: ' . $serverKey . ', hardwareKey: ' . $hardwareKey . ', displayName: ' . $displayName);

        // Check the serverKey matches
        if ($serverKey != Config::GetSetting('SERVER_KEY'))
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
            $display = DisplayFactory::getByLicence($hardwareKey);

            $this->logProcessor->setDisplay($this->display->displayId);

            // Append the time
            $displayElement->setAttribute('date', Date::getLocalDate());
            $displayElement->setAttribute('timezone', Config::GetSetting('defaultTimezone'));

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

                $nodeName = ($clientType == 'windows') ? 'XmrNetworkAddress' : 'xmrNetworkAddress';
                $node = $return->createElement($nodeName, Config::GetSetting('XMR_PUB_ADDRESS'));
                $node->setAttribute('type', 'string');
                $displayElement->appendChild($node);

                // Check to see if the channel/pubKey are already entered
                if ($display->isAuditing == 1) {
                    Log::debug('xmrChannel: [' . $xmrChannel . ']. xmrPublicKey: [' . $xmrPubKey . ']');
                }

                if ($display->xmrPubKey == '') {
                    // If they aren't then allow new ones.
                    $display->xmrChannel = $xmrChannel;
                    $display->xmrPubKey = $xmrPubKey;
                }

                // Send Notification if required
                $this->AlertDisplayUp();
            }

        } catch (NotFoundException $e) {

            // Add a new display
            try {
                $display = new Display();
                $display->display = $displayName;
                $display->isAuditing = 0;
                $display->defaultLayoutId = 4;
                $display->license = $hardwareKey;
                $display->licensed = 0;
                $display->incSchedule = 0;
                $display->clientAddress = $this->getIp();
                $display->xmrChannel = $xmrChannel;
                $display->xmrPubKey = $xmrPubKey;
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
        $this->LogBandwidth($display->displayId, Bandwidth::$REGISTER, strlen($returnXml));

        // Audit our return
        if ($display->isAuditing == 1)
            Log::debug($returnXml, $display->displayId);

        return $returnXml;
    }
}