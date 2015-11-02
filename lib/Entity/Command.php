<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Command.php)
 */


namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Xibo\Factory\DisplayProfileFactory;
use Xibo\Storage\PDOConnect;

class Command implements \JsonSerializable
{
    use EntityTrait;

    /**
     * Command Id
     * @var int
     */
    public $commandId;

    /**
     * Command Name
     * @var string
     */
    public $command;

    /**
     * Unique Code
     * @var string
     */
    public $code;

    /**
     * Description
     * @var string
     */
    public $description;

    /**
     * User Id
     * @var int
     */
    public $userId;

    /**
     * Command String - when child of a Display Profile
     * @var string
     */
    public $commandString;

    /**
     * Validation String - when child of a Display Profile
     * @var string
     */
    public $validationString;

    /**
     * Display Profiles using this command
     * @var array[DisplayProfile]
     */
    private $displayProfiles = [];

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
     */
    public function validate()
    {
        if (!v::string()->notEmpty()->length(1, 254)->validate($this->command))
            throw new \InvalidArgumentException(__('Please enter a command name between 1 and 254 characters'));

        if (!v::string()->notEmpty()->length(1, 50)->validate($this->code))
            throw new \InvalidArgumentException(__('Please enter a code between 1 and 50 characters'));

        if (!v::string()->notEmpty()->length(1, 1000)->validate($this->description))
            throw new \InvalidArgumentException(__('Please enter a description between 1 and 1000 characters'));
    }

    /**
     * Load
     * @param array $options
     */
    public function load($options = [])
    {
        if ($this->loaded || $this->commandId == null)
            return;

        $this->displayProfiles = DisplayProfileFactory::getByCommandId($this->commandId);
    }

    /**
     * Save
     * @param array $options
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
        }

        PDOConnect::update('DELETE FROM `command` WHERE `commandId` = :commandId', ['commandId' => $this->commandId]);
    }

    private function add()
    {
        $this->commandId = PDOConnect::insert('INSERT INTO `command` (`command`, `code`, `description`, `userId`) VALUES (:command, :code, :description, :userId)', [
            'command' => $this->command,
            'code' => $this->code,
            'description' => $this->description,
            'userId' => $this->userId
        ]);
    }

    private function edit()
    {
        PDOConnect::update('
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