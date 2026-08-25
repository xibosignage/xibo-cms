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

import type { TFunction } from 'i18next';

import type { DisplayProfileType } from '@/types/displayProfile';

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
  hisense: new Set([
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
    'disableTimerManagement',
  ]),
};

export type FieldInputType =
  | 'checkbox'
  | 'datepicker'
  | 'daypart'
  | 'dropdown'
  | 'email'
  | 'hisense-picture-options'
  | 'hisense-timers'
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
  /** CMS setting key that must be truthy (default: enabled) for this field to be shown at all. */
  requiresSetting?: string;
  /** Another field's key that must currently be truthy for this field to be shown. */
  requiresField?: string;
  /** Minimum allowed value for number inputs. */
  min?: number;
  /** Player types this field should NOT render for, even though it's defined here. */
  excludeTypes?: DisplayProfileType[];
}

export type FieldMetaMap = Record<string, FieldMeta>;

/**
 * Whether a field should be shown, based on its `requiresSetting` gate (if any) and the
 * CMS-wide settings from the current user's bootstrap payload, plus its `requiresField`
 * gate (if any) against another field's current live value. Missing/undefined settings
 * default to enabled, matching the settings' own DB default of '1'.
 */
export function isFieldMetaEnabled(
  meta: FieldMeta,
  settings?: Record<string, unknown> | null,
  getBool?: (key: string) => boolean,
): boolean {
  if (meta.requiresSetting) {
    const value = settings?.[meta.requiresSetting];
    if (!(value === undefined || value === null || Number(value) !== 0)) {
      return false;
    }
  }
  if (meta.requiresField && getBool && !getBool(meta.requiresField)) {
    return false;
  }
  return true;
}

function commonMeta(t: TFunction): FieldMetaMap {
  return {
    // ---- General tab ----
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
    statsEnabled: {
      label: t('Enable stats reporting?'),
      tab: 'general',
      helpText: t('Should the application send proof of play stats to the CMS.'),
      inputType: 'checkbox',
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
      requiresField: 'statsEnabled',
    },
    isRecordGeoLocationOnProofOfPlay: {
      label: t('Record geolocation on each Proof of Play?'),
      tab: 'general',
      helpText: t(
        'If the geolocation of the Display is known, enable to record that location against each proof of play record.',
      ),
      inputType: 'checkbox',
      // Legacy rendered this for Windows and Android (and Hisense, an Android-based type).
      // For 4.5 - included Linux and ChromeOS. No support yet for lg/sssp.
      excludeTypes: ['lg', 'sssp'],
    },
    // ---- Troubleshooting tab ----
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
    // ---- Network tab ----
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
    updateStartWindow: {
      label: t('Update Window Start Time'),
      tab: 'network',
      helpText: t('The start of the time window to install application updates.'),
      inputType: 'time',
      // Legacy doc marks these "Android-only" (also legitimately used by lg/sssp,
      // which relocates them to its own General tab below).
      excludeTypes: ['windows', 'linux', 'chromeOS'],
    },
    updateEndWindow: {
      label: t('Update Window End Time'),
      tab: 'network',
      helpText: t('The end of the time window to install application updates.'),
      inputType: 'time',
      excludeTypes: ['windows', 'linux', 'chromeOS'],
    },
    forceHttps: {
      label: t('Force HTTPS?'),
      tab: 'network',
      helpText: t('Should Displays be forced to use HTTPS connection to the CMS?'),
      inputType: 'checkbox',
    },
    dayPartId: {
      label: t('Operating Hours'),
      tab: 'network',
      helpText: t(
        'Select a day part that should act as operating hours for this display - email alerts will not be sent outside of operating hours',
      ),
      inputType: 'daypart',
    },
    // ---- Advanced tab ----
    enableShellCommands: {
      label: t('Enable Shell Commands'),
      tab: 'advanced',
      helpText: t('Enable the Shell Command module.'),
      inputType: 'checkbox',
      // Legacy only ever had this for Windows/Linux.
      excludeTypes: ['android', 'lg', 'sssp', 'chromeOS', 'hisense'],
    },
    sendCurrentLayoutAsStatusUpdate: {
      label: t('Notify current layout'),
      tab: 'advanced',
      helpText: t(
        'When enabled the Player will send the current layout to the CMS each time it changes. Warning: This is bandwidth intensive and should be disabled unless on a LAN.',
      ),
      inputType: 'checkbox',
      requiresSetting: 'DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED',
    },
    expireModifiedLayouts: {
      label: t('Expire Modified Layouts?'),
      tab: 'advanced',
      helpText: t(
        'Expire Modified Layouts immediately on change. This means a layout can be cut during playback if it receives an update from the CMS',
      ),
      inputType: 'checkbox',
      // Legacy never had this for lg/sssp/ChromeOS.
      excludeTypes: ['lg', 'sssp', 'chromeOS'],
    },
    maxConcurrentDownloads: {
      label: t('Maximum concurrent downloads'),
      tab: 'advanced',
      helpText: t('The maximum number of concurrent downloads the Player will attempt.'),
      inputType: 'number',
      min: 0,
      excludeTypes: ['android', 'lg', 'sssp', 'chromeOS', 'hisense'],
    },
    shellCommandAllowList: {
      label: t('Shell Command Allow List'),
      tab: 'advanced',
      helpText: t('Which shell commands should the Player execute?'),
      inputType: 'text',
      excludeTypes: ['android', 'lg', 'sssp', 'chromeOS', 'hisense'],
    },
    screenShotRequestInterval: {
      label: t('Screen shot interval'),
      tab: 'advanced',
      helpText: t(
        'The duration between status screen shots in minutes. 0 to disable. Warning: This is bandwidth intensive.',
      ),
      inputType: 'number',
      min: 0,
      requiresSetting: 'DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED',
    },
    screenShotSize: {
      label: t('Screen Shot Size'),
      tab: 'advanced',
      helpText: t('The size of the largest dimension. Empty or 0 means the screen size.'),
      inputType: 'number',
      min: 0,
    },
    maxLogFileUploads: {
      label: t('Limit the number of log files uploaded concurrently'),
      tab: 'advanced',
      helpText: t(
        'The number of log files to upload concurrently. The lower the number the longer it will take, but the better for memory usage.',
      ),
      inputType: 'number',
      min: 0,
      excludeTypes: ['android', 'lg', 'sssp', 'chromeOS', 'hisense'],
    },
    embeddedServerPort: {
      label: t('Embedded Web Server Port'),
      tab: 'advanced',
      helpText: t(
        'The port number to use for the embedded web server on the Player. Only change this if there is a port conflict reported on the status screen.',
      ),
      inputType: 'number',
      min: 0,
      // Android and lg/sssp each define their own, correctly-scoped `serverPort` field —
      // this generic one leaking in caused a literal duplicate "Embedded Web Server Port".
      excludeTypes: ['android', 'lg', 'sssp', 'chromeOS', 'hisense'],
    },
    embeddedServerAllowWan: {
      label: t('Embedded Web Server allow WAN?'),
      tab: 'advanced',
      helpText: t(
        'Should we allow access to the Player Embedded Web Server from WAN? You may need to adjust the device firewall to allow external traffic',
      ),
      inputType: 'checkbox',
      // Legacy ChromeOS Advanced never had this.
      excludeTypes: ['chromeOS'],
    },
    preventSleep: {
      label: t('Prevent Sleep?'),
      tab: 'advanced',
      helpText: t('Stop the player PC power management from Sleeping the PC'),
      inputType: 'checkbox',
      excludeTypes: ['android', 'lg', 'sssp', 'chromeOS', 'hisense'],
    },
  };
}

function androidMeta(t: TFunction, common: FieldMetaMap): FieldMetaMap {
  return {
    emailAddress: {
      label: t('Licence Code'),
      tab: 'general',
      helpText: t(
        'Provide the Licence Code (formerly Licence email address) to license Players using this Display Profile.',
      ),
      inputType: 'email',
    },
    settingsPassword: {
      label: t('Password Protect Settings'),
      tab: 'general',
      helpText: t('Provide a Password which will be required to access settings'),
      inputType: 'text',
    },
    // Legacy interleaves these common General fields between the two above and
    // versionMediaId below — referenced (not copied) from commonMeta so label/
    // helpText changes there still apply here automatically.
    collectInterval: common.collectInterval!,
    xmrWebSocketAddress: common.xmrWebSocketAddress!,
    xmrNetworkAddress: common.xmrNetworkAddress!,
    statsEnabled: common.statsEnabled!,
    aggregationLevel: common.aggregationLevel!,
    isRecordGeoLocationOnProofOfPlay: common.isRecordGeoLocationOnProofOfPlay!,
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
    // Legacy has these two common Troubleshooting fields last, after the three above.
    logLevel: common.logLevel!,
    elevateLogsUntil: common.elevateLogsUntil!,
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
      min: 0,
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
      min: 10,
    },
    // Legacy interleaves these three common Advanced fields here, before
    // Screen Shot Intent.
    sendCurrentLayoutAsStatusUpdate: common.sendCurrentLayoutAsStatusUpdate!,
    expireModifiedLayouts: common.expireModifiedLayouts!,
    screenShotRequestInterval: common.screenShotRequestInterval!,
    screenShotIntent: {
      label: t('Action for Screen Shot Intent'),
      tab: 'advanced',
      helpText: t(
        'The Intent Action to use for requesting a screen shot. Leave empty to natively create an image from the player screen content.',
      ),
      inputType: 'text',
    },
    // Legacy has Screen Shot Size directly after Screen Shot Intent.
    screenShotSize: common.screenShotSize!,
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
    // Legacy has this common Advanced field directly after Embedded Web Server Port.
    embeddedServerAllowWan: common.embeddedServerAllowWan!,
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
      min: 0,
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
        'If this device will be used as a touch screen check this option. Checking this option will cause a message to appear on the player which needs to be manually dismissed once. If this option is disabled, touching the screen will show the action bar according to the Action Bar Mode option. Available from v3 R300.',
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

function windowsMeta(t: TFunction, common: FieldMetaMap): FieldMetaMap {
  return {
    powerpointEnabled: {
      label: t('Enable PowerPoint?'),
      // Legacy has this as the last field on General, not Advanced.
      tab: 'general',
      helpText: t(
        'Should Microsoft PowerPoint be Enabled? The Player will need PowerPoint installed to Display PowerPoint files.',
      ),
      inputType: 'checkbox',
    },
    sizeX: {
      label: t('Width'),
      tab: 'location',
      helpText: t('The Width of the Display Window. 0 means full width.'),
      inputType: 'number',
      min: 0,
    },
    sizeY: {
      label: t('Height'),
      tab: 'location',
      helpText: t('The Height of the Display Window. 0 means full height.'),
      inputType: 'number',
      min: 0,
    },
    offsetX: {
      label: t('Left Coordinate'),
      tab: 'location',
      helpText: t('The left pixel position the display window should be sized from.'),
      inputType: 'number',
    },
    offsetY: {
      label: t('Top Coordinate'),
      tab: 'location',
      helpText: t('The top pixel position the display window should be sized from.'),
      inputType: 'number',
    },
    clientInfomationCtrlKey: {
      label: t('CTRL Key required to access Player Information Screen?'),
      // Legacy has this first on Troubleshooting, not Advanced.
      tab: 'troubleshooting',
      helpText: t('Should the Player information screen require the CTRL key?'),
      inputType: 'checkbox',
    },
    clientInformationKeyCode: {
      label: t('Key for Player Information Screen'),
      tab: 'troubleshooting',
      helpText: t('Which key should activate the Player information screen? A single character.'),
      inputType: 'text',
    },
    // Legacy has these two common Troubleshooting fields between the key code
    // field above and Log to disk below.
    logLevel: common.logLevel!,
    elevateLogsUntil: common.elevateLogsUntil!,
    logToDiskLocation: {
      label: t('Log file path name'),
      tab: 'troubleshooting',
      helpText: t(
        'Create a log file on disk in this location. Please enter a fully qualified path.',
      ),
      inputType: 'text',
    },
    showInTaskbar: {
      label: t('Show the icon in the task bar?'),
      tab: 'advanced',
      helpText: t('Should the application icon be shown in the task bar?'),
      inputType: 'checkbox',
    },
    cursorStartPosition: {
      label: t('Cursor Start Position'),
      // Legacy has this on Advanced (right after "Show in taskbar?"), not Location.
      tab: 'advanced',
      helpText: t('The position of the cursor when the Player starts up.'),
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
      label: t('Enable Double Buffering'),
      tab: 'advanced',
      helpText: t(
        'Double buffering helps smooth the playback but should be disabled if graphics errors occur',
      ),
      inputType: 'checkbox',
    },
    emptyLayoutDuration: {
      label: t('Duration for Empty Layouts'),
      tab: 'advanced',
      helpText: t(
        'If an empty layout is detected how long (in seconds) should it remain on screen? Must be greater than 1.',
      ),
      inputType: 'number',
      min: 2,
    },
    enableMouse: {
      label: t('Enable Mouse'),
      tab: 'advanced',
      helpText: t('Enable the mouse.'),
      inputType: 'checkbox',
    },
    // Legacy has these common Advanced fields directly after Enable Mouse.
    enableShellCommands: common.enableShellCommands!,
    sendCurrentLayoutAsStatusUpdate: common.sendCurrentLayoutAsStatusUpdate!,
    expireModifiedLayouts: common.expireModifiedLayouts!,
    maxConcurrentDownloads: common.maxConcurrentDownloads!,
    shellCommandAllowList: common.shellCommandAllowList!,
    screenShotRequestInterval: common.screenShotRequestInterval!,
    screenShotSize: common.screenShotSize!,
    maxLogFileUploads: common.maxLogFileUploads!,
    embeddedServerPort: common.embeddedServerPort!,
    embeddedServerAllowWan: common.embeddedServerAllowWan!,
    preventSleep: common.preventSleep!,
    authServerWhitelist: {
      label: t('Authentication Whitelist'),
      tab: 'network',
      helpText: t(
        'A comma separated list of domains which should be allowed to perform NTML/Negotiate authentication.',
      ),
      inputType: 'text',
    },
    edgeBrowserWhitelist: {
      label: t('Edge Browser Whitelist'),
      tab: 'network',
      helpText: t(
        'A comma separated list of website urls which should be rendered by the Edge Browser instead of Chromium.',
      ),
      inputType: 'text',
    },
  };
}

function lgSsspMeta(t: TFunction, clientType: 'lg' | 'sssp', common: FieldMetaMap): FieldMetaMap {
  return {
    emailAddress: {
      label: t('Licence Code'),
      tab: 'general',
      helpText: t(
        'Provide the Licence Code (formerly Licence email address) to license Players using this Display Profile.',
      ),
      inputType: 'email',
    },
    // Legacy has these common General fields between Licence Code and Player
    // Version.
    collectInterval: common.collectInterval!,
    xmrWebSocketAddress: common.xmrWebSocketAddress!,
    xmrNetworkAddress: common.xmrNetworkAddress!,
    statsEnabled: common.statsEnabled!,
    aggregationLevel: common.aggregationLevel!,
    versionMediaId: {
      label: t('Player Version'),
      tab: 'general',
      helpText: t(
        'Set the Player Version to install, making sure that the selected version is suitable for your device',
      ),
      inputType: 'player-version',
    },
    // Legacy has Orientation directly after Player Version.
    orientation: {
      label: t('Orientation'),
      tab: 'general',
      helpText: t(
        'Set the orientation of the device (portrait mode will only work if supported by the hardware) Application Restart Required.',
      ),
      inputType: 'dropdown',
      options: [
        { value: '0', label: t('Landscape') },
        { value: '1', label: t('Portrait') },
        { value: '8', label: t('Reverse Landscape') },
        { value: '9', label: t('Reverse Portrait') },
      ],
    },
    // LG/SSSP profiles have no Network or Location tabs — surface these common
    // fields on the General tab so they remain accessible.
    downloadStartWindow: {
      label: t('Download Window Start Time'),
      tab: 'general',
      helpText: t('The start of the time window to connect to the CMS and download updates.'),
      inputType: 'time',
    },
    downloadEndWindow: {
      label: t('Download Window End Time'),
      tab: 'general',
      helpText: t('The end of the time window to connect to the CMS and download updates.'),
      inputType: 'time',
    },
    updateStartWindow: {
      label: t('Update Window Start Time'),
      tab: 'general',
      helpText: t('The start of the time window to install application updates.'),
      inputType: 'time',
    },
    updateEndWindow: {
      label: t('Update Window End Time'),
      tab: 'general',
      helpText: t('The end of the time window to install application updates.'),
      inputType: 'time',
    },
    // Legacy has Force HTTPS directly after the update window, before
    // Operating Hours.
    forceHttps: {
      label: t('Force HTTPS?'),
      tab: 'general',
      helpText: t('Should Displays be forced to use HTTPS connection to the CMS?'),
      inputType: 'checkbox',
    },
    dayPartId: {
      label: t('Operating Hours'),
      tab: 'general',
      helpText: t(
        'Select a day part that should act as operating hours for this display - email alerts will not be sent outside of operating hours',
      ),
      inputType: 'daypart',
    },
    // LG/SSSP has no Troubleshooting tab — these live on Advanced instead
    // (matches the pattern already used by chromeOsMeta for the same fields).
    logLevel: {
      label: t('Log Level'),
      tab: 'advanced',
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
      tab: 'advanced',
      helpText: t(
        'Elevate log level for the specified time. Should only be used if there is a problem with the display.',
      ),
      inputType: 'datepicker',
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
      min: 0,
    },
    // Legacy has these two common Advanced fields between Action Bar Display
    // Duration and Screen Shot Size below.
    sendCurrentLayoutAsStatusUpdate: common.sendCurrentLayoutAsStatusUpdate!,
    screenShotRequestInterval: common.screenShotRequestInterval!,
    screenShotSize: {
      label: t('Screen Shot Size'),
      tab: 'advanced',
      helpText: t('The size of the screenshot to return when requested.'),
      inputType: 'dropdown',
      options:
        clientType === 'lg'
          ? [
              { value: '1', label: t('Thumbnail') },
              { value: '2', label: t('HD') },
              { value: '3', label: t('FHD') },
            ]
          : [
              { value: '1', label: t('Thumbnail') },
              { value: '2', label: t('Standard') },
            ],
    },
    // Legacy has Send progress while downloading directly after Screen Shot
    // Size, before Embedded Web Server Port.
    mediaInventoryTimer: {
      label: t('Send progress while downloading'),
      tab: 'advanced',
      helpText: t(
        'How often, in minutes, should the Display send its download progress while it is downloading new content?',
      ),
      inputType: 'number',
    },
    serverPort: {
      label: t('Embedded Web Server Port'),
      tab: 'advanced',
      helpText: t(
        'The port number to use for the embedded web server on the Player. Only change this if there is a port conflict reported on the status screen.',
      ),
      inputType: 'number',
    },
    // Legacy has this common Advanced field directly after Embedded Web
    // Server Port.
    embeddedServerAllowWan: common.embeddedServerAllowWan!,
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
      label: t('Disable managing on/off timers'),
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

function chromeOsMeta(t: TFunction, common: FieldMetaMap): FieldMetaMap {
  return {
    licenceCode: {
      label: t('Licence Code'),
      tab: 'general',
      helpText: t('Provide the Licence Code to license Players using this Display Profile.'),
      inputType: 'email',
    },
    // Legacy has these common General fields between Licence Code and Player
    // Version.
    collectInterval: common.collectInterval!,
    xmrWebSocketAddress: common.xmrWebSocketAddress!,
    xmrNetworkAddress: common.xmrNetworkAddress!,
    statsEnabled: common.statsEnabled!,
    aggregationLevel: common.aggregationLevel!,
    playerVersionId: {
      label: t('Player Version'),
      tab: 'general',
      helpText: t(
        'Set the Player Version to install, making sure that the selected version is suitable for your device',
      ),
      inputType: 'player-version',
    },
    // ChromeOS has no Network tab — surface Operating Hours on the General tab
    // so it remains accessible, same pattern as lgSsspMeta's dayPartId override.
    dayPartId: {
      label: t('Operating Hours'),
      tab: 'general',
      helpText: t(
        'Select a day part that should act as operating hours for this display - email alerts will not be sent outside of operating hours',
      ),
      inputType: 'daypart',
    },
    // ChromeOS has no Troubleshooting tab — these live on Advanced instead.
    logLevel: {
      label: t('Log Level'),
      tab: 'advanced',
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
      tab: 'advanced',
      helpText: t(
        'Elevate log level for the specified time. Should only be used if there is a problem with the display.',
      ),
      inputType: 'datepicker',
    },
    // The backend default for ChromeOS (DisplayProfileFactory::loadForType())
    // comes from the DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT CMS setting
    // (typically 200, a pixel value), which doesn't match either option value
    // below ('1'/'2') — deliberately left alone to avoid backend changes.
    // ChromeOsFields.tsx's getValue() normalizes any unmatched value to '1'
    // (the first option) so the dropdown doesn't look unselected.
    screenShotSize: {
      label: t('Screen Shot Size'),
      tab: 'advanced',
      helpText: t('The size of the screenshot to return when requested.'),
      inputType: 'dropdown',
      options: [
        { value: '1', label: t('Thumbnail') },
        { value: '2', label: t('Standard') },
      ],
    },
  };
}

function linuxMeta(t: TFunction, common: FieldMetaMap): FieldMetaMap {
  return {
    // ---- General tab ----
    // Re-declared (not just overridden) alongside the two XMR fields below so
    // mergeWithOwnOrder positions all 5 in commonMeta's existing relative
    // order — see fieldMetadata.ts's linuxMeta doc comment in the parity plan.
    collectInterval: common.collectInterval!,
    // Legacy Linux uses different wording for these two than every other
    // type ("Please enter..." instead of "Override the CMS...").
    xmrWebSocketAddress: {
      ...common.xmrWebSocketAddress!,
      helpText: t('Please enter the WebSocket address for XMR.'),
    },
    xmrNetworkAddress: {
      ...common.xmrNetworkAddress!,
      helpText: t('Please enter the public address for XMR.'),
    },
    statsEnabled: common.statsEnabled!,
    aggregationLevel: common.aggregationLevel!,
    // ---- Location tab ----
    sizeX: {
      label: t('Width'),
      tab: 'location',
      helpText: t('The Width of the Display Window. 0 means full width.'),
      inputType: 'number',
      min: 0,
    },
    sizeY: {
      label: t('Height'),
      tab: 'location',
      helpText: t('The Height of the Display Window. 0 means full height.'),
      inputType: 'number',
      min: 0,
    },
    offsetX: {
      label: t('Left Coordinate'),
      tab: 'location',
      helpText: t('The left pixel position the display window should be sized from.'),
      inputType: 'number',
    },
    offsetY: {
      label: t('Top Coordinate'),
      tab: 'location',
      helpText: t('The top pixel position the display window should be sized from.'),
      inputType: 'number',
    },
  };
}

function hisenseMeta(t: TFunction): FieldMetaMap {
  return {
    brightness: {
      label: t('Brightness'),
      tab: 'pictureOptions',
      helpText: t('Set the screen brightness (0-100).'),
      inputType: 'number',
    },
    contrast: {
      label: t('Contrast'),
      tab: 'pictureOptions',
      helpText: t('Set the screen contrast (0-100).'),
      inputType: 'number',
    },
    backlight: {
      label: t('Backlight'),
      tab: 'pictureOptions',
      helpText: t('Set the backlight level (0-100).'),
      inputType: 'number',
    },
    saturation: {
      label: t('Saturation'),
      tab: 'pictureOptions',
      helpText: t('Set the colour saturation (0-100).'),
      inputType: 'number',
    },
    gammaMode: {
      label: t('Gamma Mode'),
      tab: 'pictureOptions',
      helpText: t('Set the gamma mode (0-2).'),
      inputType: 'number',
    },
    dynamicContrast: {
      label: t('Dynamic Contrast'),
      tab: 'pictureOptions',
      helpText: t('Set the dynamic contrast level (0-2).'),
      inputType: 'number',
    },
    colourTemperature: {
      label: t('Colour Temperature'),
      tab: 'pictureOptions',
      helpText: t('Set the colour temperature (0-100).'),
      inputType: 'number',
    },
    hisensePictureOptions: {
      label: t('Picture Options'),
      tab: 'pictureOptions',
      helpText: t('Configure picture settings including brightness, contrast, and colour options.'),
      inputType: 'hisense-picture-options',
    },
    timers: {
      label: t('Timers'),
      tab: 'timers',
      helpText: t('Configure on/off timers for this display.'),
      inputType: 'hisense-timers',
    },
    disableTimerManagement: {
      label: t('Disable managing on/off timer'),
      tab: 'timers',
      helpText: t(
        'When disabled on/off timers can be controlled on the screen and will not be modified by the CMS',
      ),
      inputType: 'checkbox',
    },
  };
}

function excludeForType(map: FieldMetaMap, clientType: DisplayProfileType): FieldMetaMap {
  return Object.fromEntries(
    Object.entries(map).filter(([, meta]) => !meta.excludeTypes?.includes(clientType)),
  );
}

/**
 * Merges `own` on top of `common`, but drops any key from `common` that `own`
 * also defines *before* merging — so a field a type redefines is treated as a
 * brand-new key, positioned by `own`'s insertion order instead of `common`'s.
 * Plain `{...common, ...own}` only overrides the *value* for a shared key;
 * its position always stays wherever `common` first inserted it (JS object
 * spread never moves an existing key). This is what actually lets a type
 * interleave a shared field between two of its own fields.
 *
 * A type that doesn't redefine a given key is completely unaffected — for it,
 * `filtered` is identical to `common`, so this behaves exactly like the plain
 * spread it replaces.
 */
function mergeWithOwnOrder(common: FieldMetaMap, own: FieldMetaMap): FieldMetaMap {
  const filtered = Object.fromEntries(Object.entries(common).filter(([key]) => !(key in own)));
  return { ...filtered, ...own };
}

export function getFieldMetaForType(
  clientType: string | null | undefined,
  t: TFunction,
): FieldMetaMap {
  const common = commonMeta(t);

  let merged: FieldMetaMap;
  switch (clientType) {
    case 'android':
      merged = mergeWithOwnOrder(common, androidMeta(t, common));
      break;
    case 'hisense':
      merged = mergeWithOwnOrder(mergeWithOwnOrder(common, androidMeta(t, common)), hisenseMeta(t));
      break;
    case 'windows':
      merged = mergeWithOwnOrder(common, windowsMeta(t, common));
      break;
    case 'linux':
      merged = mergeWithOwnOrder(common, linuxMeta(t, common));
      break;
    case 'lg':
    case 'sssp':
      merged = mergeWithOwnOrder(common, lgSsspMeta(t, clientType, common));
      break;
    case 'chromeOS':
      merged = mergeWithOwnOrder(common, chromeOsMeta(t, common));
      break;
    default:
      return common;
  }

  return excludeForType(merged, clientType as DisplayProfileType);
}
