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
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\NotificationFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Factory\ScheduleReminderFactory;
use Xibo\Factory\UserFactory;
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

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->date = $container->get('dateService');
        $this->userFactory = $container->get('userFactory');
        $this->scheduleFactory = $container->get('scheduleFactory');
        $this->campaignFactory = $container->get('campaignFactory');
        $this->scheduleReminderFactory = $container->get('scheduleReminderFactory');
        $this->notificationFactory = $container->get('notificationFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Schedule reminder') . PHP_EOL . PHP_EOL;

        // Long running task
        set_time_limit(0);

        $this->runScheduleReminder();
    }

    /**
     *
     */
    private function runScheduleReminder()
    {
        // Get all schedules
        $schedules = $this->scheduleFactory->query();

        foreach($schedules as $schedule) {

            // Get all reminders of the schedule
            $reminders = $this->scheduleReminderFactory->query(null, ['eventId' => $schedule->eventId]);

            foreach ($reminders as $reminder) {

                $now = $this->date->parse();
                if ($reminder->reminderDt <= $now->format('U')) {

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
                            throw new \Xibo\Exception\NotFoundException('Unknown type');
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
                            throw new \Xibo\Exception\NotFoundException('Unknown option');
                    }

                    // Create a notification
                    $subject = sprintf(__("Reminder for %s"), $title);
                    if ($reminder->option == ScheduleReminder::$OPTION_BEFORE_START || $reminder->option == ScheduleReminder::$OPTION_BEFORE_END) {
                        $body = sprintf(__("The event (%s) is %s in %d %s"), $title, $typeOptionText, $reminder->value, $typeText);
                    } elseif ($reminder->option == ScheduleReminder::$OPTION_AFTER_START || $reminder->option == ScheduleReminder::$OPTION_AFTER_END) {
                        $body = sprintf(__("The event (%s) has %s %d %s ago"), $title, $typeOptionText, $reminder->value, $typeText);
                    }

                    // Send a notification to the event owner
                    $notification = $this->notificationFactory->createEmpty();
                    $notification->subject = $subject;
                    $notification->body = $body;
                    $notification->createdDt = $this->date->getLocalDate(null, 'U');
                    $notification->releaseDt = $reminder->reminderDt;
                    $notification->isEmail = $reminder->isEmail;
                    $notification->isInterrupt = 0;
                    $notification->userId = $schedule->userId; // event owner

                    // Send
                    $notification->save();

                    // Is this schedule a recurring event?
                    if ($schedule->recurrenceType != '') {

                        $now = $this->date->parse();
                        $remindSeconds = $reminder->value * $type;
                        $nextReminderDate = $schedule->getNextReminderDate($now, $reminder, $remindSeconds);
                        if($nextReminderDate != null) {

                            $reminder->reminderDt = $nextReminderDate;
                            $reminder->save();
                            $this->appendRunMessage(__('Reminder added for  '. $title));
                        } else {

                            // Remove the reminder
                            $reminder->delete();
                            $this->appendRunMessage(__('Reminder removed for '.$title));
                        }
                    } else {
                        // Remove the reminder for non recurrence event
                        $reminder->delete();
                        $this->appendRunMessage(__('Reminder removed for '.$title));
                    }

                }
            }
        }
    }
}