<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

use Respect\Validation\Validator as v;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class Command
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Command implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(
     *  description="Command Id"
     * )
     * @var int
     */
    public $commandId;

    /**
     * @SWG\Property(
     *  description="Command Name"
     * )
     * @var string
     */
    public $command;

    /**
     * @SWG\Property(
     *  description="Unique Code"
     * )
     * @var string
     */
    public $code;

    /**
     * @SWG\Property(
     *  description="Description"
     * )
     * @var string
     */
    public $description;

    /**
     * @SWG\Property(
     *  description="User Id"
     * )
     * @var int
     */
    public $userId;

    /**
     * @SWG\Property(
     *  description="Command String"
     * )
     * @var string
     */
    public $commandString;

    /**
     * @SWG\Property(
     *  description="Validation String"
     * )
     * @var string
     */
    public $validationString;

    /**
     * @SWG\Property(
     *  description="DisplayProfileId if specific to a Display Profile"
     * )
     * @var int
     */
    public $displayProfileId;

    /**
     * @SWG\Property(
     *  description="Command String specific to the provided DisplayProfile"
     * )
     * @var string
     */
    public $commandStringDisplayProfile;

    /**
     * @SWG\Property(
     *  description="Validation String specific to the provided DisplayProfile"
     * )
     * @var string
     */
    public $validationStringDisplayProfile;

    /**
     * @SWG\Property(
     *  description="A comma separated list of player types this command is available on"
     * )
     * @var string
     */
    public $availableOn;

    /**
     * @SWG\Property(
     *  description="Define if execution of this command should create an alert on success, failure, always or never."
     * )
     * @var string
     */
    public $createAlertOn;

    /**
     * @SWG\Property(
     *     description="Create Alert On specific to the provided DisplayProfile."
     * )
     */
    public $createAlertOnDisplayProfile;

    /**
     * @SWG\Property(description="A comma separated list of groups/users with permissions to this Command")
     * @var string
     */
    public $groupsWithPermissions;

    /**
     * Command constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct($store, $log, $dispatcher)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);
    }

    /**
     * Get Id
     * @return int
     */
    public function getId()
    {
        return $this->commandId;
    }

    /**
     * Get OwnerId
     * @return int
     */
    public function getOwnerId()
    {
        return $this->userId;
    }

    /**
     * @return string
     */
    public function getCommandString()
    {
        return empty($this->commandStringDisplayProfile) ? $this->commandString : $this->commandStringDisplayProfile;
    }

    /**
     * @return string
     */
    public function getValidationString()
    {
        return empty($this->validationStringDisplayProfile)
            ? $this->validationString
            : $this->validationStringDisplayProfile;
    }

    /**
     * @return string
     */
    public function getCreateAlertOn(): string
    {
        return empty($this->createAlertOnDisplayProfile)
            ? $this->createAlertOn
            : $this->createAlertOnDisplayProfile;
    }

    /**
     * @return array
     */
    public function getAvailableOn()
    {
        return empty($this->availableOn) ? [] : explode(',', $this->availableOn);
    }

    /**
     * @param string $type Player Type
     * @return bool
     */
    public function isAvailableOn($type)
    {
        $availableOn = $this->getAvailableOn();
        return count($availableOn) <= 0 || in_array($type, $availableOn);
    }

    /**
     * @return bool
     */
    public function isReady()
    {
        return !empty($this->getCommandString());
    }

    /**
     * Validate
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (!v::stringType()->notEmpty()->length(1, 254)->validate($this->command)) {
            throw new InvalidArgumentException(
                __('Please enter a command name between 1 and 254 characters'),
                'command'
            );
        }

        if (!v::alpha('_')->NoWhitespace()->notEmpty()->length(1, 50)->validate($this->code)) {
            throw new InvalidArgumentException(
                __('Please enter a code between 1 and 50 characters containing only alpha characters and no spaces'),
                'code'
            );
        }

        if (!v::stringType()->length(0, 1000)->validate($this->description)) {
            throw new InvalidArgumentException(
                __('Please enter a description between 1 and 1000 characters'),
                'description'
            );
        }
    }

    /**
     * Save
     * @param array $options
     *
     * @throws InvalidArgumentException
     */
    public function save($options = [])
    {
        $options = array_merge($options, ['validate' => true]);

        if ($options['validate']) {
            $this->validate();
        }

        if ($this->commandId == null) {
            $this->add();
        } else {
            $this->edit();
        }
    }

    /**
     * Delete
     */
    public function delete()
    {
        $this->getStore()->update(
            'DELETE FROM `command` WHERE `commandId` = :commandId',
            ['commandId' => $this->commandId]
        );
    }

    private function add()
    {
        $this->commandId = $this->getStore()->insert('
            INSERT INTO `command` (
                `command`,
                `code`,
                `description`,
                `userId`,
                `commandString`,
                `validationString`,
                `availableOn`,
                `createAlertOn`
            ) 
            VALUES (
                :command,
                :code,
                :description,
                :userId,
                :commandString,
                :validationString,
                :availableOn,
                :createAlertOn
            )
        ', [
            'command' => $this->command,
            'code' => $this->code,
            'description' => $this->description,
            'userId' => $this->userId,
            'commandString' => $this->commandString,
            'validationString' => $this->validationString,
            'availableOn' => $this->availableOn,
            'createAlertOn' => $this->createAlertOn
        ]);
    }

    private function edit()
    {
        $this->getStore()->update('
            UPDATE `command` SET
              `command` = :command,
              `code` = :code,
              `description` = :description,
              `userId` = :userId,
              `commandString` = :commandString, 
              `validationString` = :validationString,
              `availableOn` = :availableOn,
              `createAlertOn` = :createAlertOn
             WHERE `commandId` = :commandId
        ', [
            'command' => $this->command,
            'code' => $this->code,
            'description' => $this->description,
            'userId' => $this->userId,
            'commandId' => $this->commandId,
            'commandString' => $this->commandString,
            'validationString' => $this->validationString,
            'availableOn' => $this->availableOn,
            'createAlertOn' => $this->createAlertOn
        ]);
    }
}