<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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
namespace Xibo\Controller;

use Xibo\Exception\InvalidArgumentException;

/**
 * Trait DisplayProfileConfigFields
 * @package Xibo\Controller
 */
trait DisplayProfileConfigFields
{
    /**
     * Edit config fields
     * @param \Xibo\Entity\DisplayProfile $displayProfile
     * @param null|array $config if empty will edit the config of provided display profile
     * @return null|array
     * @throws \Xibo\Exception\InvalidArgumentException
     */
    public function editConfigFields($displayProfile, $config = null)
    {
        // Setting on our own config or not?
        $ownConfig = ($config === null);

        switch ($displayProfile->getClientType()) {

            case 'android':
                $displayProfile->setSetting('emailAddress', $this->getSanitizer()->getString('emailAddress'), $ownConfig, $config);
                $displayProfile->setSetting('settingsPassword', $this->getSanitizer()->getString('settingsPassword'), $ownConfig, $config);
                $displayProfile->setSetting('collectInterval', $this->getSanitizer()->getInt('collectInterval'), $ownConfig, $config);
                $displayProfile->setSetting('downloadStartWindow', $this->getSanitizer()->getString('downloadStartWindow'), $ownConfig, $config);
                $displayProfile->setSetting('downloadEndWindow', $this->getSanitizer()->getString('downloadEndWindow'), $ownConfig, $config);
                $displayProfile->setSetting('xmrNetworkAddress', $this->getSanitizer()->getString('xmrNetworkAddress'), $ownConfig, $config);
                $displayProfile->setSetting('statsEnabled', $this->getSanitizer()->getCheckbox('statsEnabled'), $ownConfig, $config);
                $displayProfile->setSetting('orientation', $this->getSanitizer()->getInt('orientation'), $ownConfig, $config);
                $displayProfile->setSetting('screenDimensions', $this->getSanitizer()->getString('screenDimensions'), $ownConfig, $config);
                $displayProfile->setSetting('blacklistVideo', $this->getSanitizer()->getCheckbox('blacklistVideo'), $ownConfig, $config);
                $displayProfile->setSetting('storeHtmlOnInternal', $this->getSanitizer()->getCheckbox('storeHtmlOnInternal'), $ownConfig, $config);
                $displayProfile->setSetting('useSurfaceVideoView', $this->getSanitizer()->getCheckbox('useSurfaceVideoView'), $ownConfig, $config);
                $displayProfile->setSetting('logLevel', $this->getSanitizer()->getString('logLevel'), $ownConfig, $config);
                $displayProfile->setSetting('versionMediaId', $this->getSanitizer()->getInt('versionMediaId'), $ownConfig, $config);
                $displayProfile->setSetting('startOnBoot', $this->getSanitizer()->getCheckbox('startOnBoot'), $ownConfig, $config);
                $displayProfile->setSetting('actionBarMode', $this->getSanitizer()->getInt('actionBarMode'), $ownConfig, $config);
                $displayProfile->setSetting('actionBarDisplayDuration', $this->getSanitizer()->getInt('actionBarDisplayDuration'), $ownConfig, $config);
                $displayProfile->setSetting('actionBarIntent', $this->getSanitizer()->getString('actionBarIntent'), $ownConfig, $config);
                $displayProfile->setSetting('autoRestart', $this->getSanitizer()->getCheckbox('autoRestart'), $ownConfig, $config);
                $displayProfile->setSetting('startOnBootDelay', $this->getSanitizer()->getInt('startOnBootDelay'), $ownConfig, $config);
                $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                $displayProfile->setSetting('screenShotRequestInterval', $this->getSanitizer()->getInt('screenShotRequestInterval'), $ownConfig, $config);
                $displayProfile->setSetting('expireModifiedLayouts', $this->getSanitizer()->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                $displayProfile->setSetting('screenShotIntent', $this->getSanitizer()->getString('screenShotIntent'), $ownConfig, $config);
                $displayProfile->setSetting('screenShotSize', $this->getSanitizer()->getInt('screenShotSize'), $ownConfig, $config);
                $displayProfile->setSetting('updateStartWindow', $this->getSanitizer()->getString('updateStartWindow'), $ownConfig, $config);
                $displayProfile->setSetting('updateEndWindow', $this->getSanitizer()->getString('updateEndWindow'), $ownConfig, $config);
                $displayProfile->setSetting('webViewPluginState', $this->getSanitizer()->getString('webViewPluginState'), $ownConfig, $config);
                $displayProfile->setSetting('hardwareAccelerateWebViewMode', $this->getSanitizer()->getString('hardwareAccelerateWebViewMode'), $ownConfig, $config);
                $displayProfile->setSetting('timeSyncFromCms', $this->getSanitizer()->getCheckbox('timeSyncFromCms'), $ownConfig, $config);
                $displayProfile->setSetting('webCacheEnabled', $this->getSanitizer()->getCheckbox('webCacheEnabled'), $ownConfig, $config);
                $displayProfile->setSetting('serverPort', $this->getSanitizer()->getInt('serverPort'), $ownConfig, $config);
                $displayProfile->setSetting('installWithLoadedLinkLibraries', $this->getSanitizer()->getCheckbox('installWithLoadedLinkLibraries'), $ownConfig, $config);
                break;

            case 'windows':
                $displayProfile->setSetting('collectInterval', $this->getSanitizer()->getInt('collectInterval'), $ownConfig, $config);
                $displayProfile->setSetting('downloadStartWindow', $this->getSanitizer()->getString('downloadStartWindow'), $ownConfig, $config);
                $displayProfile->setSetting('downloadEndWindow', $this->getSanitizer()->getString('downloadEndWindow'), $ownConfig, $config);
                $displayProfile->setSetting('xmrNetworkAddress', $this->getSanitizer()->getString('xmrNetworkAddress'), $ownConfig, $config);
                $displayProfile->setSetting('statsEnabled', $this->getSanitizer()->getCheckbox('statsEnabled'), $ownConfig, $config);
                $displayProfile->setSetting('powerpointEnabled', $this->getSanitizer()->getCheckbox('powerpointEnabled'), $ownConfig, $config);
                $displayProfile->setSetting('sizeX', $this->getSanitizer()->getDouble('sizeX'), $ownConfig, $config);
                $displayProfile->setSetting('sizeY', $this->getSanitizer()->getDouble('sizeY'), $ownConfig, $config);
                $displayProfile->setSetting('offsetX', $this->getSanitizer()->getDouble('offsetX'), $ownConfig, $config);
                $displayProfile->setSetting('offsetY', $this->getSanitizer()->getDouble('offsetY'), $ownConfig, $config);
                $displayProfile->setSetting('clientInfomationCtrlKey', $this->getSanitizer()->getCheckbox('clientInfomationCtrlKey'), $ownConfig, $config);
                $displayProfile->setSetting('clientInformationKeyCode', $this->getSanitizer()->getString('clientInformationKeyCode'), $ownConfig, $config);
                $displayProfile->setSetting('logLevel', $this->getSanitizer()->getString('logLevel'), $ownConfig, $config);
                $displayProfile->setSetting('logToDiskLocation', $this->getSanitizer()->getString('logToDiskLocation'), $ownConfig, $config);
                $displayProfile->setSetting('showInTaskbar', $this->getSanitizer()->getCheckbox('showInTaskbar'), $ownConfig, $config);
                $displayProfile->setSetting('cursorStartPosition', $this->getSanitizer()->getString('cursorStartPosition'), $ownConfig, $config);
                $displayProfile->setSetting('doubleBuffering', $this->getSanitizer()->getCheckbox('doubleBuffering'), $ownConfig, $config);
                $displayProfile->setSetting('emptyLayoutDuration', $this->getSanitizer()->getInt('emptyLayoutDuration'), $ownConfig, $config);
                $displayProfile->setSetting('enableMouse', $this->getSanitizer()->getCheckbox('enableMouse'), $ownConfig, $config);
                $displayProfile->setSetting('enableShellCommands', $this->getSanitizer()->getCheckbox('enableShellCommands'), $ownConfig, $config);
                $displayProfile->setSetting('expireModifiedLayouts', $this->getSanitizer()->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                $displayProfile->setSetting('maxConcurrentDownloads', $this->getSanitizer()->getInt('maxConcurrentDownloads'), $ownConfig, $config);
                $displayProfile->setSetting('shellCommandAllowList', $this->getSanitizer()->getString('shellCommandAllowList'), $ownConfig, $config);
                $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                $displayProfile->setSetting('screenShotRequestInterval', $this->getSanitizer()->getInt('screenShotRequestInterval'), $ownConfig, $config);
                $displayProfile->setSetting('screenShotSize', $this->getSanitizer()->getInt('screenShotSize'), $ownConfig, $config);
                $displayProfile->setSetting('maxLogFileUploads', $this->getSanitizer()->getInt('maxLogFileUploads'), $ownConfig, $config);
                $displayProfile->setSetting('embeddedServerPort', $this->getSanitizer()->getInt('embeddedServerPort'), $ownConfig, $config);
                $displayProfile->setSetting('preventSleep', $this->getSanitizer()->getCheckbox('preventSleep'), $ownConfig, $config);
                break;

            case 'linux':
                $displayProfile->setSetting('collectInterval', $this->getSanitizer()->getInt('collectInterval'), $ownConfig, $config);
                $displayProfile->setSetting('downloadStartWindow', $this->getSanitizer()->getString('downloadStartWindow'), $ownConfig, $config);
                $displayProfile->setSetting('downloadEndWindow', $this->getSanitizer()->getString('downloadEndWindow'), $ownConfig, $config);
                $displayProfile->setSetting('xmrNetworkAddress', $this->getSanitizer()->getString('xmrNetworkAddress'), $ownConfig, $config);
                $displayProfile->setSetting('statsEnabled', $this->getSanitizer()->getCheckbox('statsEnabled'), $ownConfig, $config);
                $displayProfile->setSetting('sizeX', $this->getSanitizer()->getDouble('sizeX'), $ownConfig, $config);
                $displayProfile->setSetting('sizeY', $this->getSanitizer()->getDouble('sizeY'), $ownConfig, $config);
                $displayProfile->setSetting('offsetX', $this->getSanitizer()->getDouble('offsetX'), $ownConfig, $config);
                $displayProfile->setSetting('offsetY', $this->getSanitizer()->getDouble('offsetY'), $ownConfig, $config);
                $displayProfile->setSetting('logLevel', $this->getSanitizer()->getString('logLevel'), $ownConfig, $config);
                $displayProfile->setSetting('enableShellCommands', $this->getSanitizer()->getCheckbox('enableShellCommands'), $ownConfig, $config);
                $displayProfile->setSetting('expireModifiedLayouts', $this->getSanitizer()->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                $displayProfile->setSetting('maxConcurrentDownloads', $this->getSanitizer()->getInt('maxConcurrentDownloads'), $ownConfig, $config);
                $displayProfile->setSetting('shellCommandAllowList', $this->getSanitizer()->getString('shellCommandAllowList'), $ownConfig, $config);
                $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                $displayProfile->setSetting('screenShotRequestInterval', $this->getSanitizer()->getInt('screenShotRequestInterval'), $ownConfig, $config);
                $displayProfile->setSetting('screenShotSize', $this->getSanitizer()->getInt('screenShotSize'), $ownConfig, $config);
                $displayProfile->setSetting('maxLogFileUploads', $this->getSanitizer()->getInt('maxLogFileUploads'), $ownConfig, $config);
                $displayProfile->setSetting('embeddedServerPort', $this->getSanitizer()->getInt('embeddedServerPort'), $ownConfig, $config);
                $displayProfile->setSetting('preventSleep', $this->getSanitizer()->getCheckbox('preventSleep'), $ownConfig, $config);
                break;

            case 'lg':
            case 'sssp':

                $displayProfile->setSetting('emailAddress', $this->getSanitizer()->getString('emailAddress'), $ownConfig, $config);
                $displayProfile->setSetting('collectInterval', $this->getSanitizer()->getInt('collectInterval'), $ownConfig, $config);
                $displayProfile->setSetting('downloadStartWindow', $this->getSanitizer()->getString('downloadStartWindow'), $ownConfig, $config);
                $displayProfile->setSetting('downloadEndWindow', $this->getSanitizer()->getString('downloadEndWindow'), $ownConfig, $config);
                $displayProfile->setSetting('xmrNetworkAddress', $this->getSanitizer()->getString('xmrNetworkAddress'), $ownConfig, $config);
                $displayProfile->setSetting('statsEnabled', $this->getSanitizer()->getCheckbox('statsEnabled'), $ownConfig, $config);
                $displayProfile->setSetting('orientation', $this->getSanitizer()->getInt('orientation'), $ownConfig, $config);
                $displayProfile->setSetting('logLevel', $this->getSanitizer()->getString('logLevel'), $ownConfig, $config);
                $displayProfile->setSetting('versionMediaId', $this->getSanitizer()->getInt('versionMediaId'), $ownConfig, $config);
                $displayProfile->setSetting('actionBarMode', $this->getSanitizer()->getInt('actionBarMode'), $ownConfig, $config);
                $displayProfile->setSetting('actionBarDisplayDuration', $this->getSanitizer()->getInt('actionBarDisplayDuration'), $ownConfig, $config);
                $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                $displayProfile->setSetting('screenShotSize', $this->getSanitizer()->getInt('screenShotSize'), $ownConfig, $config);
                $displayProfile->setSetting('mediaInventoryTimer', $this->getSanitizer()->getInt('mediaInventoryTimer'), $ownConfig, $config);

                // Options object to be converted to a JSON string
                $timerOptions = (object)[];

                $timers = $this->getSanitizer()->getStringArray('timers');

                foreach ($timers as $timer) {
                    $timerDay = $timer['day'];

                    if (sizeof($timers) == 1 && $timerDay == '') {
                        break;
                    } else if ($timerDay == '' || property_exists($timerOptions, $timerDay)) {
                        // Repeated or Empty day input, throw exception
                        throw new InvalidArgumentException(__('On/Off Timers: Please check the days selected and remove the duplicates or empty'), 'timers');
                    } else {
                        // Get time values
                        $timerOn = $timer['on'];
                        $timerOff = $timer['off'];

                        // Check the on/off times are in the correct format (H:i)
                        if (strlen($timerOn) != 5 || strlen($timerOff) != 5) {
                            throw new InvalidArgumentException(__('On/Off Timers: Please enter a on and off date for any row with a day selected, or remove that row'), 'timers');
                        } else {
                            //Build object and add it to the main options object
                            $temp = [];
                            $temp['on'] = $timerOn;
                            $temp['off'] = $timerOff;
                            $timerOptions->$timerDay = $temp;
                        }
                    }
                }

                // Encode option and save it as a string to the lock setting
                $displayProfile->setSetting('timers', json_encode($timerOptions), $ownConfig, $config);

                // Options object to be converted to a JSON string
                $pictureControlsOptions = (object)[];

                // Special string properties map
                $specialProperties = (object)[];
                $specialProperties->dynamicContrast = ["off", "low", "medium", "high"];
                $specialProperties->superResolution = ["off", "low", "medium", "high"];
                $specialProperties->colorGamut = ["normal", "extended"];
                $specialProperties->dynamicColor = ["off", "low", "medium", "high"];
                $specialProperties->noiseReduction = ["auto", "off", "low", "medium", "high"];
                $specialProperties->mpegNoiseReduction = ["auto", "off", "low", "medium", "high"];
                $specialProperties->blackLevel = ["low", "high"];
                $specialProperties->gamma = ["low", "medium", "high", "high2"];

                // Get array from request
                $pictureControls = $this->getSanitizer()->getStringArray('pictureControls');

                foreach ($pictureControls as $pictureControl) {
                    $propertyName = $pictureControl['property'];

                    if(sizeof($pictureControls) == 1 && $propertyName == '') {
                        break;
                    } else if($propertyName == '' || property_exists($pictureControlsOptions, $propertyName)) {
                        // Repeated or Empty property input, throw exception
                        throw new InvalidArgumentException(__('Picture: Please check the settings selected and remove the duplicates or empty'), 'pictureOptions');
                    } else {
                        // Get time values
                        $propertyValue = $pictureControl['value'];

                        // Check the on/off times are in the correct format (H:i)
                        if (property_exists($specialProperties, $propertyName)) {
                            $pictureControlsOptions->$propertyName = $specialProperties->$propertyName[$propertyValue];
                        } else {
                            //Build object and add it to the main options object
                            $pictureControlsOptions->$propertyName = (int)$propertyValue;
                        }
                    }
                }

                // Encode option and save it as a string to the lock setting
                $displayProfile->setSetting('pictureOptions', json_encode($pictureControlsOptions), $ownConfig, $config);

                // Get values from lockOptions params
                $usblock = $this->getSanitizer()->getString('usblock', '');
                $osdlock = $this->getSanitizer()->getString('osdlock', '');
                $keylockLocal = $this->getSanitizer()->getString('keylockLocal', '');
                $keylockRemote = $this->getSanitizer()->getString('keylockRemote', '');

                // Options object to be converted to a JSON string
                $lockOptions = (object)[];

                if ($usblock != 'empty') {
                    $lockOptions->usblock = $usblock === 'true'? true: false;
                }

                if ($osdlock != 'empty') {
                    $lockOptions->osdlock = $osdlock === 'true'? true: false;
                }

                if ($keylockLocal != '' || $keylockRemote != '') {
                    // Keylock sub object
                    $lockOptions->keylock = (object)[];

                    if ($keylockLocal != '') {
                        $lockOptions->keylock->local = $keylockLocal;
                    }

                    if ($keylockRemote != '') {
                        $lockOptions->keylock->remote = $keylockRemote;
                    }
                }

                // Encode option and save it as a string to the lock setting
                $displayProfile->setSetting('lockOptions', json_encode($lockOptions), $ownConfig, $config);

                break;

            default:
                $this->getLog()->info('Edit for unknown type ' . $displayProfile->getClientType());
        }

        return $config;
    }
}