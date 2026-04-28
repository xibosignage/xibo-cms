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

import type {TFunction} from 'i18next';

import type {DisplayProfileType} from '@/types/displayProfile';

export const CHECKBOX_FIELDS_BY_TYPE: Record<DisplayProfileType, Set<string>> = {
  android: new Set([
    'statsEnabled',
    'isRecordGeoLocationOnProofOfPlay',
    'forceHttps',
    'restartWifiOnConnectionFailure',
    'blacklistVideo',
    'storeHtmlOnInternal',
    'useSurfaceVideoView',
    'startOnBoot',
    'autoRestart',
    'sendCurrentLayoutAsStatusUpdate',
    'expireModifiedLayouts',
    'timeSyncFromCms',
    'webCacheEnabled',
    'embeddedServerAllowWan',
    'installWithLoadedLinkLibraries',
    'isTouchEnabled',
  ]),
  windows: new Set([
    'statsEnabled',
    'isRecordGeoLocationOnProofOfPlay',
    'powerpointEnabled',
    'forceHttps',
    'clientInfomationCtrlKey',
    'showInTaskbar',
    'doubleBuffering',
    'enableMouse',
    'enableShellCommands',
    'sendCurrentLayoutAsStatusUpdate',
    'expireModifiedLayouts',
    'timeSyncFromCms',
    'embeddedServerAllowWan',
    'preventSleep',
  ]),
  linux: new Set([
    'statsEnabled',
    'isRecordGeoLocationOnProofOfPlay',
    'forceHttps',
    'expireModifiedLayouts',
    'enableShellCommands',
    'sendCurrentLayoutAsStatusUpdate',
    'preventSleep',
    'timeSyncFromCms',
  ]),
  lg: new Set([
    'statsEnabled',
    'isRecordGeoLocationOnProofOfPlay',
    'forceHttps',
    'embeddedServerAllowWan',
    'sendCurrentLayoutAsStatusUpdate',
    'disableTimerManagement',
  ]),
  sssp: new Set([
    'statsEnabled',
    'isRecordGeoLocationOnProofOfPlay',
    'forceHttps',
    'embeddedServerAllowWan',
    'sendCurrentLayoutAsStatusUpdate',
    'disableTimerManagement',
  ]),
  chromeOS: new Set([
    'statsEnabled',
    'isRecordGeoLocationOnProofOfPlay',
    'sendCurrentLayoutAsStatusUpdate',
  ]),
};

export type FieldInputType =
  | 'checkbox'
  | 'datepicker'
  | 'daypart'
  | 'dropdown'
  | 'number'
  | 'player-version'
  | 'text'
  | 'time'
  | 'timers'
  | 'picture-options'
  | 'lock-options';

export type ProfileTab =
  | 'general'
  | 'network'
  | 'location'
  | 'troubleshooting'
  | 'timers'
  | 'pictureOptions'
  | 'lockSettings'
  | 'advanced';

export interface FieldMeta {
  label: string;
  tab: ProfileTab;
  helpText?: string;
  inputType: FieldInputType;
  options?: Array<{ value: string; label: string }>;
}

export type FieldMetaMap = Record<string, FieldMeta>;

function commonMeta(t: TFunction): FieldMetaMap {
  return {
    collectInterval: {
      label: t('Collect interval'),
      tab: 'general',
      helpText: t('How often should the Player check for new content.'),
      inputType: 'dropdown',
      options: [
        { value: '60', label: t('1 minute') },
        { value: '300', label: t('5 minutes') },
        { value: '600', label: t('10 minutes') },
        { value: '1800', label: t('30 minutes') },
        { value: '3600', label: t('1 hour') },
        { value: '5400', label: t('1 hour 30 minutes') },
        { value: '7200', label: t('2 hours') },
        { value: '9000', label: t('2 hours 30 minutes') },
        { value: '10800', label: t('3 hours') },
        { value: '12600', label: t('3 hours 30 minutes') },
        { value: '14400', label: t('4 hours') },
        { value: '18000', label: t('5 hours') },
        { value: '21600', label: t('6 hours') },
        { value: '25200', label: t('7 hours') },
        { value: '28800', label: t('8 hours') },
        { value: '32400', label: t('9 hours') },
        { value: '36000', label: t('10 hours') },
        { value: '39600', label: t('11 hours') },
        { value: '43200', label: t('12 hours') },
        { value: '86400', label: t('24 hours') },
      ],
    },
    aggregationLevel: {
      label: t('Aggregation level'),
      tab: 'general',
      helpText: t(
        'Set the level of collection for Proof of Play Statistics to be applied to selected Layouts / Media and Widget items.',
      ),
      inputType: 'dropdown',
      options: [
        { value: 'Individual', label: t('Individual') },
        { value: 'Hourly', label: t('Hourly') },
        { value: 'Daily', label: t('Daily') },
      ],
    },
    statsEnabled: {
      label: t('Enable stats reporting?'),
      tab: 'general',
      helpText: t('Should the application send proof of play stats to the CMS.'),
      inputType: 'checkbox',
    },
    isRecordGeoLocationOnProofOfPlay: {
      label: t('Record geolocation on each Proof of Play?'),
      tab: 'general',
      helpText: t(
        'If the geolocation of the Display is known, enable to record that location against each proof of play record.',
      ),
      inputType: 'checkbox',
    },
    logLevel: {
      label: t('Log Level'),
      tab: 'troubleshooting',
      helpText: t('The resting logging level that should be recorded by the Player.'),
      inputType: 'dropdown',
      options: [
        { value: 'emergency', label: t('Emergency') },
        { value: 'alert', label: t('Alert') },
        { value: 'critical', label: t('Critical') },
        { value: 'error', label: t('Error') },
        { value: 'off', label: t('Off') },
      ],
    },
    elevateLogsUntil: {
      label: t('Elevate Logging until'),
      tab: 'troubleshooting',
      helpText: t(
        'Elevate log level for the specified time. Should only be used if there is a problem with the display.',
      ),
      inputType: 'datepicker',
    },
    forceHttps: {
      label: t('Force HTTPS?'),
      tab: 'network',
      helpText: t('Should Displays be forced to use HTTPS connection to the CMS?'),
      inputType: 'checkbox',
    },
    downloadStartWindow: {
      label: t('Download Window Start Time'),
      tab: 'network',
      helpText: t('The start of the time window to connect to the CMS and download updates.'),
      inputType: 'time',
    },
    downloadEndWindow: {
      label: t('Download Window End Time'),
      tab: 'network',
      helpText: t('The end of the time window to connect to the CMS and download updates.'),
      inputType: 'time',
    },
    xmrWebSocketAddress: {
      label: t('XMR WebSocket Address'),
      tab: 'general',
      helpText: t('Override the CMS WebSocket address for XMR.'),
      inputType: 'text',
    },
    xmrNetworkAddress: {
      label: t('XMR Public Address'),
      tab: 'general',
      helpText: t('Override the CMS public address for XMR.'),
      inputType: 'text',
    },
    sendCurrentLayoutAsStatusUpdate: {
      label: t('Notify current layout'),
      tab: 'advanced',
      helpText: t(
        'When enabled the Player will send the current layout to the CMS each time it changes. Warning: This is bandwidth intensive and should be disabled unless on a LAN.',
      ),
      inputType: 'checkbox',
    },
    expireModifiedLayouts: {
      label: t('Expire Modified Layouts?'),
      tab: 'advanced',
      helpText: t(
        'Expire Modified Layouts immediately on change. This means a layout can be cut during playback if it receives an update from the CMS',
      ),
      inputType: 'checkbox',
    },
    screenShotRequestInterval: {
      label: t('Screen shot interval'),
      tab: 'advanced',
      helpText: t(
        'The duration between status screen shots in minutes. 0 to disable. Warning: This is bandwidth intensive.',
      ),
      inputType: 'number',
    },
    screenShotSize: {
      label: t('Screen Shot Size'),
      tab: 'advanced',
      helpText: t('The size of the largest dimension. Empty or 0 means the screen size.'),
      inputType: 'number',
    },
    dayPartId: {
      label: t('Operating Hours'),
      tab: 'network',
      helpText: t(
        'Select a day part that should act as operating hours for this display - email alerts will not be sent outside of operating hours',
      ),
      inputType: 'daypart',
    },
    embeddedServerAllowWan: {
      label: t('Embedded Web Server allow WAN?'),
      tab: 'advanced',
      helpText: t(
        'Should we allow access to the Player Embedded Web Server from WAN? You may need to adjust the device firewall to allow external traffic',
      ),
      inputType: 'checkbox',
    },
    enableShellCommands: {
      label: t('Enable Shell Commands'),
      tab: 'advanced',
      helpText: t('Enable the Shell Command module for this Display Profile.'),
      inputType: 'checkbox',
    },
    maxConcurrentDownloads: {
      label: t('Max Concurrent Downloads'),
      tab: 'advanced',
      helpText: t('Set the maximum number of concurrent downloads on the Player.'),
      inputType: 'number',
    },
    shellCommandAllowList: {
      label: t('Shell Command Allow List'),
      tab: 'advanced',
      helpText: t('A comma separated list of Shell Commands to allow.'),
      inputType: 'text',
    },
    maxLogFileUploads: {
      label: t('Maximum Log File Uploads'),
      tab: 'advanced',
      helpText: t('Limit the number of log files that can be uploaded concurrently.'),
      inputType: 'number',
    },
    embeddedServerPort: {
      label: t('Embedded Web Server Port'),
      tab: 'advanced',
      helpText: t(
        'The port number to use for the embedded web server on the Player. Only change this if there is a port conflict reported on the status screen.',
      ),
      inputType: 'number',
    },
    preventSleep: {
      label: t('Prevent Sleep?'),
      tab: 'advanced',
      helpText: t('Stop the device from going to sleep while the player is active.'),
      inputType: 'checkbox',
    },
    updateStartWindow: {
      label: t('Update Window Start Time'),
      tab: 'network',
      helpText: t('The start of the time window to install application updates.'),
      inputType: 'time',
    },
    updateEndWindow: {
      label: t('Update Window End Time'),
      tab: 'network',
      helpText: t('The end of the time window to install application updates.'),
      inputType: 'time',
    },
  };
}

function androidMeta(t: TFunction): FieldMetaMap {
  return {
    emailAddress: {
      label: t('Licence Code'),
      tab: 'general',
      helpText: t(
        'Provide the Licence Code (formerly Licence email address) to license Players using this Display Profile.',
      ),
      inputType: 'text',
    },
    settingsPassword: {
      label: t('Password Protect Settings'),
      tab: 'general',
      helpText: t('Provide a Password which will be required to access settings'),
      inputType: 'text',
    },
    versionMediaId: {
      label: t('Player Version'),
      tab: 'general',
      helpText: t(
        'Set the Player Version to install, making sure that the selected version is suitable for your device',
      ),
      inputType: 'player-version',
    },
    orientation: {
      label: t('Orientation'),
      tab: 'location',
      helpText: t(
        'Set the orientation of the device (portrait mode will only work if supported by the hardware) Application Restart Required.',
      ),
      inputType: 'dropdown',
      options: [
        { value: '-1', label: t('Device Default') },
        { value: '0', label: t('Landscape') },
        { value: '1', label: t('Portrait') },
        { value: '8', label: t('Reverse Landscape') },
        { value: '9', label: t('Reverse Portrait') },
      ],
    },
    screenDimensions: {
      label: t('Screen Dimensions'),
      tab: 'location',
      helpText: t(
        'Set dimensions to be used for the Player window ensuring that they do not exceed the actual screen size. Enter the following values representing the pixel sizings for; Top,Left,Width,Height. This requires a Player Restart to action.',
      ),
      inputType: 'text',
    },
    blacklistVideo: {
      label: t('Blacklist Videos?'),
      tab: 'troubleshooting',
      helpText: t('Should Videos we fail to play be blacklisted and no longer attempted?'),
      inputType: 'checkbox',
    },
    storeHtmlOnInternal: {
      label: t('Store HTML resources on the Internal Storage?'),
      tab: 'troubleshooting',
      helpText: t(
        'Store all HTML resources on the Internal Storage? Should be selected if the device cannot display text, ticker, dataset media.',
      ),
      inputType: 'checkbox',
    },
    useSurfaceVideoView: {
      label: t('Use a SurfaceView for Video Rendering?'),
      tab: 'troubleshooting',
      helpText: t(
        'If the device is having trouble playing video, it may be useful to switch to a Surface View for Video Rendering.',
      ),
      inputType: 'checkbox',
    },
    startOnBoot: {
      label: t('Start during device start up?'),
      tab: 'advanced',
      helpText: t(
        'When the device starts and Android finishes loading, should the Player start up and come to the foreground?',
      ),
      inputType: 'checkbox',
    },
    actionBarMode: {
      label: t('Action Bar Mode'),
      tab: 'advanced',
      helpText: t('How should the action bar behave?'),
      inputType: 'dropdown',
      options: [
        { value: '0', label: t('Hide') },
        { value: '1', label: t('Timed') },
        { value: '2', label: t('Run Intent') },
      ],
    },
    actionBarDisplayDuration: {
      label: t('Action Bar Display Duration'),
      tab: 'advanced',
      helpText: t('How long should the Action Bar be shown for, in seconds?'),
      inputType: 'number',
    },
    actionBarIntent: {
      label: t('Action Bar Intent'),
      tab: 'advanced',
      helpText: t(
        'When set to Run Intent, which intent should be run. Format is: Action|ExtraKey,ExtraMsg',
      ),
      inputType: 'text',
    },
    autoRestart: {
      label: t('Automatic Restart'),
      tab: 'advanced',
      helpText: t('Automatically Restart the application if we detect it is not visible.'),
      inputType: 'checkbox',
    },
    startOnBootDelay: {
      label: t('Start delay for device start up'),
      tab: 'advanced',
      helpText: t(
        'The number of seconds to wait before starting the application after the device has started. Minimum 10.',
      ),
      inputType: 'number',
    },
    screenShotIntent: {
      label: t('Action for Screen Shot Intent'),
      tab: 'advanced',
      helpText: t(
        'The Intent Action to use for requesting a screen shot. Leave empty to natively create an image from the player screen content.',
      ),
      inputType: 'text',
    },
    webViewPluginState: {
      label: t('WebView Plugin State'),
      tab: 'advanced',
      helpText: t('What plugin state should be used when starting a web view.'),
      inputType: 'dropdown',
      options: [
        { value: 'OFF', label: t('Off') },
        { value: 'DEMAND', label: t('On Demand') },
        { value: 'ON', label: t('On') },
      ],
    },
    hardwareAccelerateWebViewMode: {
      label: t('Hardware Accelerate Web Content'),
      tab: 'advanced',
      helpText: t('Mode for hardware acceleration of web based content.'),
      inputType: 'dropdown',
      options: [
        { value: '0', label: t('Off') },
        { value: '2', label: t('Off when transparent') },
        { value: '1', label: t('On') },
      ],
    },
    timeSyncFromCms: {
      label: t('Use CMS time?'),
      tab: 'advanced',
      helpText: t(
        'Set the device time using the CMS. Only available on rooted devices or system signed players.',
      ),
      inputType: 'checkbox',
    },
    webCacheEnabled: {
      label: t('Enable caching of Web Resources?'),
      tab: 'advanced',
      helpText: t(
        'The standard browser cache will be used - we recommend this is switched off unless specifically required. Effects Web Page and Embedded.',
      ),
      inputType: 'checkbox',
    },
    serverPort: {
      label: t('Embedded Web Server Port'),
      tab: 'advanced',
      helpText: t(
        'The port number to use for the embedded web server on the Player. Only change this if there is a port conflict reported on the status screen.',
      ),
      inputType: 'number',
    },
    installWithLoadedLinkLibraries: {
      label: t('Load Link Libraries for APK Update'),
      tab: 'advanced',
      helpText: t(
        'Should the update command include dynamic link libraries? Only change this if your updates are failing.',
      ),
      inputType: 'checkbox',
    },
    isUseMultipleVideoDecoders: {
      label: t('Use Multiple Video Decoders'),
      tab: 'advanced',
      helpText: t(
        'Should the Player try to use Multiple Video Decoders when preparing and showing Video content.',
      ),
      inputType: 'dropdown',
      options: [
        { value: 'default', label: t('Device Default') },
        { value: 'on', label: t('On') },
        { value: 'off', label: t('Off') },
      ],
    },
    maxRegionCount: {
      label: t('Maximum Region Count'),
      tab: 'advanced',
      helpText: t(
        'This setting is a memory limit protection setting which will stop rendering regions beyond the limit set. Leave at 0 for no limit.',
      ),
      inputType: 'number',
    },
    videoEngine: {
      label: t('Video Engine'),
      tab: 'advanced',
      helpText: t(
        'Select which video engine should be used to playback video. ExoPlayer is usually better, but if you experience issues you can revert back to Android Media Player. HLS always uses ExoPlayer. Available from v3 R300.',
      ),
      inputType: 'dropdown',
      options: [
        { value: 'default', label: t('Device Default') },
        { value: 'exoplayer', label: t('ExoPlayer') },
        { value: 'mediaplayer', label: t('Android Media Player') },
      ],
    },
    isTouchEnabled: {
      label: t('Enable touch capabilities on the device?'),
      tab: 'advanced',
      helpText: t(
        'If this device will be used as a touch screen check this option. Available from v3 R300.',
      ),
      inputType: 'checkbox',
    },
    restartWifiOnConnectionFailure: {
      label: t('Restart Wifi on connection failure?'),
      tab: 'network',
      helpText: t(
        'If an attempted connection to the CMS fails 10 times in a row, restart the Wifi adaptor.',
      ),
      inputType: 'checkbox',
    },
  };
}

function windowsMeta(t: TFunction): FieldMetaMap {
  return {
    powerpointEnabled: {
      label: t('Enable PowerPoint?'),
      tab: 'advanced',
      helpText: t('Should Microsoft PowerPoint be enabled for this Display Profile?'),
      inputType: 'checkbox',
    },
    sizeX: {
      label: t('Width'),
      tab: 'location',
      helpText: t('The Width of the Player window. 0 means full width.'),
      inputType: 'number',
    },
    sizeY: {
      label: t('Height'),
      tab: 'location',
      helpText: t('The Height of the Player window. 0 means full height.'),
      inputType: 'number',
    },
    offsetX: {
      label: t('Offset - Left'),
      tab: 'location',
      helpText: t('The left pixel position of the Player window.'),
      inputType: 'number',
    },
    offsetY: {
      label: t('Offset - Top'),
      tab: 'location',
      helpText: t('The top pixel position of the Player window.'),
      inputType: 'number',
    },
    clientInfomationCtrlKey: {
      label: t('Show status window using Ctrl+I?'),
      tab: 'advanced',
      helpText: t('Show the Player status window using Ctrl+I keyboard shortcut.'),
      inputType: 'checkbox',
    },
    clientInformationKeyCode: {
      label: t('Status window key'),
      tab: 'advanced',
      helpText: t('The key to use in combination with Ctrl to show the status window.'),
      inputType: 'text',
    },
    logToDiskLocation: {
      label: t('Log to disk'),
      tab: 'troubleshooting',
      helpText: t('The full file path for log output. Leave empty to disable.'),
      inputType: 'text',
    },
    showInTaskbar: {
      label: t('Show in taskbar?'),
      tab: 'advanced',
      helpText: t('Should the Player show in the Windows taskbar?'),
      inputType: 'checkbox',
    },
    cursorStartPosition: {
      label: t('Cursor Start Position'),
      tab: 'location',
      helpText: t('The position of the cursor when the Player starts.'),
      inputType: 'dropdown',
      options: [
        { value: 'Unchanged', label: t('Unchanged') },
        { value: 'Top Left', label: t('Top Left') },
        { value: 'Top Right', label: t('Top Right') },
        { value: 'Bottom Left', label: t('Bottom Left') },
        { value: 'Bottom Right', label: t('Bottom Right') },
      ],
    },
    doubleBuffering: {
      label: t('Double Buffering'),
      tab: 'advanced',
      helpText: t(
        'Enable double buffering on the Player. Disable if you experience glitching on transitions.',
      ),
      inputType: 'checkbox',
    },
    emptyLayoutDuration: {
      label: t('Empty Layout Duration'),
      tab: 'advanced',
      helpText: t('The duration to show an empty layout in seconds.'),
      inputType: 'number',
    },
    enableMouse: {
      label: t('Enable Mouse?'),
      tab: 'advanced',
      helpText: t('Should the mouse cursor be shown on the display?'),
      inputType: 'checkbox',
    },
    authServerWhitelist: {
      label: t('Whitelist for Auth Server'),
      tab: 'network',
      helpText: t('A comma separated list of URLs to whitelist for the auth server.'),
      inputType: 'text',
    },
    edgeBrowserWhitelist: {
      label: t('Whitelist for Edge Browser'),
      tab: 'network',
      helpText: t('A comma separated list of URLs to whitelist for the Edge browser.'),
      inputType: 'text',
    },
  };
}

function lgSsspMeta(t: TFunction): FieldMetaMap {
  return {
    emailAddress: {
      label: t('Licence Code'),
      tab: 'general',
      helpText: t('Provide the Licence Code to license Players using this Display Profile.'),
      inputType: 'text',
    },
    versionMediaId: {
      label: t('Player Version'),
      tab: 'general',
      helpText: t(
        'Set the Player Version to install, making sure that the selected version is suitable for your device',
      ),
      inputType: 'player-version',
    },
    orientation: {
      label: t('Orientation'),
      tab: 'location',
      helpText: t('Set the orientation of the device.'),
      inputType: 'dropdown',
      options: [
        { value: '0', label: t('Landscape') },
        { value: '1', label: t('Portrait') },
        { value: '8', label: t('Reverse Landscape') },
        { value: '9', label: t('Reverse Portrait') },
      ],
    },
    actionBarMode: {
      label: t('Action Bar Mode'),
      tab: 'advanced',
      helpText: t('How should the action bar behave?'),
      inputType: 'dropdown',
      options: [
        { value: '0', label: t('Hide') },
        { value: '1', label: t('Timed') },
      ],
    },
    actionBarDisplayDuration: {
      label: t('Action Bar Display Duration'),
      tab: 'advanced',
      helpText: t('How long should the Action Bar be shown for, in seconds?'),
      inputType: 'number',
    },
    mediaInventoryTimer: {
      label: t('Media Inventory Timer'),
      tab: 'advanced',
      helpText: t('The number of minutes between Media Inventory runs. 0 to disable.'),
      inputType: 'number',
    },
    serverPort: {
      label: t('Embedded Web Server Port'),
      tab: 'advanced',
      helpText: t('The port number to use for the embedded web server on the Player.'),
      inputType: 'number',
    },
    isUseMultipleVideoDecoders: {
      label: t('Use Multiple Video Decoders'),
      tab: 'advanced',
      helpText: t(
        'Should the Player try to use Multiple Video Decoders when preparing and showing Video content.',
      ),
      inputType: 'dropdown',
      options: [
        { value: 'on', label: t('On') },
        { value: 'off', label: t('Off') },
      ],
    },
    disableTimerManagement: {
      label: t('Disable managing on/off timer'),
      tab: 'timers',
      helpText: t(
        'When disabled on/off timers can be controlled on the screen and will not be modified by the CMS',
      ),
      inputType: 'checkbox',
    },
    timers: {
      label: t('Timers'),
      tab: 'timers',
      helpText: t('Configure on/off timers for this display.'),
      inputType: 'timers',
    },
    pictureOptions: {
      label: t('Picture Options'),
      tab: 'pictureOptions',
      helpText: t('Configure picture options for this display.'),
      inputType: 'picture-options',
    },
    lockOptions: {
      label: t('Lock Options'),
      tab: 'lockSettings',
      helpText: t('Configure lock options for this display.'),
      inputType: 'lock-options',
    },
  };
}

function chromeOsMeta(t: TFunction): FieldMetaMap {
  return {
    licenceCode: {
      label: t('Licence Code'),
      tab: 'general',
      helpText: t('Provide the Licence Code to license Players using this Display Profile.'),
      inputType: 'text',
    },
    playerVersionId: {
      label: t('Player Version'),
      tab: 'general',
      helpText: t(
        'Set the Player Version to install, making sure that the selected version is suitable for your device',
      ),
      inputType: 'player-version',
    },
    screenShotSize: {
      label: t('Screen Shot Size'),
      tab: 'advanced',
      helpText: t('The size of the screen shot to take.'),
      inputType: 'dropdown',
      options: [
        { value: '1', label: t('Small') },
        { value: '2', label: t('Medium') },
      ],
    },
  };
}

function linuxMeta(t: TFunction): FieldMetaMap {
  return {
    sizeX: {
      label: t('Width'),
      tab: 'location',
      helpText: t('The Width of the Player window. 0 means full width.'),
      inputType: 'number',
    },
    sizeY: {
      label: t('Height'),
      tab: 'location',
      helpText: t('The Height of the Player window. 0 means full height.'),
      inputType: 'number',
    },
    offsetX: {
      label: t('Offset - Left'),
      tab: 'location',
      helpText: t('The left pixel position of the Player window.'),
      inputType: 'number',
    },
    offsetY: {
      label: t('Offset - Top'),
      tab: 'location',
      helpText: t('The top pixel position of the Player window.'),
      inputType: 'number',
    },
  };
}

export function getFieldMetaForType(
  clientType: string | null | undefined,
  t: TFunction,
): FieldMetaMap {
  const common = commonMeta(t);

  switch (clientType) {
    case 'android':
      return { ...common, ...androidMeta(t) };
    case 'windows':
      return { ...common, ...windowsMeta(t) };
    case 'linux':
      return { ...common, ...linuxMeta(t) };
    case 'lg':
    case 'sssp':
      return { ...common, ...lgSsspMeta(t) };
    case 'chromeOS':
      return { ...common, ...chromeOsMeta(t) };
    default:
      return common;
  }
}
