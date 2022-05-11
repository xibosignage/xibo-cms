<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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


use Xibo\Factory\ScheduleReminderFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
* Class ScheduleReminder
* @package Xibo\Entity
*
* @SWG\Definition()
*/
class ScheduleReminder implements \JsonSerializable
{
    use EntityTrait;

    public static $TYPE_MINUTE = 1;
    public static $TYPE_HOUR = 2;
    public static $TYPE_DAY = 3;
    public static $TYPE_WEEK = 4;
    public static $TYPE_MONTH = 5;

    public static $OPTION_BEFORE_START = 1;
    public static $OPTION_AFTER_START = 2;
    public static $OPTION_BEFORE_END = 3;
    public static $OPTION_AFTER_END = 4;

    public static $MINUTE = 60;
    public static $HOUR = 3600;
    public static $DAY = 86400;
    public static $WEEK = 604800;
    public static $MONTH = 30 * 86400;

    /**
     * @SWG\Property(description="Schedule Reminder ID")
     * @var int
     */
    public $scheduleReminderId;

    /**
     * @SWG\Property(description="The event ID of the schedule reminder")
     * @var int
     */
    public $eventId;

    /**
     * @SWG\Property(description="An integer number to define minutes, hours etc.")
     * @var int
     */
    public $value;

    /**
     * @SWG\Property(description="The type of the reminder (i.e. Minute, Hour, Day, Week, Month)")
     * @var int
     */
    public $type;

    /**
     * @SWG\Property(description="The options regarding sending a reminder for an event. (i.e., Before start, After start, Before end, After end)")
     * @var int
     */
    public $option;

    /**
     * @SWG\Property(description="Email flag for schedule reminder")
     * @var int
     */
    public $isEmail;

    /**
     * @SWG\Property(description="A date that indicates the reminder date")
     * @var int
     */
    public $reminderDt;

    /**
     * @SWG\Property(description="Last reminder date a reminder was sent")
     * @var int
     */
    public $lastReminderDt = 0;

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var ScheduleReminderFactory
     */
    private $scheduleReminderFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param ConfigServiceInterface $config
     * @param ScheduleReminderFactory $scheduleReminderFactory
     */
    public function __construct($store, $log, $dispatcher, $config, $scheduleReminderFactory)
    {
        $this->setCommonDependencies($store, $log, $dispatcher);

        $this->config = $config;
        $this->scheduleReminderFactory = $scheduleReminderFactory;
    }

    /**
     * Add
     */
    private function add()
    {
        $this->scheduleReminderId = $this->getStore()->insert('
            INSERT INTO `schedulereminder` (`eventId`, `value`, `type`, `option`, `reminderDt`, `isEmail`, `lastReminderDt`)
              VALUES (:eventId, :value, :type, :option, :reminderDt, :isEmail, :lastReminderDt)
        ', [
            'eventId' => $this->eventId,
            'value' => $this->value,
            'type' => $this->type,
            'option' => $this->option,
            'reminderDt' => $this->reminderDt,
            'isEmail' => $this->isEmail,
            'lastReminderDt' => $this->lastReminderDt,
        ]);
    }

    /**
     * Edit
     */
    private function edit()
    {
        $sql = '
          UPDATE `schedulereminder`
            SET `eventId` = :eventId,
                `type` = :type,
                `value` = :value,
                `option` = :option,
                `reminderDt` = :reminderDt,
                `isEmail` = :isEmail,
                `lastReminderDt` = :lastReminderDt
           WHERE scheduleReminderId = :scheduleReminderId
        ';

        $params = [
            'eventId' => $this->eventId,
            'type' => $this->type,
            'value' => $this->value,
            'option' => $this->option,
            'reminderDt' => $this->reminderDt,
            'isEmail' => $this->isEmail,
            'lastReminderDt' => $this->lastReminderDt,
            'scheduleReminderId' => $this->scheduleReminderId,
        ];

        $this->getStore()->update($sql, $params);
    }


    /**
     * Delete
     */
    public function delete()
    {
        $this->load();

        $this->getLog()->debug('Delete schedule reminder: '.$this->scheduleReminderId);
        $this->getStore()->update('DELETE FROM `schedulereminder` WHERE `scheduleReminderId` = :scheduleReminderId', [
            'scheduleReminderId' => $this->scheduleReminderId
        ]);
    }

    /**
     * Load
     */
    public function load()
    {
        if ($this->loaded || $this->scheduleReminderId == null) {
            return;
        }

        $this->loaded = true;
    }

    /**
     * Get Id
     * @return int
     */
    public function getId()
    {
        return $this->scheduleReminderId;
    }

    /**
     * Get Reminder Date
     * @return int
     */
    public function getReminderDt()
    {
        return $this->reminderDt;
    }

    /**
     * Save
     */
    public function save()
    {
        if ($this->scheduleReminderId == null || $this->scheduleReminderId == 0) {
            $this->add();
        } else {
            $this->edit();
        }
    }
}