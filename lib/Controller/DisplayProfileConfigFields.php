<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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
 *
 * @package Xibo\Controller
 */
trait DisplayProfileConfigFields
{
    /**
     * Edit config fields
     *
     * @param  \Xibo\Entity\DisplayProfile $displayProfile
     * @param  SanitizerInterface          $sanitizedParams
     * @param  null|array                  $config          if empty will edit the config of provided display profile
     * @param  \Xibo\Entity\Display        $display
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
                $this->applyAndroidConfigFields(
                    $displayProfile,
                    $sanitizedParams,
                    $config,
                    $display,
                    $changedSettings,
                    $ownConfig
                );
                break;

            case 'windows':
                $this->applyWindowsConfigFields(
                    $displayProfile,
                    $sanitizedParams,
                    $config,
                    $display,
                    $changedSettings,
                    $ownConfig
                );
                break;

            case 'linux':
                $this->applyLinuxConfigFields(
                    $displayProfile,
                    $sanitizedParams,
                    $config,
                    $display,
                    $changedSettings,
                    $ownConfig
                );
                break;

            case 'lg':
            case 'sssp':
                $this->applyLgConfigFields(
                    $displayProfile,
                    $sanitizedParams,
                    $config,
                    $display,
                    $changedSettings,
                    $ownConfig
                );
                break;

            case 'hisense':
                $this->applyHisenseConfigFields(
                    $displayProfile,
                    $sanitizedParams,
                    $config,
                    $display,
                    $changedSettings,
                    $ownConfig
                );
                break;

            case 'chromeOS':
                $this->applyChromeOsConfigFields(
                    $displayProfile,
                    $sanitizedParams,
                    $config,
                    $display,
                    $changedSettings,
                    $ownConfig
                );
                break;

            default:
                $this->getLog()->warning('Edit for unknown display profile type ' . $displayProfile->getClientType());
        }

        if ($changedSettings != []) {
            $this->getLog()->audit(
                ($ownConfig) ? 'DisplayProfile' : 'Display',
                ($ownConfig) ? $displayProfile->displayProfileId : $display->displayId,
                ($ownConfig) ? 'Updated' : 'Display Saved',
                $changedSettings
            );
        }

        return $config;
    }

    private function handleChangedSettings($setting, $oldValue, $newValue, &$changedSettings)
    {
        if ($oldValue != $newValue) {
            $changedSettings[$setting] = $oldValue . ' > ' . $newValue;
        }
    }

    private function applyAndroidConfigFields(
        $displayProfile,
        SanitizerInterface $sanitizedParams,
        ?array &$config,
        $display,
        array &$changedSettings,
        bool $ownConfig
    ): void {
        if ($sanitizedParams->hasParam('emailAddress')) {
            $this->handleChangedSettings(
                'emailAddress',
                ($ownConfig)
                    ? $displayProfile->getSetting('emailAddress')
                    : $display->getSetting('emailAddress'),
                $sanitizedParams->getString('emailAddress'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'emailAddress',
                $sanitizedParams->getString('emailAddress'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('settingsPassword')) {
            $this->handleChangedSettings(
                'settingsPassword',
                ($ownConfig)
                    ? $displayProfile->getSetting('settingsPassword')
                    : $display->getSetting('settingsPassword'),
                $sanitizedParams->getString('settingsPassword'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'settingsPassword',
                $sanitizedParams->getString('settingsPassword'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('collectInterval')) {
            $this->handleChangedSettings(
                'collectInterval',
                ($ownConfig)
                    ? $displayProfile->getSetting('collectInterval')
                    : $display->getSetting('collectInterval'),
                $sanitizedParams->getInt('collectInterval'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'collectInterval',
                $sanitizedParams->getInt('collectInterval'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('downloadStartWindow')) {
            $this->handleChangedSettings(
                'downloadStartWindow',
                ($ownConfig)
                    ? $displayProfile->getSetting('downloadStartWindow')
                    : $display->getSetting('downloadStartWindow'),
                $sanitizedParams->getString('downloadStartWindow'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'downloadStartWindow',
                $sanitizedParams->getString('downloadStartWindow'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('downloadEndWindow')) {
            $this->handleChangedSettings(
                'downloadEndWindow',
                ($ownConfig)
                    ? $displayProfile->getSetting('downloadEndWindow')
                    : $display->getSetting('downloadEndWindow'),
                $sanitizedParams->getString('downloadEndWindow'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'downloadEndWindow',
                $sanitizedParams->getString('downloadEndWindow'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('xmrNetworkAddress')) {
            $this->handleChangedSettings(
                'xmrNetworkAddress',
                ($ownConfig)
                    ? $displayProfile->getSetting('xmrNetworkAddress')
                    : $display->getSetting('xmrNetworkAddress'),
                $sanitizedParams->getString('xmrNetworkAddress'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'xmrNetworkAddress',
                $sanitizedParams->getString('xmrNetworkAddress'),
                $ownConfig,
                $config
            );
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
            $this->handleChangedSettings(
                'statsEnabled',
                ($ownConfig)
                    ? $displayProfile->getSetting('statsEnabled')
                    : $display->getSetting('statsEnabled'),
                $sanitizedParams->getCheckbox('statsEnabled'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'statsEnabled',
                $sanitizedParams->getCheckbox('statsEnabled'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('aggregationLevel')) {
            $this->handleChangedSettings(
                'aggregationLevel',
                ($ownConfig)
                    ? $displayProfile->getSetting('aggregationLevel')
                    : $display->getSetting('aggregationLevel'),
                $sanitizedParams->getString('aggregationLevel'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'aggregationLevel',
                $sanitizedParams->getString('aggregationLevel'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('orientation')) {
            $this->handleChangedSettings(
                'orientation',
                ($ownConfig)
                    ? $displayProfile->getSetting('orientation')
                    : $display->getSetting('orientation'),
                $sanitizedParams->getInt('orientation'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'orientation',
                $sanitizedParams->getInt('orientation'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('screenDimensions')) {
            $this->handleChangedSettings(
                'screenDimensions',
                ($ownConfig)
                    ? $displayProfile->getSetting('screenDimensions')
                    : $display->getSetting('screenDimensions'),
                $sanitizedParams->getString('screenDimensions'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'screenDimensions',
                $sanitizedParams->getString('screenDimensions'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('blacklistVideo')) {
            $this->handleChangedSettings(
                'blacklistVideo',
                ($ownConfig)
                    ? $displayProfile->getSetting('blacklistVideo')
                    : $display->getSetting('blacklistVideo'),
                $sanitizedParams->getCheckbox('blacklistVideo'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'blacklistVideo',
                $sanitizedParams->getCheckbox('blacklistVideo'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('storeHtmlOnInternal')) {
            $this->handleChangedSettings(
                'storeHtmlOnInternal',
                ($ownConfig)
                    ? $displayProfile->getSetting('storeHtmlOnInternal')
                    : $display->getSetting('storeHtmlOnInternal'),
                $sanitizedParams->getCheckbox('storeHtmlOnInternal'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'storeHtmlOnInternal',
                $sanitizedParams->getCheckbox('storeHtmlOnInternal'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('useSurfaceVideoView')) {
            $this->handleChangedSettings(
                'useSurfaceVideoView',
                ($ownConfig)
                    ? $displayProfile->getSetting('useSurfaceVideoView')
                    : $display->getSetting('useSurfaceVideoView'),
                $sanitizedParams->getCheckbox('useSurfaceVideoView'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'useSurfaceVideoView',
                $sanitizedParams->getCheckbox('useSurfaceVideoView'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('logLevel')) {
            $this->handleChangedSettings(
                'logLevel',
                ($ownConfig)
                    ? $displayProfile->getSetting('logLevel')
                    : $display->getSetting('logLevel'),
                $sanitizedParams->getString('logLevel'),
                $changedSettings
            );
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
            $this->handleChangedSettings(
                'versionMediaId',
                ($ownConfig)
                    ? $displayProfile->getSetting('versionMediaId')
                    : $display->getSetting('versionMediaId'),
                $sanitizedParams->getInt('versionMediaId'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'versionMediaId',
                $sanitizedParams->getInt('versionMediaId'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('startOnBoot')) {
            $this->handleChangedSettings(
                'startOnBoot',
                ($ownConfig)
                    ? $displayProfile->getSetting('startOnBoot')
                    : $display->getSetting('startOnBoot'),
                $sanitizedParams->getCheckbox('startOnBoot'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'startOnBoot',
                $sanitizedParams->getCheckbox('startOnBoot'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('actionBarMode')) {
            $this->handleChangedSettings(
                'actionBarMode',
                ($ownConfig)
                    ? $displayProfile->getSetting('actionBarMode')
                    : $display->getSetting('actionBarMode'),
                $sanitizedParams->getInt('actionBarMode'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'actionBarMode',
                $sanitizedParams->getInt('actionBarMode'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('actionBarDisplayDuration')) {
            $this->handleChangedSettings(
                'actionBarDisplayDuration',
                ($ownConfig)
                    ? $displayProfile->getSetting('actionBarDisplayDuration')
                    : $display->getSetting('actionBarDisplayDuration'),
                $sanitizedParams->getInt('actionBarDisplayDuration'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'actionBarDisplayDuration',
                $sanitizedParams->getInt('actionBarDisplayDuration'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('actionBarIntent')) {
            $this->handleChangedSettings(
                'actionBarIntent',
                ($ownConfig)
                    ? $displayProfile->getSetting('actionBarIntent')
                    : $display->getSetting('actionBarIntent'),
                $sanitizedParams->getString('actionBarIntent'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'actionBarIntent',
                $sanitizedParams->getString('actionBarIntent'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('autoRestart')) {
            $this->handleChangedSettings(
                'autoRestart',
                ($ownConfig)
                    ? $displayProfile->getSetting('autoRestart')
                    : $display->getSetting('autoRestart'),
                $sanitizedParams->getCheckbox('autoRestart'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'autoRestart',
                $sanitizedParams->getCheckbox('autoRestart'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('startOnBootDelay')) {
            $this->handleChangedSettings(
                'startOnBootDelay',
                ($ownConfig)
                    ? $displayProfile->getSetting('startOnBootDelay')
                    : $display->getSetting('startOnBootDelay'),
                $sanitizedParams->getInt('startOnBootDelay'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'startOnBootDelay',
                $sanitizedParams->getInt('startOnBootDelay'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('sendCurrentLayoutAsStatusUpdate')) {
            $this->handleChangedSettings(
                'sendCurrentLayoutAsStatusUpdate',
                ($ownConfig)
                    ? $displayProfile->getSetting('sendCurrentLayoutAsStatusUpdate')
                    : $display->getSetting('sendCurrentLayoutAsStatusUpdate'),
                $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'sendCurrentLayoutAsStatusUpdate',
                $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('screenShotRequestInterval')) {
            $this->handleChangedSettings(
                'screenShotRequestInterval',
                ($ownConfig)
                    ? $displayProfile->getSetting('screenShotRequestInterval')
                    : $display->getSetting('screenShotRequestInterval'),
                $sanitizedParams->getInt('screenShotRequestInterval'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'screenShotRequestInterval',
                $sanitizedParams->getInt('screenShotRequestInterval'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('expireModifiedLayouts')) {
            $this->handleChangedSettings(
                'expireModifiedLayouts',
                ($ownConfig)
                    ? $displayProfile->getSetting('expireModifiedLayouts')
                    : $display->getSetting('expireModifiedLayouts'),
                $sanitizedParams->getCheckbox('expireModifiedLayouts'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'expireModifiedLayouts',
                $sanitizedParams->getCheckbox('expireModifiedLayouts'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('screenShotIntent')) {
            $this->handleChangedSettings(
                'screenShotIntent',
                ($ownConfig)
                    ? $displayProfile->getSetting('screenShotIntent')
                    : $display->getSetting('screenShotIntent'),
                $sanitizedParams->getString('screenShotIntent'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'screenShotIntent',
                $sanitizedParams->getString('screenShotIntent'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('screenShotSize')) {
            $this->handleChangedSettings(
                'screenShotSize',
                ($ownConfig)
                    ? $displayProfile->getSetting('screenShotSize')
                    : $display->getSetting('screenShotSize'),
                $sanitizedParams->getInt('screenShotSize'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'screenShotSize',
                $sanitizedParams->getInt('screenShotSize'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('updateStartWindow')) {
            $this->handleChangedSettings(
                'updateStartWindow',
                ($ownConfig)
                    ? $displayProfile->getSetting('updateStartWindow')
                    : $display->getSetting('updateStartWindow'),
                $sanitizedParams->getString('updateStartWindow'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'updateStartWindow',
                $sanitizedParams->getString('updateStartWindow'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('updateEndWindow')) {
            $this->handleChangedSettings(
                'updateEndWindow',
                ($ownConfig)
                    ? $displayProfile->getSetting('updateEndWindow')
                    : $display->getSetting('updateEndWindow'),
                $sanitizedParams->getString('updateEndWindow'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'updateEndWindow',
                $sanitizedParams->getString('updateEndWindow'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('dayPartId')) {
            $this->handleChangedSettings(
                'dayPartId',
                ($ownConfig)
                    ? $displayProfile->getSetting('dayPartId')
                    : $display->getSetting('dayPartId'),
                $sanitizedParams->getInt('dayPartId'),
                $changedSettings
            );
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
            $this->handleChangedSettings(
                'webViewPluginState',
                ($ownConfig)
                    ? $displayProfile->getSetting('webViewPluginState')
                    : $display->getSetting('webViewPluginState'),
                $sanitizedParams->getString('webViewPluginState'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'webViewPluginState',
                $sanitizedParams->getString('webViewPluginState'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('hardwareAccelerateWebViewMode')) {
            $this->handleChangedSettings(
                'hardwareAccelerateWebViewMode',
                ($ownConfig)
                    ? $displayProfile->getSetting('hardwareAccelerateWebViewMode')
                    : $display->getSetting('hardwareAccelerateWebViewMode'),
                $sanitizedParams->getString('hardwareAccelerateWebViewMode'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'hardwareAccelerateWebViewMode',
                $sanitizedParams->getString('hardwareAccelerateWebViewMode'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('timeSyncFromCms')) {
            $this->handleChangedSettings(
                'timeSyncFromCms',
                ($ownConfig)
                    ? $displayProfile->getSetting('timeSyncFromCms')
                    : $display->getSetting('timeSyncFromCms'),
                $sanitizedParams->getCheckbox('timeSyncFromCms'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'timeSyncFromCms',
                $sanitizedParams->getCheckbox('timeSyncFromCms'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('webCacheEnabled')) {
            $this->handleChangedSettings(
                'webCacheEnabled',
                ($ownConfig)
                    ? $displayProfile->getSetting('webCacheEnabled')
                    : $display->getSetting('webCacheEnabled'),
                $sanitizedParams->getCheckbox('webCacheEnabled'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'webCacheEnabled',
                $sanitizedParams->getCheckbox('webCacheEnabled'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('serverPort')) {
            $this->handleChangedSettings(
                'serverPort',
                ($ownConfig)
                    ? $displayProfile->getSetting('serverPort')
                    : $display->getSetting('serverPort'),
                $sanitizedParams->getInt('serverPort'),
                $changedSettings
            );
            $displayProfile->setSetting('serverPort', $sanitizedParams->getInt('serverPort'), $ownConfig, $config);
        }

        if ($sanitizedParams->hasParam('installWithLoadedLinkLibraries')) {
            $this->handleChangedSettings(
                'installWithLoadedLinkLibraries',
                ($ownConfig)
                    ? $displayProfile->getSetting('installWithLoadedLinkLibraries')
                    : $display->getSetting('installWithLoadedLinkLibraries'),
                $sanitizedParams->getCheckbox('installWithLoadedLinkLibraries'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'installWithLoadedLinkLibraries',
                $sanitizedParams->getCheckbox('installWithLoadedLinkLibraries'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('forceHttps')) {
            $this->handleChangedSettings(
                'forceHttps',
                ($ownConfig)
                    ? $displayProfile->getSetting('forceHttps')
                    : $display->getSetting('forceHttps'),
                $sanitizedParams->getCheckbox('forceHttps'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'forceHttps',
                $sanitizedParams->getCheckbox('forceHttps'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('isUseMultipleVideoDecoders')) {
            $this->handleChangedSettings(
                'isUseMultipleVideoDecoders',
                ($ownConfig)
                    ? $displayProfile->getSetting('isUseMultipleVideoDecoders')
                    : $display->getSetting('isUseMultipleVideoDecoders'),
                $sanitizedParams->getString('isUseMultipleVideoDecoders'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'isUseMultipleVideoDecoders',
                $sanitizedParams->getString('isUseMultipleVideoDecoders'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('maxRegionCount')) {
            $this->handleChangedSettings(
                'maxRegionCount',
                ($ownConfig)
                    ? $displayProfile->getSetting('maxRegionCount')
                    : $display->getSetting('maxRegionCount'),
                $sanitizedParams->getInt('maxRegionCount'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'maxRegionCount',
                $sanitizedParams->getInt('maxRegionCount'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('embeddedServerAllowWan')) {
            $this->handleChangedSettings(
                'embeddedServerAllowWan',
                ($ownConfig)
                    ? $displayProfile->getSetting('embeddedServerAllowWan')
                    : $display->getSetting('embeddedServerAllowWan'),
                $sanitizedParams->getCheckbox('embeddedServerAllowWan'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'embeddedServerAllowWan',
                $sanitizedParams->getCheckbox('embeddedServerAllowWan'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('isRecordGeoLocationOnProofOfPlay')) {
            $this->handleChangedSettings(
                'isRecordGeoLocationOnProofOfPlay',
                ($ownConfig)
                    ? $displayProfile->getSetting('isRecordGeoLocationOnProofOfPlay')
                    : $display->getSetting('isRecordGeoLocationOnProofOfPlay'),
                $sanitizedParams->getCheckbox('isRecordGeoLocationOnProofOfPlay'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'isRecordGeoLocationOnProofOfPlay',
                $sanitizedParams->getCheckbox('isRecordGeoLocationOnProofOfPlay'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('videoEngine')) {
            $this->handleChangedSettings(
                'videoEngine',
                ($ownConfig)
                    ? $displayProfile->getSetting('videoEngine')
                    : $display->getSetting('videoEngine'),
                $sanitizedParams->getString('videoEngine'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'videoEngine',
                $sanitizedParams->getString('videoEngine'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('isTouchEnabled')) {
            $this->handleChangedSettings(
                'isTouchEnabled',
                ($ownConfig)
                    ? $displayProfile->getSetting('isTouchEnabled')
                    : $display->getSetting('isTouchEnabled'),
                $sanitizedParams->getCheckbox('isTouchEnabled'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'isTouchEnabled',
                $sanitizedParams->getCheckbox('isTouchEnabled'),
                $ownConfig,
                $config
            );
        }
    }

    private function applyWindowsConfigFields(
        $displayProfile,
        SanitizerInterface $sanitizedParams,
        ?array &$config,
        $display,
        array &$changedSettings,
        bool $ownConfig
    ): void {
        if ($sanitizedParams->hasParam('collectInterval')) {
            $this->handleChangedSettings(
                'collectInterval',
                ($ownConfig)
                    ? $displayProfile->getSetting('collectInterval')
                    : $display->getSetting('collectInterval'),
                $sanitizedParams->getInt('collectInterval'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'collectInterval',
                $sanitizedParams->getInt('collectInterval'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('downloadStartWindow')) {
            $this->handleChangedSettings(
                'downloadStartWindow',
                ($ownConfig)
                    ? $displayProfile->getSetting('downloadStartWindow')
                    : $display->getSetting('downloadStartWindow'),
                $sanitizedParams->getString('downloadStartWindow'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'downloadStartWindow',
                $sanitizedParams->getString('downloadStartWindow'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('downloadEndWindow')) {
            $this->handleChangedSettings(
                'downloadEndWindow',
                ($ownConfig)
                    ? $displayProfile->getSetting('downloadEndWindow')
                    : $display->getSetting('downloadEndWindow'),
                $sanitizedParams->getString('downloadEndWindow'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'downloadEndWindow',
                $sanitizedParams->getString('downloadEndWindow'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('xmrNetworkAddress')) {
            $this->handleChangedSettings(
                'xmrNetworkAddress',
                ($ownConfig)
                    ? $displayProfile->getSetting('xmrNetworkAddress')
                    : $display->getSetting('xmrNetworkAddress'),
                $sanitizedParams->getString('xmrNetworkAddress'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'xmrNetworkAddress',
                $sanitizedParams->getString('xmrNetworkAddress'),
                $ownConfig,
                $config
            );
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

        if ($sanitizedParams->hasParam('dayPartId')) {
            $this->handleChangedSettings(
                'dayPartId',
                ($ownConfig)
                    ? $displayProfile->getSetting('dayPartId')
                    : $display->getSetting('dayPartId'),
                $sanitizedParams->getInt('dayPartId'),
                $changedSettings
            );
            $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
        }

        if ($sanitizedParams->hasParam('statsEnabled')) {
            $this->handleChangedSettings(
                'statsEnabled',
                ($ownConfig)
                    ? $displayProfile->getSetting('statsEnabled')
                    : $display->getSetting('statsEnabled'),
                $sanitizedParams->getCheckbox('statsEnabled'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'statsEnabled',
                $sanitizedParams->getCheckbox('statsEnabled'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('aggregationLevel')) {
            $this->handleChangedSettings(
                'aggregationLevel',
                ($ownConfig)
                    ? $displayProfile->getSetting('aggregationLevel')
                    : $display->getSetting('aggregationLevel'),
                $sanitizedParams->getString('aggregationLevel'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'aggregationLevel',
                $sanitizedParams->getString('aggregationLevel'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('powerpointEnabled')) {
            $this->handleChangedSettings(
                'powerpointEnabled',
                ($ownConfig)
                    ? $displayProfile->getSetting('powerpointEnabled')
                    : $display->getSetting('powerpointEnabled'),
                $sanitizedParams->getCheckbox('powerpointEnabled'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'powerpointEnabled',
                $sanitizedParams->getCheckbox('powerpointEnabled'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('sizeX')) {
            $this->handleChangedSettings(
                'sizeX',
                ($ownConfig) ? $displayProfile->getSetting('sizeX') : $display->getSetting('sizeX'),
                $sanitizedParams->getDouble('sizeX'),
                $changedSettings
            );
            $displayProfile->setSetting('sizeX', $sanitizedParams->getDouble('sizeX'), $ownConfig, $config);
        }

        if ($sanitizedParams->hasParam('sizeY')) {
            $this->handleChangedSettings(
                'sizeY',
                ($ownConfig) ? $displayProfile->getSetting('sizeY') : $display->getSetting('sizeY'),
                $sanitizedParams->getDouble('sizeY'),
                $changedSettings
            );
            $displayProfile->setSetting('sizeY', $sanitizedParams->getDouble('sizeY'), $ownConfig, $config);
        }

        if ($sanitizedParams->hasParam('offsetX')) {
            $this->handleChangedSettings(
                'offsetX',
                ($ownConfig) ? $displayProfile->getSetting('offsetX') : $display->getSetting('offsetX'),
                $sanitizedParams->getDouble('offsetX'),
                $changedSettings
            );
            $displayProfile->setSetting('offsetX', $sanitizedParams->getDouble('offsetX'), $ownConfig, $config);
        }

        if ($sanitizedParams->hasParam('offsetY')) {
            $this->handleChangedSettings(
                'offsetY',
                ($ownConfig) ? $displayProfile->getSetting('offsetY') : $display->getSetting('offsetY'),
                $sanitizedParams->getDouble('offsetY'),
                $changedSettings
            );
            $displayProfile->setSetting('offsetY', $sanitizedParams->getDouble('offsetY'), $ownConfig, $config);
        }

        if ($sanitizedParams->hasParam('clientInfomationCtrlKey')) {
            $this->handleChangedSettings(
                'clientInfomationCtrlKey',
                ($ownConfig)
                    ? $displayProfile->getSetting('clientInfomationCtrlKey')
                    : $display->getSetting('clientInfomationCtrlKey'),
                $sanitizedParams->getCheckbox('clientInfomationCtrlKey'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'clientInfomationCtrlKey',
                $sanitizedParams->getCheckbox('clientInfomationCtrlKey'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('clientInformationKeyCode')) {
            $this->handleChangedSettings(
                'clientInformationKeyCode',
                ($ownConfig)
                    ? $displayProfile->getSetting('clientInformationKeyCode')
                    : $display->getSetting('clientInformationKeyCode'),
                $sanitizedParams->getString('clientInformationKeyCode'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'clientInformationKeyCode',
                $sanitizedParams->getString('clientInformationKeyCode'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('logLevel')) {
            $this->handleChangedSettings(
                'logLevel',
                ($ownConfig)
                    ? $displayProfile->getSetting('logLevel')
                    : $display->getSetting('logLevel'),
                $sanitizedParams->getString('logLevel'),
                $changedSettings
            );
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

        if ($sanitizedParams->hasParam('logToDiskLocation')) {
            $this->handleChangedSettings(
                'logToDiskLocation',
                ($ownConfig)
                    ? $displayProfile->getSetting('logToDiskLocation')
                    : $display->getSetting('logToDiskLocation'),
                $sanitizedParams->getString('logToDiskLocation'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'logToDiskLocation',
                $sanitizedParams->getString('logToDiskLocation'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('showInTaskbar')) {
            $this->handleChangedSettings(
                'showInTaskbar',
                ($ownConfig)
                    ? $displayProfile->getSetting('showInTaskbar')
                    : $display->getSetting('showInTaskbar'),
                $sanitizedParams->getCheckbox('showInTaskbar'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'showInTaskbar',
                $sanitizedParams->getCheckbox('showInTaskbar'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('cursorStartPosition')) {
            $this->handleChangedSettings(
                'cursorStartPosition',
                ($ownConfig)
                    ? $displayProfile->getSetting('cursorStartPosition')
                    : $display->getSetting('cursorStartPosition'),
                $sanitizedParams->getString('cursorStartPosition'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'cursorStartPosition',
                $sanitizedParams->getString('cursorStartPosition'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('doubleBuffering')) {
            $this->handleChangedSettings(
                'doubleBuffering',
                ($ownConfig)
                    ? $displayProfile->getSetting('doubleBuffering')
                    : $display->getSetting('doubleBuffering'),
                $sanitizedParams->getCheckbox('doubleBuffering'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'doubleBuffering',
                $sanitizedParams->getCheckbox('doubleBuffering'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('emptyLayoutDuration')) {
            $this->handleChangedSettings(
                'emptyLayoutDuration',
                ($ownConfig)
                    ? $displayProfile->getSetting('emptyLayoutDuration')
                    : $display->getSetting('emptyLayoutDuration'),
                $sanitizedParams->getInt('emptyLayoutDuration'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'emptyLayoutDuration',
                $sanitizedParams->getInt('emptyLayoutDuration'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('enableMouse')) {
            $this->handleChangedSettings(
                'enableMouse',
                ($ownConfig)
                    ? $displayProfile->getSetting('enableMouse')
                    : $display->getSetting('enableMouse'),
                $sanitizedParams->getCheckbox('enableMouse'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'enableMouse',
                $sanitizedParams->getCheckbox('enableMouse'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('enableShellCommands')) {
            $this->handleChangedSettings(
                'enableShellCommands',
                ($ownConfig)
                    ? $displayProfile->getSetting('enableShellCommands')
                    : $display->getSetting('enableShellCommands'),
                $sanitizedParams->getCheckbox('enableShellCommands'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'enableShellCommands',
                $sanitizedParams->getCheckbox('enableShellCommands'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('expireModifiedLayouts')) {
            $this->handleChangedSettings(
                'expireModifiedLayouts',
                ($ownConfig)
                    ? $displayProfile->getSetting('expireModifiedLayouts')
                    : $display->getSetting('expireModifiedLayouts'),
                $sanitizedParams->getCheckbox('expireModifiedLayouts'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'expireModifiedLayouts',
                $sanitizedParams->getCheckbox('expireModifiedLayouts'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('maxConcurrentDownloads')) {
            $this->handleChangedSettings(
                'maxConcurrentDownloads',
                ($ownConfig)
                    ? $displayProfile->getSetting('maxConcurrentDownloads')
                    : $display->getSetting('maxConcurrentDownloads'),
                $sanitizedParams->getInt('maxConcurrentDownloads'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'maxConcurrentDownloads',
                $sanitizedParams->getInt('maxConcurrentDownloads'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('shellCommandAllowList')) {
            $this->handleChangedSettings(
                'shellCommandAllowList',
                ($ownConfig)
                    ? $displayProfile->getSetting('shellCommandAllowList')
                    : $display->getSetting('shellCommandAllowList'),
                $sanitizedParams->getString('shellCommandAllowList'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'shellCommandAllowList',
                $sanitizedParams->getString('shellCommandAllowList'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('sendCurrentLayoutAsStatusUpdate')) {
            $this->handleChangedSettings(
                'sendCurrentLayoutAsStatusUpdate',
                ($ownConfig)
                    ? $displayProfile->getSetting('sendCurrentLayoutAsStatusUpdate')
                    : $display->getSetting('sendCurrentLayoutAsStatusUpdate'),
                $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'sendCurrentLayoutAsStatusUpdate',
                $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('screenShotRequestInterval')) {
            $this->handleChangedSettings(
                'screenShotRequestInterval',
                ($ownConfig)
                    ? $displayProfile->getSetting('screenShotRequestInterval')
                    : $display->getSetting('screenShotRequestInterval'),
                $sanitizedParams->getInt('screenShotRequestInterval'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'screenShotRequestInterval',
                $sanitizedParams->getInt('screenShotRequestInterval'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('screenShotSize')) {
            $this->handleChangedSettings(
                'screenShotSize',
                ($ownConfig)
                    ? $displayProfile->getSetting('screenShotSize')
                    : $display->getSetting('screenShotSize'),
                $sanitizedParams->getInt('screenShotSize'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'screenShotSize',
                $sanitizedParams->getInt('screenShotSize'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('maxLogFileUploads')) {
            $this->handleChangedSettings(
                'maxLogFileUploads',
                ($ownConfig)
                    ? $displayProfile->getSetting('maxLogFileUploads')
                    : $display->getSetting('maxLogFileUploads'),
                $sanitizedParams->getInt('maxLogFileUploads'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'maxLogFileUploads',
                $sanitizedParams->getInt('maxLogFileUploads'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('embeddedServerPort')) {
            $this->handleChangedSettings(
                'embeddedServerPort',
                ($ownConfig)
                    ? $displayProfile->getSetting('embeddedServerPort')
                    : $display->getSetting('embeddedServerPort'),
                $sanitizedParams->getInt('embeddedServerPort'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'embeddedServerPort',
                $sanitizedParams->getInt('embeddedServerPort'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('preventSleep')) {
            $this->handleChangedSettings(
                'preventSleep',
                ($ownConfig)
                    ? $displayProfile->getSetting('preventSleep')
                    : $display->getSetting('preventSleep'),
                $sanitizedParams->getCheckbox('preventSleep'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'preventSleep',
                $sanitizedParams->getCheckbox('preventSleep'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('forceHttps')) {
            $this->handleChangedSettings(
                'forceHttps',
                ($ownConfig)
                    ? $displayProfile->getSetting('forceHttps')
                    : $display->getSetting('forceHttps'),
                $sanitizedParams->getCheckbox('forceHttps'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'forceHttps',
                $sanitizedParams->getCheckbox('forceHttps'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('authServerWhitelist')) {
            $this->handleChangedSettings(
                'authServerWhitelist',
                ($ownConfig)
                    ? $displayProfile->getSetting('authServerWhitelist')
                    : $display->getSetting('authServerWhitelist'),
                $sanitizedParams->getString('authServerWhitelist'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'authServerWhitelist',
                $sanitizedParams->getString('authServerWhitelist'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('edgeBrowserWhitelist')) {
            $this->handleChangedSettings(
                'edgeBrowserWhitelist',
                ($ownConfig)
                    ? $displayProfile->getSetting('edgeBrowserWhitelist')
                    : $display->getSetting('edgeBrowserWhitelist'),
                $sanitizedParams->getString('edgeBrowserWhitelist'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'edgeBrowserWhitelist',
                $sanitizedParams->getString('edgeBrowserWhitelist'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('embeddedServerAllowWan')) {
            $this->handleChangedSettings(
                'embeddedServerAllowWan',
                ($ownConfig)
                    ? $displayProfile->getSetting('embeddedServerAllowWan')
                    : $display->getSetting('embeddedServerAllowWan'),
                $sanitizedParams->getCheckbox('embeddedServerAllowWan'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'embeddedServerAllowWan',
                $sanitizedParams->getCheckbox('embeddedServerAllowWan'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('isRecordGeoLocationOnProofOfPlay')) {
            $this->handleChangedSettings(
                'isRecordGeoLocationOnProofOfPlay',
                ($ownConfig)
                    ? $displayProfile->getSetting('isRecordGeoLocationOnProofOfPlay')
                    : $display->getSetting('isRecordGeoLocationOnProofOfPlay'),
                $sanitizedParams->getCheckbox('isRecordGeoLocationOnProofOfPlay'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'isRecordGeoLocationOnProofOfPlay',
                $sanitizedParams->getCheckbox('isRecordGeoLocationOnProofOfPlay'),
                $ownConfig,
                $config
            );
        }
    }

    private function applyLinuxConfigFields(
        $displayProfile,
        SanitizerInterface $sanitizedParams,
        ?array &$config,
        $display,
        array &$changedSettings,
        bool $ownConfig
    ): void {
        if ($sanitizedParams->hasParam('collectInterval')) {
            $this->handleChangedSettings(
                'collectInterval',
                ($ownConfig)
                    ? $displayProfile->getSetting('collectInterval')
                    : $display->getSetting('collectInterval'),
                $sanitizedParams->getInt('collectInterval'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'collectInterval',
                $sanitizedParams->getInt('collectInterval'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('downloadStartWindow')) {
            $this->handleChangedSettings(
                'downloadStartWindow',
                ($ownConfig)
                    ? $displayProfile->getSetting('downloadStartWindow')
                    : $display->getSetting('downloadStartWindow'),
                $sanitizedParams->getString('downloadStartWindow'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'downloadStartWindow',
                $sanitizedParams->getString('downloadStartWindow'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('downloadEndWindow')) {
            $this->handleChangedSettings(
                'downloadEndWindow',
                ($ownConfig)
                    ? $displayProfile->getSetting('downloadEndWindow')
                    : $display->getSetting('downloadEndWindow'),
                $sanitizedParams->getString('downloadEndWindow'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'downloadEndWindow',
                $sanitizedParams->getString('downloadEndWindow'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('dayPartId')) {
            $this->handleChangedSettings(
                'dayPartId',
                ($ownConfig)
                    ? $displayProfile->getSetting('dayPartId')
                    : $display->getSetting('dayPartId'),
                $sanitizedParams->getInt('dayPartId'),
                $changedSettings
            );
            $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
        }

        if ($sanitizedParams->hasParam('xmrNetworkAddress')) {
            $this->handleChangedSettings(
                'xmrNetworkAddress',
                ($ownConfig)
                    ? $displayProfile->getSetting('xmrNetworkAddress')
                    : $display->getSetting('xmrNetworkAddress'),
                $sanitizedParams->getString('xmrNetworkAddress'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'xmrNetworkAddress',
                $sanitizedParams->getString('xmrNetworkAddress'),
                $ownConfig,
                $config
            );
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
            $this->handleChangedSettings(
                'statsEnabled',
                ($ownConfig)
                    ? $displayProfile->getSetting('statsEnabled')
                    : $display->getSetting('statsEnabled'),
                $sanitizedParams->getCheckbox('statsEnabled'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'statsEnabled',
                $sanitizedParams->getCheckbox('statsEnabled'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('aggregationLevel')) {
            $this->handleChangedSettings(
                'aggregationLevel',
                ($ownConfig)
                    ? $displayProfile->getSetting('aggregationLevel')
                    : $display->getSetting('aggregationLevel'),
                $sanitizedParams->getString('aggregationLevel'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'aggregationLevel',
                $sanitizedParams->getString('aggregationLevel'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('sizeX')) {
            $this->handleChangedSettings(
                'sizeX',
                ($ownConfig) ? $displayProfile->getSetting('sizeX') : $display->getSetting('sizeX'),
                $sanitizedParams->getDouble('sizeX'),
                $changedSettings
            );
            $displayProfile->setSetting('sizeX', $sanitizedParams->getDouble('sizeX'), $ownConfig, $config);
        }

        if ($sanitizedParams->hasParam('sizeY')) {
            $this->handleChangedSettings(
                'sizeY',
                ($ownConfig) ? $displayProfile->getSetting('sizeY') : $display->getSetting('sizeY'),
                $sanitizedParams->getDouble('sizeY'),
                $changedSettings
            );
            $displayProfile->setSetting('sizeY', $sanitizedParams->getDouble('sizeY'), $ownConfig, $config);
        }

        if ($sanitizedParams->hasParam('offsetX')) {
            $this->handleChangedSettings(
                'offsetX',
                ($ownConfig) ? $displayProfile->getSetting('offsetX') : $display->getSetting('offsetX'),
                $sanitizedParams->getDouble('offsetX'),
                $changedSettings
            );
            $displayProfile->setSetting('offsetX', $sanitizedParams->getDouble('offsetX'), $ownConfig, $config);
        }

        if ($sanitizedParams->hasParam('offsetY')) {
            $this->handleChangedSettings(
                'offsetY',
                ($ownConfig) ? $displayProfile->getSetting('offsetY') : $display->getSetting('offsetY'),
                $sanitizedParams->getDouble('offsetY'),
                $changedSettings
            );
            $displayProfile->setSetting('offsetY', $sanitizedParams->getDouble('offsetY'), $ownConfig, $config);
        }

        if ($sanitizedParams->hasParam('logLevel')) {
            $this->handleChangedSettings(
                'logLevel',
                ($ownConfig)
                    ? $displayProfile->getSetting('logLevel')
                    : $display->getSetting('logLevel'),
                $sanitizedParams->getString('logLevel'),
                $changedSettings
            );
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
            $this->handleChangedSettings(
                'enableShellCommands',
                ($ownConfig)
                    ? $displayProfile->getSetting('enableShellCommands')
                    : $display->getSetting('enableShellCommands'),
                $sanitizedParams->getCheckbox('enableShellCommands'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'enableShellCommands',
                $sanitizedParams->getCheckbox('enableShellCommands'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('expireModifiedLayouts')) {
            $this->handleChangedSettings(
                'expireModifiedLayouts',
                ($ownConfig)
                    ? $displayProfile->getSetting('expireModifiedLayouts')
                    : $display->getSetting('expireModifiedLayouts'),
                $sanitizedParams->getCheckbox('expireModifiedLayouts'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'expireModifiedLayouts',
                $sanitizedParams->getCheckbox('expireModifiedLayouts'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('maxConcurrentDownloads')) {
            $this->handleChangedSettings(
                'maxConcurrentDownloads',
                ($ownConfig)
                    ? $displayProfile->getSetting('maxConcurrentDownloads')
                    : $display->getSetting('maxConcurrentDownloads'),
                $sanitizedParams->getInt('maxConcurrentDownloads'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'maxConcurrentDownloads',
                $sanitizedParams->getInt('maxConcurrentDownloads'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('shellCommandAllowList')) {
            $this->handleChangedSettings(
                'shellCommandAllowList',
                ($ownConfig)
                    ? $displayProfile->getSetting('shellCommandAllowList')
                    : $display->getSetting('shellCommandAllowList'),
                $sanitizedParams->getString('shellCommandAllowList'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'shellCommandAllowList',
                $sanitizedParams->getString('shellCommandAllowList'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('sendCurrentLayoutAsStatusUpdate')) {
            $this->handleChangedSettings(
                'sendCurrentLayoutAsStatusUpdate',
                ($ownConfig)
                    ? $displayProfile->getSetting('sendCurrentLayoutAsStatusUpdate')
                    : $display->getSetting('sendCurrentLayoutAsStatusUpdate'),
                $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'sendCurrentLayoutAsStatusUpdate',
                $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('screenShotRequestInterval')) {
            $this->handleChangedSettings(
                'screenShotRequestInterval',
                ($ownConfig)
                    ? $displayProfile->getSetting('screenShotRequestInterval')
                    : $display->getSetting('screenShotRequestInterval'),
                $sanitizedParams->getInt('screenShotRequestInterval'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'screenShotRequestInterval',
                $sanitizedParams->getInt('screenShotRequestInterval'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('screenShotSize')) {
            $this->handleChangedSettings(
                'screenShotSize',
                ($ownConfig)
                    ? $displayProfile->getSetting('screenShotSize')
                    : $display->getSetting('screenShotSize'),
                $sanitizedParams->getInt('screenShotSize'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'screenShotSize',
                $sanitizedParams->getInt('screenShotSize'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('maxLogFileUploads')) {
            $this->handleChangedSettings(
                'maxLogFileUploads',
                ($ownConfig)
                    ? $displayProfile->getSetting('maxLogFileUploads')
                    : $display->getSetting('maxLogFileUploads'),
                $sanitizedParams->getInt('maxLogFileUploads'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'maxLogFileUploads',
                $sanitizedParams->getInt('maxLogFileUploads'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('embeddedServerPort')) {
            $this->handleChangedSettings(
                'embeddedServerPort',
                ($ownConfig)
                    ? $displayProfile->getSetting('embeddedServerPort')
                    : $display->getSetting('embeddedServerPort'),
                $sanitizedParams->getInt('embeddedServerPort'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'embeddedServerPort',
                $sanitizedParams->getInt('embeddedServerPort'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('preventSleep')) {
            $this->handleChangedSettings(
                'preventSleep',
                ($ownConfig)
                    ? $displayProfile->getSetting('preventSleep')
                    : $display->getSetting('preventSleep'),
                $sanitizedParams->getCheckbox('preventSleep'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'preventSleep',
                $sanitizedParams->getCheckbox('preventSleep'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('forceHttps')) {
            $this->handleChangedSettings(
                'forceHttps',
                ($ownConfig)
                    ? $displayProfile->getSetting('forceHttps')
                    : $display->getSetting('forceHttps'),
                $sanitizedParams->getCheckbox('forceHttps'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'forceHttps',
                $sanitizedParams->getCheckbox('forceHttps'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('embeddedServerAllowWan')) {
            $this->handleChangedSettings(
                'embeddedServerAllowWan',
                ($ownConfig)
                    ? $displayProfile->getSetting('embeddedServerAllowWan')
                    : $display->getSetting('embeddedServerAllowWan'),
                $sanitizedParams->getCheckbox('embeddedServerAllowWan'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'embeddedServerAllowWan',
                $sanitizedParams->getCheckbox('embeddedServerAllowWan'),
                $ownConfig,
                $config
            );
        }
    }

    private function applyLgConfigFields(
        $displayProfile,
        SanitizerInterface $sanitizedParams,
        ?array &$config,
        $display,
        array &$changedSettings,
        bool $ownConfig
    ): void {
        if ($sanitizedParams->hasParam('emailAddress')) {
            $this->handleChangedSettings(
                'emailAddress',
                ($ownConfig)
                    ? $displayProfile->getSetting('emailAddress')
                    : $display->getSetting('emailAddress'),
                $sanitizedParams->getString('emailAddress'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'emailAddress',
                $sanitizedParams->getString('emailAddress'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('collectInterval')) {
            $this->handleChangedSettings(
                'collectInterval',
                ($ownConfig)
                    ? $displayProfile->getSetting('collectInterval')
                    : $display->getSetting('collectInterval'),
                $sanitizedParams->getInt('collectInterval'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'collectInterval',
                $sanitizedParams->getInt('collectInterval'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('downloadStartWindow')) {
            $this->handleChangedSettings(
                'downloadStartWindow',
                ($ownConfig)
                    ? $displayProfile->getSetting('downloadStartWindow')
                    : $display->getSetting('downloadStartWindow'),
                $sanitizedParams->getString('downloadStartWindow'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'downloadStartWindow',
                $sanitizedParams->getString('downloadStartWindow'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('downloadEndWindow')) {
            $this->handleChangedSettings(
                'downloadEndWindow',
                ($ownConfig)
                    ? $displayProfile->getSetting('downloadEndWindow')
                    : $display->getSetting('downloadEndWindow'),
                $sanitizedParams->getString('downloadEndWindow'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'downloadEndWindow',
                $sanitizedParams->getString('downloadEndWindow'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('updateStartWindow')) {
            $this->handleChangedSettings(
                'updateStartWindow',
                ($ownConfig)
                    ? $displayProfile->getSetting('updateStartWindow')
                    : $display->getSetting('updateStartWindow'),
                $sanitizedParams->getString('updateStartWindow'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'updateStartWindow',
                $sanitizedParams->getString('updateStartWindow'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('updateEndWindow')) {
            $this->handleChangedSettings(
                'updateEndWindow',
                ($ownConfig)
                    ? $displayProfile->getSetting('updateEndWindow')
                    : $display->getSetting('updateEndWindow'),
                $sanitizedParams->getString('updateEndWindow'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'updateEndWindow',
                $sanitizedParams->getString('updateEndWindow'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('dayPartId')) {
            $this->handleChangedSettings(
                'dayPartId',
                ($ownConfig)
                    ? $displayProfile->getSetting('dayPartId')
                    : $display->getSetting('dayPartId'),
                $sanitizedParams->getInt('dayPartId'),
                $changedSettings
            );
            $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
        }

        if ($sanitizedParams->hasParam('xmrNetworkAddress')) {
            $this->handleChangedSettings(
                'xmrNetworkAddress',
                ($ownConfig)
                    ? $displayProfile->getSetting('xmrNetworkAddress')
                    : $display->getSetting('xmrNetworkAddress'),
                $sanitizedParams->getString('xmrNetworkAddress'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'xmrNetworkAddress',
                $sanitizedParams->getString('xmrNetworkAddress'),
                $ownConfig,
                $config
            );
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
            $this->handleChangedSettings(
                'statsEnabled',
                ($ownConfig)
                    ? $displayProfile->getSetting('statsEnabled')
                    : $display->getSetting('statsEnabled'),
                $sanitizedParams->getCheckbox('statsEnabled'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'statsEnabled',
                $sanitizedParams->getCheckbox('statsEnabled'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('aggregationLevel')) {
            $this->handleChangedSettings(
                'aggregationLevel',
                ($ownConfig)
                    ? $displayProfile->getSetting('aggregationLevel')
                    : $display->getSetting('aggregationLevel'),
                $sanitizedParams->getString('aggregationLevel'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'aggregationLevel',
                $sanitizedParams->getString('aggregationLevel'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('orientation')) {
            $this->handleChangedSettings(
                'orientation',
                ($ownConfig)
                    ? $displayProfile->getSetting('orientation')
                    : $display->getSetting('orientation'),
                $sanitizedParams->getInt('orientation'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'orientation',
                $sanitizedParams->getInt('orientation'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('logLevel')) {
            $this->handleChangedSettings(
                'logLevel',
                ($ownConfig)
                    ? $displayProfile->getSetting('logLevel')
                    : $display->getSetting('logLevel'),
                $sanitizedParams->getString('logLevel'),
                $changedSettings
            );
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
            $this->handleChangedSettings(
                'versionMediaId',
                ($ownConfig)
                    ? $displayProfile->getSetting('versionMediaId')
                    : $display->getSetting('versionMediaId'),
                $sanitizedParams->getInt('versionMediaId'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'versionMediaId',
                $sanitizedParams->getInt('versionMediaId'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('actionBarMode')) {
            $this->handleChangedSettings(
                'actionBarMode',
                ($ownConfig)
                    ? $displayProfile->getSetting('actionBarMode')
                    : $display->getSetting('actionBarMode'),
                $sanitizedParams->getInt('actionBarMode'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'actionBarMode',
                $sanitizedParams->getInt('actionBarMode'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('actionBarDisplayDuration')) {
            $this->handleChangedSettings(
                'actionBarDisplayDuration',
                ($ownConfig)
                    ? $displayProfile->getSetting('actionBarDisplayDuration')
                    : $display->getSetting('actionBarDisplayDuration'),
                $sanitizedParams->getInt('actionBarDisplayDuration'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'actionBarDisplayDuration',
                $sanitizedParams->getInt('actionBarDisplayDuration'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('sendCurrentLayoutAsStatusUpdate')) {
            $this->handleChangedSettings(
                'sendCurrentLayoutAsStatusUpdate',
                ($ownConfig)
                    ? $displayProfile->getSetting('sendCurrentLayoutAsStatusUpdate')
                    : $display->getSetting('sendCurrentLayoutAsStatusUpdate'),
                $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'sendCurrentLayoutAsStatusUpdate',
                $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('screenShotSize')) {
            $this->handleChangedSettings(
                'screenShotSize',
                ($ownConfig)
                    ? $displayProfile->getSetting('screenShotSize')
                    : $display->getSetting('screenShotSize'),
                $sanitizedParams->getInt('screenShotSize'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'screenShotSize',
                $sanitizedParams->getInt('screenShotSize'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('mediaInventoryTimer')) {
            $this->handleChangedSettings(
                'mediaInventoryTimer',
                ($ownConfig)
                    ? $displayProfile->getSetting('mediaInventoryTimer')
                    : $display->getSetting('mediaInventoryTimer'),
                $sanitizedParams->getInt('mediaInventoryTimer'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'mediaInventoryTimer',
                $sanitizedParams->getInt('mediaInventoryTimer'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('forceHttps')) {
            $this->handleChangedSettings(
                'forceHttps',
                ($ownConfig)
                    ? $displayProfile->getSetting('forceHttps')
                    : $display->getSetting('forceHttps'),
                $sanitizedParams->getCheckbox('forceHttps'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'forceHttps',
                $sanitizedParams->getCheckbox('forceHttps'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('serverPort')) {
            $this->handleChangedSettings(
                'serverPort',
                ($ownConfig)
                    ? $displayProfile->getSetting('serverPort')
                    : $display->getSetting('serverPort'),
                $sanitizedParams->getInt('serverPort'),
                $changedSettings
            );
            $displayProfile->setSetting('serverPort', $sanitizedParams->getInt('serverPort'), $ownConfig, $config);
        }

        if ($sanitizedParams->hasParam('embeddedServerAllowWan')) {
            $this->handleChangedSettings(
                'embeddedServerAllowWan',
                ($ownConfig)
                    ? $displayProfile->getSetting('embeddedServerAllowWan')
                    : $display->getSetting('embeddedServerAllowWan'),
                $sanitizedParams->getCheckbox('embeddedServerAllowWan'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'embeddedServerAllowWan',
                $sanitizedParams->getCheckbox('embeddedServerAllowWan'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('screenShotRequestInterval')) {
            $this->handleChangedSettings(
                'screenShotRequestInterval',
                ($ownConfig)
                    ? $displayProfile->getSetting('screenShotRequestInterval')
                    : $display->getSetting('screenShotRequestInterval'),
                $sanitizedParams->getInt('screenShotRequestInterval'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'screenShotRequestInterval',
                $sanitizedParams->getInt('screenShotRequestInterval'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('disableTimerManagement')) {
            $this->handleChangedSettings(
                'disableTimerManagement',
                ($ownConfig)
                    ? $displayProfile->getSetting('disableTimerManagement')
                    : $display->getSetting('disableTimerManagement'),
                $sanitizedParams->getCheckbox('disableTimerManagement'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'disableTimerManagement',
                $sanitizedParams->getCheckbox('disableTimerManagement'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('timers')) {
            $timerOptions = (object)[];
            $timers = $sanitizedParams->getArray('timers');

            foreach ($timers as $timer) {
                $timerDay = $timer['day'];

                if (sizeof($timers) == 1 && $timerDay == '') {
                    break;
                } elseif ($timerDay == '' || property_exists($timerOptions, $timerDay)) {
                    throw new InvalidArgumentException(
                        __('On/Off Timers: Please check the days selected and remove the duplicates or empty'),
                        'timers'
                    );
                } else {
                    $timerOn = $timer['on'];
                    $timerOff = $timer['off'];

                    if (strlen($timerOn) != 5 || strlen($timerOff) != 5) {
                        throw new InvalidArgumentException(
                            __(
                                'On/Off Timers: Please enter a on and off date for any'
                                . ' row with a day selected, or remove that row'
                            ),
                            'timers'
                        );
                    }

                    $temp = [];
                    $temp['on'] = $timerOn;
                    $temp['off'] = $timerOff;
                    $timerOptions->$timerDay = $temp;
                }
            }

            $this->handleChangedSettings(
                'timers',
                ($ownConfig) ? $displayProfile->getSetting('timers') : $display->getSetting('timers'),
                json_encode($timerOptions),
                $changedSettings
            );
            $displayProfile->setSetting('timers', json_encode($timerOptions), $ownConfig, $config);
        }

        if ($sanitizedParams->hasParam('pictureControls')) {
            $pictureControlsOptions = (object)[];

            $specialProperties = (object)[];
            $specialProperties->dynamicContrast = ['off', 'low', 'medium', 'high'];
            $specialProperties->superResolution = ['off', 'low', 'medium', 'high'];
            $specialProperties->colorGamut = ['normal', 'extended'];
            $specialProperties->dynamicColor = ['off', 'low', 'medium', 'high'];
            $specialProperties->noiseReduction = ['auto', 'off', 'low', 'medium', 'high'];
            $specialProperties->mpegNoiseReduction = ['auto', 'off', 'low', 'medium', 'high'];
            $specialProperties->blackLevel = ['low', 'high'];
            $specialProperties->gamma = ['low', 'medium', 'high', 'high2'];

            $pictureControls = $sanitizedParams->getArray('pictureControls');

            foreach ($pictureControls as $pictureControl) {
                $propertyName = $pictureControl['property'];

                if (sizeof($pictureControls) == 1 && $propertyName == '') {
                    break;
                } elseif ($propertyName == '' || property_exists($pictureControlsOptions, $propertyName)) {
                    throw new InvalidArgumentException(
                        __('Picture: Please check the settings selected and remove the duplicates or empty'),
                        'pictureOptions'
                    );
                } else {
                    $propertyValue = $pictureControl['value'];

                    if (property_exists($specialProperties, $propertyName)) {
                        $val = $specialProperties->$propertyName[$propertyValue];
                        $pictureControlsOptions->$propertyName = $val;
                    } else {
                        $pictureControlsOptions->$propertyName = (int)$propertyValue;
                    }
                }
            }

            $this->handleChangedSettings(
                'pictureOptions',
                ($ownConfig)
                    ? $displayProfile->getSetting('pictureOptions')
                    : $display->getSetting('pictureOptions'),
                json_encode($pictureControlsOptions),
                $changedSettings
            );
            $displayProfile->setSetting(
                'pictureOptions',
                json_encode($pictureControlsOptions),
                $ownConfig,
                $config
            );
        }

        $usblock = $sanitizedParams->getString('usblock', ['default' => 'empty']);
        $osdlock = $sanitizedParams->getString('osdlock', ['default' => 'empty']);
        $keylockLocal = $sanitizedParams->getString('keylockLocal', ['default' => '']);
        $keylockRemote = $sanitizedParams->getString('keylockRemote', ['default' => '']);

        $lockOptions = (object)[];

        if ($usblock != 'empty' && $displayProfile->type == 'lg') {
            $lockOptions->usblock = $usblock === 'true' ? true : false;
        }

        if ($osdlock != 'empty') {
            $lockOptions->osdlock = $osdlock === 'true' ? true : false;
        }

        if ($keylockLocal != '' || $keylockRemote != '') {
            $lockOptions->keylock = (object)[];

            if ($keylockLocal != '') {
                $lockOptions->keylock->local = $keylockLocal;
            }

            if ($keylockRemote != '') {
                $lockOptions->keylock->remote = $keylockRemote;
            }
        }

        $this->handleChangedSettings(
            'lockOptions',
            ($ownConfig) ? $displayProfile->getSetting('lockOptions') : $display->getSetting('lockOptions'),
            json_encode($lockOptions),
            $changedSettings
        );
        $displayProfile->setSetting('lockOptions', json_encode($lockOptions), $ownConfig, $config);

        if ($sanitizedParams->hasParam('isUseMultipleVideoDecoders')) {
            $this->handleChangedSettings(
                'isUseMultipleVideoDecoders',
                ($ownConfig)
                    ? $displayProfile->getSetting('isUseMultipleVideoDecoders')
                    : $display->getSetting('isUseMultipleVideoDecoders'),
                $sanitizedParams->getString('isUseMultipleVideoDecoders'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'isUseMultipleVideoDecoders',
                $sanitizedParams->getString('isUseMultipleVideoDecoders'),
                $ownConfig,
                $config
            );
        }
    }

    private function applyHisenseConfigFields(
        $displayProfile,
        SanitizerInterface $sanitizedParams,
        ?array &$config,
        $display,
        array &$changedSettings,
        bool $ownConfig
    ): void {
        // All android settings apply to Hisense
        $this->applyAndroidConfigFields(
            $displayProfile,
            $sanitizedParams,
            $config,
            $display,
            $changedSettings,
            $ownConfig
        );

        // Hisense-exclusive integer picture settings with per-setting range validation
        $intSettings = [
            'brightness'        => ['min' => 0, 'max' => 100],
            'contrast'          => ['min' => 0, 'max' => 100],
            'backlight'         => ['min' => 0, 'max' => 100],
            'saturation'        => ['min' => 0, 'max' => 100],
            'colourTemperature' => ['min' => 0, 'max' => 100],
            'gammaMode'         => ['min' => 0, 'max' => 2],
            'dynamicContrast'   => ['min' => 0, 'max' => 1],
        ];

        foreach ($intSettings as $intSetting => $range) {
            if ($sanitizedParams->hasParam($intSetting)) {
                $value = $sanitizedParams->getInt($intSetting);
                if ($value !== null && ($value < $range['min'] || $value > $range['max'])) {
                    throw new InvalidArgumentException(
                        sprintf(
                            __('%s must be between %d and %d'),
                            $intSetting,
                            $range['min'],
                            $range['max']
                        ),
                        $intSetting
                    );
                }
                $this->handleChangedSettings(
                    $intSetting,
                    ($ownConfig)
                        ? $displayProfile->getSetting($intSetting)
                        : $display->getSetting($intSetting),
                    $value,
                    $changedSettings
                );
                $displayProfile->setSetting($intSetting, $value, $ownConfig, $config);
            }
        }

        // Hisense on/off timers — index-keyed rule slots (0-2 = power-on, 3-5 = power-off).
        // React submits time as "HH:MM"; we split into separate hour/minute integers for the API.
        if ($sanitizedParams->hasParam('timers')) {
            $rawRules = $sanitizedParams->getArray('timers');

            if (empty($rawRules)) {
                // Empty submission: disable CMS timer management (store null)
                $displayProfile->setSetting('timers', null, $ownConfig, $config);
            } else {
                $validTypes = [0, 2, 3, 4, 5, 6];
                $seenIndices = [];
                $rules = [];

                foreach ($rawRules as $rule) {
                    $index = (int)($rule['index'] ?? -1);
                    $type  = (int)($rule['type'] ?? 0);

                    // Skip type-0 (off) entries — absent slots are implicitly off
                    if ($type === 0) {
                        continue;
                    }

                    if ($index < 0 || $index > 5 || in_array($index, $seenIndices)) {
                        throw new InvalidArgumentException(
                            __('Timers: each rule must have a unique slot index (0–5)'),
                            'timers'
                        );
                    }

                    if (!in_array($type, $validTypes)) {
                        throw new InvalidArgumentException(
                            __('Timers: invalid type value'),
                            'timers'
                        );
                    }

                    // Parse HH:MM into separate hour/minute integers
                    $time = $rule['time'] ?? '';
                    if (strlen($time) !== 5 || $time[2] !== ':') {
                        throw new InvalidArgumentException(
                            __('Timers: time must be in HH:MM format'),
                            'timers'
                        );
                    }
                    [$hourStr, $minuteStr] = explode(':', $time);
                    $hour = (int)$hourStr;
                    $minute = (int)$minuteStr;
                    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
                        throw new InvalidArgumentException(
                            __('Timers: time values out of range (hour 0–23, minute 0–59)'),
                            'timers'
                        );
                    }

                    $entry = [
                        'index'      => $index,
                        'type'       => $type,
                        'hour'       => $hour,
                        'minute'     => $minute,
                        'isPowerOff' => $index >= 3,
                    ];

                    if ($type === 6) {
                        $manualWeeks = array_map('intval', (array)($rule['manualWeeks'] ?? []));
                        if (empty($manualWeeks)) {
                            throw new InvalidArgumentException(
                                __('Timers: manual day selection requires at least one day'),
                                'timers'
                            );
                        }
                        foreach ($manualWeeks as $day) {
                            if ($day < 0 || $day > 6) {
                                throw new InvalidArgumentException(
                                    __('Timers: manual day index must be 0 (Sun) – 6 (Sat)'),
                                    'timers'
                                );
                            }
                        }
                        $entry['manualWeeks'] = $manualWeeks;
                    }

                    $seenIndices[] = $index;
                    $rules[] = $entry;
                }

                usort($rules, fn($a, $b) => $a['index'] <=> $b['index']);

                $encoded = json_encode($rules);
                $this->handleChangedSettings(
                    'timers',
                    ($ownConfig) ? $displayProfile->getSetting('timers') : $display->getSetting('timers'),
                    $encoded,
                    $changedSettings
                );
                $displayProfile->setSetting('timers', $encoded, $ownConfig, $config);
            }
        }
    }

    private function applyChromeOsConfigFields(
        $displayProfile,
        SanitizerInterface $sanitizedParams,
        ?array &$config,
        $display,
        array &$changedSettings,
        bool $ownConfig
    ): void {
        if ($sanitizedParams->hasParam('licenceCode')) {
            $this->handleChangedSettings(
                'licenceCode',
                ($ownConfig)
                    ? $displayProfile->getSetting('licenceCode')
                    : $display->getSetting('licenceCode'),
                $sanitizedParams->getString('licenceCode'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'licenceCode',
                $sanitizedParams->getString('licenceCode'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('collectInterval')) {
            $this->handleChangedSettings(
                'collectInterval',
                ($ownConfig)
                    ? $displayProfile->getSetting('collectInterval')
                    : $display->getSetting('collectInterval'),
                $sanitizedParams->getInt('collectInterval'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'collectInterval',
                $sanitizedParams->getInt('collectInterval'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('dayPartId')) {
            $this->handleChangedSettings(
                'dayPartId',
                ($ownConfig)
                    ? $displayProfile->getSetting('dayPartId')
                    : $display->getSetting('dayPartId'),
                $sanitizedParams->getInt('dayPartId'),
                $changedSettings
            );
            $displayProfile->setSetting('dayPartId', $sanitizedParams->getInt('dayPartId'), $ownConfig, $config);
        }

        if ($sanitizedParams->hasParam('xmrNetworkAddress')) {
            $this->handleChangedSettings(
                'xmrNetworkAddress',
                ($ownConfig)
                    ? $displayProfile->getSetting('xmrNetworkAddress')
                    : $display->getSetting('xmrNetworkAddress'),
                $sanitizedParams->getString('xmrNetworkAddress'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'xmrNetworkAddress',
                $sanitizedParams->getString('xmrNetworkAddress'),
                $ownConfig,
                $config
            );
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
            $this->handleChangedSettings(
                'statsEnabled',
                ($ownConfig)
                    ? $displayProfile->getSetting('statsEnabled')
                    : $display->getSetting('statsEnabled'),
                $sanitizedParams->getCheckbox('statsEnabled'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'statsEnabled',
                $sanitizedParams->getCheckbox('statsEnabled'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('aggregationLevel')) {
            $this->handleChangedSettings(
                'aggregationLevel',
                ($ownConfig)
                    ? $displayProfile->getSetting('aggregationLevel')
                    : $display->getSetting('aggregationLevel'),
                $sanitizedParams->getString('aggregationLevel'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'aggregationLevel',
                $sanitizedParams->getString('aggregationLevel'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('logLevel')) {
            $this->handleChangedSettings(
                'logLevel',
                ($ownConfig)
                    ? $displayProfile->getSetting('logLevel')
                    : $display->getSetting('logLevel'),
                $sanitizedParams->getString('logLevel'),
                $changedSettings
            );
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

        if ($sanitizedParams->hasParam('sendCurrentLayoutAsStatusUpdate')) {
            $this->handleChangedSettings(
                'sendCurrentLayoutAsStatusUpdate',
                ($ownConfig)
                    ? $displayProfile->getSetting('sendCurrentLayoutAsStatusUpdate')
                    : $display->getSetting('sendCurrentLayoutAsStatusUpdate'),
                $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'sendCurrentLayoutAsStatusUpdate',
                $sanitizedParams->getCheckbox('sendCurrentLayoutAsStatusUpdate'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('playerVersionId')) {
            $this->handleChangedSettings(
                'playerVersionId',
                ($ownConfig)
                    ? $displayProfile->getSetting('playerVersionId')
                    : $display->getSetting('playerVersionId'),
                $sanitizedParams->getInt('playerVersionId'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'playerVersionId',
                $sanitizedParams->getInt('playerVersionId'),
                $ownConfig,
                $config
            );
        }

        if ($sanitizedParams->hasParam('screenShotSize')) {
            $this->handleChangedSettings(
                'screenShotSize',
                ($ownConfig)
                    ? $displayProfile->getSetting('screenShotSize')
                    : $display->getSetting('screenShotSize'),
                $sanitizedParams->getInt('screenShotSize'),
                $changedSettings
            );
            $displayProfile->setSetting(
                'screenShotSize',
                $sanitizedParams->getInt('screenShotSize'),
                $ownConfig,
                $config
            );
        }
    }
}
