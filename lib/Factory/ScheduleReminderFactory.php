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

namespace Xibo\Factory;

use Jenssegers\Date\Date;
use Xibo\Entity\ScheduleReminder;
use Xibo\Entity\User;
use Xibo\Exception\NotFoundException;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class ScheduleReminderFactory
 * @package Xibo\Factory
 */
class ScheduleReminderFactory extends BaseFactory
{
    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * Construct a factory
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param User $user
     * @param UserFactory $userFactory
     * @param ConfigServiceInterface $config
     */
    public function __construct($store, $log, $sanitizerService, $user, $userFactory, $config)
    {
        $this->setCommonDependencies($store, $log, $sanitizerService);
        $this->setAclDependencies($user, $userFactory);

        $this->config = $config;

    }

    /**
     * Create Empty
     * @return ScheduleReminder
     */
    public function createEmpty()
    {
        return new ScheduleReminder($this->getStore(), $this->getLog(), $this->config, $this);
    }

    /**
     * Populate Schedule Reminder table
     * @param int $eventId
     * @param int $type
     * @param int $option
     * @param int $value
     * @param int $reminderDt
     * @param int $isEmail
     * @param int $lastReminderDt
     * @return ScheduleReminder
     */
    public function create($eventId, $value, $type, $option, $reminderDt, $isEmail, $lastReminderDt)
    {
        $scheduleReminder = $this->createEmpty();
        $scheduleReminder->eventId = $eventId;
        $scheduleReminder->value = $value;
        $scheduleReminder->type = $type;
        $scheduleReminder->option = $option;
        $scheduleReminder->reminderDt = $reminderDt;
        $scheduleReminder->isEmail = $isEmail;
        $scheduleReminder->lastReminderDt = $lastReminderDt;
        $scheduleReminder->save();

        return $scheduleReminder;
    }

    /**
     * Get by Schedule Reminder Id
     * @param int $scheduleReminderId
     * @return ScheduleReminder
     * @throws NotFoundException
     */
    public function getById($scheduleReminderId)
    {
        $scheduleReminders = $this->query(null, ['scheduleReminderId' => $scheduleReminderId]);

        if (count($scheduleReminders) <= 0)
            throw new NotFoundException(__('Cannot find schedule reminder'));

        return $scheduleReminders[0];
    }

    /**
     * Get due reminders
     * @param Date $nextRunDate
     * @return array[ScheduleReminder]
     * @throws NotFoundException
     */
    public function getDueReminders($nextRunDate)
    {
        return $this->query(null, ['nextRunDate' => $nextRunDate]);
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return ScheduleReminder[]
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder === null)
            $sortOrder = ['scheduleReminderId '];

        $params = [];
        $entries = [];

        $select = '
            SELECT  
               schedulereminder.scheduleReminderId,
               schedulereminder.eventId,
               schedulereminder.value,
               schedulereminder.type,
               schedulereminder.option,
               schedulereminder.reminderDt,
               schedulereminder.isEmail,
               schedulereminder.lastReminderDt
            ';

        $body = ' FROM schedulereminder ';

        $body .= " WHERE 1 = 1 ";

        if ($this->getSanitizer()->getInt('scheduleReminderId', -1, $filterBy) != -1) {
            $body .= " AND schedulereminder.scheduleReminderId = :scheduleReminderId ";
            $params['scheduleReminderId'] = $this->getSanitizer()->getInt('scheduleReminderId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('eventId', $filterBy) !== null) {
            $body .= " AND schedulereminder.eventId = :eventId ";
            $params['eventId'] = $this->getSanitizer()->getInt('eventId', $filterBy);
        }

        if ($this->getSanitizer()->getInt('value', $filterBy) !== null) {
            $body .= " AND schedulereminder.value = :value ";
            $params['value'] = $this->getSanitizer()->getInt('value', $filterBy);
        }

        if ($this->getSanitizer()->getInt('type', $filterBy) !== null) {
            $body .= " AND schedulereminder.type = :type ";
            $params['type'] = $this->getSanitizer()->getInt('type', $filterBy);
        }

        if ($this->getSanitizer()->getInt('option', $filterBy) !== null) {
            $body .= " AND schedulereminder.option = :option ";
            $params['option'] = $this->getSanitizer()->getInt('option', $filterBy);
        }

        if ($this->getSanitizer()->getInt('reminderDt', $filterBy) !== null) {
            $body .= ' AND `schedulereminder`.reminderDt = :reminderDt ';
            $params['reminderDt'] = $this->getSanitizer()->getInt('reminderDt', $filterBy);
        }

        if ($this->getSanitizer()->getInt('nextRunDate', $filterBy) !== null) {
            $body .= ' AND `schedulereminder`.reminderDt <= :nextRunDate AND `schedulereminder`.reminderDt > `schedulereminder`.lastReminderDt ';
            $params['nextRunDate'] = $this->getSanitizer()->getInt('nextRunDate', $filterBy);
        }

        if ($this->getSanitizer()->getInt('isEmail', $filterBy) !== null) {
            $body .= ' AND `schedulereminder`.isEmail = :isEmail ';
            $params['isEmail'] = $this->getSanitizer()->getInt('isEmail', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $version = $this->createEmpty()->hydrate($row, [
                'intProperties' => [
                    'value', 'type', 'option', 'reminderDt', 'isEmail', 'lastReminderDt'
                ]
            ]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;


    }


}