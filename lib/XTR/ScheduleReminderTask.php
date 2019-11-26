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

namespace Xibo\XTR;
use Xibo\Entity\ScheduleReminder;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\ScheduleReminderFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Service\DateServiceInterface;

/**
 * Class ScheduleReminderTask
 * @package Xibo\XTR
 */
class ScheduleReminderTask implements TaskInterface
{
    use TaskTrait;

    /** @var DateServiceInterface */
    private $date;

    /** @var UserFactory */
    private $userFactory;

    /** @var ScheduleFactory */
    private $scheduleFactory;

    /** @var CampaignFactory */
    private $campaignFactory;

    /** @var ScheduleReminderFactory */
    private $scheduleReminderFactory;

    /** @var NotificationFactory */
    private $notificationFactory;

    /** @var UserGroupFactory */
    private $userGroupFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->date = $container->get('dateService');
        $this->userFactory = $container->get('userFactory');
        $this->scheduleFactory = $container->get('scheduleFactory');
        $this->campaignFactory = $container->get('campaignFactory');
        $this->scheduleReminderFactory = $container->get('scheduleReminderFactory');
        $this->notificationFactory = $container->get('notificationFactory');
        $this->userGroupFactory = $container->get('userGroupFactory');

        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Schedule reminder') . PHP_EOL . PHP_EOL;

        $this->runScheduleReminder();
    }

    /**
     *
     */
    private function runScheduleReminder()
    {

        $task = $this->getTask();
        $nextRunDate = $task->nextRunDate();
        $task->lastRunDt = time();
        $task->save();

        // Get those reminders that have reminderDt <= nextRunDate && reminderDt > lastReminderDt
        // Those which have reminderDt < lastReminderDt exclude them
        $reminders = $this->scheduleReminderFactory->getDueReminders($nextRunDate);

        foreach($reminders as $reminder) {

            // Get the schedule
            $schedule = $this->scheduleFactory->getById($reminder->eventId);
            $schedule->setCampaignFactory($this->campaignFactory);
            $title = $schedule->getEventTitle();

            switch ($reminder->type) {
                case ScheduleReminder::$TYPE_MINUTE:
                    $type = ScheduleReminder::$MINUTE;
                    $typeText = 'Minute(s)';
                    break;
                case ScheduleReminder::$TYPE_HOUR:
                    $type = ScheduleReminder::$HOUR;
                    $typeText = 'Hour(s)';
                    break;
                case ScheduleReminder::$TYPE_DAY:
                    $type = ScheduleReminder::$DAY;
                    $typeText = 'Day(s)';
                    break;
                case ScheduleReminder::$TYPE_WEEK:
                    $type = ScheduleReminder::$WEEK;
                    $typeText = 'Week(s)';
                    break;
                case ScheduleReminder::$TYPE_MONTH:
                    $type = ScheduleReminder::$MONTH;
                    $typeText = 'Month(s)';
                    break;
                default:
                    $this->log->error('Unknown schedule reminder type has been provided');
                    continue;
            }

            switch ($reminder->option) {
                case ScheduleReminder::$OPTION_BEFORE_START:
                    $typeOptionText = 'starting';
                    break;
                case ScheduleReminder::$OPTION_AFTER_START:
                    $typeOptionText = 'started';
                    break;
                case ScheduleReminder::$OPTION_BEFORE_END:
                    $typeOptionText = 'ending';
                    break;
                case ScheduleReminder::$OPTION_AFTER_END:
                    $typeOptionText = 'ended';
                    break;
                default:
                    $this->log->error('Unknown schedule reminder option has been provided');
                    continue;
            }

            // Create a notification
            $subject = sprintf(__("Reminder for %s"), $title);
            if ($reminder->option == ScheduleReminder::$OPTION_BEFORE_START || $reminder->option == ScheduleReminder::$OPTION_BEFORE_END) {
                $body = sprintf(__("The event (%s) is %s in %d %s"), $title, $typeOptionText, $reminder->value, $typeText);
            } elseif ($reminder->option == ScheduleReminder::$OPTION_AFTER_START || $reminder->option == ScheduleReminder::$OPTION_AFTER_END) {
                $body = sprintf(__("The event (%s) has %s %d %s ago"), $title, $typeOptionText, $reminder->value, $typeText);
            }

            // Is this schedule a recurring event?
            if ($schedule->recurrenceType != '') {

                $now = $this->date->parse();
                $remindSeconds = $reminder->value * $type;

                // Get the next reminder date
                $nextReminderDate = 0;
                try {
                    $nextReminderDate = $schedule->getNextReminderDate($now, $reminder, $remindSeconds);
                } catch (NotFoundException $error) {
                    $this->log->error('No next occurrence of reminderDt found.');
                }

                $i = 0;
                $lastReminderDate = $reminder->reminderDt;
                while ($nextReminderDate != 0 && $nextReminderDate < $nextRunDate) {

                    // Keep the last reminder date
                    $lastReminderDate = $nextReminderDate;

                    $now = $this->date->parse($nextReminderDate + 1, 'U');
                    try {
                        $nextReminderDate = $schedule->getNextReminderDate($now, $reminder, $remindSeconds);
                    } catch (NotFoundException $error) {
                        $nextReminderDate = 0;
                        $this->log->debug('No next occurrence of reminderDt found. ReminderDt set to 0.');
                    }

                    $this->createNotification($subject, $body, $reminder, $schedule, $lastReminderDate);

                    $i++;
                }

                if ($i == 0) {
                    // Create only 1 notification as the next event is outside the nextRunDt
                    $this->createNotification($subject, $body, $reminder, $schedule, $reminder->reminderDt);
                    $this->log->debug('Create only 1 notification as the next event is outside the nextRunDt.');

                } else {
                    $this->log->debug($i. ' notifications created.');
                }

                $reminder->reminderDt = $nextReminderDate;
                $reminder->lastReminderDt = $lastReminderDate;
                $reminder->save();

            } else { // one-off event

                $this->createNotification($subject, $body, $reminder, $schedule, $reminder->reminderDt);

                // Current reminderDt will be used as lastReminderDt
                $reminder->lastReminderDt = $reminder->reminderDt;
            }

            // Save
            $reminder->save();
        }
    }

    private function createNotification($subject, $body, $reminder, $schedule, $releaseDt = null) {

        $notification = $this->notificationFactory->createEmpty();
        $notification->subject = $subject;
        $notification->body = $body;
        $notification->createdDt = $this->date->getLocalDate(null, 'U');
        $notification->releaseDt = $releaseDt;
        $notification->isEmail = $reminder->isEmail;
        $notification->isInterrupt = 0;
        $notification->userId = $schedule->userId; // event owner

        // Get user group to create user notification
        $notificationUser = $this->userFactory->getById($schedule->userId);
        $notification->assignUserGroup($this->userGroupFactory->getById($notificationUser->groupId));

        $notification->save();
    }
}