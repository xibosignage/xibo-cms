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
     * @param null|\Xibo\Entity\Display $display
     * @return null|array
     * @throws InvalidArgumentException
     * @throws \Xibo\Exception\NotFoundException
     */
    public function editConfigFields($displayProfile, $config = null, $display = null)
    {
        // Setting on our own config or not?
        $ownConfig = ($config === null);

        $changedSettings = [];

        switch ($displayProfile->getClientType()) {

            case 'android':
                if ($this->getSanitizer()->hasParam('emailAddress')) {
                    $this->handleChangedSettings('emailAddress', ($ownConfig) ? $displayProfile->getSetting('emailAddress') : $display->getSetting('emailAddress'), $this->getSanitizer()->getString('emailAddress'), $changedSettings);
                    $displayProfile->setSetting('emailAddress', $this->getSanitizer()->getString('emailAddress'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('settingsPassword')) {
                    $this->handleChangedSettings('settingsPassword', ($ownConfig) ? $displayProfile->getSetting('settingsPassword') : $display->getSetting('settingsPassword'), $this->getSanitizer()->getString('settingsPassword'), $changedSettings);
                    $displayProfile->setSetting('settingsPassword', $this->getSanitizer()->getString('settingsPassword'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('collectInterval')) {
                    $this->handleChangedSettings('collectInterval', ($ownConfig) ? $displayProfile->getSetting('collectInterval') : $display->getSetting('collectInterval'), $this->getSanitizer()->getInt('collectInterval'), $changedSettings);
                    $displayProfile->setSetting('collectInterval', $this->getSanitizer()->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadStartWindow')) {
                    $this->handleChangedSettings('downloadStartWindow', ($ownConfig) ? $displayProfile->getSetting('downloadStartWindow') : $display->getSetting('downloadStartWindow'), $this->getSanitizer()->getString('downloadStartWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadStartWindow', $this->getSanitizer()->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadEndWindow')) {
                    $this->handleChangedSettings('downloadEndWindow', ($ownConfig) ? $displayProfile->getSetting('downloadEndWindow') : $display->getSetting('downloadEndWindow'), $this->getSanitizer()->getString('downloadEndWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadEndWindow', $this->getSanitizer()->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('xmrNetworkAddress')) {
                    $this->handleChangedSettings('xmrNetworkAddress', ($ownConfig) ? $displayProfile->getSetting('xmrNetworkAddress') : $display->getSetting('xmrNetworkAddress'), $this->getSanitizer()->getString('xmrNetworkAddress'), $changedSettings);
                    $displayProfile->setSetting('xmrNetworkAddress', $this->getSanitizer()->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('statsEnabled')) {
                    $this->handleChangedSettings('statsEnabled', ($ownConfig) ? $displayProfile->getSetting('statsEnabled') : $display->getSetting('statsEnabled'), $this->getSanitizer()->getCheckbox('statsEnabled'), $changedSettings);
                    $displayProfile->setSetting('statsEnabled', $this->getSanitizer()->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('aggregationLevel')) {
                    $this->handleChangedSettings('aggregationLevel', ($ownConfig) ? $displayProfile->getSetting('aggregationLevel') : $display->getSetting('aggregationLevel'), $this->getSanitizer()->getString('aggregationLevel'), $changedSettings);
                    $displayProfile->setSetting('aggregationLevel', $this->getSanitizer()->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('orientation')) {
                    $this->handleChangedSettings('orientation', ($ownConfig) ? $displayProfile->getSetting('orientation') : $display->getSetting('orientation'), $this->getSanitizer()->getInt('orientation'), $changedSettings);
                    $displayProfile->setSetting('orientation', $this->getSanitizer()->getInt('orientation'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenDimensions')) {
                    $this->handleChangedSettings('screenDimensions', ($ownConfig) ? $displayProfile->getSetting('screenDimensions') : $display->getSetting('screenDimensions'), $this->getSanitizer()->getString('screenDimensions'), $changedSettings);
                    $displayProfile->setSetting('screenDimensions', $this->getSanitizer()->getString('screenDimensions'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('blacklistVideo')) {
                    $this->handleChangedSettings('blacklistVideo', ($ownConfig) ? $displayProfile->getSetting('blacklistVideo') : $display->getSetting('blacklistVideo'), $this->getSanitizer()->getCheckbox('blacklistVideo'), $changedSettings);
                    $displayProfile->setSetting('blacklistVideo', $this->getSanitizer()->getCheckbox('blacklistVideo'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('storeHtmlOnInternal')) {
                    $this->handleChangedSettings('storeHtmlOnInternal', ($ownConfig) ? $displayProfile->getSetting('storeHtmlOnInternal') : $display->getSetting('storeHtmlOnInternal'), $this->getSanitizer()->getCheckbox('storeHtmlOnInternal'), $changedSettings);
                    $displayProfile->setSetting('storeHtmlOnInternal', $this->getSanitizer()->getCheckbox('storeHtmlOnInternal'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('useSurfaceVideoView')) {
                    $this->handleChangedSettings('useSurfaceVideoView', ($ownConfig) ? $displayProfile->getSetting('useSurfaceVideoView') : $display->getSetting('useSurfaceVideoView'), $this->getSanitizer()->getCheckbox('useSurfaceVideoView'), $changedSettings);
                    $displayProfile->setSetting('useSurfaceVideoView', $this->getSanitizer()->getCheckbox('useSurfaceVideoView'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('logLevel')) {
                    $this->handleChangedSettings('logLevel', ($ownConfig) ? $displayProfile->getSetting('logLevel') : $display->getSetting('logLevel'), $this->getSanitizer()->getString('logLevel'), $changedSettings);
                    $displayProfile->setSetting('logLevel', $this->getSanitizer()->getString('logLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('versionMediaId')) {
                    $this->handleChangedSettings('versionMediaId', ($ownConfig) ? $displayProfile->getSetting('versionMediaId') : $display->getSetting('versionMediaId'), $this->getSanitizer()->getInt('versionMediaId'), $changedSettings);
                    $displayProfile->setSetting('versionMediaId', $this->getSanitizer()->getInt('versionMediaId'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('startOnBoot')) {
                    $this->handleChangedSettings('startOnBoot', ($ownConfig) ? $displayProfile->getSetting('startOnBoot') : $display->getSetting('startOnBoot'), $this->getSanitizer()->getCheckbox('startOnBoot'), $changedSettings);
                    $displayProfile->setSetting('startOnBoot', $this->getSanitizer()->getCheckbox('startOnBoot'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('actionBarMode')) {
                    $this->handleChangedSettings('actionBarMode', ($ownConfig) ? $displayProfile->getSetting('actionBarMode') : $display->getSetting('actionBarMode'), $this->getSanitizer()->getInt('actionBarMode'), $changedSettings);
                    $displayProfile->setSetting('actionBarMode', $this->getSanitizer()->getInt('actionBarMode'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('actionBarDisplayDuration')) {
                    $this->handleChangedSettings('actionBarDisplayDuration', ($ownConfig) ? $displayProfile->getSetting('actionBarDisplayDuration') : $display->getSetting('actionBarDisplayDuration'), $this->getSanitizer()->getInt('actionBarDisplayDuration'), $changedSettings);
                    $displayProfile->setSetting('actionBarDisplayDuration', $this->getSanitizer()->getInt('actionBarDisplayDuration'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('actionBarIntent')) {
                    $this->handleChangedSettings('actionBarIntent', ($ownConfig) ? $displayProfile->getSetting('actionBarIntent') : $display->getSetting('actionBarIntent'), $this->getSanitizer()->getString('actionBarIntent'), $changedSettings);
                    $displayProfile->setSetting('actionBarIntent', $this->getSanitizer()->getString('actionBarIntent'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('autoRestart')) {
                    $this->handleChangedSettings('autoRestart', ($ownConfig) ? $displayProfile->getSetting('autoRestart') : $display->getSetting('autoRestart'), $this->getSanitizer()->getCheckbox('autoRestart'), $changedSettings);
                    $displayProfile->setSetting('autoRestart', $this->getSanitizer()->getCheckbox('autoRestart'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('startOnBootDelay')) {
                    $this->handleChangedSettings('startOnBootDelay', ($ownConfig) ? $displayProfile->getSetting('startOnBootDelay') : $display->getSetting('startOnBootDelay'), $this->getSanitizer()->getInt('startOnBootDelay'), $changedSettings);
                    $displayProfile->setSetting('startOnBootDelay', $this->getSanitizer()->getInt('startOnBootDelay'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $this->handleChangedSettings('sendCurrentLayoutAsStatusUpdate', ($ownConfig) ? $displayProfile->getSetting('sendCurrentLayoutAsStatusUpdate') : $display->getSetting('sendCurrentLayoutAsStatusUpdate'), $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $changedSettings);
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotRequestInterval')) {
                    $this->handleChangedSettings('screenShotRequestInterval', ($ownConfig) ? $displayProfile->getSetting('screenShotRequestInterval') : $display->getSetting('screenShotRequestInterval'), $this->getSanitizer()->getInt('screenShotRequestInterval'), $changedSettings);
                    $displayProfile->setSetting('screenShotRequestInterval', $this->getSanitizer()->getInt('screenShotRequestInterval'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('expireModifiedLayouts')) {
                    $this->handleChangedSettings('expireModifiedLayouts', ($ownConfig) ? $displayProfile->getSetting('expireModifiedLayouts') : $display->getSetting('expireModifiedLayouts'), $this->getSanitizer()->getCheckbox('expireModifiedLayouts'), $changedSettings);
                    $displayProfile->setSetting('expireModifiedLayouts', $this->getSanitizer()->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotIntent')) {
                    $this->handleChangedSettings('screenShotIntent', ($ownConfig) ? $displayProfile->getSetting('screenShotIntent') : $display->getSetting('screenShotIntent'), $this->getSanitizer()->getString('screenShotIntent'), $changedSettings);
                    $displayProfile->setSetting('screenShotIntent', $this->getSanitizer()->getString('screenShotIntent'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotSize')) {
                    $this->handleChangedSettings('screenShotSize', ($ownConfig) ? $displayProfile->getSetting('screenShotSize') : $display->getSetting('screenShotSize'), $this->getSanitizer()->getInt('screenShotSize'), $changedSettings);
                    $displayProfile->setSetting('screenShotSize', $this->getSanitizer()->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('updateStartWindow')) {
                    $this->handleChangedSettings('updateStartWindow', ($ownConfig) ? $displayProfile->getSetting('updateStartWindow') : $display->getSetting('updateStartWindow'), $this->getSanitizer()->getString('updateStartWindow'), $changedSettings);
                    $displayProfile->setSetting('updateStartWindow', $this->getSanitizer()->getString('updateStartWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('updateEndWindow')) {
                    $this->handleChangedSettings('updateEndWindow', ($ownConfig) ? $displayProfile->getSetting('updateEndWindow') : $display->getSetting('updateEndWindow'), $this->getSanitizer()->getString('updateEndWindow'), $changedSettings);
                    $displayProfile->setSetting('updateEndWindow', $this->getSanitizer()->getString('updateEndWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('dayPartId')) {
                    $this->handleChangedSettings('dayPartId', ($ownConfig) ? $displayProfile->getSetting('dayPartId') : $display->getSetting('dayPartId'), $this->getSanitizer()->getInt('dayPartId'), $changedSettings);
                    $displayProfile->setSetting('dayPartId', $this->getSanitizer()->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('webViewPluginState')) {
                    $this->handleChangedSettings('webViewPluginState', ($ownConfig) ? $displayProfile->getSetting('webViewPluginState') : $display->getSetting('webViewPluginState'), $this->getSanitizer()->getString('webViewPluginState'), $changedSettings);
                    $displayProfile->setSetting('webViewPluginState', $this->getSanitizer()->getString('webViewPluginState'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('hardwareAccelerateWebViewMode')) {
                    $this->handleChangedSettings('hardwareAccelerateWebViewMode', ($ownConfig) ? $displayProfile->getSetting('hardwareAccelerateWebViewMode') : $display->getSetting('hardwareAccelerateWebViewMode'), $this->getSanitizer()->getString('hardwareAccelerateWebViewMode'), $changedSettings);
                    $displayProfile->setSetting('hardwareAccelerateWebViewMode', $this->getSanitizer()->getString('hardwareAccelerateWebViewMode'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('timeSyncFromCms')) {
                    $this->handleChangedSettings('timeSyncFromCms', ($ownConfig) ? $displayProfile->getSetting('timeSyncFromCms') : $display->getSetting('timeSyncFromCms'), $this->getSanitizer()->getCheckbox('timeSyncFromCms'), $changedSettings);
                    $displayProfile->setSetting('timeSyncFromCms', $this->getSanitizer()->getCheckbox('timeSyncFromCms'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('webCacheEnabled')) {
                    $this->handleChangedSettings('webCacheEnabled', ($ownConfig) ? $displayProfile->getSetting('webCacheEnabled') : $display->getSetting('webCacheEnabled'), $this->getSanitizer()->getCheckbox('webCacheEnabled'), $changedSettings);
                    $displayProfile->setSetting('webCacheEnabled', $this->getSanitizer()->getCheckbox('webCacheEnabled'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('serverPort')) {
                    $this->handleChangedSettings('serverPort', ($ownConfig) ? $displayProfile->getSetting('serverPort') : $display->getSetting('serverPort'), $this->getSanitizer()->getInt('serverPort'), $changedSettings);
                    $displayProfile->setSetting('serverPort', $this->getSanitizer()->getInt('serverPort'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('installWithLoadedLinkLibraries')) {
                    $this->handleChangedSettings('installWithLoadedLinkLibraries', ($ownConfig) ? $displayProfile->getSetting('installWithLoadedLinkLibraries') : $display->getSetting('installWithLoadedLinkLibraries'), $this->getSanitizer()->getCheckbox('installWithLoadedLinkLibraries'), $changedSettings);
                    $displayProfile->setSetting('installWithLoadedLinkLibraries', $this->getSanitizer()->getCheckbox('installWithLoadedLinkLibraries'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('forceHttps')) {
                    $this->handleChangedSettings('forceHttps', ($ownConfig) ? $displayProfile->getSetting('forceHttps') : $display->getSetting('forceHttps'), $this->getSanitizer()->getCheckbox('forceHttps'), $changedSettings);
                    $displayProfile->setSetting('forceHttps', $this->getSanitizer()->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('isUseMultipleVideoDecoders')) {
                    $this->handleChangedSettings('isUseMultipleVideoDecoders', ($ownConfig) ? $displayProfile->getSetting('isUseMultipleVideoDecoders') : $display->getSetting('isUseMultipleVideoDecoders'), $this->getSanitizer()->getString('isUseMultipleVideoDecoders'), $changedSettings);
                    $displayProfile->setSetting('isUseMultipleVideoDecoders', $this->getSanitizer()->getString('isUseMultipleVideoDecoders'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('maxRegionCount')) {
                    $this->handleChangedSettings('maxRegionCount', ($ownConfig) ? $displayProfile->getSetting('maxRegionCount') : $display->getSetting('maxRegionCount'), $this->getSanitizer()->getInt('maxRegionCount'), $changedSettings);
                    $displayProfile->setSetting('maxRegionCount', $this->getSanitizer()->getInt('maxRegionCount'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('isRecordGeoLocationOnProofOfPlay')) {
                    $displayProfile->setSetting('isRecordGeoLocationOnProofOfPlay', $this->getSanitizer()->getCheckbox('isRecordGeoLocationOnProofOfPlay'), $ownConfig, $config);
                }

                break;

            case 'windows':
                if ($this->getSanitizer()->hasParam('collectInterval')) {
                    $this->handleChangedSettings('collectInterval', ($ownConfig) ? $displayProfile->getSetting('collectInterval') : $display->getSetting('collectInterval'), $this->getSanitizer()->getInt('collectInterval'), $changedSettings);
                    $displayProfile->setSetting('collectInterval', $this->getSanitizer()->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadStartWindow')) {
                    $this->handleChangedSettings('downloadStartWindow', ($ownConfig) ? $displayProfile->getSetting('downloadStartWindow') : $display->getSetting('downloadStartWindow'), $this->getSanitizer()->getString('downloadStartWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadStartWindow', $this->getSanitizer()->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadEndWindow')) {
                    $this->handleChangedSettings('downloadEndWindow', ($ownConfig) ? $displayProfile->getSetting('downloadEndWindow') : $display->getSetting('downloadEndWindow'), $this->getSanitizer()->getString('downloadEndWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadEndWindow', $this->getSanitizer()->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('xmrNetworkAddress')) {
                    $this->handleChangedSettings('xmrNetworkAddress', ($ownConfig) ? $displayProfile->getSetting('xmrNetworkAddress') : $display->getSetting('xmrNetworkAddress'), $this->getSanitizer()->getString('xmrNetworkAddress'), $changedSettings);
                    $displayProfile->setSetting('xmrNetworkAddress', $this->getSanitizer()->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('dayPartId')) {
                    $this->handleChangedSettings('dayPartId', ($ownConfig) ? $displayProfile->getSetting('dayPartId') : $display->getSetting('dayPartId'), $this->getSanitizer()->getInt('dayPartId'), $changedSettings);
                    $displayProfile->setSetting('dayPartId', $this->getSanitizer()->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('statsEnabled')) {
                    $this->handleChangedSettings('statsEnabled', ($ownConfig) ? $displayProfile->getSetting('statsEnabled') : $display->getSetting('statsEnabled'), $this->getSanitizer()->getCheckbox('statsEnabled'), $changedSettings);
                    $displayProfile->setSetting('statsEnabled', $this->getSanitizer()->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('aggregationLevel')) {
                    $this->handleChangedSettings('aggregationLevel', ($ownConfig) ? $displayProfile->getSetting('aggregationLevel') : $display->getSetting('aggregationLevel'), $this->getSanitizer()->getString('aggregationLevel'), $changedSettings);
                    $displayProfile->setSetting('aggregationLevel', $this->getSanitizer()->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('powerpointEnabled')) {
                    $this->handleChangedSettings('powerpointEnabled', ($ownConfig) ? $displayProfile->getSetting('powerpointEnabled') : $display->getSetting('powerpointEnabled'), $this->getSanitizer()->getCheckbox('powerpointEnabled'), $changedSettings);
                    $displayProfile->setSetting('powerpointEnabled', $this->getSanitizer()->getCheckbox('powerpointEnabled'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sizeX')) {
                    $this->handleChangedSettings('sizeX', ($ownConfig) ? $displayProfile->getSetting('sizeX') : $display->getSetting('sizeX'), $this->getSanitizer()->getDouble('sizeX'), $changedSettings);
                    $displayProfile->setSetting('sizeX', $this->getSanitizer()->getDouble('sizeX'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sizeY')) {
                    $this->handleChangedSettings('sizeY', ($ownConfig) ? $displayProfile->getSetting('sizeY') : $display->getSetting('sizeY'), $this->getSanitizer()->getDouble('sizeY'), $changedSettings);
                    $displayProfile->setSetting('sizeY', $this->getSanitizer()->getDouble('sizeY'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('offsetX')) {
                    $this->handleChangedSettings('offsetX', ($ownConfig) ? $displayProfile->getSetting('offsetX') : $display->getSetting('offsetX'), $this->getSanitizer()->getDouble('offsetX'), $changedSettings);
                    $displayProfile->setSetting('offsetX', $this->getSanitizer()->getDouble('offsetX'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('offsetY')) {
                    $this->handleChangedSettings('offsetY', ($ownConfig) ? $displayProfile->getSetting('offsetY') : $display->getSetting('offsetY'), $this->getSanitizer()->getDouble('offsetY'), $changedSettings);
                    $displayProfile->setSetting('offsetY', $this->getSanitizer()->getDouble('offsetY'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('clientInfomationCtrlKey')) {
                    $this->handleChangedSettings('clientInfomationCtrlKey', ($ownConfig) ? $displayProfile->getSetting('clientInfomationCtrlKey') : $display->getSetting('clientInfomationCtrlKey'), $this->getSanitizer()->getCheckbox('clientInfomationCtrlKey'), $changedSettings);
                    $displayProfile->setSetting('clientInfomationCtrlKey', $this->getSanitizer()->getCheckbox('clientInfomationCtrlKey'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('clientInformationKeyCode')) {
                    $this->handleChangedSettings('clientInformationKeyCode', ($ownConfig) ? $displayProfile->getSetting('clientInformationKeyCode') : $display->getSetting('clientInformationKeyCode'), $this->getSanitizer()->getString('clientInformationKeyCode'), $changedSettings);
                    $displayProfile->setSetting('clientInformationKeyCode', $this->getSanitizer()->getString('clientInformationKeyCode'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('logLevel')) {
                    $this->handleChangedSettings('logLevel', ($ownConfig) ? $displayProfile->getSetting('logLevel') : $display->getSetting('logLevel'), $this->getSanitizer()->getString('logLevel'), $changedSettings);
                    $displayProfile->setSetting('logLevel', $this->getSanitizer()->getString('logLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('logToDiskLocation')) {
                    $this->handleChangedSettings('logToDiskLocation', ($ownConfig) ? $displayProfile->getSetting('logToDiskLocation') : $display->getSetting('logToDiskLocation'), $this->getSanitizer()->getString('logToDiskLocation'), $changedSettings);
                    $displayProfile->setSetting('logToDiskLocation', $this->getSanitizer()->getString('logToDiskLocation'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('showInTaskbar')) {
                    $this->handleChangedSettings('showInTaskbar', ($ownConfig) ? $displayProfile->getSetting('showInTaskbar') : $display->getSetting('showInTaskbar'), $this->getSanitizer()->getCheckbox('showInTaskbar'), $changedSettings);
                    $displayProfile->setSetting('showInTaskbar', $this->getSanitizer()->getCheckbox('showInTaskbar'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('cursorStartPosition')) {
                    $this->handleChangedSettings('cursorStartPosition', ($ownConfig) ? $displayProfile->getSetting('cursorStartPosition') : $display->getSetting('cursorStartPosition'), $this->getSanitizer()->getString('cursorStartPosition'), $changedSettings);
                    $displayProfile->setSetting('cursorStartPosition', $this->getSanitizer()->getString('cursorStartPosition'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('doubleBuffering')) {
                    $this->handleChangedSettings('doubleBuffering', ($ownConfig) ? $displayProfile->getSetting('doubleBuffering') : $display->getSetting('doubleBuffering'), $this->getSanitizer()->getCheckbox('doubleBuffering'), $changedSettings);
                    $displayProfile->setSetting('doubleBuffering', $this->getSanitizer()->getCheckbox('doubleBuffering'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('emptyLayoutDuration')) {
                    $this->handleChangedSettings('emptyLayoutDuration', ($ownConfig) ? $displayProfile->getSetting('emptyLayoutDuration') : $display->getSetting('emptyLayoutDuration'), $this->getSanitizer()->getInt('emptyLayoutDuration'), $changedSettings);
                    $displayProfile->setSetting('emptyLayoutDuration', $this->getSanitizer()->getInt('emptyLayoutDuration'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('enableMouse')) {
                    $this->handleChangedSettings('enableMouse', ($ownConfig) ? $displayProfile->getSetting('enableMouse') : $display->getSetting('enableMouse'), $this->getSanitizer()->getCheckbox('enableMouse'), $changedSettings);
                    $displayProfile->setSetting('enableMouse', $this->getSanitizer()->getCheckbox('enableMouse'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('enableShellCommands')) {
                    $this->handleChangedSettings('enableShellCommands', ($ownConfig) ? $displayProfile->getSetting('enableShellCommands') : $display->getSetting('enableShellCommands'), $this->getSanitizer()->getCheckbox('enableShellCommands'), $changedSettings);
                    $displayProfile->setSetting('enableShellCommands', $this->getSanitizer()->getCheckbox('enableShellCommands'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('expireModifiedLayouts')) {
                    $this->handleChangedSettings('expireModifiedLayouts', ($ownConfig) ? $displayProfile->getSetting('expireModifiedLayouts') : $display->getSetting('expireModifiedLayouts'), $this->getSanitizer()->getCheckbox('expireModifiedLayouts'), $changedSettings);
                    $displayProfile->setSetting('expireModifiedLayouts', $this->getSanitizer()->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('maxConcurrentDownloads')) {
                    $this->handleChangedSettings('maxConcurrentDownloads', ($ownConfig) ? $displayProfile->getSetting('maxConcurrentDownloads') : $display->getSetting('maxConcurrentDownloads'), $this->getSanitizer()->getInt('maxConcurrentDownloads'), $changedSettings);
                    $displayProfile->setSetting('maxConcurrentDownloads', $this->getSanitizer()->getInt('maxConcurrentDownloads'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('shellCommandAllowList')) {
                    $this->handleChangedSettings('shellCommandAllowList', ($ownConfig) ? $displayProfile->getSetting('shellCommandAllowList') : $display->getSetting('shellCommandAllowList'), $this->getSanitizer()->getString('shellCommandAllowList'), $changedSettings);
                    $displayProfile->setSetting('shellCommandAllowList', $this->getSanitizer()->getString('shellCommandAllowList'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $this->handleChangedSettings('sendCurrentLayoutAsStatusUpdate', ($ownConfig) ? $displayProfile->getSetting('sendCurrentLayoutAsStatusUpdate') : $display->getSetting('sendCurrentLayoutAsStatusUpdate'), $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $changedSettings);
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotRequestInterval')) {
                    $this->handleChangedSettings('screenShotRequestInterval', ($ownConfig) ? $displayProfile->getSetting('screenShotRequestInterval') : $display->getSetting('screenShotRequestInterval'), $this->getSanitizer()->getInt('screenShotRequestInterval'), $changedSettings);
                    $displayProfile->setSetting('screenShotRequestInterval', $this->getSanitizer()->getInt('screenShotRequestInterval'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotSize')) {
                    $this->handleChangedSettings('screenShotSize', ($ownConfig) ? $displayProfile->getSetting('screenShotSize') : $display->getSetting('screenShotSize'), $this->getSanitizer()->getInt('screenShotSize'), $changedSettings);
                    $displayProfile->setSetting('screenShotSize', $this->getSanitizer()->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('maxLogFileUploads')) {
                    $this->handleChangedSettings('maxLogFileUploads', ($ownConfig) ? $displayProfile->getSetting('maxLogFileUploads') : $display->getSetting('maxLogFileUploads'), $this->getSanitizer()->getInt('maxLogFileUploads'), $changedSettings);
                    $displayProfile->setSetting('maxLogFileUploads', $this->getSanitizer()->getInt('maxLogFileUploads'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('embeddedServerPort')) {
                    $this->handleChangedSettings('embeddedServerPort', ($ownConfig) ? $displayProfile->getSetting('embeddedServerPort') : $display->getSetting('embeddedServerPort'), $this->getSanitizer()->getInt('embeddedServerPort'), $changedSettings);
                    $displayProfile->setSetting('embeddedServerPort', $this->getSanitizer()->getInt('embeddedServerPort'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('preventSleep')) {
                    $this->handleChangedSettings('preventSleep', ($ownConfig) ? $displayProfile->getSetting('preventSleep') : $display->getSetting('preventSleep'), $this->getSanitizer()->getCheckbox('preventSleep'), $changedSettings);
                    $displayProfile->setSetting('preventSleep', $this->getSanitizer()->getCheckbox('preventSleep'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('forceHttps')) {
                    $this->handleChangedSettings('forceHttps', ($ownConfig) ? $displayProfile->getSetting('forceHttps') : $display->getSetting('forceHttps'), $this->getSanitizer()->getCheckbox('forceHttps'), $changedSettings);
                    $displayProfile->setSetting('forceHttps', $this->getSanitizer()->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('authServerWhitelist')) {
                    $this->handleChangedSettings('authServerWhitelist', ($ownConfig) ? $displayProfile->getSetting('authServerWhitelist') : $display->getSetting('authServerWhitelist'), $this->getSanitizer()->getString('authServerWhitelist'), $changedSettings);
                    $displayProfile->setSetting('authServerWhitelist', $this->getSanitizer()->getString('authServerWhitelist'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('edgeBrowserWhitelist')) {
                    $this->handleChangedSettings('edgeBrowserWhitelist', ($ownConfig) ? $displayProfile->getSetting('edgeBrowserWhitelist') : $display->getSetting('edgeBrowserWhitelist'), $this->getSanitizer()->getString('edgeBrowserWhitelist'), $changedSettings);
                    $displayProfile->setSetting('edgeBrowserWhitelist', $this->getSanitizer()->getString('edgeBrowserWhitelist'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('isRecordGeoLocationOnProofOfPlay')) {
                    $displayProfile->setSetting('isRecordGeoLocationOnProofOfPlay', $this->getSanitizer()->getCheckbox('isRecordGeoLocationOnProofOfPlay'), $ownConfig, $config);
                }

                break;

            case 'linux':
                if ($this->getSanitizer()->hasParam('collectInterval')) {
                    $this->handleChangedSettings('collectInterval',($ownConfig) ? $displayProfile->getSetting('collectInterval') : $display->getSetting('collectInterval'), $this->getSanitizer()->getInt('collectInterval'), $changedSettings);
                    $displayProfile->setSetting('collectInterval', $this->getSanitizer()->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadStartWindow')) {
                    $this->handleChangedSettings('downloadStartWindow', ($ownConfig) ? $displayProfile->getSetting('downloadStartWindow') : $display->getSetting('downloadStartWindow'), $this->getSanitizer()->getString('downloadStartWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadStartWindow', $this->getSanitizer()->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadEndWindow')) {
                    $this->handleChangedSettings('downloadEndWindow', ($ownConfig) ? $displayProfile->getSetting('downloadEndWindow') : $display->getSetting('downloadEndWindow'), $this->getSanitizer()->getString('downloadEndWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadEndWindow', $this->getSanitizer()->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('dayPartId')) {
                    $this->handleChangedSettings('dayPartId', ($ownConfig) ? $displayProfile->getSetting('dayPartId') : $display->getSetting('dayPartId'), $this->getSanitizer()->getInt('dayPartId'), $changedSettings);
                    $displayProfile->setSetting('dayPartId', $this->getSanitizer()->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('xmrNetworkAddress')) {
                    $this->handleChangedSettings('xmrNetworkAddress',($ownConfig) ? $displayProfile->getSetting('xmrNetworkAddress') : $display->getSetting('xmrNetworkAddress'), $this->getSanitizer()->getString('xmrNetworkAddress'), $changedSettings);
                    $displayProfile->setSetting('xmrNetworkAddress', $this->getSanitizer()->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('statsEnabled')) {
                    $this->handleChangedSettings('statsEnabled', ($ownConfig) ? $displayProfile->getSetting('statsEnabled') : $display->getSetting('statsEnabled'), $this->getSanitizer()->getCheckbox('statsEnabled'), $changedSettings);
                    $displayProfile->setSetting('statsEnabled', $this->getSanitizer()->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('aggregationLevel')) {
                    $this->handleChangedSettings('aggregationLevel', ($ownConfig) ? $displayProfile->getSetting('aggregationLevel') : $display->getSetting('aggregationLevel'), $this->getSanitizer()->getString('aggregationLevel'), $changedSettings);
                    $displayProfile->setSetting('aggregationLevel', $this->getSanitizer()->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sizeX')) {
                    $this->handleChangedSettings('sizeX', ($ownConfig) ? $displayProfile->getSetting('sizeX') : $display->getSetting('sizeX'), $this->getSanitizer()->getDouble('sizeX'), $changedSettings);
                    $displayProfile->setSetting('sizeX', $this->getSanitizer()->getDouble('sizeX'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sizeY')) {
                    $this->handleChangedSettings('sizeY', ($ownConfig) ? $displayProfile->getSetting('sizeY') : $display->getSetting('sizeY'), $this->getSanitizer()->getDouble('sizeY'), $changedSettings);
                    $displayProfile->setSetting('sizeY', $this->getSanitizer()->getDouble('sizeY'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('offsetX')) {
                    $this->handleChangedSettings('offsetX', ($ownConfig) ? $displayProfile->getSetting('offsetX') : $display->getSetting('offsetX'), $this->getSanitizer()->getDouble('offsetX'), $changedSettings);
                    $displayProfile->setSetting('offsetX', $this->getSanitizer()->getDouble('offsetX'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('offsetY')) {
                    $this->handleChangedSettings('offsetY', ($ownConfig) ? $displayProfile->getSetting('offsetY') : $display->getSetting('offsetY'), $this->getSanitizer()->getDouble('offsetY'), $changedSettings);
                    $displayProfile->setSetting('offsetY', $this->getSanitizer()->getDouble('offsetY'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('logLevel')) {
                    $this->handleChangedSettings('logLevel', ($ownConfig) ? $displayProfile->getSetting('logLevel') : $display->getSetting('logLevel'), $this->getSanitizer()->getString('logLevel'), $changedSettings);
                    $displayProfile->setSetting('logLevel', $this->getSanitizer()->getString('logLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('enableShellCommands')) {
                    $this->handleChangedSettings('enableShellCommands',($ownConfig) ? $displayProfile->getSetting('enableShellCommands') : $display->getSetting('enableShellCommands'), $this->getSanitizer()->getCheckbox('enableShellCommands'), $changedSettings);
                    $displayProfile->setSetting('enableShellCommands', $this->getSanitizer()->getCheckbox('enableShellCommands'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('expireModifiedLayouts')) {
                    $this->handleChangedSettings('expireModifiedLayouts',($ownConfig) ? $displayProfile->getSetting('expireModifiedLayouts') : $display->getSetting('expireModifiedLayouts'), $this->getSanitizer()->getCheckbox('expireModifiedLayouts'), $changedSettings);
                    $displayProfile->setSetting('expireModifiedLayouts', $this->getSanitizer()->getCheckbox('expireModifiedLayouts'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('maxConcurrentDownloads')) {
                    $this->handleChangedSettings('maxConcurrentDownloads', ($ownConfig) ? $displayProfile->getSetting('maxConcurrentDownloads') : $display->getSetting('maxConcurrentDownloads'), $this->getSanitizer()->getInt('maxConcurrentDownloads'), $changedSettings);
                    $displayProfile->setSetting('maxConcurrentDownloads', $this->getSanitizer()->getInt('maxConcurrentDownloads'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('shellCommandAllowList')) {
                    $this->handleChangedSettings('shellCommandAllowList', ($ownConfig) ? $displayProfile->getSetting('shellCommandAllowList') : $display->getSetting('shellCommandAllowList'), $this->getSanitizer()->getString('shellCommandAllowList'), $changedSettings);
                    $displayProfile->setSetting('shellCommandAllowList', $this->getSanitizer()->getString('shellCommandAllowList'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $this->handleChangedSettings('sendCurrentLayoutAsStatusUpdate', ($ownConfig) ? $displayProfile->getSetting('sendCurrentLayoutAsStatusUpdate') : $display->getSetting('sendCurrentLayoutAsStatusUpdate'), $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $changedSettings);
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotRequestInterval')) {
                    $this->handleChangedSettings('screenShotRequestInterval', ($ownConfig) ? $displayProfile->getSetting('screenShotRequestInterval') : $display->getSetting('screenShotRequestInterval'), $this->getSanitizer()->getInt('screenShotRequestInterval'), $changedSettings);
                    $displayProfile->setSetting('screenShotRequestInterval', $this->getSanitizer()->getInt('screenShotRequestInterval'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotSize')) {
                    $this->handleChangedSettings('screenShotSize', ($ownConfig) ? $displayProfile->getSetting('screenShotSize') : $display->getSetting('screenShotSize'), $this->getSanitizer()->getInt('screenShotSize'), $changedSettings);
                    $displayProfile->setSetting('screenShotSize', $this->getSanitizer()->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('maxLogFileUploads')) {
                    $this->handleChangedSettings('maxLogFileUploads', ($ownConfig) ? $displayProfile->getSetting('maxLogFileUploads') : $display->getSetting('maxLogFileUploads'), $this->getSanitizer()->getInt('maxLogFileUploads'), $changedSettings);
                    $displayProfile->setSetting('maxLogFileUploads', $this->getSanitizer()->getInt('maxLogFileUploads'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('embeddedServerPort')) {
                    $this->handleChangedSettings('embeddedServerPort',($ownConfig) ? $displayProfile->getSetting('embeddedServerPort') : $display->getSetting('embeddedServerPort'), $this->getSanitizer()->getInt('embeddedServerPort'), $changedSettings);
                    $displayProfile->setSetting('embeddedServerPort', $this->getSanitizer()->getInt('embeddedServerPort'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('preventSleep')) {
                    $this->handleChangedSettings('preventSleep',($ownConfig) ? $displayProfile->getSetting('preventSleep') : $display->getSetting('preventSleep'), $this->getSanitizer()->getCheckbox('preventSleep'), $changedSettings);
                    $displayProfile->setSetting('preventSleep', $this->getSanitizer()->getCheckbox('preventSleep'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('forceHttps')) {
                    $this->handleChangedSettings('forceHttps', ($ownConfig) ? $displayProfile->getSetting('forceHttps') : $display->getSetting('forceHttps'), $this->getSanitizer()->getCheckbox('forceHttps'), $changedSettings);
                    $displayProfile->setSetting('forceHttps', $this->getSanitizer()->getCheckbox('forceHttps'), $ownConfig, $config);
                }

                break;

            case 'lg':
            case 'sssp':

                if ($this->getSanitizer()->hasParam('emailAddress')) {
                    $this->handleChangedSettings('emailAddress', ($ownConfig) ? $displayProfile->getSetting('emailAddress') : $display->getSetting('emailAddress'), $this->getSanitizer()->getString('emailAddress'), $changedSettings);
                    $displayProfile->setSetting('emailAddress', $this->getSanitizer()->getString('emailAddress'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('collectInterval')) {
                    $this->handleChangedSettings('collectInterval', ($ownConfig) ? $displayProfile->getSetting('collectInterval') : $display->getSetting('collectInterval'), $this->getSanitizer()->getInt('collectInterval'), $changedSettings);
                    $displayProfile->setSetting('collectInterval', $this->getSanitizer()->getInt('collectInterval'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadStartWindow')) {
                    $this->handleChangedSettings('downloadStartWindow', ($ownConfig) ? $displayProfile->getSetting('downloadStartWindow') : $display->getSetting('downloadStartWindow'), $this->getSanitizer()->getString('downloadStartWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadStartWindow', $this->getSanitizer()->getString('downloadStartWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('downloadEndWindow')) {
                    $this->handleChangedSettings('downloadEndWindow', ($ownConfig) ? $displayProfile->getSetting('downloadEndWindow') : $display->getSetting('downloadEndWindow'), $this->getSanitizer()->getString('downloadEndWindow'), $changedSettings);
                    $displayProfile->setSetting('downloadEndWindow', $this->getSanitizer()->getString('downloadEndWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('updateStartWindow')) {
                    $this->handleChangedSettings('updateStartWindow', ($ownConfig) ? $displayProfile->getSetting('updateStartWindow') : $display->getSetting('updateStartWindow'), $this->getSanitizer()->getString('updateStartWindow'), $changedSettings);
                    $displayProfile->setSetting('updateStartWindow', $this->getSanitizer()->getString('updateStartWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('updateEndWindow')) {
                    $this->handleChangedSettings('updateEndWindow', ($ownConfig) ? $displayProfile->getSetting('updateEndWindow') : $display->getSetting('updateEndWindow'), $this->getSanitizer()->getString('updateEndWindow'), $changedSettings);
                    $displayProfile->setSetting('updateEndWindow', $this->getSanitizer()->getString('updateEndWindow'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('dayPartId')) {
                    $this->handleChangedSettings('dayPartId', ($ownConfig) ? $displayProfile->getSetting('dayPartId') : $display->getSetting('dayPartId'), $this->getSanitizer()->getInt('dayPartId'), $changedSettings);
                    $displayProfile->setSetting('dayPartId', $this->getSanitizer()->getInt('dayPartId'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('xmrNetworkAddress')) {
                    $this->handleChangedSettings('xmrNetworkAddress',($ownConfig) ? $displayProfile->getSetting('xmrNetworkAddress') : $display->getSetting('xmrNetworkAddress'), $this->getSanitizer()->getString('xmrNetworkAddress'), $changedSettings);
                    $displayProfile->setSetting('xmrNetworkAddress', $this->getSanitizer()->getString('xmrNetworkAddress'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('statsEnabled')) {
                    $this->handleChangedSettings('statsEnabled', ($ownConfig) ? $displayProfile->getSetting('statsEnabled') : $display->getSetting('statsEnabled'), $this->getSanitizer()->getCheckbox('statsEnabled'), $changedSettings);
                    $displayProfile->setSetting('statsEnabled', $this->getSanitizer()->getCheckbox('statsEnabled'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('aggregationLevel')) {
                    $this->handleChangedSettings('aggregationLevel', ($ownConfig) ? $displayProfile->getSetting('aggregationLevel') : $display->getSetting('aggregationLevel'), $this->getSanitizer()->getString('aggregationLevel'), $changedSettings);
                    $displayProfile->setSetting('aggregationLevel', $this->getSanitizer()->getString('aggregationLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('orientation')) {
                    $this->handleChangedSettings('orientation',($ownConfig) ? $displayProfile->getSetting('orientation') : $display->getSetting('orientation'), $this->getSanitizer()->getInt('orientation'), $changedSettings);
                    $displayProfile->setSetting('orientation', $this->getSanitizer()->getInt('orientation'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('logLevel')) {
                    $this->handleChangedSettings('logLevel', ($ownConfig) ? $displayProfile->getSetting('logLevel') : $display->getSetting('logLevel'), $this->getSanitizer()->getString('logLevel'), $changedSettings);
                    $displayProfile->setSetting('logLevel', $this->getSanitizer()->getString('logLevel'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('versionMediaId')) {
                    $this->handleChangedSettings('versionMediaId', ($ownConfig) ? $displayProfile->getSetting('versionMediaId') : $display->getSetting('versionMediaId'), $this->getSanitizer()->getInt('versionMediaId'), $changedSettings);
                    $displayProfile->setSetting('versionMediaId', $this->getSanitizer()->getInt('versionMediaId'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('actionBarMode')) {
                    $this->handleChangedSettings('actionBarMode', ($ownConfig) ? $displayProfile->getSetting('actionBarMode') : $display->getSetting('actionBarMode'), $this->getSanitizer()->getInt('actionBarMode'), $changedSettings);
                    $displayProfile->setSetting('actionBarMode', $this->getSanitizer()->getInt('actionBarMode'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('actionBarDisplayDuration')) {
                    $this->handleChangedSettings('actionBarDisplayDuration', ($ownConfig) ? $displayProfile->getSetting('actionBarDisplayDuration') : $display->getSetting('actionBarDisplayDuration'), $this->getSanitizer()->getInt('actionBarDisplayDuration'), $changedSettings);
                    $displayProfile->setSetting('actionBarDisplayDuration', $this->getSanitizer()->getInt('actionBarDisplayDuration'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('sendCurrentLayoutAsStatusUpdate')) {
                    $this->handleChangedSettings('sendCurrentLayoutAsStatusUpdate', ($ownConfig) ? $displayProfile->getSetting('sendCurrentLayoutAsStatusUpdate') : $display->getSetting('sendCurrentLayoutAsStatusUpdate'), $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $changedSettings);
                    $displayProfile->setSetting('sendCurrentLayoutAsStatusUpdate', $this->getSanitizer()->getCheckbox('sendCurrentLayoutAsStatusUpdate'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('screenShotSize')) {
                    $this->handleChangedSettings('screenShotSize', ($ownConfig) ? $displayProfile->getSetting('screenShotSize') : $display->getSetting('screenShotSize'), $this->getSanitizer()->getInt('screenShotSize'), $changedSettings);
                    $displayProfile->setSetting('screenShotSize', $this->getSanitizer()->getInt('screenShotSize'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('mediaInventoryTimer')) {
                    $this->handleChangedSettings('mediaInventoryTimer',($ownConfig) ? $displayProfile->getSetting('mediaInventoryTimer') : $display->getSetting('mediaInventoryTimer'), $this->getSanitizer()->getInt('mediaInventoryTimer'), $changedSettings);
                    $displayProfile->setSetting('mediaInventoryTimer', $this->getSanitizer()->getInt('mediaInventoryTimer'), $ownConfig, $config);
                }

                if ($this->getSanitizer()->hasParam('forceHttps')) {
                    $this->handleChangedSettings('forceHttps', ($ownConfig) ? $displayProfile->getSetting('forceHttps') : $display->getSetting('forceHttps'), $this->getSanitizer()->getCheckbox('forceHttps'), $changedSettings);
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

                    $this->handleChangedSettings('timers', ($ownConfig) ? $displayProfile->getSetting('timers') : $display->getSetting('timers'), json_encode($timerOptions), $changedSettings);
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

                    $this->handleChangedSettings('pictureOptions', ($ownConfig) ? $displayProfile->getSetting('pictureOptions') : $display->getSetting('pictureOptions'),  json_encode($pictureControlsOptions), $changedSettings);
                    // Encode option and save it as a string to the lock setting
                    $displayProfile->setSetting('pictureOptions', json_encode($pictureControlsOptions), $ownConfig, $config);
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

                $this->handleChangedSettings('lockOptions', ($ownConfig) ? $displayProfile->getSetting('lockOptions') : $display->getSetting('lockOptions'), json_encode($lockOptions), $changedSettings);
                // Encode option and save it as a string to the lock setting
                $displayProfile->setSetting('lockOptions', json_encode($lockOptions), $ownConfig, $config);
                break;

            default:
                $this->getLog()->info('Edit for unknown type ' . $displayProfile->getClientType());
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