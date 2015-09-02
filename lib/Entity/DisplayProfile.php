<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayProfile.php)
 */


namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Xibo\Helper\Log;
use Xibo\Helper\Theme;
use Xibo\Storage\PDOConnect;

/**
 * Class DisplayProfile
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class DisplayProfile
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID of this Display Profile")
     * @var int
     */
    public $displayProfileId;

    /**
     * @SWG\Property(description="The name of this Display Profile")
     * @var string
     */
    public $name;

    /**
     * @SWG\Property(description="The player type that this Display Profile is for")
     * @var string
     */
    public $type;

    /**
     * @SWG\Property(description="The configuration options for this Profile")
     * @var string[]
     */
    public $config;

    /**
     * @SWG\Property(description="A flag indicating if this profile should be used as the Default for the client type")
     * @var int
     */
    public $isDefault;

    /**
     * @SWG\Property(description="The userId of the User that owns this profile")
     * @var int
     */
    public $userId;

    /**
     * @SWG\Property(description="The default configuration options for this Profile")
     * @var string[]
     */
    public $configDefault;

    /**
     * @SWG\Property(description="Array of tab names to logically group the configuration options")
     * @var string[]
     */
    public $configTabs;

    public function getId()
    {
        return $this->displayProfileId;
    }

    public function getOwnerId()
    {
        return $this->userId;
    }

    public function load()
    {
        $this->config = json_decode($this->config, true);
        Log::debug('Config loaded [%d]: %s', count($this->config), json_encode($this->config, JSON_PRETTY_PRINT));

        $this->configDefault = $this->loadFromFile();
        $this->configTabs = $this->configDefault[$this->type]['tabs'];
        $this->configDefault = $this->configDefault[$this->type]['settings'];

        // Just populate the values with the defaults if the values aren't set already
        for ($i = 0; $i < count($this->configDefault); $i++) {
            $this->configDefault[$i]['value'] = isset($this->configDefault[$i]['value']) ? $this->configDefault[$i]['value'] : $this->configDefault[$i]['default'];
        }

        // Override the defaults
        for ($i = 0; $i < count($this->configDefault); $i++) {
            // Does this setting exist in our store?
            for ($j = 0; $j < count($this->config); $j++) {
                // If we have found our default config setting
                if ($this->configDefault[$i]['name'] == $this->config[$j]['name']) {
                    // Override the the default with our setting
                    $this->configDefault[$i]['value'] = $this->config[$j]['value'];
                    break;
                }
            }
        }
    }

    /**
     * Validate
     */
    public function validate()
    {
        if (!v::string()->notEmpty()->validate($this->name))
            throw new \InvalidArgumentException(__('Missing name'));

        if (!v::string()->notEmpty()->validate($this->type))
            throw new \InvalidArgumentException(__('Missing type'));

        // Check there is only 1 default (including this one)
        $count = PDOConnect::select('SELECT COUNT(*) AS cnt FROM `displayprofile` WHERE type = :type AND isdefault = 1 AND displayprofileid <> :displayProfileId', [
            'type' => $this->type,
            'displayProfileId' => $this->displayProfileId
        ]);

        if ($count[0]['cnt'] + $this->isDefault > 1)
            throw new \InvalidArgumentException(__('Only 1 default per display type is allowed.'));
    }

    /**
     * Save
     * @param bool $validate
     */
    public function save($validate = true)
    {
        if ($validate)
            $this->validate();

        if ($this->displayProfileId == null || $this->displayProfileId == 0)
            $this->add();
        else
            $this->edit();
    }

    public function delete()
    {
        PDOConnect::update('DELETE FROM `displayprofile` WHERE displayprofileid = :displayProfileId', ['displayProfileId' => $this->displayProfileId]);
    }

    private function add()
    {
        $this->displayProfileId = PDOConnect::insert('
            INSERT INTO `displayprofile` (`name`, type, config, isdefault, userid)
              VALUES (:name, :type, :config, :isDefault, :userId)
        ', [
            'name' => $this->name,
            'type' => $this->type,
            'config' => ($this->config == '') ? '[]' : json_encode($this->config),
            'isDefault' => $this->isDefault,
            'userId' => $this->userId
        ]);
    }

    private function edit()
    {
        PDOConnect::update('
          UPDATE `displayprofile`
            SET `name` = :name, type = :type, config = :config, isdefault = :isDefault
           WHERE displayprofileid = :displayProfileId', [
            'name' => $this->name,
            'type' => $this->type,
            'config' => ($this->config == '') ? '[]' : json_encode($this->config),
            'isDefault' => $this->isDefault,
            'displayProfileId' => $this->displayProfileId
        ]);
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->configDefault;
    }

    /**
     * Load the config from the file
     */
    private function loadFromFile()
    {
        return array(
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
                        'type' => 'int',
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
                        'type' => 'string',
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
                        'type' => 'string',
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
                        'type' => 'checkbox',
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
                        'type' => 'checkbox',
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
                        'type' => 'double',
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
                        'type' => 'double',
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
                        'type' => 'double',
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
                        'type' => 'double',
                        'fieldType' => 'number',
                        'default' => '0',
                        'helpText' => __('The top pixel position the display window should be sized from.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'ClientInfomationCtrlKey',
                        'tabId' => 'trouble',
                        'title' => __('CTRL Key required to access Client Information Screen?'),
                        'type' => 'checkbox',
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
                        'type' => 'string',
                        'fieldType' => 'text',
                        'default' => 'I',
                        'helpText' => __('Which key should activate the client information screen? A single character.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'LogLevel',
                        'tabId' => 'trouble',
                        'title' => __('Log Level'),
                        'type' => 'string',
                        'fieldType' => 'dropdown',
                        'options' => array(
                            array('id' => 'audit', 'value' => 'Audit'),
                            array('id' => 'info', 'value' => 'Information'),
                            array('id' => 'error', 'value' => 'Error'),
                            array('id' => 'off', 'value' => 'Off')
                        ),
                        'default' => 'error',
                        'helpText' => __('The position of the cursor when the client starts up.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'LogToDiskLocation',
                        'tabId' => 'trouble',
                        'title' => __('Log file path name.'),
                        'type' => 'string',
                        'fieldType' => 'text',
                        'default' => '',
                        'helpText' => __('Create a log file on disk in this location. Please enter a fully qualified path.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'ShowInTaskbar',
                        'tabId' => 'advanced',
                        'title' => __('Show the icon in the task bar?'),
                        'type' => 'checkbox',
                        'fieldType' => 'checkbox',
                        'default' => 1,
                        'helpText' => __('Should the application icon be shown in the task bar?'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'CursorStartPosition',
                        'tabId' => 'advanced',
                        'title' => __('Cursor Start Position'),
                        'type' => 'string',
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
                        'type' => 'checkbox',
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
                        'type' => 'int',
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
                        'type' => 'checkbox',
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
                        'type' => 'checkbox',
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
                        'type' => 'checkbox',
                        'fieldType' => 'checkbox',
                        'default' => 1,
                        'helpText' => __('Expire Modified Layouts immediately on change. This means a layout can be cut during playback if it receives an update from the CMS'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'MaxConcurrentDownloads',
                        'tabId' => 'advanced',
                        'title' => __('Maximum concurrent downloads'),
                        'type' => 'int',
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
                        'type' => 'string',
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
                        'type' => 'checkbox',
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
                        'type' => 'checkbox',
                        'fieldType' => 'checkbox',
                        'default' => 0,
                        'helpText' => __('When enabled the client will send the current layout to the CMS each time it changes. Warning: This is bandwidth intensive and should be disabled unless on a LAN.'),
                        'enabled' => Theme::getConfig('client_sendCurrentLayoutAsStatusUpdate_enabled', true),
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'ScreenShotRequestInterval',
                        'tabId' => 'advanced',
                        'title' => __('Screen shot interval'),
                        'type' => 'int',
                        'fieldType' => 'number',
                        'default' => 0,
                        'helpText' => __('The duration between status screen shots in minutes. 0 to disable. Warning: This is bandwidth intensive.'),
                        'enabled' => Theme::getConfig('client_screenShotRequestInterval_enabled', true),
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'MaxLogFileUploads',
                        'tabId' => 'advanced',
                        'title' => __('Limit the number of log files uploaded concurrently'),
                        'type' => 'int',
                        'fieldType' => 'number',
                        'default' => 3,
                        'helpText' => __('The number of log files to upload concurrently. The lower the number the longer it will take, but the better for memory usage.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    )
                )
            ),
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
                        'type' => 'string',
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
                        'type' => 'string',
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
                        'type' => 'int',
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
                        'name' => 'downloadStartWindow',
                        'tabId' => 'general',
                        'title' => __('Download Window Start Time'),
                        'type' => 'string',
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
                        'type' => 'string',
                        'fieldType' => 'timePicker',
                        'default' => 0,
                        'helpText' => __('The end of the time window to connect to the CMS and download updates.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'orientation',
                        'tabId' => 'location',
                        'title' => __('Orientation'),
                        'type' => 'int',
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
                        'name' => 'screenDimensions',
                        'tabId' => 'location',
                        'title' => __('Screen Dimensions'),
                        'type' => 'string',
                        'fieldType' => 'text',
                        'default' => '',
                        'helpText' => __('Override the screen dimensions (left,top,width,height). Requires restart. Care should be taken to ensure these are within the actual screen size.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'blacklistVideo',
                        'tabId' => 'trouble',
                        'title' => __('Blacklist Videos?'),
                        'type' => 'checkbox',
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
                        'type' => 'checkbox',
                        'fieldType' => 'checkbox',
                        'default' => 0,
                        'helpText' => __('Store all HTML resources on the Internal Storage? Should be selected if the device cannot display text, ticker, dataset media.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'startOnBoot',
                        'tabId' => 'advanced',
                        'title' => __('Start during device start up?'),
                        'type' => 'checkbox',
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
                        'type' => 'int',
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
                        'type' => 'int',
                        'fieldType' => 'text',
                        'default' => 30,
                        'helpText' => __('How long should the Action Bar be shown for, in seconds?'),
                        'validation' => 'numeric',
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'autoRestart',
                        'tabId' => 'advanced',
                        'title' => __('Automatic Restart'),
                        'type' => 'checkbox',
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
                        'type' => 'int',
                        'fieldType' => 'text',
                        'default' => 60,
                        'helpText' => __('The number of seconds to wait before starting the application after the device has started. Minimum 10.'),
                        'validation' => 'numeric',
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'sendCurrentLayoutAsStatusUpdate',
                        'tabId' => 'advanced',
                        'title' => __('Notify current layout'),
                        'type' => 'checkbox',
                        'fieldType' => 'checkbox',
                        'default' => 0,
                        'helpText' => __('When enabled the client will send the current layout to the CMS each time it changes. Warning: This is bandwidth intensive and should be disabled unless on a LAN.'),
                        'enabled' => Theme::getConfig('client_sendCurrentLayoutAsStatusUpdate_enabled', true),
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'screenShotRequestInterval',
                        'tabId' => 'advanced',
                        'title' => __('Screen shot interval'),
                        'type' => 'int',
                        'fieldType' => 'number',
                        'default' => 0,
                        'helpText' => __('The duration between status screen shots in minutes. 0 to disable. Warning: This is bandwidth intensive.'),
                        'enabled' => Theme::getConfig('client_screenShotRequestInterval_enabled', true),
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'expireModifiedLayouts',
                        'tabId' => 'advanced',
                        'title' => __('Expire Modified Layouts?'),
                        'type' => 'checkbox',
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
                        'type' => 'string',
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
                        'type' => 'string',
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
                        'type' => 'string',
                        'fieldType' => 'timePicker',
                        'default' => 0,
                        'helpText' => __('The end of the time window to install application updates.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'webViewPluginState',
                        'tabId' => 'advanced',
                        'title' => __('WebView Plugin State'),
                        'type' => 'string',
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
                        'name' => 'hardwareAccelerateWebViewMode',
                        'tabId' => 'advanced',
                        'title' => __('Hardware Accelerate Web Content'),
                        'type' => 'string',
                        'fieldType' => 'dropdown',
                        'options' => array(
                            array('id' => '0', 'value' => __('Off')),
                            array('id' => '2', 'value' => __('Off when transparent')),
                            array('id' => '1', 'value' => __('On'))
                        ),
                        'default' => '2',
                        'helpText' => __('Mode for hardware acceleration of web based content.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'timeSyncFromCms',
                        'tabId' => 'advanced',
                        'title' => __('Use CMS time?'),
                        'type' => 'checkbox',
                        'fieldType' => 'checkbox',
                        'default' => 0,
                        'helpText' => __('Set the device time using the CMS. Only available on rooted devices or system signed players.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'statsEnabled',
                        'tabId' => 'general',
                        'title' => __('Enable stats reporting?'),
                        'type' => 'checkbox',
                        'fieldType' => 'checkbox',
                        'default' => 1,
                        'helpText' => __('Should the application send proof of play stats to the CMS.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    )
                )
            )
        );
    }
}