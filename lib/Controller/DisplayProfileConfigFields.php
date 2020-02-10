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
    public function editConfigFields($displayProfile, $config = null, Request $request)
    {

        $sanitizedParams = $this->getSanitizer($request->getParams());

        // Setting on our own config or not?
        $ownConfig = ($config === null);

        switch ($displayProfile->getClientType()) {

            case 'android':
                if (!empty($request->getParam('emailAddress'))) {
                    $displayProfile->setSetting('emailAddress', $sanitizedParams->getString('emailAddress'), $ownConfig, $config);
                }

                if (!empty($request->getParam('settingsPassword'))) {
                    $displayProfile->setSetting('settingsPassword', $sanitizedParams->getString('settingsPassword'), $ownConfig, $config);
                }

                if (!empty($sanitizedParams->getInt('collectInterval'))) {
                    $displayProfile->setSetting('collectInterval', $sanitizedParams->getInt('collectInterval'), $ownConfig, $config);
                }

                if (!empty($request->getParam('downloadStartWindow'))) {
                    $displayProfile->setSetting('downloadStartWindow', $sanitizedParams->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if (!empty($request->getParam('downloadEndWindow'))) {
                    $displayProfile->setSetting('downloadEndWindow', $sanitizedParams->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if (!empty($request->getParam('xmrNetworkAddress'))) {
                    $displayProfile->setSetting('xmrNetworkAddress', $sanitizedParams->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if (!empty($request->getParam('statsEnabled'))) {
                    $displayProfile->setSetting('statsEnabled', $sanitizedParams->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if (!empty($request->getParam('aggregationLevel'))) {
                    $displayProfile->setSetting('aggregationLevel', $sanitizedParams->getString('aggregationLevel'), $ownConfig, $config);
                }

                if (!empty($request->getParam('orientation'))) {
                    $displayProfile->setSetting('orientation', $sanitizedParams->getInt('orientation'), $ownConfig, $config);
                }

                if (!empty($request->getParam('screenDimensions'))) {
                    $displayProfile->setSetting('screenDimensions', $sanitizedParams->getString('screenDimensions'), $ownConfig, $config);
                }

                if (!empty($request->getParam('blacklistVideo'))) {
                    $displayProfile->setSetting('blacklistVideo', $sanitizedParams->getCheckbox('blacklistVideo'), $ownConfig, $config);
                }

                if (!empty($request->getParam('storeHtmlOnInternal'))) {
                    $displayProfile->setSetting('storeHtmlOnInternal', $sanitizedParams->getCheckbox('storeHtmlOnInternal'), $ownConfig, $config);
                }

                if (!empty($request->getParam('useSurfaceVideoView'))) {
                    $displayProfile->setSetting('useSurfaceVideoView', $sanitizedParams->getCheckbox('useSurfaceVideoView'), $ownConfig, $config);
                }

                if (!empty($request->getParam('logLevel'))) {
                    $displayProfile->setSetting('logLevel', $sanitizedParams->getString('logLevel'), $ownConfig, $config);
                }

                if (!empty($request->getParam('versionMediaId'))) {
                    $displayProfile->setSetting('versionMediaId', $sanitizedParams->getInt('versionMediaId'), $ownConfig, $config);
                }

                if (!empty($request->getParam('startOnBoot'))) {
                    $displayProfile->setSetting('startOnBoot', $sanitizedParams->getCheckbox('startOnBoot'), $ownConfig, $config);
                }

                if (!empty($request->getParam('actionBarMode'))) {
                    $displayProfile->setSetting('actionBarMode', $sanitizedParams->getInt('actionBarMode'), $ownConfig, $config);
                }

                if (!empty($request->getParam('actionBarDisplayDuration'))) {
                    $displayProfile->setSetting('actionBarDisplayDuration', $sanitizedParams->getInt('actionBarDisplayDuration'), $ownConfig, $config);
                }

                if (!empty($request->getParam('actionBarIntent'))) {
                    $displayProfile->setSetting('actionBarIntent', $sanitizedParams->getString('actionBarIntent'), $ownConfig, $config);
                }

                if (!empty($request->getParam('autoRestart'))) {
                    $displayProfile->setSetting('autoRestart', $sanitizedParams->getCheckbox('autoRestart'), $ownConfig, $config);
                }

                if (!empty($request->getParam('startOnBootDelay'))) {
                    $displayProfile->setSetting('startOnBootDelay', $sanitizedParams->getInt('startOnBootDelay'), $ownConfig, $config);
                }

                if (!empty($request->getParam('sendCurrentLayoutAsStatusUpdate'))) {
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if (!empty($request->getParam('screenShotRequestInterval'))) {
                    $displayProfile->setSetting('screenShotRequestInterval', $sanitizedParams->getInt('screenShotRequestInterval'), $ownConfig, $config);
                }

                if (!empty($request->getParam('expireModifiedLayouts'))) {
                    $displayProfile->setSetting('expireModifiedLayouts', $sanitizedParams->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                }

                if (!empty($request->getParam('screenShotIntent'))) {
                    $displayProfile->setSetting('screenShotIntent', $sanitizedParams->getString('screenShotIntent'), $ownConfig, $config);
                }

                if (!empty($request->getParam('screenShotSize'))) {
                    $displayProfile->setSetting('screenShotSize', $sanitizedParams->getInt('screenShotSize'), $ownConfig, $config);
                }

                if (!empty($request->getParam('updateStartWindow'))) {
                    $displayProfile->setSetting('updateStartWindow', $sanitizedParams->getString('updateStartWindow'), $ownConfig, $config);
                }

                if (!empty($request->getParam('updateEndWindow'))) {
                    $displayProfile->setSetting('updateEndWindow', $sanitizedParams->getString('updateEndWindow'), $ownConfig, $config);
                }

                if (!empty($request->getParam('dayPartId'))) {
                    $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
                }

                if (!empty($request->getParam('webViewPluginState'))) {
                    $displayProfile->setSetting('webViewPluginState', $sanitizedParams->getString('webViewPluginState'), $ownConfig, $config);
                }

                if (!empty($request->getParam('hardwareAccelerateWebViewMode'))) {
                    $displayProfile->setSetting('hardwareAccelerateWebViewMode', $sanitizedParams->getString('hardwareAccelerateWebViewMode'), $ownConfig, $config);
                }

                if (!empty($request->getParam('timeSyncFromCms'))) {
                    $displayProfile->setSetting('timeSyncFromCms', $sanitizedParams->getCheckbox('timeSyncFromCms'), $ownConfig, $config);
                }

                if (!empty($request->getParam('webCacheEnabled'))) {
                    $displayProfile->setSetting('webCacheEnabled', $sanitizedParams->getCheckbox('webCacheEnabled'), $ownConfig, $config);
                }

                if (!empty($request->getParam('serverPort'))) {
                    $displayProfile->setSetting('serverPort', $sanitizedParams->getInt('serverPort'), $ownConfig, $config);
                }

                if (!empty($request->getParam('installWithLoadedLinkLibraries'))) {
                    $displayProfile->setSetting('installWithLoadedLinkLibraries', $sanitizedParams->getCheckbox('installWithLoadedLinkLibraries'), $ownConfig, $config);
                }

                if (!empty($request->getParam('forceHttps'))) {
                    $displayProfile->setSetting('forceHttps', $sanitizedParams->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                break;

            case 'windows':
                if (!empty($request->getParam('collectInterval'))) {
                    $displayProfile->setSetting('collectInterval', $sanitizedParams->getInt('collectInterval'), $ownConfig, $config);
                }

                if (!empty($request->getParam('downloadStartWindow'))) {
                    $displayProfile->setSetting('downloadStartWindow', $sanitizedParams->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if (!empty($request->getParam('downloadEndWindow')))  {
                    $displayProfile->setSetting('downloadEndWindow', $sanitizedParams->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if (!empty($request->getParam('xmrNetworkAddress')))  {
                    $displayProfile->setSetting('xmrNetworkAddress', $sanitizedParams->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if (!empty($request->getParam('dayPartId')))  {
                    $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
                }

                if (!empty($request->getParam('statsEnabled')))  {
                    $displayProfile->setSetting('statsEnabled', $sanitizedParams->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if (!empty($request->getParam('aggregationLevel')))  {
                    $displayProfile->setSetting('aggregationLevel', $sanitizedParams->getString('aggregationLevel'), $ownConfig, $config);
                }

                if (!empty($request->getParam('powerpointEnabled')))  {
                    $displayProfile->setSetting('powerpointEnabled', $sanitizedParams->getCheckbox('powerpointEnabled'), $ownConfig, $config);
                }

                if (!empty($request->getParam('sizeX')))  {
                    $displayProfile->setSetting('sizeX', $sanitizedParams->getDouble('sizeX'), $ownConfig, $config);
                }

                if (!empty($request->getParam('sizeY'))) {
                    $displayProfile->setSetting('sizeY', $sanitizedParams->getDouble('sizeY'), $ownConfig, $config);
                }

                if (!empty($request->getParam('offsetX')))  {
                    $displayProfile->setSetting('offsetX', $sanitizedParams->getDouble('offsetX'), $ownConfig, $config);
                }

                if (!empty($request->getParam('offsetY')))  {
                    $displayProfile->setSetting('offsetY', $sanitizedParams->getDouble('offsetY'), $ownConfig, $config);
                }

                if (!empty($request->getParam('clientInfomationCtrlKey')))  {
                    $displayProfile->setSetting('clientInfomationCtrlKey', $sanitizedParams->getCheckbox('clientInfomationCtrlKey'), $ownConfig, $config);
                }

                if (!empty($request->getParam('clientInformationKeyCode')))  {
                    $displayProfile->setSetting('clientInformationKeyCode', $sanitizedParams->getString('clientInformationKeyCode'), $ownConfig, $config);
                }

                if (!empty($request->getParam('logLevel')))  {
                    $displayProfile->setSetting('logLevel', $sanitizedParams->getString('logLevel'), $ownConfig, $config);
                }

                if (!empty($request->getParam('logToDiskLocation')))  {
                    $displayProfile->setSetting('logToDiskLocation', $sanitizedParams->getString('logToDiskLocation'), $ownConfig, $config);
                }

                if (!empty($request->getParam('showInTaskbar')))  {
                    $displayProfile->setSetting('showInTaskbar', $sanitizedParams->getCheckbox('showInTaskbar'), $ownConfig, $config);
                }

                if (!empty($request->getParam('cursorStartPosition')))  {
                    $displayProfile->setSetting('cursorStartPosition', $sanitizedParams->getString('cursorStartPosition'), $ownConfig, $config);
                }

                if (!empty($request->getParam('doubleBuffering')))  {
                    $displayProfile->setSetting('doubleBuffering', $sanitizedParams->getCheckbox('doubleBuffering'), $ownConfig, $config);
                }

                if (!empty($request->getParam('emptyLayoutDuration')))  {
                    $displayProfile->setSetting('emptyLayoutDuration', $sanitizedParams->getInt('emptyLayoutDuration'), $ownConfig, $config);
                }

                if (!empty($request->getParam('enableMouse')))  {
                    $displayProfile->setSetting('enableMouse', $sanitizedParams->getCheckbox('enableMouse'), $ownConfig, $config);
                }

                if (!empty($request->getParam('enableShellCommands')))  {
                    $displayProfile->setSetting('enableShellCommands', $sanitizedParams->getCheckbox('enableShellCommands'), $ownConfig, $config);
                }

                if (!empty($request->getParam('expireModifiedLayouts')))  {
                    $displayProfile->setSetting('expireModifiedLayouts', $sanitizedParams->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                }

                if (!empty($request->getParam('maxConcurrentDownloads')))  {
                    $displayProfile->setSetting('maxConcurrentDownloads', $sanitizedParams->getInt('maxConcurrentDownloads'), $ownConfig, $config);
                }

                if (!empty($request->getParam('shellCommandAllowList')))  {
                    $displayProfile->setSetting('shellCommandAllowList', $sanitizedParams->getString('shellCommandAllowList'), $ownConfig, $config);
                }

                if (!empty($request->getParam('sendCurrentLayoutAsStatusUpdate')))  {
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if (!empty($request->getParam('screenShotRequestInterval')))  {
                    $displayProfile->setSetting('screenShotRequestInterval', $sanitizedParams->getInt('screenShotRequestInterval'), $ownConfig, $config);
                }

                if (!empty($request->getParam('screenShotSize')))  {
                    $displayProfile->setSetting('screenShotSize', $sanitizedParams->getInt('screenShotSize'), $ownConfig, $config);
                }

                if (!empty($request->getParam('maxLogFileUploads')))  {
                    $displayProfile->setSetting('maxLogFileUploads', $sanitizedParams->getInt('maxLogFileUploads'), $ownConfig, $config);
                }

                if (!empty($request->getParam('embeddedServerPort')))  {
                    $displayProfile->setSetting('embeddedServerPort', $sanitizedParams->getInt('embeddedServerPort'), $ownConfig, $config);
                }

                if (!empty($request->getParam('preventSleep')))  {
                    $displayProfile->setSetting('preventSleep', $sanitizedParams->getCheckbox('preventSleep'), $ownConfig, $config);
                }

                if (!empty($request->getParam('forceHttps')))  {
                    $displayProfile->setSetting('forceHttps', $sanitizedParams->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                break;

            case 'linux':
                if (!empty($request->getParam('collectInterval'))) {
                    $displayProfile->setSetting('collectInterval', $sanitizedParams->getInt('collectInterval'), $ownConfig, $config);
                }

                if (!empty($request->getParam('downloadStartWindow'))) {
                    $displayProfile->setSetting('downloadStartWindow', $sanitizedParams->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if (!empty($request->getParam('downloadEndWindow'))) {
                    $displayProfile->setSetting('downloadEndWindow', $sanitizedParams->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if (!empty($request->getParam('dayPartId'))) {
                    $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
                }

                if (!empty($request->getParam('xmrNetworkAddress'))) {
                    $displayProfile->setSetting('xmrNetworkAddress', $sanitizedParams->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if (!empty($request->getParam('statsEnabled'))) {
                    $displayProfile->setSetting('statsEnabled', $sanitizedParams->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if (!empty($request->getParam('aggregationLevel'))) {
                    $displayProfile->setSetting('aggregationLevel', $sanitizedParams->getString('aggregationLevel'), $ownConfig, $config);
                }

                if (!empty($request->getParam('sizeX'))) {
                    $displayProfile->setSetting('sizeX', $sanitizedParams->getDouble('sizeX'), $ownConfig, $config);
                }

                if (!empty($request->getParam('sizeY'))) {
                    $displayProfile->setSetting('sizeY', $sanitizedParams->getDouble('sizeY'), $ownConfig, $config);
                }

                if (!empty($request->getParam('offsetX'))) {
                    $displayProfile->setSetting('offsetX', $sanitizedParams->getDouble('offsetX'), $ownConfig, $config);
                }

                if (!empty($request->getParam('offsetY'))) {
                    $displayProfile->setSetting('offsetY', $sanitizedParams->getDouble('offsetY'), $ownConfig, $config);
                }

                if (!empty($request->getParam('logLevel'))) {
                    $displayProfile->setSetting('logLevel', $sanitizedParams->getString('logLevel'), $ownConfig, $config);
                }

                if (!empty($request->getParam('enableShellCommands'))) {
                    $displayProfile->setSetting('enableShellCommands', $sanitizedParams->getCheckbox('enableShellCommands'), $ownConfig, $config);
                }

                if (!empty($request->getParam('expireModifiedLayouts'))) {
                    $displayProfile->setSetting('expireModifiedLayouts', $sanitizedParams->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                }

                if (!empty($request->getParam('maxConcurrentDownloads'))) {
                    $displayProfile->setSetting('maxConcurrentDownloads', $sanitizedParams->getInt('maxConcurrentDownloads'), $ownConfig, $config);
                }

                if (!empty($request->getParam('shellCommandAllowList'))) {
                    $displayProfile->setSetting('shellCommandAllowList', $sanitizedParams->getString('shellCommandAllowList'), $ownConfig, $config);
                }

                if (!empty($request->getParam('sendCurrentLayoutAsStatusUpdate'))) {
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if (!empty($request->getParam('screenShotRequestInterval'))) {
                    $displayProfile->setSetting('screenShotRequestInterval', $sanitizedParams->getInt('screenShotRequestInterval'), $ownConfig, $config);
                }

                if (!empty($request->getParam('screenShotSize'))) {
                    $displayProfile->setSetting('screenShotSize', $sanitizedParams->getInt('screenShotSize'), $ownConfig, $config);
                }

                if (!empty($request->getParam('maxLogFileUploads'))) {
                    $displayProfile->setSetting('maxLogFileUploads', $sanitizedParams->getInt('maxLogFileUploads'), $ownConfig, $config);
                }

                if (!empty($request->getParam('embeddedServerPort'))) {
                    $displayProfile->setSetting('embeddedServerPort', $sanitizedParams->getInt('embeddedServerPort'), $ownConfig, $config);
                }

                if (!empty($request->getParam('preventSleep'))) {
                    $displayProfile->setSetting('preventSleep', $sanitizedParams->getCheckbox('preventSleep'), $ownConfig, $config);
                }

                if (!empty($request->getParam('forceHttps'))) {
                    $displayProfile->setSetting('forceHttps', $sanitizedParams->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                break;

            case 'lg':
            case 'sssp':

                if (!empty($request->getParam('emailAddress'))) {
                    $displayProfile->setSetting('emailAddress', $sanitizedParams->getString('emailAddress'), $ownConfig, $config);
                }

                if (!empty($request->getParam('collectInterval'))) {
                    $displayProfile->setSetting('collectInterval', $sanitizedParams->getInt('collectInterval'), $ownConfig, $config);
                }

                if (!empty($request->getParam('downloadStartWindow'))) {
                    $displayProfile->setSetting('downloadStartWindow', $sanitizedParams->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if (!empty($request->getParam('downloadEndWindow'))) {
                    $displayProfile->setSetting('downloadEndWindow', $sanitizedParams->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if (!empty($request->getParam('dayPartId'))) {
                    $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
                }

                if (!empty($request->getParam('xmrNetworkAddress'))) {
                    $displayProfile->setSetting('xmrNetworkAddress', $sanitizedParams->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if (!empty($request->getParam('statsEnabled'))) {
                    $displayProfile->setSetting('statsEnabled', $sanitizedParams->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if (!empty($request->getParam('aggregationLevel'))) {
                    $displayProfile->setSetting('aggregationLevel', $sanitizedParams->getString('aggregationLevel'), $ownConfig, $config);
                }

                if (!empty($request->getParam('orientation'))) {
                    $displayProfile->setSetting('orientation', $sanitizedParams->getInt('orientation'), $ownConfig, $config);
                }

                if (!empty($request->getParam('logLevel'))) {
                    $displayProfile->setSetting('logLevel', $sanitizedParams->getString('logLevel'), $ownConfig, $config);
                }

                if (!empty($request->getParam('versionMediaId'))) {
                    $displayProfile->setSetting('versionMediaId', $sanitizedParams->getInt('versionMediaId'), $ownConfig, $config);
                }

                if (!empty($request->getParam('actionBarMode'))) {
                    $displayProfile->setSetting('actionBarMode', $sanitizedParams->getInt('actionBarMode'), $ownConfig, $config);
                }

                if (!empty($request->getParam('actionBarDisplayDuration'))) {
                    $displayProfile->setSetting('actionBarDisplayDuration', $sanitizedParams->getInt('actionBarDisplayDuration'), $ownConfig, $config);
                }

                if (!empty($request->getParam('sendCurrentLayoutAsStatusUpdate'))){
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if (!empty($request->getParam('screenShotSize'))) {
                    $displayProfile->setSetting('screenShotSize', $sanitizedParams->getInt('screenShotSize'), $ownConfig, $config);
                }

                if (!empty($request->getParam('mediaInventoryTimer'))) {
                    $displayProfile->setSetting('mediaInventoryTimer', $sanitizedParams->getInt('mediaInventoryTimer'), $ownConfig, $config);
                }

                if (!empty($request->getParam('forceHttps'))) {
                    $displayProfile->setSetting('forceHttps', $sanitizedParams->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                if (!empty($request->getParam('timers'))) {
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

                if (!empty($request->getParam('pictureControls'))) {
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
                    $displayProfile->setSetting('pictureOptions', json_encode($pictureControlsOptions), $ownConfig,
                        $config);
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