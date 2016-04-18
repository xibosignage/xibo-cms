<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Schedule.php)
 */


namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Service\ConfigServiceInterface;
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
     */
    public function setChildObjectDependencies($displayFactory, $layoutFactory, $mediaFactory, $scheduleFactory)
    {
        $this->displayFactory = $displayFactory;
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
        $this->scheduleFactory = $scheduleFactory;
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

        $this->getLog()->debug('EventTypeId: %d. CampaignId: %d, CommandId: %d', $this->eventTypeId, $this->campaignId, $this->commandId);

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
     * @param bool $validate
     */
    public function save($validate = true)
    {
        if ($validate)
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
          INSERT INTO `schedule` (eventTypeId, CampaignId, commandId, userID, is_priority, FromDT, ToDT, DisplayOrder, recurrence_type, recurrence_detail, recurrence_range, `dayPartId`)
            VALUES (:eventTypeId, :campaignId, :commandId, :userId, :isPriority, :fromDt, :toDt, :displayOrder, :recurrenceType, :recurrenceDetail, :recurrenceRange, :dayPartId)
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
        // TODO: generate 30 days in advance.
        $daysToGenerate = 30;

        if ($this->dayPartId == Schedule::$DAY_PART_ALWAYS) {
            // Create events with min/max dates
            $this->addDetail(Schedule::$DATE_MIN, Schedule::$DATE_MAX);

            return;
        }

        // Add the detail for the main event
        $this->addDetail($this->fromDt, $this->toDt);

        // If we don't have any recurrence, we are done
        if ($this->recurrenceType == '')
            return;

        // Set the temp starts
        $t_start_temp = $this->fromDt;
        $t_end_temp = $this->toDt;

        // loop until we have added the recurring events for the schedule
        while ($t_start_temp < $this->recurrenceRange)
        {
            // add the appropriate time to the start and end
            switch ($this->recurrenceType)
            {
                case 'Minute':
                    $t_start_temp = mktime(date("H", $t_start_temp), date("i", $t_start_temp) + $this->recurrenceDetail, date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp), date("Y", $t_start_temp));
                    $t_end_temp = mktime(date("H", $t_end_temp), date("i", $t_end_temp) + $this->recurrenceDetail, date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp), date("Y", $t_end_temp));
                    break;

                case 'Hour':
                    $t_start_temp = mktime(date("H", $t_start_temp) + $this->recurrenceDetail, date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp), date("Y", $t_start_temp));
                    $t_end_temp = mktime(date("H", $t_end_temp) + $this->recurrenceDetail, date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp), date("Y", $t_end_temp));
                    break;

                case 'Day':
                    $t_start_temp = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp)+$this->recurrenceDetail, date("Y", $t_start_temp));
                    $t_end_temp = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp)+$this->recurrenceDetail, date("Y", $t_end_temp));
                    break;

                case 'Week':
                    $t_start_temp = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp) + ($this->recurrenceDetail * 7), date("Y", $t_start_temp));
                    $t_end_temp = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp) + ($this->recurrenceDetail * 7), date("Y", $t_end_temp));
                    break;

                case 'Month':
                    $t_start_temp = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp)+$this->recurrenceDetail ,date("d", $t_start_temp), date("Y", $t_start_temp));
                    $t_end_temp = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp)+$this->recurrenceDetail ,date("d", $t_end_temp), date("Y", $t_end_temp));
                    break;

                case 'Year':
                    $t_start_temp = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp), date("Y", $t_start_temp)+$this->recurrenceDetail);
                    $t_end_temp = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp), date("Y", $t_end_temp)+$this->recurrenceDetail);
                    break;
            }

            // after we have added the appropriate amount, are we still valid
            if ($t_start_temp > $this->recurrenceRange)
                break;

            if ($this->toDt == null)
                $this->addDetail($t_start_temp, null);
            else {
                // Check to make sure that our from/to date isn't longer than the first repeat
                if ($t_start_temp < $this->toDt)
                    throw new \InvalidArgumentException(__('The first event repeat is inside the event from/to dates.'));

                $this->addDetail($t_start_temp, $t_end_temp);
            }

            // Check these dates
            if (!$this->isInScheduleLookAhead)
                $this->isInScheduleLookAhead = $this->datesInScheduleLookAhead($t_start_temp, $t_end_temp);
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