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
     * Commands associated with this profile.
     * @var Command[]
     */
    public $commands = [];

    /** @var  string the client type */
    private $clientType;

    /** @var array Combined configuration */
    private $configCombined = [];

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

    public function __clone()
    {
        $this->displayProfileId = null;
        $this->isDefault = 0;
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
     * Get Setting
     * @param $setting
     * @param null $default
     * @param bool $fromDefault
     * @return mixed
     */
    public function getSetting($setting, $default = null, $fromDefault = false)
    {
        $this->load();

        $configs = ($fromDefault) ? $this->configDefault : $this->getProfileConfig();

        foreach ($configs as $config) {
            if ($config['name'] == $setting || $config['name'] == ucfirst($setting)) {
                $default = array_key_exists('value', $config) ? $config['value'] : ((array_key_exists('default', $config)) ? $config['default'] : $default);
                break;
            }
        }

        return $default;
    }

    /**
     * Set setting
     * @param $setting
     * @param $value
     * @param boolean $ownConfig if provided will set the values on this object and not on the member config object
     * @param array|null $config
     * @return $this
     */
    public function setSetting($setting, $value, $ownConfig = true, &$config = null)
    {
        $this->load();

        $found = false;

        // Get the setting from default
        // Which object do we operate on.
        if ($ownConfig) {
            $config = $this->config;
            $default = $this->getSetting($setting, null, true);
        } else {
            // we are editing Display object, as such we want the $default to come from display profile assigned to our display
            $default = $this->getSetting($setting, null, false);
        }

        // Check to see if we have this setting already
        for ($i = 0; $i < count($config); $i++) {
            if ($config[$i]['name'] == $setting || $config[$i]['name'] == ucfirst($setting)) {
                // We found the setting - is the value different to the default?
                if ($value !== $default) {
                    $config[$i]['value'] = $value;
                    $config[$i]['name'] = lcfirst($setting);
                } else {
                    // the value is the same as the default - unset it
                    $this->getLog()->debug('Setting [' . $setting . '] identical to the default, unsetting.');
                    unset($config[$i]);
                    $config = array_values($config);
                }
                $found = true;
                break;
            }
        }

        if (!$found && $value !== $default) {
            $this->getLog()->debug('Setting [' . $setting . '] not yet in the profile config, and different to the default. ' . var_export($value, true) . ' --- ' . var_export($default, true));
            // The config option isn't in our array yet, so add it
            $config[] = [
                'name' => lcfirst($setting),
                'value' => $value
            ];
        }

        if ($ownConfig) {
            // Reset our object
            $this->config = $config;

            // Reload our combined array
            $this->configCombined = $this->mergeConfigs($this->configDefault, $this->config);
        }

        return $this;
    }

    /**
     * Merge two configs
     * @param $default
     * @param $override
     * @return array
     */
    private function mergeConfigs($default, $override)
    {
        foreach ($default as &$defaultItem) {
            for ($i = 0; $i < count($override); $i++) {
                if ($defaultItem['name'] == $override[$i]['name']) {
                    // merge
                    $defaultItem = array_merge($defaultItem, $override[$i]);
                    break;
                }
            }
        }

        // Merge the remainder
        return $default;
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
     * @param array $options
     */
    public function load($options = [])
    {
        $options = array_merge([
            'loadConfig' => true,
            'loadCommands' => true
        ], $options);

        if ($this->loaded) {
            return;
        }

        // Load in our default config from this class, based on the client type we are
        $this->configDefault = $this->loadForType();

        $this->getLog()->debug('Config Default is: ' . json_encode($this->configDefault, JSON_PRETTY_PRINT));

        // Get our combined config
        $this->configCombined = [];

        if ($options['loadConfig']) {
            // Decode the config string (unless its already an array)
            if (!is_array($this->config)) {
                $this->config = json_decode($this->config, true);
            }
            $this->getLog()->debug('Config loaded: ' . json_encode($this->config, JSON_PRETTY_PRINT));

            // We've loaded a profile
            // dispatch an event with a reference to this object, allowing subscribers to modify the config before we
            // continue further.
            $this->dispatcher->dispatch(DisplayProfileLoadedEvent::NAME, new DisplayProfileLoadedEvent($this));

            // Populate our combined config accordingly
            $this->configCombined = $this->mergeConfigs($this->configDefault, $this->config);
        }

        $this->getLog()->debug('Config Combined is: ' . json_encode($this->configCombined, JSON_PRETTY_PRINT));

        // Load any commands
        if ($options['loadCommands']) {
            $this->commands = $this->commandFactory->getByDisplayProfileId($this->displayProfileId);
        }

        // We are loaded
        $this->loaded = true;
    }

    /**
     * Validate
     * @throws \Xibo\Exception\InvalidArgumentException
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
     * @throws \Xibo\Exception\InvalidArgumentException
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
     * @throws InvalidArgumentException
     */
    public function delete()
    {
        $this->commands = [];
        $this->manageAssignments();

        if ($this->getStore()->exists('SELECT displayId FROM display WHERE displayProfileId = :displayProfileId', ['displayProfileId' => $this->displayProfileId]) ) {
            throw new InvalidArgumentException(__('This Display Profile is currently assigned to one or more Displays'), 'displayProfileId');
        }

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
        return $this->configCombined;
    }

    /**
     * Load the config from the file
     */
    private function loadForType()
    {
        $config = array(
            'unknown' => [],
            'windows' => [
                ['name' => 'collectInterval', 'default' => 300, 'type' => 'int'],
                ['name' => 'downloadStartWindow', 'default' => '00:00', 'type' => 'string'],
                ['name' => 'downloadEndWindow', 'default' => '00:00', 'type' => 'string'],
                ['name' => 'dayPartId', 'default' => null],
                ['name' => 'xmrNetworkAddress', 'default' => null, 'type' => 'string'],
                ['name' => 'statsEnabled', 'default' => (int)$this->configService->getSetting('DISPLAY_PROFILE_STATS_DEFAULT', 0), 'type' => 'checkbox'],
                ['name' => 'aggregationLevel', 'default' => $this->configService->getSetting('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT'), 'type' => 'string'],
                ['name' => 'powerpointEnabled', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'sizeX', 'default' => 0, 'type' => 'double'],
                ['name' => 'sizeY', 'default' => 0, 'type' => 'double'],
                ['name' => 'offsetX', 'default' => 0, 'type' => 'double'],
                ['name' => 'offsetY', 'default' => 0, 'type' => 'double'],
                ['name' => 'clientInfomationCtrlKey', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'clientInformationKeyCode', 'default' => 'I', 'type' => 'string'],
                ['name' => 'logLevel', 'default' => 'error', 'type' => 'string'],
                ['name' => 'logToDiskLocation', 'default' => null, 'type' => 'string'],
                ['name' => 'showInTaskbar', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'cursorStartPosition', 'default' => 'Unchanged', 'type' => 'string'],
                ['name' => 'doubleBuffering', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'emptyLayoutDuration', 'default' => 10, 'type' => 'int'],
                ['name' => 'enableMouse', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'enableShellCommands', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'expireModifiedLayouts', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'maxConcurrentDownloads', 'default' => 2, 'type' => 'int'],
                ['name' => 'shellCommandAllowList', 'default' => null, 'type' => 'string'],
                ['name' => 'sendCurrentLayoutAsStatusUpdate', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'screenShotRequestInterval', 'default' => 0, 'type' => 'int'],
                ['name' => 'screenShotSize', 'default' => (int)$this->configService->getSetting('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT', 200), 'type' => 'int'],
                ['name' => 'maxLogFileUploads', 'default' => 3, 'type' => 'int'],
                ['name' => 'embeddedServerPort', 'default' => 9696, 'type' => 'int'],
                ['name' => 'preventSleep', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'forceHttps', 'default' => 1, 'type' => 'checkbox']
            ],
            'android' => [
                ['name' => 'emailAddress', 'default' => null],
                ['name' => 'settingsPassword', 'default' => null],
                ['name' => 'collectInterval', 'default' => 300],
                ['name' => 'downloadStartWindow', 'default' => '00:00'],
                ['name' => 'downloadEndWindow', 'default' => '00:00'],
                ['name' => 'xmrNetworkAddress', 'default' => null],
                ['name' => 'statsEnabled', 'default' => (int)$this->configService->getSetting('DISPLAY_PROFILE_STATS_DEFAULT', 0), 'type' => 'checkbox'],
                ['name' => 'aggregationLevel', 'default' => $this->configService->getSetting('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT'), 'type' => 'string'],
                ['name' => 'orientation', 'default' => 0],
                ['name' => 'screenDimensions', 'default' => null],
                ['name' => 'blacklistVideo', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'storeHtmlOnInternal', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'useSurfaceVideoView', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'logLevel', 'default' => 'error'],
                ['name' => 'versionMediaId', 'default' => null],
                ['name' => 'startOnBoot', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'actionBarMode', 'default' => 1],
                ['name' => 'actionBarDisplayDuration', 'default' => 30],
                ['name' => 'actionBarIntent', 'default' => null],
                ['name' => 'autoRestart', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'startOnBootDelay', 'default' => 60],
                ['name' => 'sendCurrentLayoutAsStatusUpdate', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'screenShotRequestInterval', 'default' => 0],
                ['name' => 'expireModifiedLayouts', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'screenShotIntent', 'default' => null],
                ['name' => 'screenShotSize', 'default' => (int)$this->configService->getSetting('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT', 200)],
                ['name' => 'updateStartWindow', 'default' => '00:00'],
                ['name' => 'updateEndWindow', 'default' => '00:00'],
                ['name' => 'dayPartId', 'default' => null],
                ['name' => 'webViewPluginState', 'default' => 'DEMAND'],
                ['name' => 'hardwareAccelerateWebViewMode', 'default' => '2'],
                ['name' => 'timeSyncFromCms', 'default' => 0],
                ['name' => 'webCacheEnabled', 'default' => 0],
                ['name' => 'serverPort', 'default' => 9696],
                ['name' => 'installWithLoadedLinkLibraries', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'forceHttps', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'useMultipleVideoDecoders', 'default' => 'default', 'type' => 'string']
            ],
            'linux' => [
                ['name' => 'collectInterval', 'default' => 300],
                ['name' => 'downloadStartWindow', 'default' => '00:00'],
                ['name' => 'downloadEndWindow', 'default' => '00:00'],
                ['name' => 'dayPartId', 'default' => null],
                ['name' => 'xmrNetworkAddress', 'default' => null],
                ['name' => 'statsEnabled', 'default' => (int)$this->configService->getSetting('DISPLAY_PROFILE_STATS_DEFAULT', 0), 'type' => 'checkbox'],
                ['name' => 'aggregationLevel', 'default' => $this->configService->getSetting('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT'), 'type' => 'string'],
                ['name' => 'sizeX', 'default' => 0],
                ['name' => 'sizeY', 'default' => 0],
                ['name' => 'offsetX', 'default' => 0],
                ['name' => 'offsetY', 'default' => 0],
                ['name' => 'logLevel', 'default' => 'error'],
                ['name' => 'enableShellCommands', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'expireModifiedLayouts', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'maxConcurrentDownloads', 'default' => 2],
                ['name' => 'shellCommandAllowList', 'default' => null],
                ['name' => 'sendCurrentLayoutAsStatusUpdate', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'screenShotRequestInterval', 'default' => 0],
                ['name' => 'screenShotSize', 'default' => (int)$this->configService->getSetting('DISPLAY_PROFILE_SCREENSHOT_SIZE_DEFAULT', 200)],
                ['name' => 'maxLogFileUploads', 'default' => 3],
                ['name' => 'embeddedServerPort', 'default' => 9696],
                ['name' => 'preventSleep', 'default' => 1, 'type' => 'checkbox'],
                ['name' => 'forceHttps', 'default' => 1, 'type' => 'checkbox']
            ],
            'lg' => [
                ['name' => 'emailAddress', 'default' => null],
                ['name' => 'collectInterval', 'default' => 300],
                ['name' => 'downloadStartWindow', 'default' => '00:00'],
                ['name' => 'downloadEndWindow', 'default' => '00:00'],
                ['name' => 'dayPartId', 'default' => null],
                ['name' => 'xmrNetworkAddress', 'default' => null],
                ['name' => 'statsEnabled', 'default' => (int)$this->configService->getSetting('DISPLAY_PROFILE_STATS_DEFAULT', 0), 'type' => 'checkbox'],
                ['name' => 'aggregationLevel', 'default' => $this->configService->getSetting('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT'), 'type' => 'string'],
                ['name' => 'orientation', 'default' => 0],
                ['name' => 'logLevel', 'default' => 'error'],
                ['name' => 'versionMediaId', 'default' => null],
                ['name' => 'actionBarMode', 'default' => 1],
                ['name' => 'actionBarDisplayDuration', 'default' => 30],
                ['name' => 'sendCurrentLayoutAsStatusUpdate', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'mediaInventoryTimer', 'default' => 0],
                ['name' => 'screenShotSize', 'default' => 1],
                ['name' => 'timers', 'default' => '{}'],
                ['name' => 'pictureOptions', 'default' => '{}'],
                ['name' => 'lockOptions', 'default' => '{}'],
                ['name' => 'forceHttps', 'default' => 1, 'type' => 'checkbox']
            ],
            'sssp' => [
                ['name' => 'emailAddress', 'default' => null],
                ['name' => 'collectInterval', 'default' => 300],
                ['name' => 'downloadStartWindow', 'default' => '00:00'],
                ['name' => 'downloadEndWindow', 'default' => '00:00'],
                ['name' => 'dayPartId', 'default' => null],
                ['name' => 'xmrNetworkAddress', 'default' => null],
                ['name' => 'statsEnabled', 'default' => (int)$this->configService->getSetting('DISPLAY_PROFILE_STATS_DEFAULT', 0), 'type' => 'checkbox'],
                ['name' => 'aggregationLevel', 'default' => $this->configService->getSetting('DISPLAY_PROFILE_AGGREGATION_LEVEL_DEFAULT'), 'type' => 'string'],
                ['name' => 'orientation', 'default' => 0],
                ['name' => 'logLevel', 'default' => 'error'],
                ['name' => 'versionMediaId', 'default' => null],
                ['name' => 'actionBarMode', 'default' => 1],
                ['name' => 'actionBarDisplayDuration', 'default' => 30],
                ['name' => 'sendCurrentLayoutAsStatusUpdate', 'default' => 0, 'type' => 'checkbox'],
                ['name' => 'mediaInventoryTimer', 'default' => 0],
                ['name' => 'screenShotSize', 'default' => 1],
                ['name' => 'timers', 'default' => '{}'],
                ['name' => 'pictureOptions', 'default' => '{}'],
                ['name' => 'lockOptions', 'default' => '{}'],
                ['name' => 'forceHttps', 'default' => 1, 'type' => 'checkbox']
            ]
        );

        return $config[$this->getClientType()];
    }
}