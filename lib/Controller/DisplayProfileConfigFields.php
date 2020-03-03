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
namespace Xibo\Controller;

use Xibo\Exception\InvalidArgumentException;
use Slim\Http\ServerRequest as Request;
use Xibo\Support\Sanitizer\SanitizerInterface;

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
     * @param Request $request
     * @return null|array
     * @throws InvalidArgumentException
     */
    public function editConfigFields($displayProfile, $config = null, Request $request)
    {

        /** @var SanitizerInterface $sanitizedParams */
        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Setting on our own config or not?
        $ownConfig = ($config === null);

        switch ($displayProfile->getClientType()) {

            case 'android':
                if ($sanitizedParams->hasParam('emailAddress')) {
                    $displayProfile->setSetting('emailAddress', $sanitizedParams->getString('emailAddress'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('settingsPassword')) {
                    $displayProfile->setSetting('settingsPassword', $sanitizedParams->getString('settingsPassword'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('collectInterval')) {
                    $displayProfile->setSetting('collectInterval', $sanitizedParams->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadStartWindow')) {
                    $displayProfile->setSetting('downloadStartWindow', $sanitizedParams->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadEndWindow')) {
                    $displayProfile->setSetting('downloadEndWindow', $sanitizedParams->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('xmrNetworkAddress')) {
                    $displayProfile->setSetting('xmrNetworkAddress', $sanitizedParams->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('statsEnabled')) {
                    $displayProfile->setSetting('statsEnabled', $sanitizedParams->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('aggregationLevel')) {
                    $displayProfile->setSetting('aggregationLevel', $sanitizedParams->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('orientation')) {
                    $displayProfile->setSetting('orientation', $sanitizedParams->getInt('orientation'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenDimensions')) {
                    $displayProfile->setSetting('screenDimensions', $sanitizedParams->getString('screenDimensions'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('blacklistVideo')) {
                    $displayProfile->setSetting('blacklistVideo', $sanitizedParams->getCheckbox('blacklistVideo'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('storeHtmlOnInternal')) {
                    $displayProfile->setSetting('storeHtmlOnInternal', $sanitizedParams->getCheckbox('storeHtmlOnInternal'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('useSurfaceVideoView')) {
                    $displayProfile->setSetting('useSurfaceVideoView', $sanitizedParams->getCheckbox('useSurfaceVideoView'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('logLevel')) {
                    $displayProfile->setSetting('logLevel', $sanitizedParams->getString('logLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('versionMediaId')) {
                    $displayProfile->setSetting('versionMediaId', $sanitizedParams->getInt('versionMediaId'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('startOnBoot')) {
                    $displayProfile->setSetting('startOnBoot', $sanitizedParams->getCheckbox('startOnBoot'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('actionBarMode')) {
                    $displayProfile->setSetting('actionBarMode', $sanitizedParams->getInt('actionBarMode'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('actionBarDisplayDuration')) {
                    $displayProfile->setSetting('actionBarDisplayDuration', $sanitizedParams->getInt('actionBarDisplayDuration'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('actionBarIntent')) {
                    $displayProfile->setSetting('actionBarIntent', $sanitizedParams->getString('actionBarIntent'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('autoRestart')) {
                    $displayProfile->setSetting('autoRestart', $sanitizedParams->getCheckbox('autoRestart'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('startOnBootDelay')) {
                    $displayProfile->setSetting('startOnBootDelay', $sanitizedParams->getInt('startOnBootDelay'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotRequestInterval')) {
                    $displayProfile->setSetting('screenShotRequestInterval', $sanitizedParams->getInt('screenShotRequestInterval'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('expireModifiedLayouts')) {
                    $displayProfile->setSetting('expireModifiedLayouts', $sanitizedParams->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotIntent')) {
                    $displayProfile->setSetting('screenShotIntent', $sanitizedParams->getString('screenShotIntent'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotSize')) {
                    $displayProfile->setSetting('screenShotSize', $sanitizedParams->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('updateStartWindow')) {
                    $displayProfile->setSetting('updateStartWindow', $sanitizedParams->getString('updateStartWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('updateEndWindow')) {
                    $displayProfile->setSetting('updateEndWindow', $sanitizedParams->getString('updateEndWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('dayPartId')) {
                    $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('webViewPluginState')) {
                    $displayProfile->setSetting('webViewPluginState', $sanitizedParams->getString('webViewPluginState'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('hardwareAccelerateWebViewMode')) {
                    $displayProfile->setSetting('hardwareAccelerateWebViewMode', $sanitizedParams->getString('hardwareAccelerateWebViewMode'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('timeSyncFromCms')) {
                    $displayProfile->setSetting('timeSyncFromCms', $sanitizedParams->getCheckbox('timeSyncFromCms'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('webCacheEnabled')) {
                    $displayProfile->setSetting('webCacheEnabled', $sanitizedParams->getCheckbox('webCacheEnabled'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('serverPort')) {
                    $displayProfile->setSetting('serverPort', $sanitizedParams->getInt('serverPort'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('installWithLoadedLinkLibraries')) {
                    $displayProfile->setSetting('installWithLoadedLinkLibraries', $sanitizedParams->getCheckbox('installWithLoadedLinkLibraries'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('forceHttps')) {
                    $displayProfile->setSetting('forceHttps', $sanitizedParams->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                break;

            case 'windows':
                if ($sanitizedParams->hasParam('collectInterval')) {
                    $displayProfile->setSetting('collectInterval', $sanitizedParams->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadStartWindow')) {
                    $displayProfile->setSetting('downloadStartWindow', $sanitizedParams->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadEndWindow')) {
                    $displayProfile->setSetting('downloadEndWindow', $sanitizedParams->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('xmrNetworkAddress')) {
                    $displayProfile->setSetting('xmrNetworkAddress', $sanitizedParams->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('dayPartId')) {
                    $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('statsEnabled')) {
                    $displayProfile->setSetting('statsEnabled', $sanitizedParams->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('aggregationLevel')) {
                    $displayProfile->setSetting('aggregationLevel', $sanitizedParams->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('powerpointEnabled')) {
                    $displayProfile->setSetting('powerpointEnabled', $sanitizedParams->getCheckbox('powerpointEnabled'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sizeX')) {
                    $displayProfile->setSetting('sizeX', $sanitizedParams->getDouble('sizeX'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sizeY')) {
                    $displayProfile->setSetting('sizeY', $sanitizedParams->getDouble('sizeY'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('offsetX')) {
                    $displayProfile->setSetting('offsetX', $sanitizedParams->getDouble('offsetX'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('offsetY')) {
                    $displayProfile->setSetting('offsetY', $sanitizedParams->getDouble('offsetY'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('clientInfomationCtrlKey')) {
                    $displayProfile->setSetting('clientInfomationCtrlKey', $sanitizedParams->getCheckbox('clientInfomationCtrlKey'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('clientInformationKeyCode')) {
                    $displayProfile->setSetting('clientInformationKeyCode', $sanitizedParams->getString('clientInformationKeyCode'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('logLevel')) {
                    $displayProfile->setSetting('logLevel', $sanitizedParams->getString('logLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('logToDiskLocation')){
                    $displayProfile->setSetting('logToDiskLocation', $sanitizedParams->getString('logToDiskLocation'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('showInTaskbar')) {
                    $displayProfile->setSetting('showInTaskbar', $sanitizedParams->getCheckbox('showInTaskbar'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('cursorStartPosition')) {
                    $displayProfile->setSetting('cursorStartPosition', $sanitizedParams->getString('cursorStartPosition'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('doubleBuffering')) {
                    $displayProfile->setSetting('doubleBuffering', $sanitizedParams->getCheckbox('doubleBuffering'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('emptyLayoutDuration')) {
                    $displayProfile->setSetting('emptyLayoutDuration', $sanitizedParams->getInt('emptyLayoutDuration'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('enableMouse')) {
                    $displayProfile->setSetting('enableMouse', $sanitizedParams->getCheckbox('enableMouse'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('enableShellCommands')) {
                    $displayProfile->setSetting('enableShellCommands', $sanitizedParams->getCheckbox('enableShellCommands'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('expireModifiedLayouts')) {
                    $displayProfile->setSetting('expireModifiedLayouts', $sanitizedParams->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('maxConcurrentDownloads')) {
                    $displayProfile->setSetting('maxConcurrentDownloads', $sanitizedParams->getInt('maxConcurrentDownloads'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('shellCommandAllowList')) {
                    $displayProfile->setSetting('shellCommandAllowList', $sanitizedParams->getString('shellCommandAllowList'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotRequestInterval')) {
                    $displayProfile->setSetting('screenShotRequestInterval', $sanitizedParams->getInt('screenShotRequestInterval'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotSize')) {
                    $displayProfile->setSetting('screenShotSize', $sanitizedParams->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('maxLogFileUploads')) {
                    $displayProfile->setSetting('maxLogFileUploads', $sanitizedParams->getInt('maxLogFileUploads'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('embeddedServerPort')) {
                    $displayProfile->setSetting('embeddedServerPort', $sanitizedParams->getInt('embeddedServerPort'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('preventSleep')) {
                    $displayProfile->setSetting('preventSleep', $sanitizedParams->getCheckbox('preventSleep'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('forceHttps')) {
                    $displayProfile->setSetting('forceHttps', $sanitizedParams->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                break;

            case 'linux':
                if ($sanitizedParams->hasParam('collectInterval'))  {
                    $displayProfile->setSetting('collectInterval', $sanitizedParams->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadStartWindow')) {
                    $displayProfile->setSetting('downloadStartWindow', $sanitizedParams->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadEndWindow')) {
                    $displayProfile->setSetting('downloadEndWindow', $sanitizedParams->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('dayPartId')) {
                    $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('xmrNetworkAddress')) {
                    $displayProfile->setSetting('xmrNetworkAddress', $sanitizedParams->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('statsEnabled')) {
                    $displayProfile->setSetting('statsEnabled', $sanitizedParams->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('aggregationLevel')) {
                    $displayProfile->setSetting('aggregationLevel', $sanitizedParams->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sizeX')) {
                    $displayProfile->setSetting('sizeX', $sanitizedParams->getDouble('sizeX'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sizeY')) {
                    $displayProfile->setSetting('sizeY', $sanitizedParams->getDouble('sizeY'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('offsetX')) {
                    $displayProfile->setSetting('offsetX', $sanitizedParams->getDouble('offsetX'), $ownConfig, $config);
                }

                if($sanitizedParams->hasParam('offsetY')) {
                    $displayProfile->setSetting('offsetY', $sanitizedParams->getDouble('offsetY'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('logLevel')) {
                    $displayProfile->setSetting('logLevel', $sanitizedParams->getString('logLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('enableShellCommands')) {
                    $displayProfile->setSetting('enableShellCommands', $sanitizedParams->getCheckbox('enableShellCommands'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('expireModifiedLayouts')) {
                    $displayProfile->setSetting('expireModifiedLayouts', $sanitizedParams->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('maxConcurrentDownloads')) {
                    $displayProfile->setSetting('maxConcurrentDownloads', $sanitizedParams->getInt('maxConcurrentDownloads'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('shellCommandAllowList')) {
                    $displayProfile->setSetting('shellCommandAllowList', $sanitizedParams->getString('shellCommandAllowList'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotRequestInterval')) {
                    $displayProfile->setSetting('screenShotRequestInterval', $sanitizedParams->getInt('screenShotRequestInterval'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotSize')) {
                    $displayProfile->setSetting('screenShotSize', $sanitizedParams->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('maxLogFileUploads')) {
                    $displayProfile->setSetting('maxLogFileUploads', $sanitizedParams->getInt('maxLogFileUploads'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('embeddedServerPort')) {
                    $displayProfile->setSetting('embeddedServerPort', $sanitizedParams->getInt('embeddedServerPort'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('preventSleep')) {
                    $displayProfile->setSetting('preventSleep', $sanitizedParams->getCheckbox('preventSleep'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('forceHttps')) {
                    $displayProfile->setSetting('forceHttps', $sanitizedParams->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                break;

            case 'lg':
            case 'sssp':

                if ($sanitizedParams->hasParam('emailAddress')) {
                    $displayProfile->setSetting('emailAddress', $sanitizedParams->getString('emailAddress'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('collectInterval')) {
                    $displayProfile->setSetting('collectInterval', $sanitizedParams->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadStartWindow')) {
                    $displayProfile->setSetting('downloadStartWindow', $sanitizedParams->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadEndWindow')) {
                    $displayProfile->setSetting('downloadEndWindow', $sanitizedParams->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('dayPartId')) {
                    $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('xmrNetworkAddress')) {
                    $displayProfile->setSetting('xmrNetworkAddress', $sanitizedParams->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('statsEnabled')) {
                    $displayProfile->setSetting('statsEnabled', $sanitizedParams->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('aggregationLevel')) {
                    $displayProfile->setSetting('aggregationLevel', $sanitizedParams->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('orientation')) {
                    $displayProfile->setSetting('orientation', $sanitizedParams->getInt('orientation'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('logLevel')) {
                    $displayProfile->setSetting('logLevel', $sanitizedParams->getString('logLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('versionMediaId')) {
                    $displayProfile->setSetting('versionMediaId', $sanitizedParams->getInt('versionMediaId'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('actionBarMode')) {
                    $displayProfile->setSetting('actionBarMode', $sanitizedParams->getInt('actionBarMode'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('actionBarDisplayDuration')) {
                    $displayProfile->setSetting('actionBarDisplayDuration', $sanitizedParams->getInt('actionBarDisplayDuration'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotSize')) {
                    $displayProfile->setSetting('screenShotSize', $sanitizedParams->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('mediaInventoryTimer')) {
                    $displayProfile->setSetting('mediaInventoryTimer', $sanitizedParams->getInt('mediaInventoryTimer'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('forceHttps')) {
                    $displayProfile->setSetting('forceHttps', $sanitizedParams->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('timers')) {
                    // Options object to be converted to a JSON string
                    $timerOptions = (object)[];

                    $timers = $sanitizedParams->getArray('timers');

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

                if ($sanitizedParams->hasParam('pictureControls')) {
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
                    $pictureControls = $sanitizedParams->getArray('pictureControls');

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
                    $displayProfile->setSetting('pictureOptions', json_encode($pictureControlsOptions), $ownConfig, $config);
                }

                // Get values from lockOptions params
                $usblock = $sanitizedParams->getString('usblock', ['default' => '']);
                $osdlock = $sanitizedParams->getString('osdlock', ['default' => '']);
                $keylockLocal = $sanitizedParams->getString('keylockLocal', ['default' => '']);
                $keylockRemote = $sanitizedParams->getString('keylockRemote', ['default' => '']);

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