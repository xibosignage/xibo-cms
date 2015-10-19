<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2015 Daniel Garner
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

// Client config
$CLIENT_CONFIG = array(

        'windows' => array(
            'synonym' => 'dotnetclient',
            'tabs' => array(
                    array('id' => 'general', 'name' => __('General')),
                    array('id' => 'location', 'name' => __('Location')),
                    array('id' => 'trouble', 'name' => __('Troubleshooting')),
                    array('id' => 'advanced', 'name' => __('Advanced')),
                ),
            'settings' => array(
                    array(
                        'name' => 'CollectInterval',
                        'tabId' => 'general',
                        'title' => __('Collection Interval (seconds)'),
                        'type' => _INT,
                        'fieldType' => 'number',
                        'default' => 900,
                        'helpText' => __('The number of seconds between connections to the CMS.'),
                        'validation' => 'numeric',
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'DownloadStartWindow',
                        'tabId' => 'general',
                        'title' => __('Download Window Start Time'),
                        'type' => _STRING,
                        'fieldType' => 'timePicker',
                        'default' => 0,
                        'helpText' => __('The start of the time window to connect to the CMS and download updates.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'DownloadEndWindow',
                        'tabId' => 'general',
                        'title' => __('Download Window End Time'),
                        'type' => _STRING,
                        'fieldType' => 'timePicker',
                        'default' => 0,
                        'helpText' => __('The end of the time window to connect to the CMS and download updates.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'PowerpointEnabled',
                        'tabId' => 'general',
                        'title' => __('Enable PowerPoint?'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 0,
                        'helpText' => __('Should Microsoft PowerPoint be Enabled?'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'StatsEnabled',
                        'tabId' => 'general',
                        'title' => __('Enable stats reporting?'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 1,
                        'helpText' => __('Should the application send proof of play stats to the CMS.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'SizeX',
                        'tabId' => 'location',
                        'title' => __('Width'),
                        'type' => _DOUBLE,
                        'fieldType' => 'number',
                        'default' => '0',
                        'helpText' => __('The Width of the Display Window. 0 means full width.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'SizeY',
                        'tabId' => 'location',
                        'title' => __('Height'),
                        'type' => _DOUBLE,
                        'fieldType' => 'number',
                        'default' => '0',
                        'helpText' => __('The Height of the Display Window. 0 means full height.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'OffsetX',
                        'tabId' => 'location',
                        'title' => __('Left Coordinate'),
                        'type' => _DOUBLE,
                        'fieldType' => 'number',
                        'default' => '0',
                        'helpText' => __('The left pixel position the display window should be sized from.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'OffsetY',
                        'tabId' => 'location',
                        'title' => __('Top Coordinate'),
                        'type' => _DOUBLE,
                        'fieldType' => 'number',
                        'default' => '0',
                        'helpText' => __('The top pixel position the display window should be sized from.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'ShowInTaskbar',
                        'tabId' => 'advanced',
                        'title' => __('Show the icon in the task bar?'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 1,
                        'helpText' => __('Should the application icon be shown in the task bar?'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'ClientInfomationCtrlKey',
                        'tabId' => 'trouble',
                        'title' => __('CTRL Key required to access Client Information Screen?'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 0,
                        'helpText' => __('Should the client information screen require the CTRL key?'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'ClientInformationKeyCode',
                        'tabId' => 'trouble',
                        'title' => __('Key for Client Information Screen'),
                        'type' => _WORD,
                        'fieldType' => 'text',
                        'default' => 'I',
                        'helpText' => __('Which key should activate the client information screen? A single character.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'CursorStartPosition',
                        'tabId' => 'advanced',
                        'title' => __('Cursor Start Position'),
                        'type' => _STRING,
                        'fieldType' => 'dropdown',
                        'options' => array(
                                array('id' => 'Top Left', 'value' => 'Top Left'),
                                array('id' => 'Top Right', 'value' => 'Top Right'),
                                array('id' => 'Bottom Left', 'value' => 'Bottom Left'),
                                array('id' => 'Bottom Right', 'value' => 'Bottom Right'),
                            ),
                        'default' => 'Bottom Right',
                        'helpText' => __('The position of the cursor when the client starts up.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'DoubleBuffering',
                        'tabId' => 'advanced',
                        'title' => __('Enable Double Buffering'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 1,
                        'helpText' => __('Double buffering helps smooth the playback but should be disabled if graphics errors occur'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'EmptyLayoutDuration',
                        'tabId' => 'advanced',
                        'title' => __('Duration for Empty Layouts'),
                        'type' => _INT,
                        'fieldType' => 'text',
                        'default' => 10,
                        'helpText' => __('If an empty layout is detected how long should it remain on screen. Must be greater then 1.'),
                        'validation' => 'number',
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'EnableMouse',
                        'tabId' => 'advanced',
                        'title' => __('Enable Mouse'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 0,
                        'helpText' => __('Enable the mouse.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'EnableShellCommands',
                        'tabId' => 'advanced',
                        'title' => __('Enable Shell Commands'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 0,
                        'helpText' => __('Enable the Shell Command module.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'ExpireModifiedLayouts',
                        'tabId' => 'advanced',
                        'title' => __('Expire Modified Layouts'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 1,
                        'helpText' => __('Expire Modified Layouts immediately on change. This means a layout can be cut during playback if it receives an update from the CMS'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'LogLevel',
                        'tabId' => 'trouble',
                        'title' => __('Log Level'),
                        'type' => _WORD,
                        'fieldType' => 'dropdown',
                        'options' => array(
                                array('id' => 'audit', 'value' => 'Audit'),
                                array('id' => 'info', 'value' => 'Information'),
                                array('id' => 'error', 'value' => 'Error'),
                                array('id' => 'off', 'value' => 'Off')
                            ),
                        'default' => 'error',
                        'helpText' => __('The logging level that should be recorded by the Player.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'LogToDiskLocation',
                        'tabId' => 'trouble',
                        'title' => __('Log file path name.'),
                        'type' => _STRING,
                        'fieldType' => 'text',
                        'default' => '',
                        'helpText' => __('Create a log file on disk in this location. Please enter a fully qualified path.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'MaxConcurrentDownloads',
                        'tabId' => 'advanced',
                        'title' => __('Maximum concurrent downloads'),
                        'type' => _INT,
                        'fieldType' => 'text',
                        'default' => '2',
                        'helpText' => __('The maximum number of concurrent downloads the client will attempt.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'ShellCommandAllowList',
                        'tabId' => 'advanced',
                        'title' => __('Shell Command Allow List'),
                        'type' => _STRING,
                        'fieldType' => 'text',
                        'default' => '',
                        'helpText' => __('Which shell commands should the client execute?'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'UseCefWebBrowser',
                        'tabId' => 'advanced',
                        'title' => __('Use CEF as the Web Browser'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 0,
                        'helpText' => __('CEF is Chrome Embedded and offers up to date web rendering. If unselected the default Internet Explorer control will be used. The Player software will need to be restarted after making this change.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'SendCurrentLayoutAsStatusUpdate',
                        'tabId' => 'advanced',
                        'title' => __('Notify current layout'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 0,
                        'helpText' => __('When enabled the client will send the current layout to the CMS each time it changes. Warning: This is bandwidth intensive and should be disabled unless on a LAN.'),
                        'enabled' => Theme::GetConfig('client_sendCurrentLayoutAsStatusUpdate_enabled', true),
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'ScreenShotRequestInterval',
                        'tabId' => 'advanced',
                        'title' => __('Screen shot interval'),
                        'type' => _INT,
                        'fieldType' => 'number',
                        'default' => 0,
                        'helpText' => __('The duration between status screen shots in minutes. 0 to disable. Warning: This is bandwidth intensive.'),
                        'enabled' => Theme::GetConfig('client_screenShotRequestInterval_enabled', true),
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'MaxLogFileUploads',
                        'tabId' => 'advanced',
                        'title' => __('Limit the number of log files uploaded concurrently'),
                        'type' => _INT,
                        'fieldType' => 'number',
                        'default' => 3,
                        'helpText' => __('The number of log files to upload concurrently. The lower the number the longer it will take, but the better for memory usage.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    )
                )
            ),

        /*'ubuntu' => array(
            'synonym' => 'python',
            'settings' => array(

                )
            ),*/

        'android' => array(
            'synonym' => 'xiboforandroid',
            'tabs' => array(
                    array('id' => 'general', 'name' => __('General')),
                    array('id' => 'location', 'name' => __('Location')),
                    array('id' => 'trouble', 'name' => __('Troubleshooting')),
                    array('id' => 'advanced', 'name' => __('Advanced')),
                ),
            'settings' => array(
                    array(
                        'name' => 'emailAddress',
                        'tabId' => 'general',
                        'title' => __('Email Address'),
                        'type' => _STRING,
                        'fieldType' => 'text',
                        'default' => '',
                        'helpText' => __('The email address will be used to license this client. This is the email address you provided when you purchased the licence.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'settingsPassword',
                        'tabId' => 'general',
                        'title' => __('Password Protect Settings'),
                        'type' => _STRING,
                        'fieldType' => 'text',
                        'default' => '',
                        'helpText' => __('Provide a Password which will be required to access settings'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'collectInterval',
                        'tabId' => 'general',
                        'title' => __('Collect interval'),
                        'type' => _INT,
                        'fieldType' => 'dropdown',
                        'options' => array(
                                array('id' => 60, 'value' => __('1 minute')),
                                array('id' => 300, 'value' => __('5 minutes')),
                                array('id' => 600, 'value' => __('10 minutes')),
                                array('id' => 1800, 'value' => __('30 minutes')),
                                array('id' => 3600, 'value' => __('1 hour')),
                                array('id' => 14400, 'value' => __('4 hours')),
                                array('id' => 43200, 'value' => __('12 hours'))
                            ),
                        'default' => 300,
                        'helpText' => __('How often should the Player check for new content.'),
                        'validation' => 'numeric',
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'orientation',
                        'tabId' => 'location',
                        'title' => __('Orientation'),
                        'type' => _INT,
                        'fieldType' => 'dropdown',
                        'options' => array(
                                array('id' => 0, 'value' => __('Landscape')),
                                array('id' => 1, 'value' => __('Portrait')),
                                array('id' => 8, 'value' => __('Reverse Landscape')),
                                array('id' => 9, 'value' => __('Reverse Portrait'))
                            ),
                        'default' => 0,
                        'helpText' => __('Set the orientation of the device (portrait mode will only work if supported by the hardware) Application Restart Required.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'startOnBoot',
                        'tabId' => 'advanced',
                        'title' => __('Start during device start up?'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 1,
                        'helpText' => __('When the device starts and Android finishes loading, should the client start up and come to the foreground?'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'actionBarMode',
                        'tabId' => 'advanced',
                        'title' => __('Action Bar Mode'),
                        'type' => _INT,
                        'fieldType' => 'dropdown',
                        'options' => array(
                                array('id' => 0, 'value' => 'Hide'),
                                array('id' => 1, 'value' => 'Timed')
                            ),
                        'default' => 1,
                        'helpText' => __('How should the action bar behave?'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'actionBarDisplayDuration',
                        'tabId' => 'advanced',
                        'title' => __('Action Bar Display Duration'),
                        'type' => _INT,
                        'fieldType' => 'text',
                        'default' => 30,
                        'helpText' => __('How long should the Action Bar be shown for, in seconds?'),
                        'validation' => 'numeric',
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'screenDimensions',
                        'tabId' => 'location',
                        'title' => __('Screen Dimensions'),
                        'type' => _STRING,
                        'fieldType' => 'text',
                        'default' => '',
                        'helpText' => __('Override the screen dimensions (left,top,width,height). Requires restart. Care should be taken to ensure these are within the actual screen size.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'autoRestart',
                        'tabId' => 'advanced',
                        'title' => __('Automatic Restart'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 1,
                        'helpText' => __('Automatically Restart the application if we detect it is not visible.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'startOnBootDelay',
                        'tabId' => 'advanced',
                        'title' => __('Start delay for device start up'),
                        'type' => _INT,
                        'fieldType' => 'text',
                        'default' => 60,
                        'helpText' => __('The number of seconds to wait before starting the application after the device has started. Minimum 10.'),
                        'validation' => 'numeric',
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'blacklistVideo',
                        'tabId' => 'trouble',
                        'title' => __('Blacklist Videos?'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 1,
                        'helpText' => __('Should Videos we fail to play be blacklisted and no longer attempted?'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'storeHtmlOnInternal',
                        'tabId' => 'trouble',
                        'title' => __('Store HTML resources on the Internal Storage?'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 0,
                        'helpText' => __('Store all HTML resources on the Internal Storage? Should be selected if the device cannot display text, ticker, dataset media.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'sendCurrentLayoutAsStatusUpdate',
                        'tabId' => 'advanced',
                        'title' => __('Notify current layout'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 0,
                        'helpText' => __('When enabled the client will send the current layout to the CMS each time it changes. Warning: This is bandwidth intensive and should be disabled unless on a LAN.'),
                        'enabled' => Theme::GetConfig('client_sendCurrentLayoutAsStatusUpdate_enabled', true),
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'screenShotRequestInterval',
                        'tabId' => 'advanced',
                        'title' => __('Screen shot interval'),
                        'type' => _INT,
                        'fieldType' => 'number',
                        'default' => 0,
                        'helpText' => __('The duration between status screen shots in minutes. 0 to disable. Warning: This is bandwidth intensive.'),
                        'enabled' => Theme::GetConfig('client_screenShotRequestInterval_enabled', true),
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'expireModifiedLayouts',
                        'tabId' => 'advanced',
                        'title' => __('Expire Modified Layouts?'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 0,
                        'helpText' => __('Expire Modified Layouts immediately on change. This means a layout can be cut during playback if it receives an update from the CMS'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'screenShotIntent',
                        'tabId' => 'advanced',
                        'title' => __('Action for Screen Shot Intent'),
                        'type' => _STRING,
                        'fieldType' => 'text',
                        'default' => '',
                        'helpText' => __('The Intent Action to use for requesting a screen shot. Leave empty to natively create an image from the player screen content.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'updateStartWindow',
                        'tabId' => 'advanced',
                        'title' => __('Update Window Start Time'),
                        'type' => _STRING,
                        'fieldType' => 'timePicker',
                        'default' => 0,
                        'helpText' => __('The start of the time window to install application updates.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'updateEndWindow',
                        'tabId' => 'advanced',
                        'title' => __('Update Window End Time'),
                        'type' => _STRING,
                        'fieldType' => 'timePicker',
                        'default' => 0,
                        'helpText' => __('The end of the time window to install application updates.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'downloadStartWindow',
                        'tabId' => 'general',
                        'title' => __('Download Window Start Time'),
                        'type' => _STRING,
                        'fieldType' => 'timePicker',
                        'default' => 0,
                        'helpText' => __('The start of the time window to connect to the CMS and download updates.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'downloadEndWindow',
                        'tabId' => 'general',
                        'title' => __('Download Window End Time'),
                        'type' => _STRING,
                        'fieldType' => 'timePicker',
                        'default' => 0,
                        'helpText' => __('The end of the time window to connect to the CMS and download updates.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'webViewPluginState',
                        'tabId' => 'advanced',
                        'title' => __('WebView Plugin State'),
                        'type' => _STRING,
                        'fieldType' => 'dropdown',
                        'options' => array(
                            array('id' => 'OFF', 'value' => __('Off')),
                            array('id' => 'DEMAND', 'value' => __('On Demand')),
                            array('id' => 'ON', 'value' => __('On'))
                        ),
                        'default' => 'DEMAND',
                        'helpText' => __('What plugin state should be used when starting a web view.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'timeSyncFromCms',
                        'tabId' => 'advanced',
                        'title' => __('Use CMS time?'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => 0,
                        'helpText' => __('Set the device time using the CMS. Only available on rooted devices or system signed players.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    )
                )
            )
    );
