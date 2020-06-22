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
                if ($this->getSanitizer()->hasParam('emailAddress')) {
                    $displayProfile->setSetting('emailAddress', $this->getSanitizer()->getString('emailAddress'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('settingsPassword')) {
                    $displayProfile->setSetting('settingsPassword', $this->getSanitizer()->getString('settingsPassword'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('collectInterval')) {
                    $displayProfile->setSetting('collectInterval', $this->getSanitizer()->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadStartWindow')) {
                    $displayProfile->setSetting('downloadStartWindow', $this->getSanitizer()->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadEndWindow')) {
                    $displayProfile->setSetting('downloadEndWindow', $this->getSanitizer()->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('xmrNetworkAddress')) {
                    $displayProfile->setSetting('xmrNetworkAddress', $this->getSanitizer()->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('statsEnabled')) {
                    $displayProfile->setSetting('statsEnabled', $this->getSanitizer()->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('aggregationLevel')) {
                    $displayProfile->setSetting('aggregationLevel', $this->getSanitizer()->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('orientation')) {
                    $displayProfile->setSetting('orientation', $this->getSanitizer()->getInt('orientation'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenDimensions')) {
                    $displayProfile->setSetting('screenDimensions', $this->getSanitizer()->getString('screenDimensions'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('blacklistVideo')) {
                    $displayProfile->setSetting('blacklistVideo', $this->getSanitizer()->getCheckbox('blacklistVideo'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('storeHtmlOnInternal')) {
                    $displayProfile->setSetting('storeHtmlOnInternal', $this->getSanitizer()->getCheckbox('storeHtmlOnInternal'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('useSurfaceVideoView')) {
                    $displayProfile->setSetting('useSurfaceVideoView', $this->getSanitizer()->getCheckbox('useSurfaceVideoView'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('logLevel')) {
                    $displayProfile->setSetting('logLevel', $this->getSanitizer()->getString('logLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('versionMediaId')) {
                    $displayProfile->setSetting('versionMediaId', $this->getSanitizer()->getInt('versionMediaId'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('startOnBoot')) {
                    $displayProfile->setSetting('startOnBoot', $this->getSanitizer()->getCheckbox('startOnBoot'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('actionBarMode')) {
                    $displayProfile->setSetting('actionBarMode', $this->getSanitizer()->getInt('actionBarMode'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('actionBarDisplayDuration')) {
                    $displayProfile->setSetting('actionBarDisplayDuration', $this->getSanitizer()->getInt('actionBarDisplayDuration'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('actionBarIntent')) {
                    $displayProfile->setSetting('actionBarIntent', $this->getSanitizer()->getString('actionBarIntent'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('autoRestart')) {
                    $displayProfile->setSetting('autoRestart', $this->getSanitizer()->getCheckbox('autoRestart'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('startOnBootDelay')) {
                    $displayProfile->setSetting('startOnBootDelay', $this->getSanitizer()->getInt('startOnBootDelay'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotRequestInterval')) {
                    $displayProfile->setSetting('screenShotRequestInterval', $this->getSanitizer()->getInt('screenShotRequestInterval'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('expireModifiedLayouts')) {
                    $displayProfile->setSetting('expireModifiedLayouts', $this->getSanitizer()->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotIntent')) {
                    $displayProfile->setSetting('screenShotIntent', $this->getSanitizer()->getString('screenShotIntent'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotSize')) {
                    $displayProfile->setSetting('screenShotSize', $this->getSanitizer()->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('updateStartWindow')) {
                    $displayProfile->setSetting('updateStartWindow', $this->getSanitizer()->getString('updateStartWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('updateEndWindow')) {
                    $displayProfile->setSetting('updateEndWindow', $this->getSanitizer()->getString('updateEndWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('dayPartId')) {
                    $displayProfile->setSetting('dayPartId', $this->getSanitizer()->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('webViewPluginState')) {
                    $displayProfile->setSetting('webViewPluginState', $this->getSanitizer()->getString('webViewPluginState'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('hardwareAccelerateWebViewMode')) {
                    $displayProfile->setSetting('hardwareAccelerateWebViewMode', $this->getSanitizer()->getString('hardwareAccelerateWebViewMode'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('timeSyncFromCms')) {
                    $displayProfile->setSetting('timeSyncFromCms', $this->getSanitizer()->getCheckbox('timeSyncFromCms'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('webCacheEnabled')) {
                    $displayProfile->setSetting('webCacheEnabled', $this->getSanitizer()->getCheckbox('webCacheEnabled'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('serverPort')) {
                    $displayProfile->setSetting('serverPort', $this->getSanitizer()->getInt('serverPort'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('installWithLoadedLinkLibraries')) {
                    $displayProfile->setSetting('installWithLoadedLinkLibraries', $this->getSanitizer()->getCheckbox('installWithLoadedLinkLibraries'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('forceHttps')) {
                    $displayProfile->setSetting('forceHttps', $this->getSanitizer()->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('useMultipleVideoDecoders')) {
                    $displayProfile->setSetting('useMultipleVideoDecoders', $this->getSanitizer()->getString('useMultipleVideoDecoders'), $ownConfig, $config);
                }

                break;

            case 'windows':
                if ($this->getSanitizer()->hasParam('collectInterval')) {
                    $displayProfile->setSetting('collectInterval', $this->getSanitizer()->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadStartWindow')) {
                    $displayProfile->setSetting('downloadStartWindow', $this->getSanitizer()->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadEndWindow')) {
                    $displayProfile->setSetting('downloadEndWindow', $this->getSanitizer()->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('xmrNetworkAddress')) {
                    $displayProfile->setSetting('xmrNetworkAddress', $this->getSanitizer()->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('dayPartId')) {
                    $displayProfile->setSetting('dayPartId', $this->getSanitizer()->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('statsEnabled')) {
                    $displayProfile->setSetting('statsEnabled', $this->getSanitizer()->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('aggregationLevel')) {
                    $displayProfile->setSetting('aggregationLevel', $this->getSanitizer()->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('powerpointEnabled')) {
                    $displayProfile->setSetting('powerpointEnabled', $this->getSanitizer()->getCheckbox('powerpointEnabled'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sizeX')) {
                    $displayProfile->setSetting('sizeX', $this->getSanitizer()->getDouble('sizeX'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sizeY')) {
                    $displayProfile->setSetting('sizeY', $this->getSanitizer()->getDouble('sizeY'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('offsetX')) {
                    $displayProfile->setSetting('offsetX', $this->getSanitizer()->getDouble('offsetX'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('offsetY')) {
                    $displayProfile->setSetting('offsetY', $this->getSanitizer()->getDouble('offsetY'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('clientInfomationCtrlKey')) {
                    $displayProfile->setSetting('clientInfomationCtrlKey', $this->getSanitizer()->getCheckbox('clientInfomationCtrlKey'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('clientInformationKeyCode')) {
                    $displayProfile->setSetting('clientInformationKeyCode', $this->getSanitizer()->getString('clientInformationKeyCode'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('logLevel')) {
                    $displayProfile->setSetting('logLevel', $this->getSanitizer()->getString('logLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('logToDiskLocation')) {
                    $displayProfile->setSetting('logToDiskLocation', $this->getSanitizer()->getString('logToDiskLocation'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('showInTaskbar')) {
                    $displayProfile->setSetting('showInTaskbar', $this->getSanitizer()->getCheckbox('showInTaskbar'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('cursorStartPosition')) {
                    $displayProfile->setSetting('cursorStartPosition', $this->getSanitizer()->getString('cursorStartPosition'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('doubleBuffering')) {
                    $displayProfile->setSetting('doubleBuffering', $this->getSanitizer()->getCheckbox('doubleBuffering'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('emptyLayoutDuration')) {
                    $displayProfile->setSetting('emptyLayoutDuration', $this->getSanitizer()->getInt('emptyLayoutDuration'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('enableMouse')) {
                    $displayProfile->setSetting('enableMouse', $this->getSanitizer()->getCheckbox('enableMouse'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('enableShellCommands')) {
                    $displayProfile->setSetting('enableShellCommands', $this->getSanitizer()->getCheckbox('enableShellCommands'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('expireModifiedLayouts')) {
                    $displayProfile->setSetting('expireModifiedLayouts', $this->getSanitizer()->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('maxConcurrentDownloads')) {
                    $displayProfile->setSetting('maxConcurrentDownloads', $this->getSanitizer()->getInt('maxConcurrentDownloads'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('shellCommandAllowList')) {
                    $displayProfile->setSetting('shellCommandAllowList', $this->getSanitizer()->getString('shellCommandAllowList'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotRequestInterval')) {
                    $displayProfile->setSetting('screenShotRequestInterval', $this->getSanitizer()->getInt('screenShotRequestInterval'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotSize')) {
                    $displayProfile->setSetting('screenShotSize', $this->getSanitizer()->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('maxLogFileUploads')) {
                    $displayProfile->setSetting('maxLogFileUploads', $this->getSanitizer()->getInt('maxLogFileUploads'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('embeddedServerPort')) {
                    $displayProfile->setSetting('embeddedServerPort', $this->getSanitizer()->getInt('embeddedServerPort'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('preventSleep')) {
                    $displayProfile->setSetting('preventSleep', $this->getSanitizer()->getCheckbox('preventSleep'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('forceHttps')) {
                    $displayProfile->setSetting('forceHttps', $this->getSanitizer()->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                break;

            case 'linux':
                if ($this->getSanitizer()->hasParam('collectInterval')) {
                    $displayProfile->setSetting('collectInterval', $this->getSanitizer()->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadStartWindow')) {
                    $displayProfile->setSetting('downloadStartWindow', $this->getSanitizer()->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadEndWindow')) {
                    $displayProfile->setSetting('downloadEndWindow', $this->getSanitizer()->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('dayPartId')) {
                    $displayProfile->setSetting('dayPartId', $this->getSanitizer()->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('xmrNetworkAddress')) {
                    $displayProfile->setSetting('xmrNetworkAddress', $this->getSanitizer()->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('statsEnabled')) {
                    $displayProfile->setSetting('statsEnabled', $this->getSanitizer()->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('aggregationLevel')) {
                    $displayProfile->setSetting('aggregationLevel', $this->getSanitizer()->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sizeX')) {
                    $displayProfile->setSetting('sizeX', $this->getSanitizer()->getDouble('sizeX'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sizeY')) {
                    $displayProfile->setSetting('sizeY', $this->getSanitizer()->getDouble('sizeY'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('offsetX')) {
                    $displayProfile->setSetting('offsetX', $this->getSanitizer()->getDouble('offsetX'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('offsetY')) {
                    $displayProfile->setSetting('offsetY', $this->getSanitizer()->getDouble('offsetY'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('logLevel')) {
                    $displayProfile->setSetting('logLevel', $this->getSanitizer()->getString('logLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('enableShellCommands')) {
                    $displayProfile->setSetting('enableShellCommands', $this->getSanitizer()->getCheckbox('enableShellCommands'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('expireModifiedLayouts')) {
                    $displayProfile->setSetting('expireModifiedLayouts', $this->getSanitizer()->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('maxConcurrentDownloads')) {
                    $displayProfile->setSetting('maxConcurrentDownloads', $this->getSanitizer()->getInt('maxConcurrentDownloads'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('shellCommandAllowList')) {
                    $displayProfile->setSetting('shellCommandAllowList', $this->getSanitizer()->getString('shellCommandAllowList'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotRequestInterval')) {
                    $displayProfile->setSetting('screenShotRequestInterval', $this->getSanitizer()->getInt('screenShotRequestInterval'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotSize')) {
                    $displayProfile->setSetting('screenShotSize', $this->getSanitizer()->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('maxLogFileUploads')) {
                    $displayProfile->setSetting('maxLogFileUploads', $this->getSanitizer()->getInt('maxLogFileUploads'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('embeddedServerPort')) {
                    $displayProfile->setSetting('embeddedServerPort', $this->getSanitizer()->getInt('embeddedServerPort'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('preventSleep')) {
                    $displayProfile->setSetting('preventSleep', $this->getSanitizer()->getCheckbox('preventSleep'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('forceHttps')) {
                    $displayProfile->setSetting('forceHttps', $this->getSanitizer()->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                break;

            case 'lg':
            case 'sssp':

                if ($this->getSanitizer()->hasParam('emailAddress')) {
                    $displayProfile->setSetting('emailAddress', $this->getSanitizer()->getString('emailAddress'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('collectInterval')) {
                    $displayProfile->setSetting('collectInterval', $this->getSanitizer()->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadStartWindow')) {
                    $displayProfile->setSetting('downloadStartWindow', $this->getSanitizer()->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadEndWindow')) {
                    $displayProfile->setSetting('downloadEndWindow', $this->getSanitizer()->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('dayPartId')) {
                    $displayProfile->setSetting('dayPartId', $this->getSanitizer()->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('xmrNetworkAddress')) {
                    $displayProfile->setSetting('xmrNetworkAddress', $this->getSanitizer()->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('statsEnabled')) {
                    $displayProfile->setSetting('statsEnabled', $this->getSanitizer()->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('aggregationLevel')) {
                    $displayProfile->setSetting('aggregationLevel', $this->getSanitizer()->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('orientation')) {
                    $displayProfile->setSetting('orientation', $this->getSanitizer()->getInt('orientation'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('logLevel')) {
                    $displayProfile->setSetting('logLevel', $this->getSanitizer()->getString('logLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('versionMediaId')) {
                    $displayProfile->setSetting('versionMediaId', $this->getSanitizer()->getInt('versionMediaId'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('actionBarMode')) {
                    $displayProfile->setSetting('actionBarMode', $this->getSanitizer()->getInt('actionBarMode'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('actionBarDisplayDuration')) {
                    $displayProfile->setSetting('actionBarDisplayDuration', $this->getSanitizer()->getInt('actionBarDisplayDuration'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotSize')) {
                    $displayProfile->setSetting('screenShotSize', $this->getSanitizer()->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('mediaInventoryTimer')) {
                    $displayProfile->setSetting('mediaInventoryTimer', $this->getSanitizer()->getInt('mediaInventoryTimer'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('forceHttps')) {
                    $displayProfile->setSetting('forceHttps', $this->getSanitizer()->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('timers')) {
                    // Options object to be converted to a JSON string
                    $timerOptions = (object)[];

                    $timers = $this->getSanitizer()->getStringArray('timers');

                    foreach ($timers as $timer) {
                        $timerDay = $timer['day'];

                        if (sizeof($timers) == 1 && $timerDay == '') {
                            break;
                        } else {
                            if ($timerDay == '' || property_exists($timerOptions, $timerDay)) {
                                // Repeated or Empty day input, throw exception
                                throw new InvalidArgumentException(__('On/Off Timers: Please check the days selected and remove the duplicates or empty'),
                                    'timers');
                            } else {
                                // Get time values
                                $timerOn = $timer['on'];
                                $timerOff = $timer['off'];

                                // Check the on/off times are in the correct format (H:i)
                                if (strlen($timerOn) != 5 || strlen($timerOff) != 5) {
                                    throw new InvalidArgumentException(__('On/Off Timers: Please enter a on and off date for any row with a day selected, or remove that row'),
                                        'timers');
                                } else {
                                    //Build object and add it to the main options object
                                    $temp = [];
                                    $temp['on'] = $timerOn;
                                    $temp['off'] = $timerOff;
                                    $timerOptions->$timerDay = $temp;
                                }
                            }
                        }
                    }

                    // Encode option and save it as a string to the lock setting
                    $displayProfile->setSetting('timers', json_encode($timerOptions), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('pictureControls')) {
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

                        if (sizeof($pictureControls) == 1 && $propertyName == '') {
                            break;
                        } else {
                            if ($propertyName == '' || property_exists($pictureControlsOptions, $propertyName)) {
                                // Repeated or Empty property input, throw exception
                                throw new InvalidArgumentException(__('Picture: Please check the settings selected and remove the duplicates or empty'),
                                    'pictureOptions');
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
                    }

                    // Encode option and save it as a string to the lock setting
                    $displayProfile->setSetting('pictureOptions', json_encode($pictureControlsOptions), $ownConfig,
                        $config);
                }

                // Get values from lockOptions params
                $usblock = $this->getSanitizer()->getString('usblock', '');
                $osdlock = $this->getSanitizer()->getString('osdlock', '');
                $keylockLocal = $this->getSanitizer()->getString('keylockLocal', '');
                $keylockRemote = $this->getSanitizer()->getString('keylockRemote', '');

                // Options object to be converted to a JSON string
                $lockOptions = (object)[];

                if ($usblock != 'empty' && $displayProfile->type == 'lg') {
                    $lockOptions->usblock = $usblock === 'true' ? true : false;
                }

                if ($osdlock != 'empty') {
                    $lockOptions->osdlock = $osdlock === 'true' ? true : false;
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