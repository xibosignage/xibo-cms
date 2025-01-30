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
namespace Xibo\Controller;

use Xibo\Support\Exception\InvalidArgumentException;
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
     * @param SanitizerInterface $sanitizedParams
     * @param null|array $config if empty will edit the config of provided display profile
     * @param \Xibo\Entity\Display $display
     * @return null|array
     * @throws InvalidArgumentException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function editConfigFields($displayProfile, $sanitizedParams, $config = null, $display = null)
    {
        // Setting on our own config or not?
        $ownConfig = ($config === null);

        $changedSettings = [];

        switch ($displayProfile->getClientType()) {

            case 'android':
                if ($sanitizedParams->hasParam('emailAddress')) {
                    $this->handleChangedSettings('emailAddress', ($ownConfig) ? $displayProfile->getSetting('emailAddress') : $display->getSetting('emailAddress'), $sanitizedParams->getString('emailAddress'), $changedSettings);
                    $displayProfile->setSetting('emailAddress', $sanitizedParams->getString('emailAddress'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('settingsPassword')) {
                    $this->handleChangedSettings('settingsPassword', ($ownConfig) ? $displayProfile->getSetting('settingsPassword') : $display->getSetting('settingsPassword'), $sanitizedParams->getString('settingsPassword'), $changedSettings);
                    $displayProfile->setSetting('settingsPassword', $sanitizedParams->getString('settingsPassword'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('collectInterval')) {
                    $this->handleChangedSettings('collectInterval', ($ownConfig) ? $displayProfile->getSetting('collectInterval') : $display->getSetting('collectInterval'), $sanitizedParams->getInt('collectInterval'), $changedSettings);
                    $displayProfile->setSetting('collectInterval', $sanitizedParams->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadStartWindow')) {
                    $this->handleChangedSettings('downloadStartWindow', ($ownConfig) ? $displayProfile->getSetting('downloadStartWindow') : $display->getSetting('downloadStartWindow'), $sanitizedParams->getString('downloadStartWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadStartWindow', $sanitizedParams->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadEndWindow')) {
                    $this->handleChangedSettings('downloadEndWindow', ($ownConfig) ? $displayProfile->getSetting('downloadEndWindow') : $display->getSetting('downloadEndWindow'), $sanitizedParams->getString('downloadEndWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadEndWindow', $sanitizedParams->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('xmrNetworkAddress')) {
                    $this->handleChangedSettings('xmrNetworkAddress', ($ownConfig) ? $displayProfile->getSetting('xmrNetworkAddress') : $display->getSetting('xmrNetworkAddress'), $sanitizedParams->getString('xmrNetworkAddress'), $changedSettings);
                    $displayProfile->setSetting('xmrNetworkAddress', $sanitizedParams->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('xmrWebSocketAddress')) {
                    $this->handleChangedSettings(
                        'xmrWebSocketAddress',
                        ($ownConfig)
                            ? $displayProfile->getSetting('xmrWebSocketAddress')
                            : $display->getSetting('xmrWebSocketAddress'),
                        $sanitizedParams->getString('xmrWebSocketAddress'),
                        $changedSettings
                    );
                    $displayProfile->setSetting(
                        'xmrWebSocketAddress',
                        $sanitizedParams->getString('xmrWebSocketAddress'),
                        $ownConfig,
                        $config
                    );
                }

                if ($sanitizedParams->hasParam('statsEnabled')) {
                    $this->handleChangedSettings('statsEnabled', ($ownConfig) ? $displayProfile->getSetting('statsEnabled') : $display->getSetting('statsEnabled'), $sanitizedParams->getCheckbox('statsEnabled'), $changedSettings);
                    $displayProfile->setSetting('statsEnabled', $sanitizedParams->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('aggregationLevel')) {
                    $this->handleChangedSettings('aggregationLevel', ($ownConfig) ? $displayProfile->getSetting('aggregationLevel') : $display->getSetting('aggregationLevel'), $sanitizedParams->getString('aggregationLevel'), $changedSettings);
                    $displayProfile->setSetting('aggregationLevel', $sanitizedParams->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('orientation')) {
                    $this->handleChangedSettings('orientation', ($ownConfig) ? $displayProfile->getSetting('orientation') : $display->getSetting('orientation'), $sanitizedParams->getInt('orientation'), $changedSettings);
                    $displayProfile->setSetting('orientation', $sanitizedParams->getInt('orientation'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenDimensions')) {
                    $this->handleChangedSettings('screenDimensions', ($ownConfig) ? $displayProfile->getSetting('screenDimensions') : $display->getSetting('screenDimensions'), $sanitizedParams->getString('screenDimensions'), $changedSettings);
                    $displayProfile->setSetting('screenDimensions', $sanitizedParams->getString('screenDimensions'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('blacklistVideo')) {
                    $this->handleChangedSettings('blacklistVideo', ($ownConfig) ? $displayProfile->getSetting('blacklistVideo') : $display->getSetting('blacklistVideo'), $sanitizedParams->getCheckbox('blacklistVideo'), $changedSettings);
                    $displayProfile->setSetting('blacklistVideo', $sanitizedParams->getCheckbox('blacklistVideo'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('storeHtmlOnInternal')) {
                    $this->handleChangedSettings('storeHtmlOnInternal', ($ownConfig) ? $displayProfile->getSetting('storeHtmlOnInternal') : $display->getSetting('storeHtmlOnInternal'), $sanitizedParams->getCheckbox('storeHtmlOnInternal'), $changedSettings);
                    $displayProfile->setSetting('storeHtmlOnInternal', $sanitizedParams->getCheckbox('storeHtmlOnInternal'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('useSurfaceVideoView')) {
                    $this->handleChangedSettings('useSurfaceVideoView', ($ownConfig) ? $displayProfile->getSetting('useSurfaceVideoView') : $display->getSetting('useSurfaceVideoView'), $sanitizedParams->getCheckbox('useSurfaceVideoView'), $changedSettings);
                    $displayProfile->setSetting('useSurfaceVideoView', $sanitizedParams->getCheckbox('useSurfaceVideoView'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('logLevel')) {
                    $this->handleChangedSettings('logLevel', ($ownConfig) ? $displayProfile->getSetting('logLevel') : $display->getSetting('logLevel'), $sanitizedParams->getString('logLevel'), $changedSettings);
                    $displayProfile->setSetting('logLevel', $sanitizedParams->getString('logLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('elevateLogsUntil')) {
                    $this->handleChangedSettings(
                        'elevateLogsUntil',
                        ($ownConfig)
                            ? $displayProfile->getSetting('elevateLogsUntil')
                            : $display->getSetting('elevateLogsUntil'),
                        $sanitizedParams->getDate('elevateLogsUntil')?->format('U'),
                        $changedSettings
                    );
                    $displayProfile->setSetting(
                        'elevateLogsUntil',
                        $sanitizedParams->getDate('elevateLogsUntil')?->format('U'),
                        $ownConfig,
                        $config
                    );
                }

                if ($sanitizedParams->hasParam('versionMediaId')) {
                    $this->handleChangedSettings('versionMediaId', ($ownConfig) ? $displayProfile->getSetting('versionMediaId') : $display->getSetting('versionMediaId'), $sanitizedParams->getInt('versionMediaId'), $changedSettings);
                    $displayProfile->setSetting('versionMediaId', $sanitizedParams->getInt('versionMediaId'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('startOnBoot')) {
                    $this->handleChangedSettings('startOnBoot', ($ownConfig) ? $displayProfile->getSetting('startOnBoot') : $display->getSetting('startOnBoot'), $sanitizedParams->getCheckbox('startOnBoot'), $changedSettings);
                    $displayProfile->setSetting('startOnBoot', $sanitizedParams->getCheckbox('startOnBoot'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('actionBarMode')) {
                    $this->handleChangedSettings('actionBarMode', ($ownConfig) ? $displayProfile->getSetting('actionBarMode') : $display->getSetting('actionBarMode'), $sanitizedParams->getInt('actionBarMode'), $changedSettings);
                    $displayProfile->setSetting('actionBarMode', $sanitizedParams->getInt('actionBarMode'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('actionBarDisplayDuration')) {
                    $this->handleChangedSettings('actionBarDisplayDuration', ($ownConfig) ? $displayProfile->getSetting('actionBarDisplayDuration') : $display->getSetting('actionBarDisplayDuration'), $sanitizedParams->getInt('actionBarDisplayDuration'), $changedSettings);
                    $displayProfile->setSetting('actionBarDisplayDuration', $sanitizedParams->getInt('actionBarDisplayDuration'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('actionBarIntent')) {
                    $this->handleChangedSettings('actionBarIntent', ($ownConfig) ? $displayProfile->getSetting('actionBarIntent') : $display->getSetting('actionBarIntent'), $sanitizedParams->getString('actionBarIntent'), $changedSettings);
                    $displayProfile->setSetting('actionBarIntent', $sanitizedParams->getString('actionBarIntent'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('autoRestart')) {
                    $this->handleChangedSettings('autoRestart', ($ownConfig) ? $displayProfile->getSetting('autoRestart') : $display->getSetting('autoRestart'), $sanitizedParams->getCheckbox('autoRestart'), $changedSettings);
                    $displayProfile->setSetting('autoRestart', $sanitizedParams->getCheckbox('autoRestart'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('startOnBootDelay')) {
                    $this->handleChangedSettings('startOnBootDelay', ($ownConfig) ? $displayProfile->getSetting('startOnBootDelay') : $display->getSetting('startOnBootDelay'), $sanitizedParams->getInt('startOnBootDelay'), $changedSettings);
                    $displayProfile->setSetting('startOnBootDelay', $sanitizedParams->getInt('startOnBootDelay'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $this->handleChangedSettings('sendCurrentLayoutAsStatusUpdate', ($ownConfig) ? $displayProfile->getSetting('sendCurrentLayoutAsStatusUpdate') : $display->getSetting('sendCurrentLayoutAsStatusUpdate'), $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $changedSettings);
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotRequestInterval')) {
                    $this->handleChangedSettings('screenShotRequestInterval', ($ownConfig) ? $displayProfile->getSetting('screenShotRequestInterval') : $display->getSetting('screenShotRequestInterval'), $sanitizedParams->getInt('screenShotRequestInterval'), $changedSettings);
                    $displayProfile->setSetting('screenShotRequestInterval', $sanitizedParams->getInt('screenShotRequestInterval'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('expireModifiedLayouts')) {
                    $this->handleChangedSettings('expireModifiedLayouts', ($ownConfig) ? $displayProfile->getSetting('expireModifiedLayouts') : $display->getSetting('expireModifiedLayouts'), $sanitizedParams->getCheckbox('expireModifiedLayouts'), $changedSettings);
                    $displayProfile->setSetting('expireModifiedLayouts', $sanitizedParams->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotIntent')) {
                    $this->handleChangedSettings('screenShotIntent', ($ownConfig) ? $displayProfile->getSetting('screenShotIntent') : $display->getSetting('screenShotIntent'), $sanitizedParams->getString('screenShotIntent'), $changedSettings);
                    $displayProfile->setSetting('screenShotIntent', $sanitizedParams->getString('screenShotIntent'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotSize')) {
                    $this->handleChangedSettings('screenShotSize', ($ownConfig) ? $displayProfile->getSetting('screenShotSize') : $display->getSetting('screenShotSize'), $sanitizedParams->getInt('screenShotSize'), $changedSettings);
                    $displayProfile->setSetting('screenShotSize', $sanitizedParams->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('updateStartWindow')) {
                    $this->handleChangedSettings('updateStartWindow', ($ownConfig) ? $displayProfile->getSetting('updateStartWindow') : $display->getSetting('updateStartWindow'), $sanitizedParams->getString('updateStartWindow'), $changedSettings);
                    $displayProfile->setSetting('updateStartWindow', $sanitizedParams->getString('updateStartWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('updateEndWindow')) {
                    $this->handleChangedSettings('updateEndWindow', ($ownConfig) ? $displayProfile->getSetting('updateEndWindow') : $display->getSetting('updateEndWindow'), $sanitizedParams->getString('updateEndWindow'), $changedSettings);
                    $displayProfile->setSetting('updateEndWindow', $sanitizedParams->getString('updateEndWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('dayPartId')) {
                    $this->handleChangedSettings('dayPartId', ($ownConfig) ? $displayProfile->getSetting('dayPartId') : $display->getSetting('dayPartId'), $sanitizedParams->getInt('dayPartId'), $changedSettings);
                    $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('restartWifiOnConnectionFailure')) {
                    $this->handleChangedSettings(
                        'restartWifiOnConnectionFailure',
                        ($ownConfig)
                            ? $displayProfile->getSetting('restartWifiOnConnectionFailure')
                            : $display->getSetting('restartWifiOnConnectionFailure'),
                        $sanitizedParams->getCheckbox('restartWifiOnConnectionFailure'),
                        $changedSettings
                    );

                    $displayProfile->setSetting(
                        'restartWifiOnConnectionFailure',
                        $sanitizedParams->getCheckbox('restartWifiOnConnectionFailure'),
                        $ownConfig,
                        $config
                    );
                }

                if ($sanitizedParams->hasParam('webViewPluginState')) {
                    $this->handleChangedSettings('webViewPluginState', ($ownConfig) ? $displayProfile->getSetting('webViewPluginState') : $display->getSetting('webViewPluginState'), $sanitizedParams->getString('webViewPluginState'), $changedSettings);
                    $displayProfile->setSetting('webViewPluginState', $sanitizedParams->getString('webViewPluginState'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('hardwareAccelerateWebViewMode')) {
                    $this->handleChangedSettings('hardwareAccelerateWebViewMode', ($ownConfig) ? $displayProfile->getSetting('hardwareAccelerateWebViewMode') : $display->getSetting('hardwareAccelerateWebViewMode'), $sanitizedParams->getString('hardwareAccelerateWebViewMode'), $changedSettings);
                    $displayProfile->setSetting('hardwareAccelerateWebViewMode', $sanitizedParams->getString('hardwareAccelerateWebViewMode'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('timeSyncFromCms')) {
                    $this->handleChangedSettings('timeSyncFromCms', ($ownConfig) ? $displayProfile->getSetting('timeSyncFromCms') : $display->getSetting('timeSyncFromCms'), $sanitizedParams->getCheckbox('timeSyncFromCms'), $changedSettings);
                    $displayProfile->setSetting('timeSyncFromCms', $sanitizedParams->getCheckbox('timeSyncFromCms'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('webCacheEnabled')) {
                    $this->handleChangedSettings('webCacheEnabled', ($ownConfig) ? $displayProfile->getSetting('webCacheEnabled') : $display->getSetting('webCacheEnabled'), $sanitizedParams->getCheckbox('webCacheEnabled'), $changedSettings);
                    $displayProfile->setSetting('webCacheEnabled', $sanitizedParams->getCheckbox('webCacheEnabled'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('serverPort')) {
                    $this->handleChangedSettings('serverPort', ($ownConfig) ? $displayProfile->getSetting('serverPort') : $display->getSetting('serverPort'), $sanitizedParams->getInt('serverPort'), $changedSettings);
                    $displayProfile->setSetting('serverPort', $sanitizedParams->getInt('serverPort'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('installWithLoadedLinkLibraries')) {
                    $this->handleChangedSettings('installWithLoadedLinkLibraries', ($ownConfig) ? $displayProfile->getSetting('installWithLoadedLinkLibraries') : $display->getSetting('installWithLoadedLinkLibraries'), $sanitizedParams->getCheckbox('installWithLoadedLinkLibraries'), $changedSettings);
                    $displayProfile->setSetting('installWithLoadedLinkLibraries', $sanitizedParams->getCheckbox('installWithLoadedLinkLibraries'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('forceHttps')) {
                    $this->handleChangedSettings('forceHttps', ($ownConfig) ? $displayProfile->getSetting('forceHttps') : $display->getSetting('forceHttps'), $sanitizedParams->getCheckbox('forceHttps'), $changedSettings);
                    $displayProfile->setSetting('forceHttps', $sanitizedParams->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('isUseMultipleVideoDecoders')) {
                    $this->handleChangedSettings('isUseMultipleVideoDecoders', ($ownConfig) ? $displayProfile->getSetting('isUseMultipleVideoDecoders') : $display->getSetting('isUseMultipleVideoDecoders'), $sanitizedParams->getString('isUseMultipleVideoDecoders'), $changedSettings);
                    $displayProfile->setSetting('isUseMultipleVideoDecoders', $sanitizedParams->getString('isUseMultipleVideoDecoders'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('maxRegionCount')) {
                    $this->handleChangedSettings('maxRegionCount', ($ownConfig) ? $displayProfile->getSetting('maxRegionCount') : $display->getSetting('maxRegionCount'), $sanitizedParams->getInt('maxRegionCount'), $changedSettings);
                    $displayProfile->setSetting('maxRegionCount', $sanitizedParams->getInt('maxRegionCount'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('embeddedServerAllowWan')) {
                    $this->handleChangedSettings('embeddedServerAllowWan', ($ownConfig) ? $displayProfile->getSetting('embeddedServerAllowWan') : $display->getSetting('embeddedServerAllowWan'), $sanitizedParams->getCheckbox('embeddedServerAllowWan'), $changedSettings);
                    $displayProfile->setSetting('embeddedServerAllowWan', $sanitizedParams->getCheckbox('embeddedServerAllowWan'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('isRecordGeoLocationOnProofOfPlay')) {
                    $this->handleChangedSettings('isRecordGeoLocationOnProofOfPlay', ($ownConfig) ? $displayProfile->getSetting('isRecordGeoLocationOnProofOfPlay') : $display->getSetting('isRecordGeoLocationOnProofOfPlay'), $sanitizedParams->getCheckbox('isRecordGeoLocationOnProofOfPlay'), $changedSettings);
                    $displayProfile->setSetting('isRecordGeoLocationOnProofOfPlay', $sanitizedParams->getCheckbox('isRecordGeoLocationOnProofOfPlay'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('videoEngine')) {
                    $this->handleChangedSettings('videoEngine', ($ownConfig) ? $displayProfile->getSetting('videoEngine') : $display->getSetting('videoEngine'), $sanitizedParams->getString('videoEngine'), $changedSettings);
                    $displayProfile->setSetting('videoEngine', $sanitizedParams->getString('videoEngine'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('isTouchEnabled')) {
                    $this->handleChangedSettings('isTouchEnabled', ($ownConfig) ? $displayProfile->getSetting('isTouchEnabled') : $display->getSetting('isTouchEnabled'), $sanitizedParams->getCheckbox('isTouchEnabled'), $changedSettings);
                    $displayProfile->setSetting('isTouchEnabled', $sanitizedParams->getCheckbox('isTouchEnabled'), $ownConfig, $config);
                }

                break;

            case 'windows':
                if ($sanitizedParams->hasParam('collectInterval')) {
                    $this->handleChangedSettings('collectInterval', ($ownConfig) ? $displayProfile->getSetting('collectInterval') : $display->getSetting('collectInterval'), $sanitizedParams->getInt('collectInterval'), $changedSettings);
                    $displayProfile->setSetting('collectInterval', $sanitizedParams->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadStartWindow')) {
                    $this->handleChangedSettings('downloadStartWindow', ($ownConfig) ? $displayProfile->getSetting('downloadStartWindow') : $display->getSetting('downloadStartWindow'), $sanitizedParams->getString('downloadStartWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadStartWindow', $sanitizedParams->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadEndWindow')) {
                    $this->handleChangedSettings('downloadEndWindow', ($ownConfig) ? $displayProfile->getSetting('downloadEndWindow') : $display->getSetting('downloadEndWindow'), $sanitizedParams->getString('downloadEndWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadEndWindow', $sanitizedParams->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('xmrNetworkAddress')) {
                    $this->handleChangedSettings('xmrNetworkAddress', ($ownConfig) ? $displayProfile->getSetting('xmrNetworkAddress') : $display->getSetting('xmrNetworkAddress'), $sanitizedParams->getString('xmrNetworkAddress'), $changedSettings);
                    $displayProfile->setSetting('xmrNetworkAddress', $sanitizedParams->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('dayPartId')) {
                    $this->handleChangedSettings('dayPartId', ($ownConfig) ? $displayProfile->getSetting('dayPartId') : $display->getSetting('dayPartId'), $sanitizedParams->getInt('dayPartId'), $changedSettings);
                    $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('statsEnabled')) {
                    $this->handleChangedSettings('statsEnabled', ($ownConfig) ? $displayProfile->getSetting('statsEnabled') : $display->getSetting('statsEnabled'), $sanitizedParams->getCheckbox('statsEnabled'), $changedSettings);
                    $displayProfile->setSetting('statsEnabled', $sanitizedParams->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('aggregationLevel')) {
                    $this->handleChangedSettings('aggregationLevel', ($ownConfig) ? $displayProfile->getSetting('aggregationLevel') : $display->getSetting('aggregationLevel'), $sanitizedParams->getString('aggregationLevel'), $changedSettings);
                    $displayProfile->setSetting('aggregationLevel', $sanitizedParams->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('powerpointEnabled')) {
                    $this->handleChangedSettings('powerpointEnabled', ($ownConfig) ? $displayProfile->getSetting('powerpointEnabled') : $display->getSetting('powerpointEnabled'), $sanitizedParams->getCheckbox('powerpointEnabled'), $changedSettings);
                    $displayProfile->setSetting('powerpointEnabled', $sanitizedParams->getCheckbox('powerpointEnabled'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sizeX')) {
                    $this->handleChangedSettings('sizeX', ($ownConfig) ? $displayProfile->getSetting('sizeX') : $display->getSetting('sizeX'), $sanitizedParams->getDouble('sizeX'), $changedSettings);
                    $displayProfile->setSetting('sizeX', $sanitizedParams->getDouble('sizeX'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sizeY')) {
                    $this->handleChangedSettings('sizeY', ($ownConfig) ? $displayProfile->getSetting('sizeY') : $display->getSetting('sizeY'), $sanitizedParams->getDouble('sizeY'), $changedSettings);
                    $displayProfile->setSetting('sizeY', $sanitizedParams->getDouble('sizeY'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('offsetX')) {
                    $this->handleChangedSettings('offsetX', ($ownConfig) ? $displayProfile->getSetting('offsetX') : $display->getSetting('offsetX'), $sanitizedParams->getDouble('offsetX'), $changedSettings);
                    $displayProfile->setSetting('offsetX', $sanitizedParams->getDouble('offsetX'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('offsetY')) {
                    $this->handleChangedSettings('offsetY', ($ownConfig) ? $displayProfile->getSetting('offsetY') : $display->getSetting('offsetY'), $sanitizedParams->getDouble('offsetY'), $changedSettings);
                    $displayProfile->setSetting('offsetY', $sanitizedParams->getDouble('offsetY'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('clientInfomationCtrlKey')) {
                    $this->handleChangedSettings('clientInfomationCtrlKey', ($ownConfig) ? $displayProfile->getSetting('clientInfomationCtrlKey') : $display->getSetting('clientInfomationCtrlKey'), $sanitizedParams->getCheckbox('clientInfomationCtrlKey'), $changedSettings);
                    $displayProfile->setSetting('clientInfomationCtrlKey', $sanitizedParams->getCheckbox('clientInfomationCtrlKey'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('clientInformationKeyCode')) {
                    $this->handleChangedSettings('clientInformationKeyCode', ($ownConfig) ? $displayProfile->getSetting('clientInformationKeyCode') : $display->getSetting('clientInformationKeyCode'), $sanitizedParams->getString('clientInformationKeyCode'), $changedSettings);
                    $displayProfile->setSetting('clientInformationKeyCode', $sanitizedParams->getString('clientInformationKeyCode'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('logLevel')) {
                    $this->handleChangedSettings('logLevel', ($ownConfig) ? $displayProfile->getSetting('logLevel') : $display->getSetting('logLevel'), $sanitizedParams->getString('logLevel'), $changedSettings);
                    $displayProfile->setSetting('logLevel', $sanitizedParams->getString('logLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('elevateLogsUntil')) {
                    $this->handleChangedSettings(
                        'elevateLogsUntil',
                        ($ownConfig)
                            ? $displayProfile->getSetting('elevateLogsUntil')
                            : $display->getSetting('elevateLogsUntil'),
                        $sanitizedParams->getDate('elevateLogsUntil')?->format('U'),
                        $changedSettings
                    );
                    $displayProfile->setSetting(
                        'elevateLogsUntil',
                        $sanitizedParams->getDate('elevateLogsUntil')?->format('U'),
                        $ownConfig,
                        $config
                    );
                }

                if ($sanitizedParams->hasParam('logToDiskLocation')){
                    $this->handleChangedSettings('logToDiskLocation', ($ownConfig) ? $displayProfile->getSetting('logToDiskLocation') : $display->getSetting('logToDiskLocation'), $sanitizedParams->getString('logToDiskLocation'), $changedSettings);
                    $displayProfile->setSetting('logToDiskLocation', $sanitizedParams->getString('logToDiskLocation'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('showInTaskbar')) {
                    $this->handleChangedSettings('showInTaskbar', ($ownConfig) ? $displayProfile->getSetting('showInTaskbar') : $display->getSetting('showInTaskbar'), $sanitizedParams->getCheckbox('showInTaskbar'), $changedSettings);
                    $displayProfile->setSetting('showInTaskbar', $sanitizedParams->getCheckbox('showInTaskbar'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('cursorStartPosition')) {
                    $this->handleChangedSettings('cursorStartPosition', ($ownConfig) ? $displayProfile->getSetting('cursorStartPosition') : $display->getSetting('cursorStartPosition'), $sanitizedParams->getString('cursorStartPosition'), $changedSettings);
                    $displayProfile->setSetting('cursorStartPosition', $sanitizedParams->getString('cursorStartPosition'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('doubleBuffering')) {
                    $this->handleChangedSettings('doubleBuffering', ($ownConfig) ? $displayProfile->getSetting('doubleBuffering') : $display->getSetting('doubleBuffering'), $sanitizedParams->getCheckbox('doubleBuffering'), $changedSettings);
                    $displayProfile->setSetting('doubleBuffering', $sanitizedParams->getCheckbox('doubleBuffering'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('emptyLayoutDuration')) {
                    $this->handleChangedSettings('emptyLayoutDuration', ($ownConfig) ? $displayProfile->getSetting('emptyLayoutDuration') : $display->getSetting('emptyLayoutDuration'), $sanitizedParams->getInt('emptyLayoutDuration'), $changedSettings);
                    $displayProfile->setSetting('emptyLayoutDuration', $sanitizedParams->getInt('emptyLayoutDuration'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('enableMouse')) {
                    $this->handleChangedSettings('enableMouse', ($ownConfig) ? $displayProfile->getSetting('enableMouse') : $display->getSetting('enableMouse'), $sanitizedParams->getCheckbox('enableMouse'), $changedSettings);
                    $displayProfile->setSetting('enableMouse', $sanitizedParams->getCheckbox('enableMouse'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('enableShellCommands')) {
                    $this->handleChangedSettings('enableShellCommands', ($ownConfig) ? $displayProfile->getSetting('enableShellCommands') : $display->getSetting('enableShellCommands'), $sanitizedParams->getCheckbox('enableShellCommands'), $changedSettings);
                    $displayProfile->setSetting('enableShellCommands', $sanitizedParams->getCheckbox('enableShellCommands'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('expireModifiedLayouts')) {
                    $this->handleChangedSettings('expireModifiedLayouts', ($ownConfig) ? $displayProfile->getSetting('expireModifiedLayouts') : $display->getSetting('expireModifiedLayouts'), $sanitizedParams->getCheckbox('expireModifiedLayouts'), $changedSettings);
                    $displayProfile->setSetting('expireModifiedLayouts', $sanitizedParams->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('maxConcurrentDownloads')) {
                    $this->handleChangedSettings('maxConcurrentDownloads', ($ownConfig) ? $displayProfile->getSetting('maxConcurrentDownloads') : $display->getSetting('maxConcurrentDownloads'), $sanitizedParams->getInt('maxConcurrentDownloads'), $changedSettings);
                    $displayProfile->setSetting('maxConcurrentDownloads', $sanitizedParams->getInt('maxConcurrentDownloads'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('shellCommandAllowList')) {
                    $this->handleChangedSettings('shellCommandAllowList', ($ownConfig) ? $displayProfile->getSetting('shellCommandAllowList') : $display->getSetting('shellCommandAllowList'), $sanitizedParams->getString('shellCommandAllowList'), $changedSettings);
                    $displayProfile->setSetting('shellCommandAllowList', $sanitizedParams->getString('shellCommandAllowList'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $this->handleChangedSettings('sendCurrentLayoutAsStatusUpdate', ($ownConfig) ? $displayProfile->getSetting('sendCurrentLayoutAsStatusUpdate') : $display->getSetting('sendCurrentLayoutAsStatusUpdate'), $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $changedSettings);
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotRequestInterval')) {
                    $this->handleChangedSettings('screenShotRequestInterval', ($ownConfig) ? $displayProfile->getSetting('screenShotRequestInterval') : $display->getSetting('screenShotRequestInterval'), $sanitizedParams->getInt('screenShotRequestInterval'), $changedSettings);
                    $displayProfile->setSetting('screenShotRequestInterval', $sanitizedParams->getInt('screenShotRequestInterval'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotSize')) {
                    $this->handleChangedSettings('screenShotSize', ($ownConfig) ? $displayProfile->getSetting('screenShotSize') : $display->getSetting('screenShotSize'), $sanitizedParams->getInt('screenShotSize'), $changedSettings);
                    $displayProfile->setSetting('screenShotSize', $sanitizedParams->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('maxLogFileUploads')) {
                    $this->handleChangedSettings('maxLogFileUploads', ($ownConfig) ? $displayProfile->getSetting('maxLogFileUploads') : $display->getSetting('maxLogFileUploads'), $sanitizedParams->getInt('maxLogFileUploads'), $changedSettings);
                    $displayProfile->setSetting('maxLogFileUploads', $sanitizedParams->getInt('maxLogFileUploads'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('embeddedServerPort')) {
                    $this->handleChangedSettings('embeddedServerPort', ($ownConfig) ? $displayProfile->getSetting('embeddedServerPort') : $display->getSetting('embeddedServerPort'), $sanitizedParams->getInt('embeddedServerPort'), $changedSettings);
                    $displayProfile->setSetting('embeddedServerPort', $sanitizedParams->getInt('embeddedServerPort'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('preventSleep')) {
                    $this->handleChangedSettings('preventSleep', ($ownConfig) ? $displayProfile->getSetting('preventSleep') : $display->getSetting('preventSleep'), $sanitizedParams->getCheckbox('preventSleep'), $changedSettings);
                    $displayProfile->setSetting('preventSleep', $sanitizedParams->getCheckbox('preventSleep'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('forceHttps')) {
                    $this->handleChangedSettings('forceHttps', ($ownConfig) ? $displayProfile->getSetting('forceHttps') : $display->getSetting('forceHttps'), $sanitizedParams->getCheckbox('forceHttps'), $changedSettings);
                    $displayProfile->setSetting('forceHttps', $sanitizedParams->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('authServerWhitelist')) {
                    $this->handleChangedSettings('authServerWhitelist', ($ownConfig) ? $displayProfile->getSetting('authServerWhitelist') : $display->getSetting('authServerWhitelist'), $sanitizedParams->getString('authServerWhitelist'), $changedSettings);
                    $displayProfile->setSetting('authServerWhitelist', $sanitizedParams->getString('authServerWhitelist'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('edgeBrowserWhitelist')) {
                    $this->handleChangedSettings('edgeBrowserWhitelist', ($ownConfig) ? $displayProfile->getSetting('edgeBrowserWhitelist') : $display->getSetting('edgeBrowserWhitelist'), $sanitizedParams->getString('edgeBrowserWhitelist'), $changedSettings);
                    $displayProfile->setSetting('edgeBrowserWhitelist', $sanitizedParams->getString('edgeBrowserWhitelist'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('embeddedServerAllowWan')) {
                    $this->handleChangedSettings('embeddedServerAllowWan', ($ownConfig) ? $displayProfile->getSetting('embeddedServerAllowWan') : $display->getSetting('embeddedServerAllowWan'), $sanitizedParams->getCheckbox('embeddedServerAllowWan'), $changedSettings);
                    $displayProfile->setSetting('embeddedServerAllowWan', $sanitizedParams->getCheckbox('embeddedServerAllowWan'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('isRecordGeoLocationOnProofOfPlay')) {
                    $this->handleChangedSettings('isRecordGeoLocationOnProofOfPlay', ($ownConfig) ? $displayProfile->getSetting('isRecordGeoLocationOnProofOfPlay') : $display->getSetting('isRecordGeoLocationOnProofOfPlay'), $sanitizedParams->getCheckbox('isRecordGeoLocationOnProofOfPlay'), $changedSettings);
                    $displayProfile->setSetting('isRecordGeoLocationOnProofOfPlay', $sanitizedParams->getCheckbox('isRecordGeoLocationOnProofOfPlay'), $ownConfig, $config);
                }

                break;

            case 'linux':
                if ($sanitizedParams->hasParam('collectInterval'))  {
                    $this->handleChangedSettings('collectInterval',($ownConfig) ? $displayProfile->getSetting('collectInterval') : $display->getSetting('collectInterval'), $sanitizedParams->getInt('collectInterval'), $changedSettings);
                    $displayProfile->setSetting('collectInterval', $sanitizedParams->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadStartWindow')) {
                    $this->handleChangedSettings('downloadStartWindow', ($ownConfig) ? $displayProfile->getSetting('downloadStartWindow') : $display->getSetting('downloadStartWindow'), $sanitizedParams->getString('downloadStartWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadStartWindow', $sanitizedParams->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadEndWindow')) {
                    $this->handleChangedSettings('downloadEndWindow', ($ownConfig) ? $displayProfile->getSetting('downloadEndWindow') : $display->getSetting('downloadEndWindow'), $sanitizedParams->getString('downloadEndWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadEndWindow', $sanitizedParams->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('dayPartId')) {
                    $this->handleChangedSettings('dayPartId', ($ownConfig) ? $displayProfile->getSetting('dayPartId') : $display->getSetting('dayPartId'), $sanitizedParams->getInt('dayPartId'), $changedSettings);
                    $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('xmrNetworkAddress')) {
                    $this->handleChangedSettings('xmrNetworkAddress',($ownConfig) ? $displayProfile->getSetting('xmrNetworkAddress') : $display->getSetting('xmrNetworkAddress'), $sanitizedParams->getString('xmrNetworkAddress'), $changedSettings);
                    $displayProfile->setSetting('xmrNetworkAddress', $sanitizedParams->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('statsEnabled')) {
                    $this->handleChangedSettings('statsEnabled', ($ownConfig) ? $displayProfile->getSetting('statsEnabled') : $display->getSetting('statsEnabled'), $sanitizedParams->getCheckbox('statsEnabled'), $changedSettings);
                    $displayProfile->setSetting('statsEnabled', $sanitizedParams->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('aggregationLevel')) {
                    $this->handleChangedSettings('aggregationLevel', ($ownConfig) ? $displayProfile->getSetting('aggregationLevel') : $display->getSetting('aggregationLevel'), $sanitizedParams->getString('aggregationLevel'), $changedSettings);
                    $displayProfile->setSetting('aggregationLevel', $sanitizedParams->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sizeX')) {
                    $this->handleChangedSettings('sizeX', ($ownConfig) ? $displayProfile->getSetting('sizeX') : $display->getSetting('sizeX'), $sanitizedParams->getDouble('sizeX'), $changedSettings);
                    $displayProfile->setSetting('sizeX', $sanitizedParams->getDouble('sizeX'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sizeY')) {
                    $this->handleChangedSettings('sizeY', ($ownConfig) ? $displayProfile->getSetting('sizeY') : $display->getSetting('sizeY'), $sanitizedParams->getDouble('sizeY'), $changedSettings);
                    $displayProfile->setSetting('sizeY', $sanitizedParams->getDouble('sizeY'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('offsetX')) {
                    $this->handleChangedSettings('offsetX', ($ownConfig) ? $displayProfile->getSetting('offsetX') : $display->getSetting('offsetX'), $sanitizedParams->getDouble('offsetX'), $changedSettings);
                    $displayProfile->setSetting('offsetX', $sanitizedParams->getDouble('offsetX'), $ownConfig, $config);
                }

                if($sanitizedParams->hasParam('offsetY')) {
                    $this->handleChangedSettings('offsetY', ($ownConfig) ? $displayProfile->getSetting('offsetY') : $display->getSetting('offsetY'), $sanitizedParams->getDouble('offsetY'), $changedSettings);
                    $displayProfile->setSetting('offsetY', $sanitizedParams->getDouble('offsetY'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('logLevel')) {
                    $this->handleChangedSettings('logLevel', ($ownConfig) ? $displayProfile->getSetting('logLevel') : $display->getSetting('logLevel'), $sanitizedParams->getString('logLevel'), $changedSettings);
                    $displayProfile->setSetting('logLevel', $sanitizedParams->getString('logLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('elevateLogsUntil')) {
                    $this->handleChangedSettings(
                        'elevateLogsUntil',
                        ($ownConfig)
                            ? $displayProfile->getSetting('elevateLogsUntil')
                            : $display->getSetting('elevateLogsUntil'),
                        $sanitizedParams->getDate('elevateLogsUntil')?->format('U'),
                        $changedSettings
                    );
                    $displayProfile->setSetting(
                        'elevateLogsUntil',
                        $sanitizedParams->getDate('elevateLogsUntil')?->format('U'),
                        $ownConfig,
                        $config
                    );
                }

                if ($sanitizedParams->hasParam('enableShellCommands')) {
                    $this->handleChangedSettings('enableShellCommands',($ownConfig) ? $displayProfile->getSetting('enableShellCommands') : $display->getSetting('enableShellCommands'), $sanitizedParams->getCheckbox('enableShellCommands'), $changedSettings);
                    $displayProfile->setSetting('enableShellCommands', $sanitizedParams->getCheckbox('enableShellCommands'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('expireModifiedLayouts')) {
                    $this->handleChangedSettings('expireModifiedLayouts',($ownConfig) ? $displayProfile->getSetting('expireModifiedLayouts') : $display->getSetting('expireModifiedLayouts'), $sanitizedParams->getCheckbox('expireModifiedLayouts'), $changedSettings);
                    $displayProfile->setSetting('expireModifiedLayouts', $sanitizedParams->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('maxConcurrentDownloads')) {
                    $this->handleChangedSettings('maxConcurrentDownloads', ($ownConfig) ? $displayProfile->getSetting('maxConcurrentDownloads') : $display->getSetting('maxConcurrentDownloads'), $sanitizedParams->getInt('maxConcurrentDownloads'), $changedSettings);
                    $displayProfile->setSetting('maxConcurrentDownloads', $sanitizedParams->getInt('maxConcurrentDownloads'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('shellCommandAllowList')) {
                    $this->handleChangedSettings('shellCommandAllowList', ($ownConfig) ? $displayProfile->getSetting('shellCommandAllowList') : $display->getSetting('shellCommandAllowList'), $sanitizedParams->getString('shellCommandAllowList'), $changedSettings);
                    $displayProfile->setSetting('shellCommandAllowList', $sanitizedParams->getString('shellCommandAllowList'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $this->handleChangedSettings('sendCurrentLayoutAsStatusUpdate', ($ownConfig) ? $displayProfile->getSetting('sendCurrentLayoutAsStatusUpdate') : $display->getSetting('sendCurrentLayoutAsStatusUpdate'), $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $changedSettings);
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotRequestInterval')) {
                    $this->handleChangedSettings('screenShotRequestInterval', ($ownConfig) ? $displayProfile->getSetting('screenShotRequestInterval') : $display->getSetting('screenShotRequestInterval'), $sanitizedParams->getInt('screenShotRequestInterval'), $changedSettings);
                    $displayProfile->setSetting('screenShotRequestInterval', $sanitizedParams->getInt('screenShotRequestInterval'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotSize')) {
                    $this->handleChangedSettings('screenShotSize', ($ownConfig) ? $displayProfile->getSetting('screenShotSize') : $display->getSetting('screenShotSize'), $sanitizedParams->getInt('screenShotSize'), $changedSettings);
                    $displayProfile->setSetting('screenShotSize', $sanitizedParams->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('maxLogFileUploads')) {
                    $this->handleChangedSettings('maxLogFileUploads', ($ownConfig) ? $displayProfile->getSetting('maxLogFileUploads') : $display->getSetting('maxLogFileUploads'), $sanitizedParams->getInt('maxLogFileUploads'), $changedSettings);
                    $displayProfile->setSetting('maxLogFileUploads', $sanitizedParams->getInt('maxLogFileUploads'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('embeddedServerPort')) {
                    $this->handleChangedSettings('embeddedServerPort',($ownConfig) ? $displayProfile->getSetting('embeddedServerPort') : $display->getSetting('embeddedServerPort'), $sanitizedParams->getInt('embeddedServerPort'), $changedSettings);
                    $displayProfile->setSetting('embeddedServerPort', $sanitizedParams->getInt('embeddedServerPort'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('preventSleep')) {
                    $this->handleChangedSettings('preventSleep',($ownConfig) ? $displayProfile->getSetting('preventSleep') : $display->getSetting('preventSleep'), $sanitizedParams->getCheckbox('preventSleep'), $changedSettings);
                    $displayProfile->setSetting('preventSleep', $sanitizedParams->getCheckbox('preventSleep'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('forceHttps')) {
                    $this->handleChangedSettings('forceHttps', ($ownConfig) ? $displayProfile->getSetting('forceHttps') : $display->getSetting('forceHttps'), $sanitizedParams->getCheckbox('forceHttps'), $changedSettings);
                    $displayProfile->setSetting('forceHttps', $sanitizedParams->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('embeddedServerAllowWan')) {
                    $this->handleChangedSettings('embeddedServerAllowWan', ($ownConfig) ? $displayProfile->getSetting('embeddedServerAllowWan') : $display->getSetting('embeddedServerAllowWan'), $sanitizedParams->getCheckbox('embeddedServerAllowWan'), $changedSettings);
                    $displayProfile->setSetting('embeddedServerAllowWan', $sanitizedParams->getCheckbox('embeddedServerAllowWan'), $ownConfig, $config);
                }

                break;

            case 'lg':
            case 'sssp':

                if ($sanitizedParams->hasParam('emailAddress')) {
                    $this->handleChangedSettings('emailAddress', ($ownConfig) ? $displayProfile->getSetting('emailAddress') : $display->getSetting('emailAddress'), $sanitizedParams->getString('emailAddress'), $changedSettings);
                    $displayProfile->setSetting('emailAddress', $sanitizedParams->getString('emailAddress'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('collectInterval')) {
                    $this->handleChangedSettings('collectInterval', ($ownConfig) ? $displayProfile->getSetting('collectInterval') : $display->getSetting('collectInterval'), $sanitizedParams->getInt('collectInterval'), $changedSettings);
                    $displayProfile->setSetting('collectInterval', $sanitizedParams->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadStartWindow')) {
                    $this->handleChangedSettings('downloadStartWindow', ($ownConfig) ? $displayProfile->getSetting('downloadStartWindow') : $display->getSetting('downloadStartWindow'), $sanitizedParams->getString('downloadStartWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadStartWindow', $sanitizedParams->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('downloadEndWindow')) {
                    $this->handleChangedSettings('downloadEndWindow', ($ownConfig) ? $displayProfile->getSetting('downloadEndWindow') : $display->getSetting('downloadEndWindow'), $sanitizedParams->getString('downloadEndWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadEndWindow', $sanitizedParams->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('updateStartWindow')) {
                    $this->handleChangedSettings('updateStartWindow', ($ownConfig) ? $displayProfile->getSetting('updateStartWindow') : $display->getSetting('updateStartWindow'), $sanitizedParams->getString('updateStartWindow'), $changedSettings);
                    $displayProfile->setSetting('updateStartWindow', $sanitizedParams->getString('updateStartWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('updateEndWindow')) {
                    $this->handleChangedSettings('updateEndWindow', ($ownConfig) ? $displayProfile->getSetting('updateEndWindow') : $display->getSetting('updateEndWindow'), $sanitizedParams->getString('updateEndWindow'), $changedSettings);
                    $displayProfile->setSetting('updateEndWindow', $sanitizedParams->getString('updateEndWindow'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('dayPartId')) {
                    $this->handleChangedSettings('dayPartId', ($ownConfig) ? $displayProfile->getSetting('dayPartId') : $display->getSetting('dayPartId'), $sanitizedParams->getInt('dayPartId'), $changedSettings);
                    $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('xmrNetworkAddress')) {
                    $this->handleChangedSettings('xmrNetworkAddress',($ownConfig) ? $displayProfile->getSetting('xmrNetworkAddress') : $display->getSetting('xmrNetworkAddress'), $sanitizedParams->getString('xmrNetworkAddress'), $changedSettings);
                    $displayProfile->setSetting('xmrNetworkAddress', $sanitizedParams->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('statsEnabled')) {
                    $this->handleChangedSettings('statsEnabled', ($ownConfig) ? $displayProfile->getSetting('statsEnabled') : $display->getSetting('statsEnabled'), $sanitizedParams->getCheckbox('statsEnabled'), $changedSettings);
                    $displayProfile->setSetting('statsEnabled', $sanitizedParams->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('aggregationLevel')) {
                    $this->handleChangedSettings('aggregationLevel', ($ownConfig) ? $displayProfile->getSetting('aggregationLevel') : $display->getSetting('aggregationLevel'), $sanitizedParams->getString('aggregationLevel'), $changedSettings);
                    $displayProfile->setSetting('aggregationLevel', $sanitizedParams->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('orientation')) {
                    $this->handleChangedSettings('orientation',($ownConfig) ? $displayProfile->getSetting('orientation') : $display->getSetting('orientation'), $sanitizedParams->getInt('orientation'), $changedSettings);
                    $displayProfile->setSetting('orientation', $sanitizedParams->getInt('orientation'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('logLevel')) {
                    $this->handleChangedSettings('logLevel', ($ownConfig) ? $displayProfile->getSetting('logLevel') : $display->getSetting('logLevel'), $sanitizedParams->getString('logLevel'), $changedSettings);
                    $displayProfile->setSetting('logLevel', $sanitizedParams->getString('logLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('elevateLogsUntil')) {
                    $this->handleChangedSettings(
                        'elevateLogsUntil',
                        ($ownConfig)
                            ? $displayProfile->getSetting('elevateLogsUntil')
                            : $display->getSetting('elevateLogsUntil'),
                        $sanitizedParams->getDate('elevateLogsUntil')?->format('U'),
                        $changedSettings
                    );
                    $displayProfile->setSetting(
                        'elevateLogsUntil',
                        $sanitizedParams->getDate('elevateLogsUntil')?->format('U'),
                        $ownConfig,
                        $config
                    );
                }

                if ($sanitizedParams->hasParam('versionMediaId')) {
                    $this->handleChangedSettings('versionMediaId', ($ownConfig) ? $displayProfile->getSetting('versionMediaId') : $display->getSetting('versionMediaId'), $sanitizedParams->getInt('versionMediaId'), $changedSettings);
                    $displayProfile->setSetting('versionMediaId', $sanitizedParams->getInt('versionMediaId'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('actionBarMode')) {
                    $this->handleChangedSettings('actionBarMode', ($ownConfig) ? $displayProfile->getSetting('actionBarMode') : $display->getSetting('actionBarMode'), $sanitizedParams->getInt('actionBarMode'), $changedSettings);
                    $displayProfile->setSetting('actionBarMode', $sanitizedParams->getInt('actionBarMode'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('actionBarDisplayDuration')) {
                    $this->handleChangedSettings('actionBarDisplayDuration', ($ownConfig) ? $displayProfile->getSetting('actionBarDisplayDuration') : $display->getSetting('actionBarDisplayDuration'), $sanitizedParams->getInt('actionBarDisplayDuration'), $changedSettings);
                    $displayProfile->setSetting('actionBarDisplayDuration', $sanitizedParams->getInt('actionBarDisplayDuration'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $this->handleChangedSettings('sendCurrentLayoutAsStatusUpdate', ($ownConfig) ? $displayProfile->getSetting('sendCurrentLayoutAsStatusUpdate') : $display->getSetting('sendCurrentLayoutAsStatusUpdate'), $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $changedSettings);
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotSize')) {
                    $this->handleChangedSettings('screenShotSize', ($ownConfig) ? $displayProfile->getSetting('screenShotSize') : $display->getSetting('screenShotSize'), $sanitizedParams->getInt('screenShotSize'), $changedSettings);
                    $displayProfile->setSetting('screenShotSize', $sanitizedParams->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('mediaInventoryTimer')) {
                    $this->handleChangedSettings('mediaInventoryTimer',($ownConfig) ? $displayProfile->getSetting('mediaInventoryTimer') : $display->getSetting('mediaInventoryTimer'), $sanitizedParams->getInt('mediaInventoryTimer'), $changedSettings);
                    $displayProfile->setSetting('mediaInventoryTimer', $sanitizedParams->getInt('mediaInventoryTimer'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('forceHttps')) {
                    $this->handleChangedSettings('forceHttps', ($ownConfig) ? $displayProfile->getSetting('forceHttps') : $display->getSetting('forceHttps'), $sanitizedParams->getCheckbox('forceHttps'), $changedSettings);
                    $displayProfile->setSetting('forceHttps', $sanitizedParams->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('serverPort')) {
                    $this->handleChangedSettings('serverPort', ($ownConfig) ? $displayProfile->getSetting('serverPort') : $display->getSetting('serverPort'), $sanitizedParams->getInt('serverPort'), $changedSettings);
                    $displayProfile->setSetting('serverPort', $sanitizedParams->getInt('serverPort'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('embeddedServerAllowWan')) {
                    $this->handleChangedSettings('embeddedServerAllowWan', ($ownConfig) ? $displayProfile->getSetting('embeddedServerAllowWan') : $display->getSetting('embeddedServerAllowWan'), $sanitizedParams->getCheckbox('embeddedServerAllowWan'), $changedSettings);
                    $displayProfile->setSetting('embeddedServerAllowWan', $sanitizedParams->getCheckbox('embeddedServerAllowWan'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotRequestInterval')) {
                    $this->handleChangedSettings('screenShotRequestInterval', ($ownConfig) ? $displayProfile->getSetting('screenShotRequestInterval') : $display->getSetting('screenShotRequestInterval'), $sanitizedParams->getInt('screenShotRequestInterval'), $changedSettings);
                    $displayProfile->setSetting('screenShotRequestInterval', $sanitizedParams->getInt('screenShotRequestInterval'), $ownConfig, $config);
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

                    $this->handleChangedSettings('timers', ($ownConfig) ? $displayProfile->getSetting('timers') : $display->getSetting('timers'), json_encode($timerOptions), $changedSettings);
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

                    $this->handleChangedSettings('pictureOptions', ($ownConfig) ? $displayProfile->getSetting('pictureOptions') : $display->getSetting('pictureOptions'),  json_encode($pictureControlsOptions), $changedSettings);
                    // Encode option and save it as a string to the lock setting
                    $displayProfile->setSetting('pictureOptions', json_encode($pictureControlsOptions), $ownConfig, $config);
                }

                // Get values from lockOptions params
                $usblock = $sanitizedParams->getString('usblock', ['default' => 'empty']);
                $osdlock = $sanitizedParams->getString('osdlock', ['default' => 'empty']);
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

                $this->handleChangedSettings('lockOptions', ($ownConfig) ? $displayProfile->getSetting('lockOptions') : $display->getSetting('lockOptions'), json_encode($lockOptions), $changedSettings);
                // Encode option and save it as a string to the lock setting
                $displayProfile->setSetting('lockOptions', json_encode($lockOptions), $ownConfig, $config);

                break;

            case 'chromeOS':
                if ($sanitizedParams->hasParam('licenceCode')) {
                    $this->handleChangedSettings('licenceCode', ($ownConfig) ? $displayProfile->getSetting('licenceCode') : $display->getSetting('licenceCode'), $sanitizedParams->getString('licenceCode'), $changedSettings);
                    $displayProfile->setSetting('licenceCode', $sanitizedParams->getString('licenceCode'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('collectInterval')) {
                    $this->handleChangedSettings('collectInterval', ($ownConfig) ? $displayProfile->getSetting('collectInterval') : $display->getSetting('collectInterval'), $sanitizedParams->getInt('collectInterval'), $changedSettings);
                    $displayProfile->setSetting('collectInterval', $sanitizedParams->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('dayPartId')) {
                    $this->handleChangedSettings('dayPartId', ($ownConfig) ? $displayProfile->getSetting('dayPartId') : $display->getSetting('dayPartId'), $sanitizedParams->getInt('dayPartId'), $changedSettings);
                    $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('xmrNetworkAddress')) {
                    $this->handleChangedSettings('xmrNetworkAddress',($ownConfig) ? $displayProfile->getSetting('xmrNetworkAddress') : $display->getSetting('xmrNetworkAddress'), $sanitizedParams->getString('xmrNetworkAddress'), $changedSettings);
                    $displayProfile->setSetting('xmrNetworkAddress', $sanitizedParams->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('statsEnabled')) {
                    $this->handleChangedSettings('statsEnabled', ($ownConfig) ? $displayProfile->getSetting('statsEnabled') : $display->getSetting('statsEnabled'), $sanitizedParams->getCheckbox('statsEnabled'), $changedSettings);
                    $displayProfile->setSetting('statsEnabled', $sanitizedParams->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('aggregationLevel')) {
                    $this->handleChangedSettings('aggregationLevel', ($ownConfig) ? $displayProfile->getSetting('aggregationLevel') : $display->getSetting('aggregationLevel'), $sanitizedParams->getString('aggregationLevel'), $changedSettings);
                    $displayProfile->setSetting('aggregationLevel', $sanitizedParams->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('logLevel')) {
                    $this->handleChangedSettings('logLevel', ($ownConfig) ? $displayProfile->getSetting('logLevel') : $display->getSetting('logLevel'), $sanitizedParams->getString('logLevel'), $changedSettings);
                    $displayProfile->setSetting('logLevel', $sanitizedParams->getString('logLevel'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('elevateLogsUntil')) {
                    $this->handleChangedSettings(
                        'elevateLogsUntil',
                        ($ownConfig)
                            ? $displayProfile->getSetting('elevateLogsUntil')
                            : $display->getSetting('elevateLogsUntil'),
                        $sanitizedParams->getDate('elevateLogsUntil')?->format('U'),
                        $changedSettings
                    );
                    $displayProfile->setSetting(
                        'elevateLogsUntil',
                        $sanitizedParams->getDate('elevateLogsUntil')?->format('U'),
                        $ownConfig,
                        $config
                    );
                }

                if ($sanitizedParams->hasParam('playerVersionId')) {
                    $this->handleChangedSettings('playerVersionId', ($ownConfig) ? $displayProfile->getSetting('playerVersionId') : $display->getSetting('playerVersionId'), $sanitizedParams->getInt('playerVersionId'), $changedSettings);
                    $displayProfile->setSetting('playerVersionId', $sanitizedParams->getInt('playerVersionId'), $ownConfig, $config);
                }

                if ($sanitizedParams->hasParam('screenShotSize')) {
                    $this->handleChangedSettings('screenShotSize', ($ownConfig) ? $displayProfile->getSetting('screenShotSize') : $display->getSetting('screenShotSize'), $sanitizedParams->getInt('screenShotSize'), $changedSettings);
                    $displayProfile->setSetting('screenShotSize', $sanitizedParams->getInt('screenShotSize'), $ownConfig, $config);
                }

                break;

            default:
                if ($displayProfile->isCustom()) {
                    $this->getLog()->info('Edit for custom Display profile type ' . $displayProfile->getClientType());
                    $config = $displayProfile->handleCustomFields($sanitizedParams, $config, $display);
                } else {
                    $this->getLog()->info('Edit for unknown type ' . $displayProfile->getClientType());
                }
        }

        if ($changedSettings != []) {
            $this->getLog()->audit( ($ownConfig) ? 'DisplayProfile' : 'Display', ($ownConfig) ? $displayProfile->displayProfileId : $display->displayId, ($ownConfig) ? 'Updated' : 'Display Saved', $changedSettings);
        }

        return $config;
    }

    private function handleChangedSettings($setting, $oldValue, $newValue, &$changedSettings)
    {
        if ($oldValue != $newValue) {
            $changedSettings[$setting] = $oldValue . ' > ' . $newValue;
        }
    }
}
