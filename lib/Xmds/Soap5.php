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
use Illuminate\Support\Str;
use Stash\Invalidation;
use Xibo\Entity\Bandwidth;
use Xibo\Entity\Display;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Random;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Soap5
 * @package Xibo\Xmds
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
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
     * @param string $licenceCheck
     * @return string
     * @throws NotFoundException
     * @throws \SoapFault
     * @throws GeneralException
     */
    public function RegisterDisplay(
        $serverKey,
        $hardwareKey,
        $displayName,
        $clientType,
        $clientVersion,
        $clientCode,
        $operatingSystem,
        $macAddress,
        $xmrChannel = null,
        $xmrPubKey = null,
        $licenceResult = null
    ) {
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
            'licenceResult' => $licenceResult
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
        $operatingSystem = $sanitized->getString('operatingSystem');

        // this is only sent from xmds v7
        $commercialLicenceString = $sanitized->getString('licenceResult');

        if ($xmrPubKey != '' && !Str::contains($xmrPubKey, 'BEGIN PUBLIC KEY')) {
            $xmrPubKey = "-----BEGIN PUBLIC KEY-----\n" . $xmrPubKey . "\n-----END PUBLIC KEY-----\n";
        }

        // Check the serverKey matches
        if ($serverKey != $this->getConfig()->getSetting('SERVER_KEY')) {
            throw new \SoapFault(
                'Sender',
                'The Server key you entered does not match with the server key at this address'
            );
        }

        // Check the Length of the hardwareKey
        if (strlen($hardwareKey) > 40) {
            throw new \SoapFault(
                'Sender',
                'The Hardware Key you sent was too long. Only 40 characters are allowed (SHA1).'
            );
        }

        // Return an XML formatted string
        $return = new \DOMDocument('1.0');
        $displayElement = $return->createElement('display');
        $return->appendChild($displayElement);

        // Uncomment this if we want additional logging in register.
        //$this->logProcessor->setDisplay(0, 'debug');

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
                $displayElement->setAttribute(
                    'message',
                    'Display is Registered and awaiting Authorisation from an Administrator in the CMS'
                );
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
                    if (strtolower($settingName) == 'xmrnetworkaddress' &&
                        (!isset($arrayItem['value']) || $arrayItem['value'] == '')
                    ) {
                        $arrayItem['value'] = $this->getConfig()->getSetting('XMR_PUB_ADDRESS');
                    }

                    // logLevels
                    if (strtolower($settingName) == 'loglevel') {
                        // return resting log level
                        // unless it is currently elevated, in which case return debug
                        $arrayItem['value'] = $this->display->getLogLevel();
                    }

                    $value = ($arrayItem['value'] ?? $arrayItem['default']);

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

                    // Apply an offset to the collectInterval
                    // https://github.com/xibosignage/xibo/issues/3530
                    if (strtolower($arrayItem['name']) == 'collectinterval') {
                        $value = $this->collectionIntervalWithOffset($value);
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
                $node->setAttribute('type', 'checkbox');
                $displayElement->appendChild($node);

                $nodeName = ($clientType == 'windows') ? 'DisplayTimeZone' : 'displayTimeZone';
                $node = $return->createElement($nodeName, (!empty($display->timeZone)) ? $display->timeZone : '');
                if ($clientType == 'windows') {
                    $node->setAttribute('type', 'string');
                }
                $displayElement->appendChild($node);

                // Adspace Enabled CMS?
                $isAdspaceEnabled = intval($this->getConfig()->getSetting('isAdspaceEnabled', 0));
                $node = $return->createElement('isAdspaceEnabled', $isAdspaceEnabled);
                $node->setAttribute('type', 'checkbox');
                $displayElement->appendChild($node);

                if (!empty($display->timeZone)) {
                    // Calculate local time
                    $dateNow->timezone($display->timeZone);

                    // Append Local Time
                    $displayElement->setAttribute('localTimezone', $display->timeZone);
                    $displayElement->setAttribute('localDate', $dateNow->format(DateFormatHelper::getSystemFormat()));
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
                            if (!$command->isReady()) {
                                continue;
                            }

                            $node = $return->createElement($command->code);
                            $commandString = $return->createElement('commandString');
                            $commandStringCData = $return->createCDATASection($command->getCommandString());
                            $commandString->appendChild($commandStringCData);
                            $validationString = $return->createElement('validationString');
                            $validationStringCData = $return->createCDATASection($command->getValidationString());
                            $validationString->appendChild($validationStringCData);
                            $alertOnString = $return->createElement('createAlertOn');
                            $alertOnStringCData = $return->createCDATASection($command->getCreateAlertOn());
                            $alertOnString->appendChild($alertOnStringCData);

                            $node->appendChild($commandString);
                            $node->appendChild($validationString);
                            $node->appendChild($alertOnString);

                            $commandElement->appendChild($node);
                        } catch (\DOMException $DOMException) {
                            $this->getLog()->error(
                                'Cannot add command to settings for displayId ' .
                                $this->display->displayId . ', ' . $DOMException->getMessage()
                            );
                        }
                    }
                }

                // Tags
                if (count($display->tags) > 0) {
                    $tagsElement = $return->createElement('tags');
                    $displayElement->appendChild($tagsElement);

                    foreach ($display->tags as $tagLink) {
                        $tagNode = $return->createElement('tag');

                        $tagNameNode = $return->createElement('tagName');
                        $tag = $return->createTextNode($tagLink->tag);
                        $tagNameNode->appendChild($tag);

                        $tagNode->appendChild($tagNameNode);

                        if ($tagLink->value !== null) {
                            $valueNode = $return->createElement('tagValue');
                            $value = $return->createTextNode($tagLink->value);
                            $valueNode->appendChild($value);

                            $tagNode->appendChild($valueNode);
                        }

                        $tagsElement->appendChild($tagNode);
                    }
                }

                // Check to see if the channel/pubKey are already entered
                $this->getLog()->debug('xmrChannel: [' . $xmrChannel . ']. xmrPublicKey: [' . $xmrPubKey . ']');

                // Update the Channel
                $display->xmrChannel = $xmrChannel;
                // Update the PUB Key only if it has been cleared
                if ($display->xmrPubKey == '') {
                    $display->xmrPubKey = $xmrPubKey;
                }
            }
        } catch (NotFoundException $e) {
            // Add a new display
            try {
                $display = $this->displayFactory->createEmpty();
                $this->display = $display;
                $display->display = $displayName;
                $display->auditingUntil = 0;
                // defaultLayoutId column cannot be null or empty string
                // if we do not have global default layout, set it here to 0 to allow register to proceed
                $display->defaultLayoutId = intval($this->getConfig()->getSetting('DEFAULT_LAYOUT', 0));
                $display->license = $hardwareKey;
                $display->licensed = $this->getConfig()->getSetting('DISPLAY_AUTO_AUTH', 0);
                $display->incSchedule = 0;
                $display->clientAddress = $this->getIp();
                $display->xmrChannel = $xmrChannel;
                $display->xmrPubKey = $xmrPubKey;
                $display->folderId = $this->getConfig()->getSetting('DISPLAY_DEFAULT_FOLDER', 1);

                if (!$display->isDisplaySlotAvailable()) {
                    $display->licensed = 0;
                }
            } catch (\InvalidArgumentException $e) {
                throw new \SoapFault('Sender', $e->getMessage());
            }

            $displayElement->setAttribute('status', 1);
            $displayElement->setAttribute('code', 'ADDED');
            if ($display->licensed == 0) {
                $displayElement->setAttribute(
                    'message',
                    'Display is now Registered and awaiting Authorisation from an Administrator in the CMS'
                );
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

        // Parse operatingSystem data
        $operatingSystemJson = json_decode($operatingSystem, false);

        // Newer version of players will return a JSON value, but for older version, it will return a string.
        // In case the json decode fails, use the operatingSystem string value as the default value for the osVersion.
        $display->osVersion = $operatingSystemJson->version ?? $operatingSystem;
        $display->osSdk = $operatingSystemJson->sdk ?? null;
        $display->manufacturer = $operatingSystemJson->manufacturer ?? null;
        $display->brand = $operatingSystemJson->brand ?? null;
        $display->model = $operatingSystemJson->model ?? null;

        // Commercial Licence Check,  0 - Not licensed, 1 - licensed, 2 - trial licence, 3 - not applicable
        // only sent by xmds v7
        if (!empty($commercialLicenceString) && !in_array($display->clientType, ['windows', 'linux'])) {
            $commercialLicenceString = strtolower($commercialLicenceString);
            if ($commercialLicenceString === 'licensed' || $commercialLicenceString === 'full') {
                $commercialLicence = 1;
            } elseif ($commercialLicenceString === 'trial') {
                $commercialLicence = 2;
            } else {
                $commercialLicence = 0;
            }

            $display->commercialLicence = $commercialLicence;
            $node = $return->createElement('commercialLicence', $commercialLicenceString);
            $displayElement->appendChild($node);
        }

        // commercial licence not applicable for Windows and Linux players.
        if (in_array($display->clientType, ['windows', 'linux'])) {
            $display->commercialLicence = 3;
        }

        if (!empty($display->syncGroupId)) {
            $syncGroup = $this->syncGroupFactory->getById($display->syncGroupId);

            if ($syncGroup->leadDisplayId === $display->displayId) {
                $syncNodeValue = 'lead';
            } else {
                $leadDisplay = $this->syncGroupFactory->getLeadDisplay($syncGroup->leadDisplayId);
                $syncNodeValue = $leadDisplay->lanIpAddress;
            }

            $syncNode = $return->createElement('syncGroup');
            $value = $return->createTextNode($syncNodeValue ?? '');
            $syncNode->appendChild($value);
            $displayElement->appendChild($syncNode);

            $syncPublisherPortNode = $return->createElement('syncPublisherPort');
            $value = $return->createTextNode($syncGroup->syncPublisherPort ?? 9590);
            $syncPublisherPortNode->appendChild($value);
            $displayElement->appendChild($syncPublisherPortNode);

            $syncSwitchDelayNode = $return->createElement('syncSwitchDelay');
            $value = $return->createTextNode($syncGroup->syncSwitchDelay ?? 750);
            $syncSwitchDelayNode->appendChild($value);
            $displayElement->appendChild($syncSwitchDelayNode);

            $syncVideoPauseDelayNode = $return->createElement('syncVideoPauseDelay');
            $value = $return->createTextNode($syncGroup->syncVideoPauseDelay ?? 100);
            $syncVideoPauseDelayNode->appendChild($value);
            $displayElement->appendChild($syncVideoPauseDelayNode);
        }

        $display->save(Display::$saveOptionsMinimum);

        // cache checks
        $cacheSchedule = $this->getPool()->getItem($this->display->getCacheKey() . '/schedule');
        $cacheSchedule->setInvalidationMethod(Invalidation::OLD);
        $displayElement->setAttribute(
            'checkSchedule',
            ($cacheSchedule->isHit() ? crc32($cacheSchedule->get()) : '')
        );

        $cacheRF = $this->getPool()->getItem($this->display->getCacheKey() . '/requiredFiles');
        $cacheRF->setInvalidationMethod(Invalidation::OLD);
        $displayElement->setAttribute('checkRf', ($cacheRF->isHit() ? crc32($cacheRF->get()) : ''));

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
     * Returns the schedule for the hardware key specified
     * @param string $serverKey
     * @param string $hardwareKey
     * @return string
     * @throws NotFoundException
     * @throws \SoapFault
     */
    public function Schedule($serverKey, $hardwareKey)
    {
        return $this->doSchedule($serverKey, $hardwareKey, ['dependentsAsNodes' => true, 'includeOverlays' => true]);
    }
}
