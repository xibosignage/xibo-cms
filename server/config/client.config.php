<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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
            'settings' => array(
                    array(
                        'name' => 'collectInterval',
                        'title' => __('Collection Interval (seconds)'),
                        'type' => _INT,
                        'fieldType' => 'text',
                        'default' => 900,
                        'helpText' => __('The number of seconds between connections to the CMS.'),
                        'validation' => 'numeric'
                    ),
                    array(
                        'name' => 'powerpointEnabled',
                        'title' => __('Enable PowerPoint?'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => false,
                        'helpText' => __('Should Microsoft PowerPoint be Enabled?'),
                    ),
                    array(
                        'name' => 'statsEnabled',
                        'title' => __('Enable stats reporting?'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => true,
                        'helpText' => __('Should the application send proof of play stats to the CMS.'),
                    ),
                    array(
                        'name' => 'sizeX',
                        'title' => __('Width'),
                        'type' => _DOUBLE,
                        'fieldType' => 'text',
                        'default' => '0',
                        'helpText' => __('The Width of the Display Window. 0 means full width.')
                    ),
                    array(
                        'name' => 'sizeY',
                        'title' => __('Height'),
                        'type' => _DOUBLE,
                        'fieldType' => 'text',
                        'default' => '0',
                        'helpText' => __('The Height of the Display Window. 0 means full height.')
                    ),
                    array(
                        'name' => 'offsetX',
                        'title' => __('Left Coordinate'),
                        'type' => _DOUBLE,
                        'fieldType' => 'text',
                        'default' => '0',
                        'helpText' => __('The left pixel position the display window should be sized from.')
                    ),
                    array(
                        'name' => 'offsetY',
                        'title' => __('Top Coordinate'),
                        'type' => _DOUBLE,
                        'fieldType' => 'text',
                        'default' => '0',
                        'helpText' => __('The top pixel position the display window should be sized from.')
                    ),
                    array(
                        'name' => 'ShowInTaskbar',
                        'title' => __('Show the icon in the task bar?'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => true,
                        'helpText' => __('Should the application icon be shown in the task bar?'),
                    ),
                    array(
                        'name' => 'ClientInfomationCtrlKey',
                        'title' => __('CTRL Key required to access Client Information Screen?'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => false,
                        'helpText' => __('Should the client information screen require the CTRL key?')
                    ),
                    array(
                        'name' => 'ClientInformationKeyCode',
                        'title' => __('Key for Client Information Screen'),
                        'type' => _WORD,
                        'fieldType' => 'text',
                        'default' => 'I',
                        'helpText' => __('Which key should activate the client information screen? A single character.')
                    ),
                    array(
                        'name' => 'CursorStartPosition',
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
                        'helpText' => __('The position of the cursor when the client starts up.')
                    ),
                    array(
                        'name' => 'DoubleBuffering',
                        'title' => __('Enable Double Buffering'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => true,
                        'helpText' => __('Double buffering helps smooth the playback but should be disabled if graphics errors occur')
                    ),
                    array(
                        'name' => 'emptyLayoutDuration',
                        'title' => __('Duration for Empty Layouts'),
                        'type' => _INT,
                        'fieldType' => 'text',
                        'default' => true,
                        'helpText' => __('If an empty layout is detected how long should it remain on screen. Must be greater then 1.'),
                        'validation' => 'number'
                    ),
                    array(
                        'name' => 'EnableMouse',
                        'title' => __('Enable Mouse'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => true,
                        'helpText' => __('Enable the mouse.'),
                    ),
                    array(
                        'name' => 'EnableShellCommands',
                        'title' => __('Enable Shell Commands'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => true,
                        'helpText' => __('Enable the Shell Command module.'),
                    ),
                    array(
                        'name' => 'expireModifiedLayouts',
                        'title' => __('Expire Modified Layouts'),
                        'type' => _CHECKBOX,
                        'fieldType' => 'checkbox',
                        'default' => true,
                        'helpText' => __('Expire Modified Layouts immediately on change. This means a layout can be cut during playback if it receives an update from the CMS'),
                    ),
                    array(
                        'name' => 'LogLevel',
                        'title' => __('Log Level'),
                        'type' => _WORD,
                        'fieldType' => 'dropdown',
                        'options' => array(
                                array('id' => 'audit', 'value' => 'Audit'),
                                array('id' => 'info', 'value' => 'Information'),
                                array('id' => 'error', 'value' => 'Error')
                            ),
                        'default' => 'error',
                        'helpText' => __('The position of the cursor when the client starts up.')
                    ),
                    array(
                        'name' => 'LogToDiskLocation',
                        'title' => __('Log file path name.'),
                        'type' => _STRING,
                        'fieldType' => 'text',
                        'default' => '',
                        'helpText' => __('Create a log file on disk in this location. Please enter a fully qualified path.')
                    ),
                    array(
                        'name' => 'MaxConcurrentDownloads',
                        'title' => __('Maximum concurrent downloads'),
                        'type' => _INT,
                        'fieldType' => 'text',
                        'default' => '5',
                        'helpText' => __('The maximum number of concurrent downloads the client will attempt.')
                    ),
                    array(
                        'name' => 'ShellCommandAllowList',
                        'title' => __('Shell Command Allow List'),
                        'type' => _STRING,
                        'fieldType' => 'text',
                        'default' => '',
                        'helpText' => __('Which shell commands should the client execute?')
                    )
                )
            )/*,

        'ubuntu' => array(
            'synonym' => 'python',
            'settings' => array(

                )
            ),

        'android' => array(
            'synonym' => 'xiboforandroid',
            'settings' => array(

                )
            )*/
    );