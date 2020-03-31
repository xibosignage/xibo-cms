<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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


namespace Xibo\Xmds;


use Carbon\Carbon;
use Illuminate\Support\Str;
use Stash\Invalidation;
use Xibo\Entity\Bandwidth;
use Xibo\Entity\Display;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Random;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

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
     * @throws NotFoundException
     * @throws \SoapFault
     * @throws GeneralException
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
            'xmrPubKey' => $xmrPubKey
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
        $xmrChannel = $sanitized->getString('xmrChannel');
        $xmrPubKey = trim($sanitized->getString('xmrPubKey'));

        if ($xmrPubKey != '' && !Str::contains($xmrPubKey, 'BEGIN PUBLIC KEY')) {
            $xmrPubKey = "-----BEGIN PUBLIC KEY-----\n" . $xmrPubKey . "\n-----END PUBLIC KEY-----\n";
        }

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

        // Uncomment this if we want additional logging in register.
        //$this->logProcessor->setDisplay(0, 1);

        // Check in the database for this hardwareKey
        try {
            $display = $this->displayFactory->getByLicence($hardwareKey);
            $this->display = $display;

            $this->logProcessor->setDisplay($display->displayId, ($display->isAuditing()));

            // Audit in
            $this->getLog()->debug('serverKey: ' . $serverKey . ', hardwareKey: ' . $hardwareKey . ', displayName: ' . $displayName . ', macAddress: ' . $macAddress);

            $dateHelper = new DateFormatHelper();
            // Now
            $dateNow = Carbon::createFromTimestamp(time());

            // Append the time
            $displayElement->setAttribute('date', $dateNow->format($dateHelper->getSystemFormat()));
            $displayElement->setAttribute('timezone', $this->getConfig()->getSetting('defaultTimezone'));

            // Determine if we are licensed or not
            if ($display->licensed == 0) {
                // It is not licensed
                $displayElement->setAttribute('status', 2);
                $displayElement->setAttribute('code', 'WAITING');
                $displayElement->setAttribute('message', 'Display is Registered and awaiting Authorisation from an Administrator in the CMS');

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

                    // Disable the CEF browser option on Windows players
                    if (strtolower($settingName) == 'usecefwebbrowser' && ($clientType == 'windows')) {
                        $arrayItem['value'] = 0;
                    }
                  
                    // Override the XMR address if empty
                    if (strtolower($settingName) == 'xmrnetworkaddress' && $arrayItem['value'] == '') {
                        $arrayItem['value'] = $this->getConfig()->getSetting('XMR_PUB_ADDRESS');
                    }

                    $value = (isset($arrayItem['value']) ? $arrayItem['value'] : $arrayItem['default']);

                    // Patch download and update windows to make sure they are only 00:00
                    // https://github.com/xibosignage/xibo/issues/1791
                    if (strtolower($arrayItem['name']) == 'downloadstartwindow'
                        || strtolower($arrayItem['name']) == 'downloadendwindow'
                        || strtolower($arrayItem['name']) == 'updatestartwindow'
                        || strtolower($arrayItem['name']) == 'updateendwindow'
                    ) {
                        // Split by :
                        $timeParts = explode(':', $value);
                        $value = $timeParts[0] . ':' . $timeParts[1];
                    }

                    $node = $return->createElement($settingName, $value);

                    if (isset($arrayItem['type'])) {
                        $node->setAttribute('type', $arrayItem['type']);
                    }

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

                // cms move
                $displayMoveAddress = ($clientType == 'windows') ? 'NewCmsAddress' : 'newCmsAddress';
                $node = $return->createElement($displayMoveAddress, $display->newCmsAddress);
                
                if ($clientType == 'windows') {
                    $node->setAttribute('type', 'string');
                }

                $displayElement->appendChild($node);

                $displayMoveKey = ($clientType == 'windows') ? 'NewCmsKey' : 'newCmsKey';
                $node = $return->createElement($displayMoveKey, $display->newCmsKey);

                if ($clientType == 'windows') {
                    $node->setAttribute('type', 'string');
                }

                $displayElement->appendChild($node);

                // Add some special settings
                $nodeName = ($clientType == 'windows') ? 'DisplayName' : 'displayName';
                $node = $return->createElement($nodeName);
                $node->appendChild($return->createTextNode($display->display));

                if ($clientType == 'windows') {
                    $node->setAttribute('type', 'string');
                }
                $displayElement->appendChild($node);

                $nodeName = ($clientType == 'windows') ? 'ScreenShotRequested' : 'screenShotRequested';
                $node = $return->createElement($nodeName, $display->screenShotRequested);
                if ($clientType == 'windows') {
                    $node->setAttribute('type', 'checkbox');
                }
                $displayElement->appendChild($node);

                $nodeName = ($clientType == 'windows') ? 'DisplayTimeZone' : 'displayTimeZone';
                $node = $return->createElement($nodeName, (!empty($display->timeZone)) ? $display->timeZone : '');
                if ($clientType == 'windows') {
                    $node->setAttribute('type', 'string');
                }
                $displayElement->appendChild($node);

                if (!empty($display->timeZone)) {
                    // Calculate local time
                    $dateNow->timezone($display->timeZone);

                    // Append Local Time
                    $displayElement->setAttribute('localTimezone', $display->timeZone);
                    $displayElement->setAttribute('localDate', $dateNow->format($dateHelper->getSystemFormat()));
                }

                // Commands
                $commands = $display->getCommands();

                if (count($commands) > 0) {
                    // Append a command element
                    $commandElement = $return->createElement('commands');
                    $displayElement->appendChild($commandElement);

                    // Append each individual command
                    foreach ($display->getCommands() as $command) {
                        try {
                            /* @var \Xibo\Entity\Command $command */
                            $node = $return->createElement($command->code);
                            $commandString = $return->createElement('commandString', $command->commandString);
                            $validationString = $return->createElement('validationString', $command->validationString);

                            $node->appendChild($commandString);
                            $node->appendChild($validationString);

                            $commandElement->appendChild($node);
                        } catch (\DOMException $DOMException) {
                            $this->getLog()->error('Cannot add command to settings for displayId ' . $this->display->displayId . ', ' . $DOMException->getMessage());
                        }
                    }
                }

                // Check to see if the channel/pubKey are already entered
                if ($display->isAuditing()) {
                    $this->getLog()->debug('xmrChannel: [' . $xmrChannel . ']. xmrPublicKey: [' . $xmrPubKey . ']');
                }

                // Update the Channel
                $display->xmrChannel = $xmrChannel;
                // Update the PUB Key only if it has been cleared
                if ($display->xmrPubKey == '')
                    $display->xmrPubKey = $xmrPubKey;
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
                $display->xmrChannel = $xmrChannel;
                $display->xmrPubKey = $xmrPubKey;

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
                $displayElement->setAttribute('message', 'Display is now Registered and awaiting Authorisation from an Administrator in the CMS');
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
        $display->save(Display::$saveOptionsMinimum);

        // cache checks
        $cacheSchedule = $this->getPool()->getItem($this->display->getCacheKey() . '/schedule');
        $cacheSchedule->setInvalidationMethod(Invalidation::OLD);
        $displayElement->setAttribute('checkSchedule', ($cacheSchedule->isHit() ? crc32($cacheSchedule->get()) : ""));

        $cacheRF = $this->getPool()->getItem($this->display->getCacheKey() . '/requiredFiles');
        $cacheRF->setInvalidationMethod(Invalidation::OLD);
        $displayElement->setAttribute('checkRf', ($cacheRF->isHit() ? crc32($cacheRF->get()) : ""));

        // Log Bandwidth
        $returnXml = $return->saveXML();
        $this->logBandwidth($display->displayId, Bandwidth::$REGISTER, strlen($returnXml));

        // Audit our return
        $this->getLog()->debug($returnXml);

        return $returnXml;
    }

    /**
     * Returns the schedule for the hardware key specified
     * @param string $serverKey
     * @param string $hardwareKey
     * @return string
     * @throws NotFoundException
     * @throws \SoapFault
     */
    function Schedule($serverKey, $hardwareKey)
    {
        return $this->doSchedule($serverKey, $hardwareKey, ['dependentsAsNodes' => true, 'includeOverlays' => true]);
    }
}