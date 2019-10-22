<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Command.php)
 */


namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

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
     *  description="Command String - when child of a Display Profile"
     * )
     * @var string
     */
    public $commandString;

    /**
     * @SWG\Property(
     *  description="Validation String - when child of a Display Profile"
     * )
     * @var string
     */
    public $validationString;

    /**
     * @SWG\Property(description="A comma separated list of groups/users with permissions to this Command")
     * @var string
     */
    public $groupsWithPermissions;

    /**
     * Display Profiles using this command
     * @var array[DisplayProfile]
     */
    private $displayProfiles = [];

    /**
     * @var DisplayProfileFactory
     */
    private $displayProfileFactory;

    /**
     * @var PermissionFactory
     */
    private $permissionFactory;

    /**
     * Command constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log, $permissionFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->permissionFactory = $permissionFactory;
    }

    /**
     * @param DisplayProfileFactory $displayProfileFactory
     */
    public function setChildObjectDependencies($displayProfileFactory)
    {
        $this->displayProfileFactory = $displayProfileFactory;
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
     * Validate
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        if (!v::stringType()->notEmpty()->length(1, 254)->validate($this->command))
            throw new InvalidArgumentException(__('Please enter a command name between 1 and 254 characters'), 'command');

        if (!v::alpha()->NoWhitespace()->notEmpty()->length(1, 50)->validate($this->code))
            throw new InvalidArgumentException(__('Please enter a code between 1 and 50 characters containing only alpha characters and no spaces'), 'code');

        if (!v::stringType()->notEmpty()->length(1, 1000)->validate($this->description))
            throw new InvalidArgumentException(__('Please enter a description between 1 and 1000 characters'), 'description');
    }

    /**
     * Load
     * @throws NotFoundException
     */
    public function load()
    {
        if ($this->loaded || $this->commandId == null)
            return;

        $this->displayProfiles = $this->displayProfileFactory->getByCommandId($this->commandId);
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

        if ($options['validate'])
            $this->validate();

        if ($this->commandId == null)
            $this->add();
        else
            $this->edit();
    }

    /**
     * Delete
     */
    public function delete()
    {
        if (!$this->loaded)
            $this->load();

        // Remove from any display profiles
        foreach ($this->displayProfiles as $profile) {
            /* @var \Xibo\Entity\DisplayProfile $profile */
            $profile->unassignCommand($this);
            $profile->save(['validate' => false]);
        }

        $this->getStore()->update('DELETE FROM `command` WHERE `commandId` = :commandId', ['commandId' => $this->commandId]);
    }

    private function add()
    {
        $this->commandId = $this->getStore()->insert('INSERT INTO `command` (`command`, `code`, `description`, `userId`) VALUES (:command, :code, :description, :userId)', [
            'command' => $this->command,
            'code' => $this->code,
            'description' => $this->description,
            'userId' => $this->userId
        ]);
    }

    private function edit()
    {
        $this->getStore()->update('
            UPDATE `command` SET
              `command` = :command,
              `code` = :code,
              `description` = :description,
              `userId` = :userId
             WHERE `commandId` = :commandId
        ', [
            'command' => $this->command,
            'code' => $this->code,
            'description' => $this->description,
            'userId' => $this->userId,
            'commandId' => $this->commandId
        ]);
    }
}