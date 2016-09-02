<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (DayPart.php)
 */


namespace Xibo\Entity;

use Xibo\Exception\ConfigurationException;
use Xibo\Factory\ScheduleFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class DayPart
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class DayPart implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(description="The ID of this Daypart")
     * @var int
     */
    public $dayPartId;
    public $name;
    public $description;
    public $isRetired;
    public $userId;

    public $monStart;
    public $monEnd;
    public $tueStart;
    public $tueEnd;
    public $wedStart;
    public $wedEnd;
    public $thuStart;
    public $thuEnd;
    public $friStart;
    public $friEnd;
    public $satStart;
    public $satEnd;
    public $sunStart;
    public $sunEnd;

    /** @var  DateServiceInterface */
    private $dateService;

    /** @var  ScheduleFactory */
    private $scheduleFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ScheduleFactory $scheduleFactory
     */
    public function __construct($store, $log, $scheduleFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->scheduleFactory = $scheduleFactory;
    }

    /**
     * @param DateServiceInterface $dateService
     * @return $this
     */
    public function setDateService($dateService)
    {
        $this->dateService = $dateService;
        return $this;
    }

    /**
     * @return DateServiceInterface
     * @throws ConfigurationException
     */
    private function getDate()
    {
        if ($this->dateService == null)
            throw new ConfigurationException('Application Error: Date Service is not set on DayPart Entity');

        return $this->dateService;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->dayPartId;
    }

    /**
     * @return int
     */
    public function getOwnerId()
    {
        return $this->userId;
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
     * Save
     */
    public function save()
    {
        if ($this->dayPartId == 0)
            $this->add();
        else
            $this->update();
    }

    /**
     * Delete
     */
    public function delete()
    {
        $this->getStore()->update('DELETE FROM `daypart` WHERE dayPartId = :dayPartId', ['dayPartId' => $this->dayPartId]);
    }

    /**
     * Add
     */
    private function add()
    {
        $this->dayPartId = $this->getStore()->insert('
            INSERT INTO `daypart` (`name`, `description`, `isRetired`, `userId`, `monStart`, `monEnd`, `tueStart`, 
                `tueEnd`, `wedStart`, `wedEnd`, `thuStart`, `thuEnd`, `friStart`, `friEnd`, `satStart`, `satEnd`, 
                `sunStart`, `sunEnd`)
              VALUES (:name, :description, :isRetired, :userId, :monStart, :monEnd, :tueStart, 
                :tueEnd, :wedStart, :wedEnd, :thuStart, :thuEnd, :friStart, :friEnd, :satStart, :satEnd, 
                :sunStart, :sunEnd)
        ', [
            'name' => $this->name,
            'description' => $this->description,
            'isRetired' => $this->isRetired,
            'userId' => $this->userId,
            'monStart' => $this->monStart,
            'monEnd' => $this->monEnd,
            'tueStart' => $this->tueStart,
            'tueEnd' => $this->tueEnd,
            'wedStart' => $this->wedStart,
            'wedEnd' => $this->wedEnd,
            'thuStart' => $this->thuStart,
            'thuEnd' => $this->thuEnd,
            'friStart' => $this->friStart,
            'friEnd' => $this->friEnd,
            'satStart' => $this->satStart,
            'satEnd' => $this->satEnd,
            'sunStart' => $this->sunStart,
            'sunEnd' => $this->sunEnd
        ]);
    }

    /**
     * Update
     */
    private function update()
    {
        $this->getStore()->update('
            UPDATE `daypart`
                SET `name` = :name,
                    `description` = :description,
                    `isRetired` = :isRetired,
                    `userId` = :userId,
                    `monStart` = :monStart,
                    `monEnd` = :monEnd,
                    `tueStart` = :tueStart,
                    `tueEnd` = :tueEnd,
                    `wedStart` = :wedStart,
                    `wedEnd` = :wedEnd,
                    `thuStart` = :thuStart,
                    `thuEnd` = :thuEnd,
                    `friStart` = :friStart,
                    `friEnd` = :friEnd,
                    `satStart` = :satStart,
                    `satEnd` = :satEnd,
                    `sunStart` = :sunStart,
                    `sunEnd` = :sunEnd
             WHERE `daypart`.dayPartId = :dayPartId
        ', [
            'dayPartId' => $this->dayPartId,
            'name' => $this->name,
            'description' => $this->description,
            'isRetired' => $this->isRetired,
            'userId' => $this->userId,
            'monStart' => $this->monStart,
            'monEnd' => $this->monEnd,
            'tueStart' => $this->tueStart,
            'tueEnd' => $this->tueEnd,
            'wedStart' => $this->wedStart,
            'wedEnd' => $this->wedEnd,
            'thuStart' => $this->thuStart,
            'thuEnd' => $this->thuEnd,
            'friStart' => $this->friStart,
            'friEnd' => $this->friEnd,
            'satStart' => $this->satStart,
            'satEnd' => $this->satEnd,
            'sunStart' => $this->sunStart,
            'sunEnd' => $this->sunEnd
        ]);
    }
}