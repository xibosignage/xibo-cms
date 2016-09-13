<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Schedule.php)
 */


namespace Xibo\Entity;

use Jenssegers\Date\Date;
use Respect\Validation\Validator as v;
use Xibo\Exception\ConfigurationException;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ScheduleFactory;
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
     * Is this event (as a whole) inside the schedule look ahead period?
     * @var bool
     */
    private $isInScheduleLookAhead = false;

    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /** @var  DateServiceInterface */
    private $dateService;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var  LayoutFactory */
    private $layoutFactory;

    /** @var  MediaFactory */
    private $mediaFactory;

    /** @var  ScheduleFactory */
    private $scheduleFactory;

    /** @var  DayPartFactory */
    private $dayPartFactory;

    /**
     * Entity constructor.
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param DisplayGroupFactory $displayGroupFactory
     */
    public function __construct($store, $log, $config, $displayGroupFactory)
    {
        $this->setCommonDependencies($store, $log);
        $this->config = $config;
        $this->displayGroupFactory = $displayGroupFactory;
    }

    /**
     * @param DisplayFactory $displayFactory
     * @param LayoutFactory $layoutFactory
     * @param MediaFactory $mediaFactory
     * @param ScheduleFactory $scheduleFactory
     * @param DayPartFactory $dayPartFactory
     * @return $this
     */
    public function setChildObjectDependencies($displayFactory, $layoutFactory, $mediaFactory, $scheduleFactory, $dayPartFactory = null)
    {
        $this->displayFactory = $displayFactory;
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
        $this->scheduleFactory = $scheduleFactory;
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
     * @param $fromDt
     * @param $toDt
     * @return bool
     */
    private function datesInScheduleLookAhead($fromDt, $toDt)
    {
        if ($this->dayPartId == Schedule::$DAY_PART_ALWAYS)
            return true;

        // From Date and To Date are in UNIX format
        $currentDate = time();
        $rfLookAhead = intval($currentDate) + intval($this->config->GetSetting('REQUIRED_FILES_LOOKAHEAD'));

        if ($toDt == null)
            $toDt = $fromDt;

        $this->getLog()->debug('Checking to see if %d and %d are between %d and %d', $fromDt, $toDt, $currentDate, $rfLookAhead);

        return ($fromDt < $rfLookAhead && $toDt > $currentDate);
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
            throw new \InvalidArgumentException(__('No display groups selected'));

        $this->getLog()->debug('EventTypeId: %d. DayPartId: %d, CampaignId: %d, CommandId: %d', $this->eventTypeId, $this->dayPartId, $this->campaignId, $this->commandId);

        if ($this->eventTypeId == Schedule::$LAYOUT_EVENT || $this->eventTypeId == Schedule::$OVERLAY_EVENT) {
            // Validate layout
            if (!v::int()->notEmpty()->validate($this->campaignId))
                throw new \InvalidArgumentException(__('Please select a Campaign/Layout for this event.'));

            if ($this->dayPartId == Schedule::$DAY_PART_CUSTOM) {
                // validate the dates
                if ($this->toDt < $this->fromDt)
                    throw new \InvalidArgumentException(__('Can not have an end time earlier than your start time'));
            }

            $this->commandId = null;

        } else if ($this->eventTypeId == Schedule::$COMMAND_EVENT) {
            // Validate command
            if (!v::int()->notEmpty()->validate($this->commandId))
                throw new \InvalidArgumentException(__('Please select a Command for this event.'));

            $this->campaignId = null;
            $this->toDt = null;

        } else {
            // No event type selected
            throw new \InvalidArgumentException(__('Please select the Event Type'));
        }
    }

    /**
     * Save
     * @param array $options
     */
    public function save($options = [])
    {
        $options = array_merge([
            'validate' => true,
            'generate' => true
        ], $options);

        if ($options['validate'])
            $this->validate();

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

        // Check the main event dates to see if we are in the schedule look ahead
        $this->isInScheduleLookAhead = $this->datesInScheduleLookAhead($this->fromDt, $this->toDt);

        // Generate the event instances
        if ($options['generate'])
            $this->generate();

        // Notify
        // Only if the schedule effects the immediate future - i.e. within the RF Look Ahead
        if ($this->isInScheduleLookAhead) {
            $this->getLog()->debug('Schedule changing is within the schedule look ahead, will notify %d display groups', $this->displayGroups);
            foreach ($this->displayGroups as $displayGroup) {
                /* @var DisplayGroup $displayGroup */
                $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
                $displayGroup->setCollectRequired();
                $displayGroup->setMediaIncomplete();
            }
        }
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

        // Delete all detail records
        $this->deleteDetail();

        // Delete the event itself
        $this->getStore()->update('DELETE FROM `schedule` WHERE eventId = :eventId', ['eventId' => $this->eventId]);

        // Check the main event dates to see if we are in the schedule look ahead
        $this->isInScheduleLookAhead = $this->datesInScheduleLookAhead($this->fromDt, $this->toDt);

        // Notify
        // Only if the schedule effects the immediate future - i.e. within the RF Look Ahead
        if ($this->isInScheduleLookAhead) {
            $this->getLog()->debug('Schedule changing is within the schedule look ahead, will notify %d display groups', $this->displayGroups);
            foreach ($notify as $displayGroup) {
                /* @var DisplayGroup $displayGroup */
                $displayGroup->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory);
                $displayGroup->setCollectRequired();
                $displayGroup->setMediaIncomplete();
            }
        }
    }

    /**
     * Add
     */
    private function add()
    {
        $this->eventId = $this->getStore()->insert('
          INSERT INTO `schedule` (eventTypeId, CampaignId, commandId, userID, is_priority, FromDT, ToDT, DisplayOrder, recurrence_type, recurrence_detail, recurrence_range, `recurrenceRepeatsOn`, `dayPartId`)
            VALUES (:eventTypeId, :campaignId, :commandId, :userId, :isPriority, :fromDt, :toDt, :displayOrder, :recurrenceType, :recurrenceDetail, :recurrenceRange, :recurrenceRepeatsOn, :dayPartId)
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
            'dayPartId' => $this->dayPartId
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
            `dayPartId` = :dayPartId
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
            'eventId' => $this->eventId
        ]);

        // Delete detail and regenerate
        $this->deleteDetail();
    }

    /**
     * Generate Instances
     */
    private function generate()
    {
        // Always events only have 1 detail entry
        if ($this->dayPartId == Schedule::$DAY_PART_ALWAYS) {
            // Create events with min/max dates
            $this->addDetail(Schedule::$DATE_MIN, Schedule::$DATE_MAX);
            return;
        }

        // Load the dates into a date object for parsing
        $start = $this->getDate()->parse($this->fromDt, 'U');
        $end = $this->getDate()->parse($this->toDt, 'U');

        // If we are a daypart event, look up the start/end times for the event
        $this->calculateDayPartTimes($start, $end);

        // Add the detail for the main event (this is the event that originally triggered the generation)
        $this->addDetail($start->format('U'), $end->format('U'));

        // If we don't have any recurrence, we are done
        if (empty($this->recurrenceType))
            return;

        // Set the temp starts
        $range = $this->getDate()->parse($this->recurrenceRange, 'U');

        // loop until we have added the recurring events for the schedule
        while ($start < $range)
        {
            // add the appropriate time to the start and end
            switch ($this->recurrenceType)
            {
                case 'Minute':
                    $start->addMinutes($this->recurrenceDetail);
                    $end->addMinutes($this->recurrenceDetail);
                    break;

                case 'Hour':
                    $start->addHours($this->recurrenceDetail);
                    $end->addHours($this->recurrenceDetail);
                    break;

                case 'Day':
                    $start->addDays($this->recurrenceDetail);
                    $end->addDays($this->recurrenceDetail);
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

                        $this->getLog()->debug('Days selected: ' . $this->recurrenceRepeatsOn . '. End of week = ' . $endOfWeek . ' start date ' . $start);

                        for ($i = 1; $i <= 7; $i++) {
                            // Add a day to the start dates
                            // after the first pass, we will already be on the first day of the week
                            if ($i > 1 || !$onStartOfWeek) {
                                $start->addDay(1);
                                $end->addDay(1);
                            }

                            $this->getLog()->debug('End of week = ' . $endOfWeek . ' assessing start date ' . $start);

                            // If we go over the recurrence range, stop
                            // if we go over the start of the week, stop
                            if ($start > $range || $start > $endOfWeek) {
                                break;
                            }

                            // Is this day set?
                            if (!in_array($dayOfWeekLookup[$start->dayOfWeek], $daysSelected))
                                continue;

                            if ($this->toDt == null)
                                $this->addDetail($start->format('U'), null);
                            else {
                                // Check to make sure that our from/to date isn't longer than the first repeat
                                if ($start->format('U') < $this->toDt) {
                                    $this->getLog()->debug($start->toDateTimeString() . ' is before ' . $this->getDate()->parse($this->toDt, 'U')->toDateTimeString());
                                    throw new \InvalidArgumentException(__('The first event repeat is inside the event from/to dates.'));
                                }

                                // If we are a daypart event, look up the start/end times for the event
                                $this->calculateDayPartTimes($start, $end);

                                $this->addDetail($start->format('U'), $end->format('U'));
                            }
                        }

                        $this->getLog()->debug('Finished 7 day roll forward, start date is ' . $start);

                        // If we haven't passed the end of the week, roll forward
                        if ($start < $endOfWeek) {
                            $start->addDay(1);
                            $end->addDay(1);
                        }

                        // Wind back a week and then add our recurrence detail
                        $start->addWeek(-1);
                        $end->addWeek(-1);

                        $this->getLog()->debug('Resetting start date to ' . $start);
                    }

                    // Jump forward a week from the original start date (when we entered this loop)
                    $start->addWeeks($this->recurrenceDetail);
                    $end->addWeeks($this->recurrenceDetail);

                    break;

                case 'Month':
                    $start->addMonths($this->recurrenceDetail);
                    $end->addMonths($this->recurrenceDetail);
                    break;

                case 'Year':
                    $start->addYears($this->recurrenceDetail);
                    $end->addYears($this->recurrenceDetail);
                    break;
            }

            // after we have added the appropriate amount, are we still valid
            if ($start > $range)
                break;

            // Don't add if we are weekly recurrency (handles it's own adding)
            if ($this->recurrenceType == 'Week' && !empty($this->recurrenceRepeatsOn))
                continue;

            if ($this->toDt == null)
                $this->addDetail($start->format('U'), null);
            else {
                // Check to make sure that our from/to date isn't longer than the first repeat
                if ($start->format('U') < $this->toDt)
                    throw new \InvalidArgumentException(__('The first event repeat is inside the event from/to dates.'));

                // If we are a daypart event, look up the start/end times for the event
                $this->calculateDayPartTimes($start, $end);

                $this->addDetail($start->format('U'), $end->format('U'));
            }

            // Check these dates
            if (!$this->isInScheduleLookAhead)
                $this->isInScheduleLookAhead = $this->datesInScheduleLookAhead($start->format('U'), $end->format('U'));
        }
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
        $this->getStore()->insert('INSERT INTO `schedule_detail` (eventId, fromDt, toDt) VALUES (:eventId, :fromDt, :toDt)', [
            'eventId' => $this->eventId,
            'fromDt' => $fromDt,
            'toDt' => $toDt
        ]);
    }

    /**
     * Delete Detail
     */
    private function deleteDetail()
    {
        $this->getStore()->update('DELETE FROM `schedule_detail` WHERE eventId = :eventId', ['eventId' => $this->eventId]);
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