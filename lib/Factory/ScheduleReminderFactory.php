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

namespace Xibo\Factory;

use Xibo\Entity\ScheduleReminder;
use Xibo\Entity\User;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Support\Exception\NotFoundException;

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
     * @param User $user
     * @param UserFactory $userFactory
     * @param ConfigServiceInterface $config
     */
    public function __construct($user, $userFactory, $config)
    {
        $this->setAclDependencies($user, $userFactory);

        $this->config = $config;
    }

    /**
     * Create Empty
     * @return ScheduleReminder
     */
    public function createEmpty()
    {
        return new ScheduleReminder($this->getStore(), $this->getLog(), $this->getDispatcher(), $this->config, $this);
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
        if ($sortOrder === null) {
            $sortOrder = ['scheduleReminderId '];
        }

        $sanitizedFilter = $this->getSanitizer($filterBy);
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

        if ($sanitizedFilter->getInt('scheduleReminderId', ['default' => -1]) != -1) {
            $body .= " AND schedulereminder.scheduleReminderId = :scheduleReminderId ";
            $params['scheduleReminderId'] = $sanitizedFilter->getInt('scheduleReminderId');
        }

        if ($sanitizedFilter->getInt('eventId') !== null) {
            $body .= " AND schedulereminder.eventId = :eventId ";
            $params['eventId'] = $sanitizedFilter->getInt('eventId');
        }

        if ($sanitizedFilter->getInt('value') !== null) {
            $body .= " AND schedulereminder.value = :value ";
            $params['value'] = $sanitizedFilter->getInt('value');
        }

        if ($sanitizedFilter->getInt('type') !== null) {
            $body .= " AND schedulereminder.type = :type ";
            $params['type'] = $sanitizedFilter->getInt('type');
        }

        if ($sanitizedFilter->getInt('option') !== null) {
            $body .= " AND schedulereminder.option = :option ";
            $params['option'] = $sanitizedFilter->getInt('option');
        }

        if ($sanitizedFilter->getInt('reminderDt') !== null) {
            $body .= ' AND `schedulereminder`.reminderDt = :reminderDt ';
            $params['reminderDt'] = $sanitizedFilter->getInt('reminderDt');
        }

        if ($sanitizedFilter->getInt('nextRunDate') !== null) {
            $body .= ' AND `schedulereminder`.reminderDt <= :nextRunDate AND `schedulereminder`.reminderDt > `schedulereminder`.lastReminderDt ';
            $params['nextRunDate'] = $sanitizedFilter->getInt('nextRunDate');
        }

        if ($sanitizedFilter->getInt('isEmail') !== null) {
            $body .= ' AND `schedulereminder`.isEmail = :isEmail ';
            $params['isEmail'] = $sanitizedFilter->getInt('isEmail');
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . $sanitizedFilter->getInt('start', ['default' => 0]) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
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