<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2012-2016 Spring Signage Ltd - http://www.springsignage.com
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
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
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

    public $startTime;
    public $endTime;
    public $exceptions;

    /**
     * @SWG\Property(description="A readonly flag determining whether this DayPart is always")
     * @var int
     */
    public $isAlways = 0;

    /**
     * @SWG\Property(description="A readonly flag determining whether this DayPart is custom")
     * @var int
     */
    public $isCustom = 0;

    private $timeHash;

    /** @var  DateServiceInterface */
    private $dateService;

    /** @var  ScheduleFactory */
    private $scheduleFactory;

    /** @var DisplayGroupFactory */
    private $displayGroupFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var  LayoutFactory */
    private $layoutFactory;

    /** @var  MediaFactory */
    private $mediaFactory;

    /** @var  DayPartFactory */
    private $dayPartFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     */
    public function __construct($store, $log)
    {
        $this->setCommonDependencies($store, $log);
    }

    /**
     * @param ScheduleFactory $scheduleFactory
     * @return $this
     */
    public function setScheduleFactory($scheduleFactory)
    {
        $this->scheduleFactory = $scheduleFactory;
        return $this;
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
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DisplayFactory $displayFactory
     * @param LayoutFactory $layoutFactory
     * @param MediaFactory $mediaFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DayPartFactory $dayPartFactory
     * @return $this
     */
    public function setChildObjectDependencies($displayGroupFactory, $displayFactory, $layoutFactory, $mediaFactory, $scheduleFactory, $dayPartFactory)
    {
        $this->displayGroupFactory = $displayGroupFactory;
        $this->displayFactory = $displayFactory;
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->dayPartFactory = $dayPartFactory;
        return $this;
    }

    /**
     * Calculate time hash
     * @return string
     */
    private function calculateTimeHash()
    {
        $hash = $this->startTime . $this->endTime;

        foreach ($this->exceptions as $exception) {
            $hash .= $exception['day'] . $exception['start'] . $exception['end'];
        }

        return md5($hash);
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

    public function validate()
    {
        $this->getLog()->debug('Validating daypart ' . $this->name);

        if (!v::stringType()->notEmpty()->validate($this->name))
            throw new InvalidArgumentException(__('Name cannot be empty'), 'name');

        // Check the start/end times are in the correct format (H:i)
        if ((strlen($this->startTime) != 8 && strlen($this->startTime) != 5) || (strlen($this->endTime) != 8 && strlen($this->endTime) != 5))
            throw new InvalidArgumentException(__('Start/End time are empty or in an incorrect format'), 'start/end time');

        foreach ($this->exceptions as $exception) {
            if ((strlen($exception['start']) != 8 && strlen($exception['start']) != 5) || (strlen($exception['end']) != 8 && strlen($exception['end']) != 5))
                throw new InvalidArgumentException(sprintf(__('Exception Start/End time for %s are empty or in an incorrect format'), $exception['day']), 'exception start/end time');
        }
    }

    /**
     * Load
     * @return $this
     */
    public function load()
    {
        $this->timeHash = $this->calculateTimeHash();

        return $this;
    }

    /**
     * Save
     * @param array $options
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true
        ], $options);

        if ($options['validate'])
            $this->validate();

        if ($this->dayPartId == 0)
            $this->add();
        else {
            // Update
            $this->update();

            // Compare the time hash with a new time hash to see if we need to update associated schedules
            if ($this->timeHash != $this->calculateTimeHash())
                $this->handleEffectedSchedules();
            else
                $this->getLog()->debug('Daypart hash identical, no need to update schedules. ' . $this->timeHash . ' vs ' . $this->calculateTimeHash());
        }
    }

    /**
     * Delete
     */
    public function delete()
    {
        // Delete all events using this daypart
        $schedules = $this->scheduleFactory->getByDayPartId($this->dayPartId);

        foreach ($schedules as $schedule) {
            $schedule->delete();
        }

        // Delete the daypart
        $this->getStore()->update('DELETE FROM `daypart` WHERE dayPartId = :dayPartId', ['dayPartId' => $this->dayPartId]);
    }

    /**
     * Add
     */
    private function add()
    {
        $this->dayPartId = $this->getStore()->insert('
            INSERT INTO `daypart` (`name`, `description`, `isRetired`, `userId`, `startTime`, `endTime`, `exceptions`)
              VALUES (:name, :description, :isRetired, :userId, :startTime, :endTime, :exceptions)
        ', [
            'name' => $this->name,
            'description' => $this->description,
            'isRetired' => $this->isRetired,
            'userId' => $this->userId,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'exceptions' => json_encode(is_array($this->exceptions) ? $this->exceptions : [])
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
                    `startTime` = :startTime,
                    `endTime` = :endTime,
                    `exceptions` = :exceptions
             WHERE `daypart`.dayPartId = :dayPartId
        ', [
            'dayPartId' => $this->dayPartId,
            'name' => $this->name,
            'description' => $this->description,
            'isRetired' => $this->isRetired,
            'userId' => $this->userId,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'exceptions' => json_encode(is_array($this->exceptions) ? $this->exceptions : [])
        ]);
    }

    /**
     * Handles schedules effected by an update
     */
    private function handleEffectedSchedules()
    {
        $now = time();

        // Get all schedules that use this dayPart and exist after the current time.
        $schedules = $this->scheduleFactory->query(null, ['dayPartId' => $this->dayPartId, 'futureSchedulesFrom' => $now]);

        $this->getLog()->debug('Daypart update effects ' . count($schedules) . ' schedules.');

        foreach ($schedules as $schedule) {
            /** @var Schedule $schedule */
            $schedule
                ->setDisplayFactory($this->displayFactory)
                ->load();

            // Is this schedule a recurring event?
            if ($schedule->recurrenceType != '' && $schedule->fromDt < $now) {
                $this->getLog()->debug('Schedule is for a recurring event which has already recurred');

                // Split the scheduled event, adjusting only the recurring end date on the original event
                $newSchedule = clone $schedule;
                $schedule->recurrenceRange = $now;
                $schedule->save();

                // Adjusting the fromdt on the new event
                $newSchedule->fromDt = $this->getDate()->parse()->addDay()->format('U');
                $newSchedule->save();
            } else {
                $this->getLog()->debug('Schedule is for a single event');

                // Update just this single event to have the new date/time
                $schedule->save();
            }
        }
    }
}