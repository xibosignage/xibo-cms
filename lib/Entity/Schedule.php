<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Schedule.php)
 */


namespace Xibo\Entity;

use Respect\Validation\Validator as v;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Storage\PDOConnect;

/**
 * Class Schedule
 * @package Xibo\Entity
 *
 * @SWG\Definition()
 */
class Schedule implements \JsonSerializable
{
    use EntityTrait;

    /**
     * @SWG\Property(
     *  description="The ID of this Event"
     * )
     * @var int
     */
    public $eventId;

    /**
     * @SWG\Property(
     *  description="The CampaignID this event is for"
     * )
     * @var int
     */
    public $campaignId;

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
     *  description="Flag indicating whether the event should be considered priority or not."
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

    public function getId()
    {
        return $this->eventId;
    }

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

    public function load()
    {
        // If we are already loaded, then don't do it again
        if ($this->loaded || $this->eventId == null || $this->eventId == 0)
            return;

        $this->displayGroups = DisplayGroupFactory::getByEventId($this->eventId);

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

        // Validate layout
        if (!v::int()->notEmpty()->min(1)->validate($this->campaignId))
            throw new \InvalidArgumentException(__('No layout selected'));

        // validate the dates
        if ($this->toDt < $this->fromDt)
            throw new \InvalidArgumentException(__('Can not have an end time earlier than your start time'));
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

        if ($this->loaded) {
            // Manage assignments
            $this->manageAssignments();
        }

        // Generate the event instances
        $this->generate();
    }

    /**
     * Delete this Schedule Event
     */
    public function delete()
    {
        // Delete display group assignments
        $this->displayGroups = [];
        $this->unlinkDisplayGroups();

        // Delete all detail records
        $this->deleteDetail();

        // Delete the event itself
        PDOConnect::update('DELETE FROM `schedule` WHERE eventId = :eventId', ['eventId' => $this->eventId]);
    }

    /**
     * Add
     */
    private function add()
    {
        $this->eventId = PDOConnect::insert('
          INSERT INTO `schedule` (CampaignId, userID, is_priority, FromDT, ToDT, DisplayOrder, recurrence_type, recurrence_detail, recurrence_range)
            VALUES (:campaignId, :userId, :isPriority, :fromDt, :toDt, :displayOrder, :recurrenceType, :recurrenceDetail, :recurrenceRange)
        ', [
            'campaignId' => $this->campaignId,
            'userId' => $this->userId,
            'isPriority' => $this->isPriority,
            'fromDt' => $this->fromDt,
            'toDt' => $this->toDt,
            'displayOrder' => $this->displayOrder,
            'recurrenceType' => $this->recurrenceType,
            'recurrenceDetail' => $this->recurrenceDetail,
            'recurrenceRange' => $this->recurrenceRange
        ]);
    }

    /**
     * Edit
     */
    private function edit()
    {
        PDOConnect::update('
          UPDATE `schedule` SET
            campaignId = :campaignId,
            is_priority = :isPriority,
            userId = :userId,
            fromDt = :fromDt,
            toDt = :toDt,
            displayOrder = :displayOrder,
            recurrence_type = :recurrenceType,
            recurrence_detail = :recurrenceDetail,
            recurrence_range = :recurrenceRange
          WHERE eventId = :eventId
        ', [
            'campaignId' => $this->campaignId,
            'userId' => $this->userId,
            'isPriority' => $this->isPriority,
            'fromDt' => $this->fromDt,
            'toDt' => $this->toDt,
            'displayOrder' => $this->displayOrder,
            'recurrenceType' => $this->recurrenceType,
            'recurrenceDetail' => $this->recurrenceDetail,
            'recurrenceRange' => $this->recurrenceRange,
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

            $this->addDetail($t_start_temp, $t_end_temp);
        }
    }

    /**
     * Add Detail
     * @param int $fromDt
     * @param int $toDt
     */
    private function addDetail($fromDt, $toDt)
    {
        PDOConnect::insert('INSERT INTO `schedule_detail` (eventId, fromDt, toDt) VALUES (:eventId, :fromDt, :toDt)', [
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
        PDOConnect::update('DELETE FROM `schedule_detail` WHERE eventId = :eventId', ['eventId' => $this->eventId]);
    }

    /**
     * Manage the assignments
     */
    private function manageAssignments()
    {
        $this->linkDisplayGroups();
        $this->unlinkDisplayGroups();

        foreach ($this->displayGroups as $displayGroup) {
            /* @var DisplayGroup $displayGroup */
            $displayGroup->setMediaIncomplete();
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

            PDOConnect::insert($sql, array(
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

        PDOConnect::update($sql, $params);
    }
}