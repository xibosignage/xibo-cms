<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Schedule.php)
 */


namespace Xibo\Entity;

use Jenssegers\Date\Date;
use Respect\Validation\Validator as v;
use Stash\Interfaces\PoolInterface;
use Xibo\Exception\ConfigurationException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\XiboException;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
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
    public static $DAY_PART_CUSTOM = 0;
    public static $DAY_PART_ALWAYS = 1;
    public static $DATE_MIN = 0;
    public static $DATE_MAX = 2556057600;

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
     * Last Recurrence Watermark
     * @var int
     */
    public $lastRecurrenceWatermark;

    /**
     * @SWG\Property(description="Flag indicating whether the event will sync to the Display timezone")
     * @var int
     */
    public $syncTimezone;

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

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param PoolInterface $pool
     * @param DisplayGroupFactory $displayGroupFactory
     */
    public function __construct($store, $log, $config, $pool, $displayGroupFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->config = $config;
        $this->pool = $pool;
        $this->displayGroupFactory = $displayGroupFactory;

        $this->excludeProperty('lastRecurrenceWatermark');
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
     * @param DayPartFactory $dayPartFactory
     * @return $this
     */
    public function setDayPartFactory($dayPartFactory)
    {
        $this->dayPartFactory = $dayPartFactory;
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
     */
    private function inScheduleLookAhead()
    {
        if ($this->dayPartId == Schedule::$DAY_PART_ALWAYS)
            return true;

        // From Date and To Date are in UNIX format
        $currentDate = time();
        $rfLookAhead = intval($currentDate) + intval($this->config->GetSetting('REQUIRED_FILES_LOOKAHEAD'));

        // If we are a recurring schedule and our recurring date is out after the required files lookahead
        if ($this->recurrenceType != '')
            return ($this->fromDt <= $currentDate && ($this->recurrenceRange == 0 || $this->recurrenceRange > $rfLookAhead));

        // Compare the event dates
        return ($this->fromDt < $rfLookAhead && $this->toDt > $currentDate);
    }

    /**
     * Load
     */
    public function load()
    {
        // If we are already loaded, then don't do it again
        if ($this->loaded || $this->eventId == null || $this->eventId == 0)
            return;

        $this->displayGroups = $this->displayGroupFactory->getByEventId($this->eventId);

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
     */
    public function validate()
    {
        if (count($this->displayGroups) <= 0)
            throw new InvalidArgumentException(__('No display groups selected'), 'displayGroups');

        $this->getLog()->debug('EventTypeId: %d. DayPartId: %d, CampaignId: %d, CommandId: %d', $this->eventTypeId, $this->dayPartId, $this->campaignId, $this->commandId);

        if ($this->eventTypeId == Schedule::$LAYOUT_EVENT || $this->eventTypeId == Schedule::$OVERLAY_EVENT) {
            // Validate layout
            if (!v::int()->notEmpty()->validate($this->campaignId))
                throw new InvalidArgumentException(__('Please select a Campaign/Layout for this event.'), 'campaignId');

            if ($this->dayPartId == Schedule::$DAY_PART_CUSTOM) {
                // validate the dates
                if ($this->toDt <= $this->fromDt)
                    throw new InvalidArgumentException(__('Can not have an end time earlier than your start time'), 'start/end');
            }

            $this->commandId = null;

        } else if ($this->eventTypeId == Schedule::$COMMAND_EVENT) {
            // Validate command
            if (!v::int()->notEmpty()->validate($this->commandId))
                throw new InvalidArgumentException(__('Please select a Command for this event.'), 'command');

            $this->campaignId = null;
            $this->toDt = null;

        } else {
            // No event type selected
            throw new InvalidArgumentException(__('Please select the Event Type'), 'eventTypeId');
        }
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

        // Handle "always" day parts
        if ($this->dayPartId == \Xibo\Entity\Schedule::$DAY_PART_ALWAYS) {
            $this->fromDt = self::$DATE_MIN;
            $this->toDt = self::$DATE_MAX;
        }

        if ($this->eventId == null || $this->eventId == 0) {
            $this->add();
            $this->loaded = true;
        }
        else
            $this->edit();

        // Manage display assignments
        if ($this->loaded) {
            // Manage assignments
            $this->manageAssignments();
        }

        // Notify
        // Only if the schedule effects the immediate future - i.e. within the RF Look Ahead
        if ($this->inScheduleLookAhead()) {
            $this->getLog()->debug('Schedule changing is within the schedule look ahead, will notify %d display groups', $this->displayGroups);
            foreach ($this->displayGroups as $displayGroup) {
                /* @var DisplayGroup $displayGroup */
                $this->displayFactory->getDisplayNotifyService()->collectNow()->notifyByDisplayGroupId($displayGroup->displayGroupId);
            }
        }

        // Drop the cache for this event
        $this->dropEventCache();
    }

    /**
     * Delete this Schedule Event
     */
    public function delete()
    {
        // Notify display groups
        $notify = $this->displayGroups;

        // Delete display group assignments
        $this->displayGroups = [];
        $this->unlinkDisplayGroups();

        // Delete the event itself
        $this->getStore()->update('DELETE FROM `schedule` WHERE eventId = :eventId', ['eventId' => $this->eventId]);

        // Notify
        // Only if the schedule effects the immediate future - i.e. within the RF Look Ahead
        if ($this->inScheduleLookAhead()) {
            $this->getLog()->debug('Schedule changing is within the schedule look ahead, will notify ' . count($this->displayGroups) . ' display groups');
            foreach ($notify as $displayGroup) {
                /* @var DisplayGroup $displayGroup */
                $this->displayFactory->getDisplayNotifyService()->collectNow()->notifyByDisplayGroupId($displayGroup->displayGroupId);
            }
        }

        // Drop the cache for this event
        $this->dropEventCache();
    }

    /**
     * Add
     */
    private function add()
    {
        $this->eventId = $this->getStore()->insert('
          INSERT INTO `schedule` (eventTypeId, CampaignId, commandId, userID, is_priority, FromDT, ToDT, DisplayOrder, recurrence_type, recurrence_detail, recurrence_range, `recurrenceRepeatsOn`, `dayPartId`, `syncTimezone`)
            VALUES (:eventTypeId, :campaignId, :commandId, :userId, :isPriority, :fromDt, :toDt, :displayOrder, :recurrenceType, :recurrenceDetail, :recurrenceRange, :recurrenceRepeatsOn, :dayPartId, :syncTimezone)
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
            'dayPartId' => $this->dayPartId,
            'syncTimezone' => $this->syncTimezone
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
            `dayPartId` = :dayPartId,
            `syncTimezone` = :syncTimezone
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
            'dayPartId' => $this->dayPartId,
            'syncTimezone' => $this->syncTimezone,
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
        // Copy the dates as we are going to be operating on them.
        $fromDt = $fromDt->copy();
        $toDt = $toDt->copy();

        if ($this->pool == null)
            throw new ConfigurationException(__('Cache pool not available'));

        if ($this->eventId == null)
            throw new InvalidArgumentException(__('Unable to generate schedule, unknown event'), 'eventId');

        $events = [];
        $fromTimeStamp = $fromDt->format('U');
        $toTimeStamp = $toDt->format('U');

        // Rewind the from date to the start of the month
        $fromDt->startOfMonth();

        // Request month cache
        while ($fromDt < $toDt) {
            $this->generateMonth($fromDt);

            $this->getLog()->debug('Events: ' . json_encode($this->scheduleEvents, JSON_PRETTY_PRINT));

            foreach ($this->scheduleEvents as $scheduleEvent) {

                if (in_array($scheduleEvent, $events))
                    continue;

                if ($scheduleEvent->toDt == null) {
                    if ($scheduleEvent->fromDt >= $fromTimeStamp && $scheduleEvent->toDt < $toTimeStamp)
                        $events[] = $scheduleEvent;
                } else {
                    if ($scheduleEvent->fromDt <= $toTimeStamp && $scheduleEvent->toDt > $fromTimeStamp)
                        $events[] = $scheduleEvent;
                }
            }

            // Move the month forwards
            $fromDt->addMonth();
        }

        $this->getLog()->debug('Filtered ' . count($this->scheduleEvents) . ' to ' . count($events));

        return $events;
    }

    /**
     * Generate Instances
     * @param Date $generateFromDt
     * @throws XiboException
     */
    private function generateMonth($generateFromDt)
    {
        $generateFromDt->startOfMonth();
        $generateToDt = $generateFromDt->copy()->addMonth();

        $this->getLog()->debug('Request for schedule events on eventId ' . $this->eventId
            . ' from: ' . $this->getDate()->getLocalDate($generateFromDt)
            . ' to: ' . $this->getDate()->getLocalDate($generateToDt)
            . ' [eventId:' . $this->eventId . ']'
        );

        // Events scheduled "always" will return one event
        if ($this->dayPartId == Schedule::$DAY_PART_ALWAYS) {
            // Create events with min/max dates
            $this->addDetail(Schedule::$DATE_MIN, Schedule::$DATE_MAX);
            return;
        }

        // Load the dates into a date object for parsing
        $start = $this->getDate()->parse($this->fromDt, 'U');
        $end = ($this->toDt == null) ? $start->copy() : $this->getDate()->parse($this->toDt, 'U');

        // If we are a daypart event, look up the start/end times for the event
        $this->calculateDayPartTimes($start, $end);

        // Does the original event fall into this window?
        if ($start <= $generateToDt && $end > $generateFromDt) {
            // Add the detail for the main event (this is the event that originally triggered the generation)
            $this->addDetail($start->format('U'), $end->format('U'));
        } else {
            $this->getLog()->debug('Main event is not in the current generation window. [eventId:' . $this->eventId . ']');
        }

        // If we don't have any recurrence, we are done
        if (empty($this->recurrenceType) || empty($this->recurrenceDetail))
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
        $lastWatermark = ($this->lastRecurrenceWatermark != 0) ? $this->getDate()->parse($this->lastRecurrenceWatermark, 'U') : $this->getDate()->parse(self::$DATE_MIN, 'U');

        $this->getLog()->debug('Recurrence calculation required - last water mark is set to: ' . $lastWatermark->toRssString() . '. Event dates: ' . $start->toRssString() . ' - ' . $end->toRssString() . ' [eventId:' . $this->eventId . ']');

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
            $this->getLog()->debug('Loop: ' . $start->toRssString() . ' to ' . $range->toRssString() . ' [eventId:' . $this->eventId . ']');

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
                    // dayOfWeek is 0 for Sunday to 6 for Saturday
                    // daysSelected is 1 for Monday to 7 for Sunday
                    $dayOfWeekLookup = [7,1,2,3,4,5,6];

                    // recurrenceRepeatsOn will contain an array we can use to determine which days it should repeat
                    // on. Roll forward 7 days, adding each day we hit
                    // if we go over the start of the week, then jump forward by the recurrence range
                    if (!empty($this->recurrenceRepeatsOn)) {
                        // Parse days selected and add the necessary events
                        $daysSelected = explode(',', $this->recurrenceRepeatsOn);

                        // Are we on the start day of this week already?
                        $onStartOfWeek = ($start->copy()->setTime(0,0,0) == $start->copy()->startOfWeek()->setTime(0,0,0));

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

                            $this->getLog()->debug('End of week = ' . $endOfWeek . ' assessing start date ' . $start . ' [eventId:' . $this->eventId . ']');

                            // If we go over the recurrence range, stop
                            // if we go over the start of the week, stop
                            if ($start > $range || $start > $endOfWeek) {
                                break;
                            }

                            // Is this day set?
                            if (!in_array($dayOfWeekLookup[$start->dayOfWeek], $daysSelected))
                                continue;

                            if ($start >= $generateFromDt) {
                                if ($this->toDt == null) {
                                    $this->addDetail($start->format('U'), null);
                                }
                                else {
                                    // If we are a daypart event, look up the start/end times for the event
                                    $this->calculateDayPartTimes($start, $end);

                                    $this->addDetail($start->format('U'), $end->format('U'));
                                }
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
                    $start->month($start->month + $this->recurrenceDetail);
                    $end->month($end->month + $this->recurrenceDetail);
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

            if ($start >= $generateFromDt) {
                if ($this->toDt == null)
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
     */
    private function calculateDayPartTimes($start, $end)
    {
        $dayOfWeekLookup = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        if ($this->dayPartId != Schedule::$DAY_PART_ALWAYS && $this->dayPartId != Schedule::$DAY_PART_CUSTOM) {

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

                    if ($start > $end)
                        $end->addDay();

                    $this->getLog()->debug('Found exception Start and end time for dayPart exception is ' . $exception['start'] . ' - ' . $exception['end']);
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                if ($start > $end)
                    $end->addDay();

                $start->setTimeFromTimeString($dayPart->startTime);
                $end->setTimeFromTimeString($dayPart->endTime);
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
     */
    private function manageAssignments()
    {
        $this->linkDisplayGroups();
        $this->unlinkDisplayGroups();
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
}