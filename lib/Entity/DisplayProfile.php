<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DisplayProfile.php)
 */


namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\DisplayProfileLoadedEvent;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Factory\CommandFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;


/**
 * Class DisplayProfile
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class DisplayProfile implements \JsonSerializable
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

    /**
     * Commands associated with this profile.
     * @var array[Command]
     */
    public $commands = [];

    /** @var  string the client type */
    private $clientType;

    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    /** @var EventDispatcherInterface  */
    private $dispatcher;

    /**
     * @var CommandFactory
     */
    private $commandFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param EventDispatcherInterface $dispatcher
     * @param CommandFactory $commandFactory
     */
    public function __construct($store, $log, $config, $dispatcher, $commandFactory)
    {
        $this->setCommonDependencies($store, $log);

        $this->configService = $config;
        $this->dispatcher = $dispatcher;
        $this->commandFactory = $commandFactory;
    }

    /**
     * Get Id
     * @return int
     */
    public function getId()
    {
        return $this->displayProfileId;
    }

    /**
     * @return int
     */
    public function getOwnerId()
    {
        return $this->userId;
    }

    /**
     * @param $clientType
     */
    public function setClientType($clientType)
    {
        $this->clientType = $clientType;
    }

    /**
     * Get the client type
     * @return string
     */
    public function getClientType()
    {
        return (empty($this->clientType)) ? $this->type : $this->clientType;
    }

    /**
     * Assign Command
     * @param Command $command
     */
    public function assignCommand($command)
    {
        $this->load();

        $assigned = false;

        foreach ($this->commands as $alreadyAssigned) {
            /* @var Command $alreadyAssigned */
            if ($alreadyAssigned->getId() == $command->getId()) {
                $alreadyAssigned->commandString = $command->commandString;
                $alreadyAssigned->validationString = $command->validationString;
                $assigned = true;
                break;
            }
        }

        if (!$assigned)
            $this->commands[] = $command;
    }

    /**
     * Unassign Command
     * @param Command $command
     */
    public function unassignCommand($command)
    {
        $this->load();

        $this->commands = array_udiff($this->commands, [$command], function ($a, $b) {
           /**
            * @var Command $a
            * @var Command $b
            */
            return $a->getId() - $b->getId();
        });
    }

    /**
     * Load
     */
    public function load()
    {
        if ($this->loaded)
            return;

        $this->config = json_decode($this->config, true);
        $this->getLog()->debug('Config loaded [%d]: %s', count($this->config), json_encode($this->config, JSON_PRETTY_PRINT));

        $this->configDefault = $this->loadFromFile();

        if (array_key_exists($this->type, $this->configDefault)) {
            $this->configTabs = $this->configDefault[$this->type]['tabs'];
            $this->configDefault = $this->configDefault[$this->type]['settings'];
        } else {
            $this->getLog()->debug('Unknown type for Display Profile: ' . $this->type);
            $this->configTabs = $this->configDefault['unknown']['tabs'];
            $this->configDefault = $this->configDefault['unknown']['settings'];
        }

        // We've loaded a profile
        // dispatch an event with a reference to this object, allowing subscribers to modify the config before we
        // continue further.
        $this->dispatcher->dispatch(DisplayProfileLoadedEvent::NAME, new DisplayProfileLoadedEvent($this));

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

        // Load any commands
        $this->commands = $this->commandFactory->getByDisplayProfileId($this->displayProfileId);

        // We are loaded
        $this->loaded = true;
    }

    /**
     * Validate
     */
    public function validate()
    {
        if (!v::stringType()->notEmpty()->validate($this->name))
            throw new InvalidArgumentException(__('Missing name'), 'name');

        if (!v::stringType()->notEmpty()->validate($this->type))
            throw new InvalidArgumentException(__('Missing type'), 'type');

        for ($j = 0; $j < count($this->config); $j++) {
            if ($this->config[$j]['name'] == 'MaxConcurrentDownloads' && $this->config[$j]['value'] <= 0 && $this->type = 'windows')
                throw new InvalidArgumentException(__('Concurrent downloads must be a positive number'), 'MaxConcurrentDownloads');
        }
        // Check there is only 1 default (including this one)
        $sql = '
          SELECT COUNT(*) AS cnt
            FROM `displayprofile`
           WHERE `type` = :type
            AND isdefault = 1
        ';

        $params = ['type' => $this->type];

        if ($this->displayProfileId != 0) {
            $sql .= ' AND displayprofileid <> :displayProfileId ';
            $params['displayProfileId'] = $this->displayProfileId;
        }

        $count = $this->getStore()->select($sql, $params);

        if ($count[0]['cnt'] + $this->isDefault > 1)
            throw new InvalidArgumentException(__('Only 1 default per display type is allowed.'), 'isDefault');
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

        $this->manageAssignments();
    }

    /**
     * Delete
     */
    public function delete()
    {
        $this->commands = [];
        $this->manageAssignments();

        $this->getStore()->update('DELETE FROM `displayprofile` WHERE displayprofileid = :displayProfileId', ['displayProfileId' => $this->displayProfileId]);
    }

    /**
     * Manage Assignments
     */
    private function manageAssignments()
    {
        $this->getLog()->debug('Managing Assignment for Display Profile: %d. %d commands.', $this->displayProfileId, count($this->commands));

        // Link
        foreach ($this->commands as $command) {
            /* @var Command $command */
            $this->getStore()->update('
              INSERT INTO `lkcommanddisplayprofile` (`commandId`, `displayProfileId`, `commandString`, `validationString`) VALUES
                (:commandId, :displayProfileId, :commandString, :validationString) ON DUPLICATE KEY UPDATE commandString = :commandString2, validationString = :validationString2
            ', [
                'commandId' => $command->commandId,
                'displayProfileId' => $this->displayProfileId,
                'commandString' => $command->commandString,
                'validationString' => $command->validationString,
                'commandString2' => $command->commandString,
                'validationString2' => $command->validationString
            ]);
        }

        // Unlink
        $params = ['displayProfileId' => $this->displayProfileId];

        $sql = 'DELETE FROM `lkcommanddisplayprofile` WHERE `displayProfileId` = :displayProfileId AND `commandId` NOT IN (0';

        $i = 0;
        foreach ($this->commands as $command) {
            /* @var Command $command */
            $i++;
            $sql .= ',:commandId' . $i;
            $params['commandId' . $i] = $command->commandId;
        }

        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }

    private function add()
    {
        $this->displayProfileId = $this->getStore()->insert('
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
        $this->getStore()->update('
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
    public function getProfileConfig()
    {
        return $this->configDefault;
    }

    /**
     * Load the config from the file
     */
    private function loadFromFile()
    {
        return array(
            'unknown' => [
                'synonym' => 'unknown',
                'tabs' => [],
                'settings' => []
            ],
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
                        'title' => __('Collect interval'),
                        'type' => 'int',
                        'fieldType' => 'dropdown',
                        'options' => array(
                            array('id' => 60, 'value' => __('1 minute')),
                            array('id' => 300, 'value' => __('5 minutes')),
                            array('id' => 600, 'value' => __('10 minutes')),
                            array('id' => 900, 'value' => __('15 minutes')),
                            array('id' => 1800, 'value' => __('30 minutes')),
                            array('id' => 3600, 'value' => __('1 hour')),
                            array('id' => 14400, 'value' => __('4 hours')),
                            array('id' => 43200, 'value' => __('12 hours')),
                            array('id' => 86400, 'value' => __('24 hours'))
                        ),
                        'default' => 900,
                        'helpText' => __('How often should the Player check for new content.'),
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
                        'default' => '00:00',
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
                        'default' => '00:00',
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
                        'default' => $this->configService->GetSetting('DISPLAY_PROFILE_STATS_DEFAULT', 0),
                        'helpText' => __('Should the application send proof of play stats to the CMS.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'XmrNetworkAddress',
                        'tabId' => 'general',
                        'title' => __('XMR Public Address'),
                        'type' => 'string',
                        'fieldType' => 'text',
                        'default' => '',
                        'helpText' => __('Please enter the public address for XMR.'),
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
                        'helpText' => __('The logging level that should be recorded by the Player.'),
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
                            array('id' => 'Unchanged', 'value' => __('Unchanged')),
                            array('id' => 'Top Left', 'value' => __('Top Left')),
                            array('id' => 'Top Right', 'value' => __('Top Right')),
                            array('id' => 'Bottom Left', 'value' => __('Bottom Left')),
                            array('id' => 'Bottom Right', 'value' => __('Bottom Right')),
                        ),
                        'default' => 'Unchanged',
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
                        'helpText' => __('If an empty layout is detected how long (in seconds) should it remain on screen? Must be greater than 1.'),
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
                        'helpText' => __('[No longer supported in 1.8+ players!] CEF is Chrome Embedded and offers up to date web rendering. If unselected the default Internet Explorer control will be used. The Player software will need to be restarted after making this change.'),
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
                        'enabled' => ($this->configService->GetSetting('DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED', 0) == 1),
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
                        'enabled' => ($this->configService->GetSetting('DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED', 0) == 1),
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'ScreenShotSize',
                        'tabId' => 'advanced',
                        'title' => __('Screen Shot Size'),
                        'type' => 'int',
                        'fieldType' => 'number',
                        'default' => $this->configService->GetSetting('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT', 200),
                        'helpText' => __('The size of the largest dimension. Empty or 0 means the screen size.'),
                        'enabled' => true,
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
                    ),
                    array(
                        'name' => 'EmbeddedServerPort',
                        'tabId' => 'advanced',
                        'title' => __('Embedded Web Server Port'),
                        'type' => 'int',
                        'fieldType' => 'number',
                        'default' => 9696,
                        'helpText' => __('The port number to use for the embedded web server on the Player. Only change this if there is a port conflict reported on the status screen.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'PreventSleep',
                        'tabId' => 'advanced',
                        'title' => __('Prevent Sleep?'),
                        'type' => 'checkbox',
                        'fieldType' => 'checkbox',
                        'default' => 1,
                        'helpText' => __('Stop the player PC power management from Sleeping the PC'),
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
                            array('id' => 43200, 'value' => __('12 hours')),
                            array('id' => 86400, 'value' => __('24 hours'))
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
                        'default' => '00:00',
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
                        'default' => '00:00',
                        'helpText' => __('The end of the time window to connect to the CMS and download updates.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'xmrNetworkAddress',
                        'tabId' => 'general',
                        'title' => __('XMR Public Address'),
                        'type' => 'string',
                        'fieldType' => 'text',
                        'default' => '',
                        'helpText' => __('Please enter the public address for XMR.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'statsEnabled',
                        'tabId' => 'general',
                        'title' => __('Enable stats reporting?'),
                        'type' => 'checkbox',
                        'fieldType' => 'checkbox',
                        'default' => $this->configService->GetSetting('DISPLAY_PROFILE_STATS_DEFAULT', 0),
                        'helpText' => __('Should the application send proof of play stats to the CMS.'),
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
                        'name' => 'useSurfaceVideoView',
                        'tabId' => 'trouble',
                        'title' => __('Use a SurfaceView for Video Rendering?'),
                        'type' => 'checkbox',
                        'fieldType' => 'checkbox',
                        'default' => 1,
                        'helpText' => __('If the device is having trouble playing video, it may be useful to switch to a Surface View for Video Rendering.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'logLevel',
                        'tabId' => 'trouble',
                        'title' => __('Log Level'),
                        'type' => 'string',
                        'fieldType' => 'dropdown',
                        'options' => array(
                            array('id' => 'audit', 'value' => 'Audit'),
                            array('id' => 'error', 'value' => 'Error'),
                            array('id' => 'off', 'value' => 'Off')
                        ),
                        'default' => 'error',
                        'helpText' => __('The logging level that should be recorded by the Player.'),
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
                            array('id' => 1, 'value' => 'Timed'),
                            array('id' => 2, 'value' => 'Run Intent')
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
                        'name' => 'actionBarIntent',
                        'tabId' => 'advanced',
                        'title' => __('Action Bar Intent'),
                        'type' => 'string',
                        'fieldType' => 'text',
                        'default' => '',
                        'helpText' => __('When set to Run Intent, which intent should be run. Format is: Action|ExtraKey,ExtraMsg'),
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
                        'enabled' => ($this->configService->GetSetting('DISPLAY_PROFILE_CURRENT_LAYOUT_STATUS_ENABLED', 0) == 1),
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
                        'enabled' => ($this->configService->GetSetting('DISPLAY_PROFILE_SCREENSHOT_INTERVAL_ENABLED', 0) == 1),
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
                        'name' => 'screenShotSize',
                        'tabId' => 'advanced',
                        'title' => __('Screen Shot Size'),
                        'type' => 'int',
                        'fieldType' => 'number',
                        'default' => $this->configService->GetSetting('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT', 200),
                        'helpText' => __('The size of the largest dimension. Empty or 0 means the screen size.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'updateStartWindow',
                        'tabId' => 'advanced',
                        'title' => __('Update Window Start Time'),
                        'type' => 'string',
                        'fieldType' => 'timePicker',
                        'default' => '00:00',
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
                        'default' => '00:00',
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
                        'name' => 'webCacheEnabled',
                        'tabId' => 'advanced',
                        'title' => __('Enable caching of Web Resources?'),
                        'type' => 'checkbox',
                        'fieldType' => 'checkbox',
                        'default' => 0,
                        'helpText' => __('The standard browser cache will be used - we recommend this is switched off unless specifically required. Effects Web Page and Embedded.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'serverPort',
                        'tabId' => 'advanced',
                        'title' => __('Embedded Web Server Port'),
                        'type' => 'int',
                        'fieldType' => 'number',
                        'default' => 9696,
                        'helpText' => __('The port number to use for the embedded web server on the Player. Only change this if there is a port conflict reported on the status screen.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ),
                    array(
                        'name' => 'installWithLoadedLinkLibraries',
                        'tabId' => 'advanced',
                        'title' => __('Load Link Libraries for APK Update'),
                        'type' => 'checkbox',
                        'fieldType' => 'checkbox',
                        'default' => 1,
                        'helpText' => __('Should the update command include dynamic link libraries? Only change this if your updates are failing.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    )
                )
            ),
            'lg' => [
                'synonym' => 'xiboforwebos',
                'tabs' => [
                    ['id' => 'general', 'name' => __('General')],
                    ['id' => 'timers', 'name' => __('On/Off Time')],
                    ['id' => 'pictureOptions', 'name' => __('Picture')],
                    ['id' => 'lockOptions', 'name' => __('Lock')],
                    ['id' => 'advanced', 'name' => __('Advanced')],
                ],
                'settings' => [
                    [
                        'name' => 'emailAddress',
                        'tabId' => 'general',
                        'title' => __('Email Address'),
                        'type' => 'string',
                        'fieldType' => 'text',
                        'default' => '',
                        'helpText' => __('The email address will be used to license this client. This is the email address you provided when you purchased the licence.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ],
                    [
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
                            array('id' => 43200, 'value' => __('12 hours')),
                            array('id' => 86400, 'value' => __('24 hours'))
                        ),
                        'default' => 300,
                        'helpText' => __('How often should the Player check for new content.'),
                        'validation' => 'numeric',
                        'enabled' => true,
                        'groupClass' => NULL
                    ],
                    [
                        'name' => 'xmrNetworkAddress',
                        'tabId' => 'general',
                        'title' => __('XMR Public Address'),
                        'type' => 'string',
                        'fieldType' => 'text',
                        'default' => '',
                        'helpText' => __('Please enter the public address for XMR.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ],
                    [
                        'name' => 'statsEnabled',
                        'tabId' => 'general',
                        'title' => __('Enable stats reporting?'),
                        'type' => 'checkbox',
                        'fieldType' => 'checkbox',
                        'default' => $this->configService->GetSetting('DISPLAY_PROFILE_STATS_DEFAULT', 0),
                        'helpText' => __('Should the application send proof of play stats to the CMS.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ],
                    [
                        'name' => 'orientation',
                        'tabId' => 'general',
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
                        'helpText' => __('Set the orientation of the device.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ],
                    [
                        'name' => 'downloadStartWindow',
                        'tabId' => 'general',
                        'title' => __('Download Window Start Time'),
                        'type' => 'string',
                        'fieldType' => 'timePicker',
                        'default' => '00:00',
                        'helpText' => __('The start of the time window to connect to the CMS and download updates.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ],
                    [
                        'name' => 'downloadEndWindow',
                        'tabId' => 'general',
                        'title' => __('Download Window End Time'),
                        'type' => 'string',
                        'fieldType' => 'timePicker',
                        'default' => '00:00',
                        'helpText' => __('The end of the time window to connect to the CMS and download updates.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ],
                    [
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
                    ],
                    [
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
                    ],
                    [
                        'name' => 'screenShotSize',
                        'tabId' => 'advanced',
                        'title' => __('Screen Shot Size'),
                        'type' => 'int',
                        'fieldType' => 'dropdown',
                        'options' => [
                            ['id' => 1, 'value' => 'Thumbnail'],
                            ['id' => 2, 'value' => 'HD'],
                            ['id' => 3, 'value' => 'FHD'],
                        ],
                        'default' => 1,
                        'helpText' => __('The size of the screenshot to return when requested.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ],
                    [
                        'name' => 'mediaInventoryTimer',
                        'tabId' => 'advanced',
                        'title' => __('Send progress while downloading'),
                        'type' => 'int',
                        'fieldType' => 'text',
                        'default' => 0,
                        'helpText' => __('How often, in minutes, should the Display send its download progress while it is downloading new content?'),
                        'validation' => 'numeric',
                        'enabled' => true,
                        'groupClass' => NULL
                    ],
                    [
                        'name' => 'logLevel',
                        'tabId' => 'advanced',
                        'title' => __('Log Level'),
                        'type' => 'string',
                        'fieldType' => 'dropdown',
                        'options' => [
                            ['id' => 'audit', 'value' => 'Audit'],
                            ['id' => 'error', 'value' => 'Error'],
                            ['id' => 'off', 'value' => 'Off']
                        ],
                        'default' => 'error',
                        'helpText' => __('The logging level that should be recorded by the Player.'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ],
                    [
                        'name' => 'timers',
                        'tabId' => 'timers',
                        'title' => __('On/Off Timers'),
                        'type' => 'string',
                        'fieldType' => 'text',
                        'default' => '{}',
                        'helpText' => __('A JSON object indicating the on/off timers to set'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ],
                    [
                        'name' => 'pictureOptions',
                        'tabId' => 'pictureOptions',
                        'title' => __('Picture Options'),
                        'type' => 'string',
                        'fieldType' => 'text',
                        'default' => '{}',
                        'helpText' => __('A JSON object indicating the picture options to set'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ],
                    [
                        'name' => 'lockOptions',
                        'tabId' => 'lockOptions',
                        'title' => __('Lock Options'),
                        'type' => 'string',
                        'fieldType' => 'text',
                        'default' => '{}',
                        'helpText' => __('A JSON object indicating the lock options to set'),
                        'enabled' => true,
                        'groupClass' => NULL
                    ]
                ]
            ]
        );
    }
}