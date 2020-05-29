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
namespace Xibo\Entity;

use Jenssegers\Date\Date;
use Respect\Validation\Validator as v;
use Stash\Interfaces\PoolInterface;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\ScheduleExclusionFactory;
use Xibo\Factory\ScheduleReminderFactory;
use Xibo\Factory\UserFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class Schedule
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Schedule implements \JsonSerializable
{
    use EntityTrait;

    public static $LAYOUT_EVENT = 1;
    public static $COMMAND_EVENT = 2;
    public static $OVERLAY_EVENT = 3;
    public static $INTERRUPT_EVENT = 4;
    public static $CAMPAIGN_EVENT = 5;
    public static $DATE_MIN = 0;
    public static $DATE_MAX = 2147483647;

    /**
     * @SWG\Property(
     *  description="The ID of this Event"
     * )
     * @var int
     */
    public $eventId;

    /**
     * @SWG\Property(
     *  description="The Event Type ID"
     * )
     * @var int
     */
    public $eventTypeId;

    /**
     * @SWG\Property(
     *  description="The CampaignID this event is for"
     * )
     * @var int
     */
    public $campaignId;

    /**
     * @SWG\Property(
     *  description="The CommandId this event is for"
     * )
     * @var int
     */
    public $commandId;

    /**
     * @SWG\Property(
     *  description="Display Groups assigned to this Scheduled Event.",
     *  type="array",
     *  @SWG\Items(ref="#/definitions/DisplayGroup")
     * )
     * @var DisplayGroup[]
     */
    public $displayGroups = [];

    /**
     * @SWG\Property(
     *  description="Schedule Reminders assigned to this Scheduled Event.",
     *  type="array",
     *  @SWG\Items(ref="#/definitions/ScheduleReminder")
     * )
     * @var ScheduleReminder[]
     */
    public $scheduleReminders = [];

    /**
     * @SWG\Property(
     *  description="The userId that owns this event."
     * )
     * @var int
     */
    public $userId;

    /**
     * @SWG\Property(
     *  description="A Unix timestamp representing the from date of this event in CMS time."
     * )
     * @var int
     */
    public $fromDt;

    /**
     * @SWG\Property(
     *  description="A Unix timestamp representing the to date of this event in CMS time."
     * )
     * @var int
     */
    public $toDt;

    /**
     * @SWG\Property(
     *  description="Integer indicating the event priority."
     * )
     * @var int
     */
    public $isPriority;

    /**
     * @SWG\Property(
     *  description="The display order for this event."
     * )
     * @var int
     */
    public $displayOrder;

    /**
     * @SWG\Property(
     *  description="If this event recurs when what is the recurrence period.",
     *  enum={"None", "Minute", "Hour", "Day", "Week", "Month", "Year"}
     * )
     * @var string
     */
    public $recurrenceType;

    /**
     * @SWG\Property(
     *  description="If this event recurs when what is the recurrence frequency.",
     * )
     * @var int
     */
    public $recurrenceDetail;

    /**
     * @SWG\Property(
     *  description="A Unix timestamp indicating the end time of the recurring events."
     * )
     * @var int
     */
    public $recurrenceRange;

    /**
     * @SWG\Property(description="Recurrence repeats on days - 0 to 7 where 0 is a monday")
     * @var string
     */
    public $recurrenceRepeatsOn;

    /**
     * @SWG\Property(description="Recurrence monthly repeats on - 0 is day of month, 1 is weekday of week")
     * @var int
     */
    public $recurrenceMonthlyRepeatsOn;

    /**
     * @SWG\Property(
     *  description="The Campaign/Layout Name",
     *  readOnly=true
     * )
     * @var string
     */
    public $campaign;

    /**
     * @SWG\Property(
     *  description="The Command Name",
     *  readOnly=true
     * )
     * @var string
     */
    public $command;

    /**
     * @SWG\Property(
     *  description="The Day Part Id"
     * )
     * @var int
     */
    public $dayPartId;

    /**
     * @SWG\Property(description="Is this event an always on event?")
     * @var int
     */
    public $isAlways;

    /**
     * @SWG\Property(description="Does this event have custom from/to date times?")
     * @var int
     */
    public $isCustom;

    /**
     * Last Recurrence Watermark
     * @var int
     */
    public $lastRecurrenceWatermark;

    /**
     * @SWG\Property(description="Flag indicating whether the event should be synchronised across displays")
     * @var int
     */
    public $syncEvent = 0;

    /**
     * @SWG\Property(description="Flag indicating whether the event will sync to the Display timezone")
     * @var int
     */
    public $syncTimezone;

    /**
     * @SWG\Property(description="Seconds (0-3600) of each full hour that is scheduled that this Layout should occupy")
     * @var int
     */
    public $shareOfVoice;

    /**
     * @SWG\Property(description="Flag (0-1), whether this event is using Geo Location")
     * @var int
     */
    public $isGeoAware;

    /**
     * @SWG\Property(description="Geo JSON representing the area of this event")
     * @var string
     */
    public $geoLocation;

    /**
     * @var ScheduleEvent[]
     */
    private $scheduleEvents = [];

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /** @var  DateServiceInterface */
    private $dateService;

    /** @var  PoolInterface */
    private $pool;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var  DayPartFactory */
    private $dayPartFactory;

    /** @var  CampaignFactory */
    private $campaignFactory;

    /** @var  ScheduleReminderFactory */
    private $scheduleReminderFactory;

    /** @var  ScheduleExclusionFactory */
    private $scheduleExclusionFactory;

    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param PoolInterface $pool
     * @param DateServiceInterface $date
     * @param DisplayGroupFactory $displayGroupFactory
     * @param DayPartFactory $dayPartFactory
     * @param UserFactory $userFactory
     * @param ScheduleReminderFactory $scheduleReminderFactory
     * @param ScheduleExclusionFactory $scheduleExclusionFactory
     */
    public function __construct($store, $log, $config, $pool, $date, $displayGroupFactory, $dayPartFactory, $userFactory, $scheduleReminderFactory, $scheduleExclusionFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->config = $config;
        $this->pool = $pool;
        $this->dateService = $date;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->dayPartFactory = $dayPartFactory;
        $this->userFactory = $userFactory;
        $this->scheduleReminderFactory = $scheduleReminderFactory;
        $this->scheduleExclusionFactory = $scheduleExclusionFactory;

        $this->excludeProperty('lastRecurrenceWatermark');
    }

    /**
     * @param CampaignFactory $campaignFactory
     * @return $this
     */
    public function setCampaignFactory($campaignFactory)
    {
        $this->campaignFactory = $campaignFactory;
        return $this;
    }

    public function __clone()
    {
        $this->eventId = null;
    }

    /**
     * @param DisplayFactory $displayFactory
     * @return $this
     */
    public function setDisplayFactory($displayFactory)
    {
        $this->displayFactory = $displayFactory;
        return $this;
    }

    /**
     * @param DateServiceInterface $dateService
     * @deprecated dateService is set by the factory
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
            throw new ConfigurationException('Application Error: Date Service is not set on Schedule Entity');

        return $this->dateService;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->eventId;
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
     * Are the provided dates within the schedule look ahead
     * @return bool
     * @throws XiboException
     */
    private function inScheduleLookAhead()
    {
        if ($this->isAlwaysDayPart())
            return true;

        // From Date and To Date are in UNIX format
        $currentDate = $this->getDate()->parse();
        $rfLookAhead = clone $currentDate;
        $rfLookAhead->addSeconds(intval($this->config->getSetting('REQUIRED_FILES_LOOKAHEAD')));

        // Dial current date back to the start of the day
        $currentDate->startOfDay();

        // Test dates
        if ($this->recurrenceType != '') {
            // A recurring event
            $this->getLog()->debug('Checking look ahead based on recurrence');
            // we should check whether the event from date is before the lookahead (i.e. the event has recurred once)
            // we should also check whether the recurrence range is still valid (i.e. we've not stopped recurring and we don't recur forever)
            return (
                $this->fromDt <= $rfLookAhead->format('U')
                && ($this->recurrenceRange == 0 || $this->recurrenceRange > $currentDate->format('U'))
            );
        } else if (!$this->isCustomDayPart() || $this->eventTypeId == self::$COMMAND_EVENT) {
            // Day parting event (non recurring) or command event
            // only test the from date.
            $this->getLog()->debug('Checking look ahead based from date ' . $currentDate->toRssString());
            return ($this->fromDt >= $currentDate->format('U') && $this->fromDt <= $rfLookAhead->format('U'));
        } else {
            // Compare the event dates
            $this->getLog()->debug('Checking look ahead based event dates ' . $currentDate->toRssString() . ' / ' . $rfLookAhead->toRssString());
            return ($this->fromDt <= $rfLookAhead->format('U') && $this->toDt >= $currentDate->format('U'));
        }
    }

    /**
     * Load
     */
    public function load($options = [])
    {
        $options = array_merge([
            'loadScheduleReminders' => false
        ], $options);

        // If we are already loaded, then don't do it again
        if ($this->loaded || $this->eventId == null || $this->eventId == 0)
            return;

        $this->displayGroups = $this->displayGroupFactory->getByEventId($this->eventId);

        // Load schedule reminders
        if ($options['loadScheduleReminders']) {
            $this->scheduleReminders = $this->scheduleReminderFactory->query(null, ['eventId'=> $this->eventId]);
        }

        // Set the original values now that we're loaded.
        $this->setOriginals();

        // We are fully loaded
        $this->loaded = true;
    }

    /**
     * Assign DisplayGroup
     * @param DisplayGroup $displayGroup
     */
    public function assignDisplayGroup($displayGroup)
    {
        $this->load();

        if (!in_array($displayGroup, $this->displayGroups))
            $this->displayGroups[] = $displayGroup;
    }

    /**
     * Unassign DisplayGroup
     * @param DisplayGroup $displayGroup
     */
    public function unassignDisplayGroup($displayGroup)
    {
        $this->load();

        $this->displayGroups = array_udiff($this->displayGroups, [$displayGroup], function ($a, $b) {
            /**
             * @var DisplayGroup $a
             * @var DisplayGroup $b
             */
            return $a->getId() - $b->getId();
        });
    }

    /**
     * Validate
     * @throws XiboException
     */
    public function validate()
    {
        if (count($this->displayGroups) <= 0) {
            throw new InvalidArgumentException(__('No display groups selected'), 'displayGroups');
        }

        $this->getLog()->debug('EventTypeId: ' . $this->eventTypeId
            . '. DayPartId: ' . $this->dayPartId
            . ', CampaignId: ' . $this->campaignId
            . ', CommandId: ' . $this->commandId);

        if ($this->eventTypeId == Schedule::$LAYOUT_EVENT ||
            $this->eventTypeId == Schedule::$CAMPAIGN_EVENT ||
            $this->eventTypeId == Schedule::$OVERLAY_EVENT ||
            $this->eventTypeId == Schedule::$INTERRUPT_EVENT
        ) {
            // Validate layout
            if (!v::intType()->notEmpty()->validate($this->campaignId))
                throw new InvalidArgumentException(__('Please select a Campaign/Layout for this event.'), 'campaignId');

            if ($this->isCustomDayPart()) {
                // validate the dates
                if ($this->toDt <= $this->fromDt)
                    throw new InvalidArgumentException(__('Can not have an end time earlier than your start time'), 'start/end');
            }

            $this->commandId = null;

            // additional validation for Interrupt Layout event type
            if ($this->eventTypeId == Schedule::$INTERRUPT_EVENT) {

                // Hack : If this is an interrupt, check that the column is a SMALLINT and if it isn't alter the table
                $sql = 'SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = :table_name AND COLUMN_NAME = :column_name';
                $params = ['table_name' => 'schedule', 'column_name' => 'shareOfVoice' ];
                $results = $this->store->select($sql, $params);

                if (count($results) > 0) {
                    $dataType = $results[0]['DATA_TYPE'];

                    if ($dataType !== 'smallint') {
                        $this->store->update('ALTER TABLE `schedule` MODIFY `shareOfVoice` SMALLINT', []);

                        // convert any existing interrupt schedules?
                        $this->store->update('UPDATE `schedule` SET `shareOfVoice` = 3600 * (shareOfVoice / 100) WHERE shareOfVoice > 0', []);
                    }
                }

                if (!v::intType()->notEmpty()->min(0)->max(3600)->validate($this->shareOfVoice)) {
                    throw new InvalidArgumentException(__('Share of Voice must be a whole number between 0 and 3600'), 'shareOfVoice');
                }
            }

        } else if ($this->eventTypeId == Schedule::$COMMAND_EVENT) {
            // Validate command
            if (!v::intType()->notEmpty()->validate($this->commandId))
                throw new InvalidArgumentException(__('Please select a Command for this event.'), 'command');

            $this->campaignId = null;
            $this->toDt = null;

        } else {
            // No event type selected
            throw new InvalidArgumentException(__('Please select the Event Type'), 'eventTypeId');
        }

        // Make sure we have a sensible recurrence setting
        if (!$this->isCustomDayPart() && ($this->recurrenceType == 'Minute' || $this->recurrenceType == 'Hour'))
            throw new InvalidArgumentException(__('Repeats selection is invalid for Always or Daypart events'), 'recurrencyType');

        // Check display order is positive
        if ($this->displayOrder < 0)
            throw new InvalidArgumentException(__('Display Order must be 0 or a positive number'), 'displayOrder');

        // Check priority is positive
        if ($this->isPriority < 0)
            throw new InvalidArgumentException(__('Priority must be 0 or a positive number'), 'isPriority');

        // Check recurrenceDetail every is positive
        if ($this->recurrenceType != '' && ($this->recurrenceDetail === null || $this->recurrenceDetail <= 0))
            throw new InvalidArgumentException(__('Repeat every must be a positive number'), 'recurrenceDetail');
    }

    /**
     * Save
     * @param array $options
     * @throws XiboException
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true,
            'audit' => true,
            'deleteOrphaned' => false,
            'notify' => true
        ], $options);

        if ($options['validate'])
            $this->validate();

        // Handle "always" day parts
        if ($this->isAlwaysDayPart()) {
            $this->fromDt = self::$DATE_MIN;
            $this->toDt = self::$DATE_MAX;
        }

        if ($this->eventId == null || $this->eventId == 0) {
            $this->add();
            $auditMessage = 'Added';
            $this->loaded = true;
            $isEdit = false;
        }
        else {
            // If this save action means there aren't any display groups assigned
            // and if we're set to deleteOrphaned, then delete
            if ($options['deleteOrphaned'] && count($this->displayGroups) <= 0) {
                $this->delete();
                return;
            } else {
                $this->edit();
                $auditMessage = 'Saved';
            }
            $isEdit = true;
        }

        // Manage display assignments
        if ($this->loaded) {
            // Manage assignments
            $this->manageAssignments($isEdit && $options['notify']);
        }

        // Notify
        if ($options['notify']) {
            // Only if the schedule effects the immediate future - i.e. within the RF Look Ahead
            if ($this->inScheduleLookAhead()) {
                $this->getLog()->debug('Schedule changing is within the schedule look ahead, will notify ' . count($this->displayGroups) . ' display groups');
                foreach ($this->displayGroups as $displayGroup) {
                    /* @var DisplayGroup $displayGroup */
                    $this->displayFactory->getDisplayNotifyService()->collectNow()->notifyByDisplayGroupId($displayGroup->displayGroupId);
                }
            } else {
                $this->getLog()->debug('Schedule changing is not within the schedule look ahead');
            }
        }

        if ($options['audit'])
            $this->audit($this->getId(), $auditMessage);

        // Drop the cache for this event
        $this->dropEventCache();
    }

    /**
     * Delete this Schedule Event
     */
    public function delete()
    {
        $this->load();

        // Notify display groups
        $notify = $this->displayGroups;

        // Delete display group assignments
        $this->displayGroups = [];
        $this->unlinkDisplayGroups();

        // Delete schedule exclusions
        $scheduleExclusions = $this->scheduleExclusionFactory->query(null, ['eventId' => $this->eventId]);
        foreach ($scheduleExclusions as $exclusion) {
            $exclusion->delete();
        }

        // Delete schedule reminders
        if ($this->scheduleReminderFactory !== null) {
            $scheduleReminders = $this->scheduleReminderFactory->query(null, ['eventId' => $this->eventId]);

            foreach ($scheduleReminders as $reminder) {
                $reminder->delete();
            }
        }

        // Delete the event itself
        $this->getStore()->update('DELETE FROM `schedule` WHERE eventId = :eventId', ['eventId' => $this->eventId]);

        // Notify
        // Only if the schedule effects the immediate future - i.e. within the RF Look Ahead
        if ($this->inScheduleLookAhead() && $this->displayFactory !== null) {
            $this->getLog()->debug('Schedule changing is within the schedule look ahead, will notify ' . count($notify) . ' display groups');
            foreach ($notify as $displayGroup) {
                /* @var DisplayGroup $displayGroup */
                $this->displayFactory->getDisplayNotifyService()->collectNow()->notifyByDisplayGroupId($displayGroup->displayGroupId);
            }
        } else if ($this->displayFactory === null) {
            $this->getLog()->info('Notify disabled, dependencies not set');
        }

        // Drop the cache for this event
        $this->dropEventCache();

        // Audit
        $this->audit($this->getId(), 'Deleted', $this->toArray());
    }

    /**
     * Add
     */
    private function add()
    {
        $this->eventId = $this->getStore()->insert('
          INSERT INTO `schedule` (eventTypeId, CampaignId, commandId, userID, is_priority, FromDT, ToDT, DisplayOrder, recurrence_type, recurrence_detail, recurrence_range, `recurrenceRepeatsOn`, `recurrenceMonthlyRepeatsOn`, `dayPartId`, `syncTimezone`, `syncEvent`, `shareOfVoice`, `isGeoAware`, `geoLocation`)
            VALUES (:eventTypeId, :campaignId, :commandId, :userId, :isPriority, :fromDt, :toDt, :displayOrder, :recurrenceType, :recurrenceDetail, :recurrenceRange, :recurrenceRepeatsOn, :recurrenceMonthlyRepeatsOn, :dayPartId, :syncTimezone, :syncEvent, :shareOfVoice, :isGeoAware, :geoLocation)
        ', [
            'eventTypeId' => $this->eventTypeId,
            'campaignId' => $this->campaignId,
            'commandId' => $this->commandId,
            'userId' => $this->userId,
            'isPriority' => $this->isPriority,
            'fromDt' => $this->fromDt,
            'toDt' => $this->toDt,
            'displayOrder' => $this->displayOrder,
            'recurrenceType' => $this->recurrenceType,
            'recurrenceDetail' => $this->recurrenceDetail,
            'recurrenceRange' => $this->recurrenceRange,
            'recurrenceRepeatsOn' => $this->recurrenceRepeatsOn,
            'recurrenceMonthlyRepeatsOn' => ($this->recurrenceMonthlyRepeatsOn == null) ? 0 : $this->recurrenceMonthlyRepeatsOn,
            'dayPartId' => $this->dayPartId,
            'syncTimezone' => $this->syncTimezone,
            'syncEvent' => $this->syncEvent,
            'shareOfVoice' => $this->shareOfVoice,
            'isGeoAware' => $this->isGeoAware,
            'geoLocation' => $this->geoLocation
        ]);
    }

    /**
     * Edit
     */
    private function edit()
    {
        $this->getStore()->update('
          UPDATE `schedule` SET
            eventTypeId = :eventTypeId,
            campaignId = :campaignId,
            commandId = :commandId,
            is_priority = :isPriority,
            userId = :userId,
            fromDt = :fromDt,
            toDt = :toDt,
            displayOrder = :displayOrder,
            recurrence_type = :recurrenceType,
            recurrence_detail = :recurrenceDetail,
            recurrence_range = :recurrenceRange,
            `recurrenceRepeatsOn` = :recurrenceRepeatsOn,
            `recurrenceMonthlyRepeatsOn` = :recurrenceMonthlyRepeatsOn,
            `dayPartId` = :dayPartId,
            `syncTimezone` = :syncTimezone,
            `syncEvent` = :syncEvent,
            `shareOfVoice` = :shareOfVoice,
            `isGeoAware` = :isGeoAware,
            `geoLocation` = :geoLocation
          WHERE eventId = :eventId
        ', [
            'eventTypeId' => $this->eventTypeId,
            'campaignId' => $this->campaignId,
            'commandId' => $this->commandId,
            'userId' => $this->userId,
            'isPriority' => $this->isPriority,
            'fromDt' => $this->fromDt,
            'toDt' => $this->toDt,
            'displayOrder' => $this->displayOrder,
            'recurrenceType' => $this->recurrenceType,
            'recurrenceDetail' => $this->recurrenceDetail,
            'recurrenceRange' => $this->recurrenceRange,
            'recurrenceRepeatsOn' => $this->recurrenceRepeatsOn,
            'recurrenceMonthlyRepeatsOn' => $this->recurrenceMonthlyRepeatsOn,
            'dayPartId' => $this->dayPartId,
            'syncTimezone' => $this->syncTimezone,
            'syncEvent' => $this->syncEvent,
            'shareOfVoice' => $this->shareOfVoice,
            'isGeoAware' => $this->isGeoAware,
            'geoLocation' => $this->geoLocation,
            'eventId' => $this->eventId
        ]);
    }

    /**
     * Get events between the provided dates.
     * @param Date $fromDt
     * @param Date $toDt
     * @return ScheduleEvent[]
     * @throws XiboException
     */
    public function getEvents($fromDt, $toDt)
    {
        // Events scheduled "always" will return one event
        if ($this->isAlwaysDayPart()) {
            // Create events with min/max dates
            $this->addDetail(Schedule::$DATE_MIN, Schedule::$DATE_MAX);
            return $this->scheduleEvents;
        }

        // Copy the dates as we are going to be operating on them.
        $fromDt = $fromDt->copy();
        $toDt = $toDt->copy();

        if ($this->pool == null)
            throw new ConfigurationException(__('Cache pool not available'));

        if ($this->eventId == null)
            throw new InvalidArgumentException(__('Unable to generate schedule, unknown event'), 'eventId');

        // What if we are requesting a single point in time?
        if ($fromDt == $toDt) {
            $this->log->debug('Requesting event for a single point in time: ' . $this->getDate()->getLocalDate($fromDt));
        }

        $events = [];
        $fromTimeStamp = $fromDt->format('U');
        $toTimeStamp = $toDt->format('U');

        // Rewind the from date to the start of the month
        $fromDt->startOfMonth();

        if ($fromDt == $toDt) {
            $this->log->debug('From and To Dates are the same after rewinding 1 month, the date is the 1st of the month, adding a month to toDate.');
            $toDt->addMonth();
        }

        // Load the dates into a date object for parsing
        $eventStart = $this->getDate()->parse($this->fromDt, 'U');
        $eventEnd = ($this->toDt == null) ? $eventStart->copy() : $this->getDate()->parse($this->toDt, 'U');

        // Does the original event go over the month boundary?
        if ($eventStart->month !== $eventEnd->month) {
            // We expect some residual events to spill out into the month we are generating
            // wind back the generate from date
            $fromDt->subMonth();

            $this->getLog()->debug('Expecting events from the prior month to spill over into this one, pulled back the generate from dt to ' . $fromDt->toRssString());
        } else {
            $this->getLog()->debug('The main event has a start and end date within the month, no need to pull it in from the prior month. [eventId:' . $this->eventId . ']');
        }

        // Request month cache
        while ($fromDt < $toDt) {

            // Empty scheduleEvents as we are looping through each month
            // we dont want to save previous month events
            $this->scheduleEvents = [];

            // Events for the month.
            $this->generateMonth($fromDt, $eventStart, $eventEnd);

            $this->getLog()->debug('Filtering Events: ' . json_encode($this->scheduleEvents, JSON_PRETTY_PRINT) . '. fromTimeStamp: ' . $fromTimeStamp . ', toTimeStamp: ' . $toTimeStamp);

            foreach ($this->scheduleEvents as $scheduleEvent) {

                // Find the excluded recurring events
                $scheduleExclusions = $this->scheduleExclusionFactory->query(null, ['eventId' => $this->eventId]);

                $exclude = false;
                foreach ($scheduleExclusions as $exclusion) {
                    if ($scheduleEvent->fromDt == $exclusion->fromDt &&
                        $scheduleEvent->toDt == $exclusion->toDt) {
                        $exclude = true;
                        continue;
                    }
                }

                if ($exclude) {
                    continue;
                }

                if (in_array($scheduleEvent, $events)) {
                    continue;
                }

                if ($scheduleEvent->toDt == null) {
                    if ($scheduleEvent->fromDt >= $fromTimeStamp && $scheduleEvent->toDt < $toTimeStamp) {
                        $events[] = $scheduleEvent;
                    }
                } else {
                    if ($scheduleEvent->fromDt <= $toTimeStamp && $scheduleEvent->toDt > $fromTimeStamp) {
                        $events[] = $scheduleEvent;
                    }
                }
            }

            // Move the month forwards
            $fromDt->addMonth();
        }

        $this->getLog()->debug('Filtered ' . count($this->scheduleEvents) . ' to ' . count($events) . ', events: ' . json_encode($events, JSON_PRETTY_PRINT));

        return $events;
    }

    /**
     * Generate Instances
     * @param Date $generateFromDt
     * @param Date $start
     * @param Date $end
     * @throws XiboException
     */
    private function generateMonth($generateFromDt, $start, $end)
    {
        // Operate on copies of the dates passed.
        $start = $start->copy();
        $end = $end->copy();
        $generateFromDt->copy()->startOfMonth();
        $generateToDt = $generateFromDt->copy()->addMonth();

        $this->getLog()->debug('Request for schedule events on eventId ' . $this->eventId
            . ' from: ' . $this->getDate()->getLocalDate($generateFromDt)
            . ' to: ' . $this->getDate()->getLocalDate($generateToDt)
            . ' [eventId:' . $this->eventId . ']'
        );

        // If we are a daypart event, look up the start/end times for the event
        $this->calculateDayPartTimes($start, $end);

        // Does the original event fall into this window?
        if ($start <= $generateToDt && $end > $generateFromDt) {
            // Add the detail for the main event (this is the event that originally triggered the generation)
            $this->getLog()->debug('Adding original event: ' . $start->toAtomString() . ' - ' . $end->toAtomString());
            $this->addDetail($start->format('U'), $end->format('U'));
        }

        // If we don't have any recurrence, we are done
        if (empty($this->recurrenceType) || empty($this->recurrenceDetail))
            return;

        // Detect invalid recurrences and quit early
        if (!$this->isCustomDayPart() && ($this->recurrenceType == 'Minute' || $this->recurrenceType == 'Hour'))
            return;

        // Check the cache
        $item = $this->pool->getItem('schedule/' . $this->eventId . '/' . $generateFromDt->format('Y-m'));

        if ($item->isHit()) {
            $this->scheduleEvents = $item->get();
            $this->getLog()->debug('Returning from cache! [eventId:' . $this->eventId . ']');
            return;
        }

        $this->getLog()->debug('Cache miss! [eventId:' . $this->eventId . ']');

        // vv anything below here means that the event window requested is not in the cache vv
        // WE ARE NOT IN THE CACHE
        // this means we need to always walk the tree from the last watermark
        // if the last watermark is after the from window, then we need to walk from the beginning

        // Handle recurrence
        $originalStart = $start->copy();
        $lastWatermark = ($this->lastRecurrenceWatermark != 0) ?
            $this->getDate()->parse($this->lastRecurrenceWatermark, 'U')
            : $this->getDate()->parse(self::$DATE_MIN, 'U');

        $this->getLog()->debug('Recurrence calculation required - last water mark is set to: ' . $lastWatermark->toRssString()
            . '. Event dates: ' . $start->toRssString() . ' - ' . $end->toRssString() . ' [eventId:' . $this->eventId . ']');

        // Set the temp starts
        // the start date should be the latest of the event start date and the last recurrence date
        if ($lastWatermark > $start && $lastWatermark < $generateFromDt) {
            $this->getLog()->debug('The last watermark is later than the event start date and the generate from dt, using the watermark for forward population'
                . ' [eventId:' . $this->eventId . ']');

            // Need to set the toDt based on the original event duration and the watermark start date
            $eventDuration = $start->diffInSeconds($end, true);

            /** @var Date $start */
            $start = $lastWatermark->copy();
            $end = $start->copy()->addSeconds($eventDuration);

            if ($start <= $generateToDt && $end >= $generateFromDt) {
                $this->addDetail($start->format('U'), $end->format('U'));
                $this->getLog()->debug('The event start/end is inside the month' );
            }
        }

        // range should be the smallest of the recurrence range and the generate window todt
        // the start/end date should be the the first recurrence in the current window
        if ($this->recurrenceRange != 0) {
            $range = $this->getDate()->parse($this->recurrenceRange, 'U');

            // Override the range to be within the period we are looking
            $range = ($range < $generateToDt) ? $range : $generateToDt->copy();
        } else {
            $range = $generateToDt->copy();
        }

        $this->getLog()->debug('[' . $generateFromDt->toRssString() . ' - ' . $generateToDt->toRssString()
            . '] Looping from ' . $start->toRssString() . ' to ' . $range->toRssString() . ' [eventId:' . $this->eventId . ']');

        // loop until we have added the recurring events for the schedule
        while ($start < $range)
        {
            $this->getLog()->debug('Loop: ' . $start->toRssString() . ' to ' . $range->toRssString() . ' [eventId:' . $this->eventId . ', end: ' . $end->toRssString() . ']');

            // add the appropriate time to the start and end
            switch ($this->recurrenceType)
            {
                case 'Minute':
                    $start->minute($start->minute + $this->recurrenceDetail);
                    $end->minute($end->minute + $this->recurrenceDetail);
                    break;

                case 'Hour':
                    $start->hour($start->hour + $this->recurrenceDetail);
                    $end->hour($end->hour + $this->recurrenceDetail);
                    break;

                case 'Day':
                    $start->day($start->day + $this->recurrenceDetail);
                    $end->day($end->day + $this->recurrenceDetail);
                    break;

                case 'Week':
                    // recurrenceRepeatsOn will contain an array we can use to determine which days it should repeat
                    // on. Roll forward 7 days, adding each day we hit
                    // if we go over the start of the week, then jump forward by the recurrence range
                    if (!empty($this->recurrenceRepeatsOn)) {
                        // Parse days selected and add the necessary events
                        $daysSelected = explode(',', $this->recurrenceRepeatsOn);

                        // Are we on the start day of this week already?
                        $onStartOfWeek = ($start->copy()->setTimeFromTimeString('00:00:00') == $start->copy()->startOfWeek()->setTimeFromTimeString('00:00:00'));

                        // What is the end of this week
                        $endOfWeek = $start->copy()->endOfWeek();

                        $this->getLog()->debug('Days selected: ' . $this->recurrenceRepeatsOn . '. End of week = ' . $endOfWeek . ' start date ' . $start . ' [eventId:' . $this->eventId . ']');

                        for ($i = 1; $i <= 7; $i++) {
                            // Add a day to the start dates
                            // after the first pass, we will already be on the first day of the week
                            if ($i > 1 || !$onStartOfWeek) {
                                $start->day($start->day + 1);
                                $end->day($end->day + 1);
                            }

                            $this->getLog()->debug('Assessing start date ' . $start->toAtomString()
                                . ', isoDayOfWeek is ' . $start->dayOfWeekIso . ' [eventId:' . $this->eventId . ']');

                            // If we go over the recurrence range, stop
                            // if we go over the start of the week, stop
                            if ($start > $range || $start > $endOfWeek) {
                                break;
                            }

                            // Is this day set?
                            if (!in_array($start->dayOfWeekIso, $daysSelected)) {
                                continue;
                            }

                            if ($start >= $generateFromDt) {
                                $this->getLog()->debug('Adding detail for ' . $start->toAtomString() . ' to ' . $end->toAtomString());

                                if ($this->eventTypeId == self::$COMMAND_EVENT) {
                                    $this->addDetail($start->format('U'), null);
                                }
                                else {
                                    // If we are a daypart event, look up the start/end times for the event
                                    $this->calculateDayPartTimes($start, $end);

                                    $this->addDetail($start->format('U'), $end->format('U'));
                                }
                            } else {
                                $this->getLog()->debug('Event is outside range');
                            }
                        }

                        $this->getLog()->debug('Finished 7 day roll forward, start date is ' . $start . ' [eventId:' . $this->eventId . ']');

                        // If we haven't passed the end of the week, roll forward
                        if ($start < $endOfWeek) {
                            $start->day($start->day + 1);
                            $end->day($end->day + 1);
                        }

                        // Wind back a week and then add our recurrence detail
                        $start->day($start->day - 7);
                        $end->day($end->day - 7);

                        $this->getLog()->debug('Resetting start date to ' . $start . ' [eventId:' . $this->eventId . ']');
                    }

                    // Jump forward a week from the original start date (when we entered this loop)
                    $start->day($start->day + ($this->recurrenceDetail * 7));
                    $end->day($end->day + ($this->recurrenceDetail * 7));

                    break;

                case 'Month':
                    // Are we repeating on the day of the month, or the day of the week
                    if ($this->recurrenceMonthlyRepeatsOn == 1) {
                        // Week day repeat
                        $difference = $end->diffInSeconds($start);

                        // Work out the position in the month of this day and the ordinal
                        $ordinals = ['first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh'];
                        $ordinal = $ordinals[ceil($originalStart->day / 7) - 1];
                        $start->month($start->month + $this->recurrenceDetail)->modify($ordinal . ' ' . $originalStart->format('l') . ' of ' . $start->format('F Y'))->setTimeFrom($originalStart);

                        $this->getLog()->debug('Setting start to: ' . $ordinal . ' ' . $start->format('l') . ' of ' . $start->format('F Y'));

                        // Base the end on the start + difference
                        $end = $start->copy()->addSeconds($difference);
                    } else {
                        // Day repeat
                        $start->month($start->month + $this->recurrenceDetail);
                        $end->month($end->month + $this->recurrenceDetail);
                    }
                    break;

                case 'Year':
                    $start->year($start->year + $this->recurrenceDetail);
                    $end->year($end->year + $this->recurrenceDetail);
                    break;

                default:
                    throw new InvalidArgumentException('Invalid recurrence type', 'recurrenceType');
            }

            // after we have added the appropriate amount, are we still valid
            if ($start > $range) {
                $this->getLog()->debug('Breaking mid loop because we\'ve exceeded the range. Start: ' . $start->toRssString() . ', range: ' . $range->toRssString() . ' [eventId:' . $this->eventId . ']');
                break;
            }

            // Push the watermark
            $lastWatermark = $start->copy();

            // Don't add if we are weekly recurrency (handles it's own adding)
            if ($this->recurrenceType == 'Week' && !empty($this->recurrenceRepeatsOn))
                continue;

            if ($start <= $generateToDt && $end >= $generateFromDt) {
                if ($this->eventTypeId == self::$COMMAND_EVENT)
                    $this->addDetail($start->format('U'), null);
                else {
                    // If we are a daypart event, look up the start/end times for the event
                    $this->calculateDayPartTimes($start, $end);

                    $this->addDetail($start->format('U'), $end->format('U'));
                }
            }
        }

        $this->getLog()->debug('Our last recurrence watermark is: ' . $lastWatermark->toRssString() . '[eventId:' . $this->eventId . ']');

        // Update our schedule with the new last watermark
        $lastWatermarkTimeStamp = $lastWatermark->format('U');

        if ($lastWatermarkTimeStamp != $this->lastRecurrenceWatermark) {
            $this->lastRecurrenceWatermark = $lastWatermarkTimeStamp;
            $this->getStore()->update('UPDATE `schedule` SET lastRecurrenceWatermark = :lastRecurrenceWatermark WHERE eventId = :eventId', [
                'eventId' => $this->eventId,
                'lastRecurrenceWatermark' => $this->lastRecurrenceWatermark
            ]);
        }

        // Update the cache
        $item->set($this->scheduleEvents);
        $item->expiresAt(Date::now()->addMonths(2));

        $this->pool->saveDeferred($item);

        return;
    }

    /**
     * Drop the event cache
     * @param $key
     */
    private function dropEventCache($key = null)
    {
        $compKey = 'schedule/' . $this->eventId;

        if ($key !== null)
            $compKey .= '/' . $key;

        $this->pool->deleteItem($compKey);
    }

    /**
     * Calculate the DayPart times
     * @param Date $start
     * @param Date $end
     * @throws XiboException
     */
    private function calculateDayPartTimes($start, $end)
    {
        $dayOfWeekLookup = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        if (!$this->isAlwaysDayPart() && !$this->isCustomDayPart()) {

            // End is always based on Start
            $end->setTimestamp($start->format('U'));

            $dayPart = $this->dayPartFactory->getById($this->dayPartId);

            $this->getLog()->debug('Start and end time for dayPart is ' . $dayPart->startTime . ' - ' . $dayPart->endTime);

            // What day of the week does this start date represent?
            // dayOfWeek is 0 for Sunday to 6 for Saturday
            $found = false;
            foreach ($dayPart->exceptions as $exception) {
                // Is there an exception for this day of the week?
                if ($exception['day'] == $dayOfWeekLookup[$start->dayOfWeek]) {
                    $start->setTimeFromTimeString($exception['start']);
                    $end->setTimeFromTimeString($exception['end']);

                    if ($start >= $end)
                        $end->addDay();

                    $this->getLog()->debug('Found exception Start and end time for dayPart exception is ' . $exception['start'] . ' - ' . $exception['end']);
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                // Set the time section of our dates based on the daypart date
                $start->setTimeFromTimeString($dayPart->startTime);
                $end->setTimeFromTimeString($dayPart->endTime);

                if ($start >= $end) {
                    $this->getLog()->debug('Start is ahead of end - adding a day to the end date');
                    $end->addDay();
                }
            }
        }
    }

    /**
     * Add Detail
     * @param int $fromDt
     * @param int $toDt
     */
    private function addDetail($fromDt, $toDt)
    {
        $this->scheduleEvents[] = new ScheduleEvent($fromDt, $toDt);
    }

    /**
     * Manage the assignments
     * @param bool $notify should we notify or not?
     * @throws \Xibo\Exception\XiboException
     */
    private function manageAssignments($notify)
    {
        $this->linkDisplayGroups();
        $this->unlinkDisplayGroups();

        $this->getLog()->debug('manageAssignments: Assessing whether we need to notify');
        $originalDisplayGroups = $this->getOriginalValue('displayGroups');

        // Get the difference between the original display groups assigned and the new display groups assigned
        if ($notify && $originalDisplayGroups !== null && $this->inScheduleLookAhead()) {
            $diff = [];
            foreach ($originalDisplayGroups as $element) {
                /** @var \Xibo\Entity\DisplayGroup $element */
                $diff[$element->getId()] = $element;
            }

            if (count($diff) > 0) {
                $this->getLog()->debug('manageAssignments: There are ' . count($diff) . ' existing DisplayGroups on this Event');
                $ids = array_map(function ($element) {
                    return $element->getId();
                }, $this->displayGroups);

                $except = array_diff(array_keys($diff), $ids);

                if (count($except) > 0) {
                    foreach ($except as $item) {
                        $this->getLog()->debug('manageAssignments: calling notify on displayGroupId ' . $diff[$item]->getId());
                        $this->displayFactory->getDisplayNotifyService()->collectNow()->notifyByDisplayGroupId($diff[$item]->getId());
                    }
                } else {
                    $this->getLog()->debug('manageAssignments: No need to notify');
                }
            } else {
                $this->getLog()->debug('manageAssignments: No change to DisplayGroup assignments');
            }
        } else {
            $this->getLog()->debug('manageAssignments: Not in look-ahead');
        }
    }

    /**
     * Link Layout
     */
    private function linkDisplayGroups()
    {
        // TODO: Make this more efficient by storing the prepared SQL statement
        $sql = 'INSERT INTO `lkscheduledisplaygroup` (eventId, displayGroupId) VALUES (:eventId, :displayGroupId) ON DUPLICATE KEY UPDATE displayGroupId = displayGroupId';

        $i = 0;
        foreach ($this->displayGroups as $displayGroup) {
            $i++;

            $this->getStore()->insert($sql, array(
                'eventId' => $this->eventId,
                'displayGroupId' => $displayGroup->displayGroupId
            ));
        }
    }

    /**
     * Unlink Layout
     */
    private function unlinkDisplayGroups()
    {
        // Unlink any layouts that are NOT in the collection
        $params = ['eventId' => $this->eventId];

        $sql = 'DELETE FROM `lkscheduledisplaygroup` WHERE eventId = :eventId AND displayGroupId NOT IN (0';

        $i = 0;
        foreach ($this->displayGroups as $displayGroup) {
            $i++;
            $sql .= ',:displayGroupId' . $i;
            $params['displayGroupId' . $i] = $displayGroup->displayGroupId;
        }

        $sql .= ')';

        $this->getStore()->update($sql, $params);
    }

    /**
     * Is this event an always daypart event
     * @return bool
     * @throws \Xibo\Exception\NotFoundException
     */
    public function isAlwaysDayPart()
    {
        $dayPart = $this->dayPartFactory->getById($this->dayPartId);

        return $dayPart->isAlways === 1;
    }

    /**
     * Is this event a custom daypart event
     * @return bool
     * @throws \Xibo\Exception\NotFoundException
     */
    public function isCustomDayPart()
    {
        $dayPart = $this->dayPartFactory->getById($this->dayPartId);

        return $dayPart->isCustom === 1;
    }

    /**
     * Get next reminder date
     * @param Date $now
     * @param ScheduleReminder $reminder
     * @param int $remindSeconds
     * @return int|null
     * @throws NotFoundException
     * @throws XiboException
     */
    public function getNextReminderDate($now, $reminder, $remindSeconds) {

        // Determine toDt so that we don't getEvents which never ends
        // adding the recurrencedetail at the end (minute/hour/week) to make sure we get at least 2 next events
        $toDt = $now->copy();

        // For a future event we need to forward now to event fromDt
        $fromDt = $this->getDate()->parse($this->fromDt, 'U');
        if ( $fromDt > $toDt ) {
            $toDt = $fromDt;
        }

        switch ($this->recurrenceType)
        {

            case 'Minute':
                $toDt->minute(($toDt->minute + $this->recurrenceDetail) + $this->recurrenceDetail);
                break;

            case 'Hour':
                $toDt->hour(($toDt->hour + $this->recurrenceDetail) + $this->recurrenceDetail);
                break;

            case 'Day':
                $toDt->day(($toDt->day + $this->recurrenceDetail) + $this->recurrenceDetail);
                break;

            case 'Week':
                $toDt->day(($toDt->day + $this->recurrenceDetail * 7 ) + $this->recurrenceDetail);
                break;

            case 'Month':
                $toDt->month(($toDt->month + $this->recurrenceDetail ) + $this->recurrenceDetail);
                break;

            case 'Year':
                $toDt->year(($toDt->year + $this->recurrenceDetail ) + $this->recurrenceDetail);
                break;

            default:
                throw new InvalidArgumentException('Invalid recurrence type', 'recurrenceType');
        }

        // toDt is set so that we get two next events from now
        $scheduleEvents = $this->getEvents($now, $toDt);

        foreach($scheduleEvents as $event) {
            if ($reminder->option == ScheduleReminder::$OPTION_BEFORE_START) {
                $reminderDt = $event->fromDt - $remindSeconds;
                if ($reminderDt >= $now->format('U')) {
                    return $reminderDt;
                }
            } elseif ($reminder->option == ScheduleReminder::$OPTION_AFTER_START) {
                $reminderDt = $event->fromDt + $remindSeconds;
                if ($reminderDt >= $now->format('U')) {
                    return $reminderDt;
                }
            } elseif ($reminder->option == ScheduleReminder::$OPTION_BEFORE_END) {
                $reminderDt = $event->toDt - $remindSeconds;
                if ($reminderDt >= $now->format('U')) {
                    return $reminderDt;
                }
            } elseif ($reminder->option == ScheduleReminder::$OPTION_AFTER_END) {
                $reminderDt = $event->toDt + $remindSeconds;
                if ($reminderDt >= $now->format('U')) {
                    return $reminderDt;
                }
            }
        }

        // No next event exist
        throw new NotFoundException('reminderDt not found as next event does not exist');
    }

    /**
     * Get event title
     * @return string
     * @throws XiboException
     */
    public function getEventTitle() {

        // Setting for whether we show Layouts with out permissions
        $showLayoutName = ($this->config->getSetting('SCHEDULE_SHOW_LAYOUT_NAME') == 1);

        // Load the display groups
        $this->load();

        $displayGroupList = '';

        if (count($this->displayGroups) >= 0) {
            $array = array_map(function ($object) {
                return $object->displayGroup;
            }, $this->displayGroups);
            $displayGroupList = implode(', ', $array);
        }

        $user = $this->userFactory->getById($this->userId);

        // Event Title
        if ($this->campaignId == 0) {
            // Command
            $title = __('%s scheduled on %s', $this->command, $displayGroupList);
        } else {
            // Should we show the Layout name, or not (depending on permission)
            // Make sure we only run the below code if we have to, its quite expensive
            if (!$showLayoutName && !$user->isSuperAdmin()) {
                // Campaign
                $campaign = $this->campaignFactory->getById($this->campaignId);

                if (!$user->checkViewable($campaign))
                    $this->campaign = __('Private Item');
            }
            $title = __('%s scheduled on %s', $this->campaign, $displayGroupList);
        }

        return $title;
    }
}