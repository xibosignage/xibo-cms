<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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
namespace Xibo\Entity;

use Carbon\Carbon;
use Respect\Validation\Validator as v;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;


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

    public $isCustom;

    /** @var  string the client type */
    private $clientType;

    /** @var array Combined configuration */
    private $configCombined = [];

    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    /**
     * @var CommandFactory
     */
    private $commandFactory;
    /**
     * @var DisplayProfileFactory
     */
    private $displayProfileFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param ConfigServiceInterface $config
     * @param CommandFactory $commandFactory
     * @param DisplayProfileFactory $displayProfileFactory
     */
    public function __construct($store, $log, $dispatcher, $config, $commandFactory, $displayProfileFactory)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);

        $this->configService = $config;
        $this->commandFactory = $commandFactory;
        $this->displayProfileFactory = $displayProfileFactory;
    }

    public function __clone()
    {
        $this->displayProfileId = null;
        $this->commands = [];
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
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getSetting($setting, $default = null, $fromDefault = false): mixed
    {
        $this->load();

        $configs = ($fromDefault) ? $this->configDefault : $this->getProfileConfig();

        foreach ($configs as $config) {
            if ($config['name'] == $setting || $config['name'] == ucfirst($setting)) {
                $default = $config['value'] ?? ($config['default'] ?? $default);
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
     * @throws \Xibo\Support\Exception\NotFoundException
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
    private function mergeConfigs($default, $override): array
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
     * @return bool
     */
    public function isCustom(): bool
    {
        return $this->isCustom;
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
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function assignCommand($command)
    {
        $this->load([]);

        $assigned = false;

        foreach ($this->commands as $alreadyAssigned) {
            /* @var Command $alreadyAssigned */
            if ($alreadyAssigned->getId() == $command->getId()) {
                $alreadyAssigned->commandString = $command->commandString;
                $alreadyAssigned->validationString = $command->validationString;
                $alreadyAssigned->createAlertOn = $command->createAlertOn;
                $assigned = true;
                break;
            }
        }

        if (!$assigned) {
            $this->commands[] = $command;
        }
    }

    /**
     * Unassign Command
     * @param Command $command
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function unassignCommand($command)
    {
        $this->load([]);

        $this->commands = array_udiff($this->commands, [$command], function ($a, $b) {
           /**
            * @var Command $a
            * @var Command $b
            */
            return $a->getId() - $b->getId();
        });
    }

    /**
     * Sets the Owner
     * @param int $ownerId
     */
    public function setOwner($ownerId)
    {
        $this->userId = $ownerId;
    }

    /**
     * Load
     * @param array $options
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function load($options = []): void
    {
        $this->getLog()->debug('load: Loading display profile, type: ' . $this->clientType
            . ' id: ' . $this->displayProfileId);

        $options = array_merge([
            'loadConfig' => true,
            'loadCommands' => true
        ], $options);

        if ($this->loaded) {
            return;
        }

        // Load in our default config from this class, based on the client type we are
        $this->configDefault = $this->displayProfileFactory->loadForType($this->getClientType());

        // Get our combined config
        $this->configCombined = [];

        if ($options['loadConfig']) {
            if (!is_array($this->config) && !empty($this->config)) {
                $this->config = json_decode($this->config, true);
            }

            // handle cases when config is empty
            if (empty($this->config)) {
                $this->config = [];
            }

            $this->getLog()->debug('Config loaded: ' . json_encode($this->config, JSON_PRETTY_PRINT));

            // Populate our combined config accordingly
            $this->configCombined = $this->mergeConfigs($this->configDefault, $this->config);
        }

        $this->getLog()->debug('Config Combined is: ' . json_encode($this->configCombined, JSON_PRETTY_PRINT));

        // Load any commands
        if ($options['loadCommands']) {
            $this->commands = $this->commandFactory->getByDisplayProfileId($this->displayProfileId, $this->type);
        }

        // We are loaded
        $this->loaded = true;
    }

    /**
     * Validate
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (!v::stringType()->notEmpty()->validate($this->name))
            throw new InvalidArgumentException(__('Missing name'), 'name');

        if (!v::stringType()->notEmpty()->validate($this->type))
            throw new InvalidArgumentException(__('Missing type'), 'type');

        for ($j = 0; $j < count($this->config); $j++) {
            if ($this->config[$j]['name'] == 'MaxConcurrentDownloads' && $this->config[$j]['value'] <= 0 && $this->type = 'windows') {
                throw new InvalidArgumentException(__('Concurrent downloads must be a positive number'), 'MaxConcurrentDownloads');
            }

            if ($this->config[$j]['name'] == 'maxRegionCount' && !v::intType()->min(0)->validate($this->config[$j]['value'])) {
                throw new InvalidArgumentException(__('Maximum Region Count must be a positive number'), 'maxRegionCount');
            }
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

        if ($count[0]['cnt'] + $this->isDefault > 1) {
            throw new InvalidArgumentException(__('Only 1 default per display type is allowed.'), 'isDefault');
        }
    }

    /**
     * Save
     * @param bool $validate
     * @throws InvalidArgumentException
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

        if ($this->isDefault === 1) {
            throw new InvalidArgumentException(__('Cannot delete default Display Profile.'), 'isDefault');
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
              INSERT INTO `lkcommanddisplayprofile` (
                  `commandId`,
                  `displayProfileId`,
                  `commandString`,
                  `validationString`,
                  `createAlertOn`
              )
              VALUES (
                  :commandId,
                  :displayProfileId,
                  :commandString,
                  :validationString,
                  :createAlertOn    
              )
              ON DUPLICATE KEY UPDATE 
                  commandString = :commandString2,
                  validationString = :validationString2,
                  createAlertOn = :createAlertOn2
            ', [
                'commandId' => $command->commandId,
                'displayProfileId' => $this->displayProfileId,
                'commandString' => $command->commandString,
                'validationString' => $command->validationString,
                'createAlertOn' => $command->createAlertOn,
                'commandString2' => $command->commandString,
                'validationString2' => $command->validationString,
                'createAlertOn2' => $command->createAlertOn
            ]);
        }

        // Unlink
        $params = ['displayProfileId' => $this->displayProfileId];

        $sql = 'DELETE FROM `lkcommanddisplayprofile`
                WHERE `displayProfileId` = :displayProfileId AND `commandId` NOT IN (0';

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
            INSERT INTO `displayprofile` (`name`, type, config, isdefault, userid, isCustom)
              VALUES (:name, :type, :config, :isDefault, :userId, :isCustom)
        ', [
            'name' => $this->name,
            'type' => $this->type,
            'config' => ($this->config == '') ? '[]' : json_encode($this->config),
            'isDefault' => $this->isDefault,
            'userId' => $this->userId,
            'isCustom' => $this->isCustom ?? 0
        ]);
    }

    private function edit()
    {
        $this->getStore()->update('
          UPDATE `displayprofile`
            SET `name` = :name, type = :type, config = :config, isdefault = :isDefault, isCustom = :isCustom
           WHERE displayprofileid = :displayProfileId', [
            'name' => $this->name,
            'type' => $this->type,
            'config' => ($this->config == '') ? '[]' : json_encode($this->config),
            'isDefault' => $this->isDefault,
            'isCustom' => $this->isCustom ?? 0,
            'displayProfileId' => $this->displayProfileId
        ]);
    }

    /**
     * @return array
     */
    public function getProfileConfig(): array
    {
        return $this->configCombined;
    }

    public function getCustomEditTemplate()
    {
        if ($this->isCustom()) {
            return $this->displayProfileFactory->getCustomEditTemplate($this->getClientType());
        } else {
            $this->getLog()->error(
                'Attempting to get Custom Edit template for Display Profile ' .
                $this->getClientType() . ' that is not custom'
            );
            return null;
        }
    }

    public function handleCustomFields($sanitizedParams, $config = null, $display = null)
    {
        return $this->displayProfileFactory->handleCustomFields($this, $sanitizedParams, $config, $display);
    }

    /**
     * Does this display profile has elevated log level?
     * @return bool
     * @throws NotFoundException
     */
    public function isElevatedLogging(): bool
    {
        $elevatedUntil = $this->getSetting('elevateLogsUntil', 0);

        $this->getLog()->debug(sprintf(
            'Testing whether this display profile has elevated log level. %d vs %d.',
            $elevatedUntil,
            Carbon::now()->format('U')
        ));

        return (!empty($elevatedUntil) && $elevatedUntil >= Carbon::now()->format('U'));
    }
}
