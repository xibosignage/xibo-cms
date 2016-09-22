<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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
namespace Xibo\Controller;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\CommandFactory;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Helper\Session;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class Schedule
 * @package Xibo\Controller
 */
class Schedule extends Base
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var CampaignFactory
     */
    private $campaignFactory;

    /**
     * @var CommandFactory
     */
    private $commandFactory;

    /** @var  DisplayFactory */
    private $displayFactory;

    /** @var  LayoutFactory */
    private $layoutFactory;

    /** @var  MediaFactory */
    private $mediaFactory;

    /** @var  DayPartFactory */
    private $dayPartFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param Session $session
     * @param ScheduleFactory $scheduleFactory
     * @param DisplayGroupFactory $displayGroupFactory
     * @param CampaignFactory $campaignFactory
     * @param CommandFactory $commandFactory
     * @param DisplayFactory $displayFactory
     * @param LayoutFactory $layoutFactory
     * @param MediaFactory $mediaFactory
     * @param DayPartFactory $dayPartFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $session, $scheduleFactory, $displayGroupFactory, $campaignFactory, $commandFactory, $displayFactory, $layoutFactory, $mediaFactory, $dayPartFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->session = $session;
        $this->scheduleFactory = $scheduleFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->campaignFactory = $campaignFactory;
        $this->commandFactory = $commandFactory;
        $this->displayFactory = $displayFactory;
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
        $this->dayPartFactory = $dayPartFactory;
    }

    function displayPage()
    {
        // We need to provide a list of displays
        $displayGroupIds = $this->session->get('displayGroupIds');
        $groups = array();
        $displays = array();

        foreach ($this->displayGroupFactory->query(null, ['isDisplaySpecific' => -1]) as $display) {
            /* @var \Xibo\Entity\DisplayGroup $display */
            if ($display->isDisplaySpecific == 1) {
                $displays[] = $display;
            } else {
                $groups[] = $display;
            }
        }

        $data = [
            'selectedDisplayGroupIds' => $displayGroupIds,
            'groups' => $groups,
            'displays' => $displays
        ];

        // Render the Theme and output
        $this->getState()->template = 'schedule-page';
        $this->getState()->setData($data);
    }

    /**
     * Generates the calendar that we draw events on
     *
     * @SWG\Get(
     *  path="/schedule/data/events",
     *  operationId="scheduleCalendarData",
     *  tags={"schedule"},
     *  @SWG\Parameter(
     *      name="DisplayGroupIds",
     *      description="The DisplayGroupIds to return the schedule for. Empty for All.",
     *      in="formData",
     *      type="array",
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Parameter(
     *      name="from",
     *      in="formData",
     *      required=true,
     *      type="integer",
     *      description="From Date Timestamp in Microseconds"
     *  ),
     *  @SWG\Parameter(
     *      name="to",
     *      in="formData",
     *      required=true,
     *      type="integer",
     *      description="To Date Timestamp in Microseconds"
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful response",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(ref="#/definitions/ScheduleCalendarData")
     *      )
     *  )
     * )
     */
    function eventData()
    {
        $this->getApp()->response()->header('Content-Type', 'application/json');
        $this->setNoOutput();

        $displayGroupIds = $this->getSanitizer()->getIntArray('displayGroupIds');
        $originalDisplayGroupIds = $displayGroupIds;
        $start = $this->getSanitizer()->getString('from', 1000) / 1000;
        $end = $this->getSanitizer()->getString('to', 1000) / 1000;

        // if we have some displayGroupIds then add them to the session info so we can default everything else.
        $this->session->set('displayGroupIds', $displayGroupIds);

        if (count($displayGroupIds) <= 0) {
            $this->getApp()->response()->body(json_encode(array('success' => 1, 'result' => [])));
            return;
        }

        // Permissions check the list of display groups with the user accessible list of display groups
        $displayGroupIds = array_diff($displayGroupIds, [-1]);

        if ($this->getUser()->getUserTypeId() != 1) {
            $userDisplayGroupIds = array_map(function($element) {
                /** @var \Xibo\Entity\DisplayGroup $element */
                return $element->displayGroupId;
            }, $this->displayGroupFactory->query(null, ['isDisplaySpecific' => -1]));

            // Reset the list to only those display groups that intersect and if 0 have been provided, only those from
            // the user list
            $displayGroupIds = (count($displayGroupIds) > 0) ? array_intersect($displayGroupIds, $userDisplayGroupIds) : $userDisplayGroupIds;

            $this->getLog()->debug('Resolved list of display groups ['
                . json_encode($displayGroupIds) . '] from provided list ['
                . json_encode($originalDisplayGroupIds) . '] and user list ['
                . json_encode($userDisplayGroupIds) . ']');

            // If we have none, then we do not return any events.
            if (count($displayGroupIds) <= 0) {
                $this->getApp()->response()->body(json_encode(array('success' => 1, 'result' => [])));
                return;
            }
        }

        $events = array();
        $filter = [
            'useDetail' => 1,
            'fromDt' => $start,
            'toDt' => $end,
            'displayGroupIds' => $displayGroupIds
        ];

        foreach ($this->scheduleFactory->query('schedule_detail.FromDT', $filter) as $row) {
            /* @var \Xibo\Entity\Schedule $row */

            // Load the display groups
            $row->load();

            $displayGroupList = '';

            if (count($row->displayGroups) >= 0) {
                $array = array_map(function ($object) {
                    return $object->displayGroup;
                }, $row->displayGroups);
                $displayGroupList = implode(', ', $array);
            }

            // Event Permissions
            $editable = $this->isEventEditable($row->displayGroups);

            $this->getLog()->debug('Parsing event dates from %s and %s', $row->fromDt, $row->toDt);

            // Handle command events which do not have a toDt
            if ($row->eventTypeId == \Xibo\Entity\Schedule::$COMMAND_EVENT)
                $row->toDt = $row->fromDt;

            // Parse our dates into a Date object, so that we convert to local time correctly.
            $fromDt = $this->getDate()->parse($row->fromDt, 'U');
            $toDt = $this->getDate()->parse($row->toDt, 'U');

            // Set the row from/to date to be an ISO date for display
            $row->fromDt = $this->getDate()->getLocalDate($row->fromDt);
            $row->toDt = $this->getDate()->getLocalDate($row->toDt);

            $this->getLog()->debug('Start date is ' . $fromDt->toRssString() . ' ' . $row->fromDt);
            $this->getLog()->debug('End date is ' . $toDt->toRssString() . ' ' . $row->toDt);

            // Event Title
            $title = sprintf(__('%s scheduled on %s'),
                ($row->campaign == '') ? $row->command : $row->campaign,
                $displayGroupList
            );

            // Event URL
            $editUrl = ($this->isApi()) ? 'schedule.edit' : 'schedule.edit.form';
            $url = ($editable) ? $this->urlFor($editUrl, ['id' => $row->eventId]) : '#';

            /**
             * @SWG\Definition(
             *  definition="ScheduleCalendarData",
             *  @SWG\Property(
             *      property="id",
             *      type="integer",
             *      description="Event ID"
             *  ),
             *  @SWG\Property(
             *      property="title",
             *      type="string",
             *      description="Event Title"
             *  ),
             *  @SWG\Property(
             *      property="sameDay",
             *      type="integer",
             *      description="Does this event happen only on 1 day"
             *  ),
             *  @SWG\Property(
             *      property="event",
             *      ref="#/definitions/Schedule"
             *  )
             * )
             */
            $events[] = array(
                'id' => $row->eventId,
                'title' => $title,
                'url' => ($editable) ? $url : null,
                'start' => $fromDt->format('U') * 1000,
                'end' => $toDt->format('U') * 1000,
                'sameDay' => ($fromDt->day == $toDt->day),
                'editable' => $editable,
                'event' => $row
            );
        }

        $this->getApp()->response()->body(json_encode(array('success' => 1, 'result' => $events)));
    }

    /**
     * Shows a form to add an event
     */
    function addForm()
    {
        $groups = array();
        $displays = array();
        $scheduleWithView = ($this->getConfig()->GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes');

        foreach ($this->displayGroupFactory->query(['displayGroup'], ['isDisplaySpecific' => -1]) as $displayGroup) {
            /* @var \Xibo\Entity\DisplayGroup $\Xibo\Entity\DisplayGroup */

            // Can't schedule with view, but no edit permissions
            if (!$scheduleWithView && !$this->getUser()->checkEditable($displayGroup))
                continue;

            if ($displayGroup->isDisplaySpecific == 1) {
                $displays[] = $displayGroup;
            } else {
                $groups[] = $displayGroup;
            }
        }

        $this->getState()->template = 'schedule-form-add';
        $this->getState()->setData([
            'displays' => $displays,
            'displayGroups' => $groups,
            'campaigns' => $this->campaignFactory->query(null, ['isLayoutSpecific' => -1]),
            'commands' => $this->commandFactory->query(),
            'dayParts' => $this->dayPartFactory->allWithSystem(),
            'displayGroupIds' => $this->session->get('displayGroupIds'),
            'help' => $this->getHelp()->link('Schedule', 'Add')
        ]);
    }

    /**
     * Add Event
     * @SWG\Post(
     *  path="/schedule",
     *  operationId="scheduleAdd",
     *  tags={"schedule"},
     *  summary="Add Schedule Event",
     *  description="Add a new scheduled event for a Campaign/Layout to be shown on a Display Group/Display.",
     *  @SWG\Parameter(
     *      name="eventTypeId",
     *      in="formData",
     *      description="The Event Type Id to use for this Event. 1=Campaign, 2=Command",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="formData",
     *      description="The Campaign ID to use for this Event. If a Layout is needed then the Campaign specific ID for that Layout should be used.",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="commandId",
     *      in="formData",
     *      description="The Command ID to use for this Event.",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="displayOrder",
     *      in="formData",
     *      description="The display order for this event. ",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="isPriority",
     *      in="formData",
     *      description="An integer indicating the priority of this event. Normal events have a priority of 0.",
     *      type="integer",
     *      required=true
     *   ),
     *   @SWG\Parameter(
     *      name="displayGroupIds",
     *      in="formData",
     *      description="The Display Group IDs for this event. Display specific Group IDs should be used to schedule on single displays.",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="integer")
     *   ),
     *   @SWG\Parameter(
     *      name="dayPartId",
     *      in="formData",
     *      description="The Day Part for this event. Overrides supported are 0(custom) and 1(always). Defaulted to 0.",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="fromDt",
     *      in="formData",
     *      description="The from date for this event.",
     *      type="string",
     *      format="date-time",
     *      required=true
     *   ),
     *   @SWG\Parameter(
     *      name="toDt",
     *      in="formData",
     *      description="The to date for this event.",
     *      type="string",
     *      format="date-time",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceType",
     *      in="formData",
     *      description="The type of recurrence to apply to this event.",
     *      type="string",
     *      required=false,
     *      enum={"", "Minute", "Hour", "Day", "Week", "Month", "Year"}
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceDetail",
     *      in="formData",
     *      description="The interval for the recurrence.",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceRange",
     *      in="formData",
     *      description="The end date for this events recurrence.",
     *      type="string",
     *      format="date-time",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceRepeatsOn",
     *      in="formData",
     *      description="The days of the week that this event repeats - weekly only",
     *      type="string",
     *      format="array",
     *      required=false,
     *      @SWG\Items(type="integer")
     *   ),
     *   @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Schedule"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function add()
    {
        $this->getLog()->debug('Add Schedule');

        $schedule = $this->scheduleFactory->createEmpty();
        $schedule->userId = $this->getUser()->userId;
        $schedule->eventTypeId = $this->getSanitizer()->getInt('eventTypeId');
        $schedule->campaignId = $this->getSanitizer()->getInt('campaignId');
        $schedule->commandId = $this->getSanitizer()->getInt('commandId');
        $schedule->displayOrder = $this->getSanitizer()->getInt('displayOrder', 0);
        $schedule->isPriority = $this->getSanitizer()->getInt('isPriority', 0);
        $schedule->dayPartId = $this->getSanitizer()->getInt('dayPartId', 0);
        $schedule->recurrenceType = $this->getSanitizer()->getString('recurrenceType');
        $schedule->recurrenceDetail = $this->getSanitizer()->getInt('recurrenceDetail');
        $recurrenceRepeatsOn = $this->getSanitizer()->getIntArray('recurrenceRepeatsOn');
        $schedule->recurrenceRepeatsOn = (empty($recurrenceRepeatsOn)) ? null : implode(',', $recurrenceRepeatsOn);

        foreach ($this->getSanitizer()->getIntArray('displayGroupIds') as $displayGroupId) {
            $schedule->assignDisplayGroup($this->displayGroupFactory->getById($displayGroupId));
        }

        if ($schedule->dayPartId != \Xibo\Entity\Schedule::$DAY_PART_ALWAYS) {
            // Handle the dates
            $fromDt = $this->getSanitizer()->getDate('fromDt');
            $toDt = $this->getSanitizer()->getDate('toDt');
            $recurrenceRange = $this->getSanitizer()->getDate('recurrenceRange');

            if ($fromDt === null)
                throw new \InvalidArgumentException(__('Please enter a from date'));

            $this->getLog()->debug('Times received are: FromDt=' . $this->getDate()->getLocalDate($fromDt) . '. ToDt=' . $this->getDate()->getLocalDate($toDt) . '. recurrenceRange=' . $this->getDate()->getLocalDate($recurrenceRange));

            // In some circumstances we want to trim the seconds from the provided dates.
            // this happens when the date format provided does not include seconds and when the add
            // event comes from the UI.
            if (!($this->isApi() || str_contains($this->getConfig()->GetSetting('DATE_FORMAT'), 's'))) {
                $this->getLog()->debug('Date format does not include seconds, removing them');
                $schedule->fromDt = $fromDt->setTime($fromDt->hour, $fromDt->minute, 0)->format('U');

                if ($toDt !== null)
                    $schedule->toDt = $toDt->setTime($toDt->hour, $toDt->minute, 0)->format('U');

                if ($recurrenceRange != null)
                    $schedule->recurrenceRange = $recurrenceRange->setTime($recurrenceRange->hour, $recurrenceRange->minute, 0)->format('U');
            } else {
                $schedule->fromDt = $fromDt->format('U');

                if ($toDt !== null)
                    $schedule->toDt = $toDt->format('U');

                if ($recurrenceRange != null)
                    $schedule->recurrenceRange = $recurrenceRange->format('U');
            }

            $this->getLog()->debug('Processed times are: FromDt=' . $this->getDate()->getLocalDate($fromDt) . '. ToDt=' . $this->getDate()->getLocalDate($toDt) . '. recurrenceRange=' . $this->getDate()->getLocalDate($recurrenceRange));
        }

        // Ready to do the add
        $schedule->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory, $this->dayPartFactory)->setDateService($this->getDate());
        $schedule->save();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => __('Added Event'),
            'id' => $schedule->eventId,
            'data' => $schedule
        ]);
    }

    /**
     * Shows a form to edit an event
     * @param int $eventId
     */
    function editForm($eventId)
    {
        $schedule = $this->scheduleFactory->getById($eventId);
        $schedule->load();

        if (!$this->isEventEditable($schedule->displayGroups))
            throw new AccessDeniedException();

        // Fix the event dates for display
        $schedule->fromDt = $this->getDate()->getLocalDate($schedule->fromDt);
        $schedule->toDt = $this->getDate()->getLocalDate($schedule->toDt);

        if ($schedule->recurrenceRange != null)
            $schedule->recurrenceRange = $this->getDate()->getLocalDate($schedule->recurrenceRange);

        $groups = array();
        $displays = array();
        $scheduleWithView = ($this->getConfig()->GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes');

        foreach ($this->displayGroupFactory->query(null, ['isDisplaySpecific' => -1]) as $displayGroup) {
            /* @var \Xibo\Entity\DisplayGroup $displayGroup */

            // Can't schedule with view, but no edit permissions
            if (!$scheduleWithView && !$this->getUser()->checkEditable($displayGroup))
                continue;

            if ($displayGroup->isDisplaySpecific == 1) {
                $displays[] = $displayGroup;
            } else {
                $groups[] = $displayGroup;
            }
        }

        $this->getState()->template = 'schedule-form-edit';
        $this->getState()->setData([
            'event' => $schedule,
            'displays' => $displays,
            'displayGroups' => $groups,
            'campaigns' => $this->campaignFactory->query(null, ['isLayoutSpecific' => -1]),
            'commands' => $this->commandFactory->query(),
            'dayParts' => $this->dayPartFactory->allWithSystem(),
            'displayGroupIds' => array_map(function($element) {
                return $element->displayGroupId;
            }, $schedule->displayGroups),
            'help' => $this->getHelp()->link('Schedule', 'Edit')
        ]);
    }

    /**
     * Edits an event
     * @param int $eventId
     *
     * @SWG\Put(
     *  path="/schedule/{eventId}",
     *  operationId="scheduleEdit",
     *  tags={"schedule"},
     *  summary="Edit Schedule Event",
     *  description="Edit a scheduled event for a Campaign/Layout to be shown on a Display Group/Display.",
     *  @SWG\Parameter(
     *      name="eventId",
     *      in="path",
     *      description="The Scheduled Event ID",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="eventTypeId",
     *      in="formData",
     *      description="The Event Type Id to use for this Event. 1=Campaign, 2=Command",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="formData",
     *      description="The Campaign ID to use for this Event. If a Layout is needed then the Campaign specific ID for that Layout should be used.",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="commandId",
     *      in="formData",
     *      description="The Command ID to use for this Event.",
     *      type="integer",
     *      required=false
     *  ),
     *  @SWG\Parameter(
     *      name="displayOrder",
     *      in="formData",
     *      description="The display order for this event. ",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="isPriority",
     *      in="formData",
     *      description="An integer indicating the priority of this event. Normal events have a priority of 0.",
     *      type="integer",
     *      required=true
     *   ),
     *   @SWG\Parameter(
     *      name="displayGroupIds",
     *      in="formData",
     *      description="The Display Group IDs for this event. Display specific Group IDs should be used to schedule on single displays.",
     *      type="array",
     *      required=true,
     *      @SWG\Items(type="integer")
     *   ),
     *   @SWG\Parameter(
     *      name="dayPartId",
     *      in="formData",
     *      description="The Day Part for this event. Overrides supported are 0(custom) and 1(always). Defaulted to 0.",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="fromDt",
     *      in="formData",
     *      description="The from date for this event.",
     *      type="string",
     *      format="date-time",
     *      required=true
     *   ),
     *   @SWG\Parameter(
     *      name="toDt",
     *      in="formData",
     *      description="The to date for this event.",
     *      type="string",
     *      format="date-time",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceType",
     *      in="formData",
     *      description="The type of recurrence to apply to this event.",
     *      type="string",
     *      required=false,
     *      enum={"", "Minute", "Hour", "Day", "Week", "Month", "Year"}
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceDetail",
     *      in="formData",
     *      description="The interval for the recurrence.",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceRange",
     *      in="formData",
     *      description="The end date for this events recurrence.",
     *      type="string",
     *      format="date-time",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="recurrenceRepeatsOn",
     *      in="formData",
     *      description="The days of the week that this event repeats - weekly only",
     *      type="string",
     *      format="array",
     *      required=false,
     *      @SWG\Items(type="integer")
     *   ),
     *   @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Schedule")
     *  )
     * )
     */
    public function edit($eventId)
    {
        $schedule = $this->scheduleFactory->getById($eventId);
        $schedule->load();

        if (!$this->isEventEditable($schedule->displayGroups))
            throw new AccessDeniedException();

        $schedule->eventTypeId = $this->getSanitizer()->getInt('eventTypeId');
        $schedule->campaignId = $this->getSanitizer()->getInt('campaignId');
        $schedule->commandId = $this->getSanitizer()->getInt('commandId');
        $schedule->displayOrder = $this->getSanitizer()->getInt('displayOrder', $schedule->displayOrder);
        $schedule->isPriority = $this->getSanitizer()->getInt('isPriority', $schedule->isPriority);
        $schedule->dayPartId = $this->getSanitizer()->getInt('dayPartId', $schedule->dayPartId);
        $schedule->recurrenceType = $this->getSanitizer()->getString('recurrenceType');
        $schedule->recurrenceDetail = $this->getSanitizer()->getInt('recurrenceDetail');
        $recurrenceRepeatsOn = $this->getSanitizer()->getIntArray('recurrenceRepeatsOn');
        $schedule->recurrenceRepeatsOn = (empty($recurrenceRepeatsOn)) ? null : implode(',', $recurrenceRepeatsOn);
        $schedule->displayGroups = [];

        foreach ($this->getSanitizer()->getIntArray('displayGroupIds') as $displayGroupId) {
            $schedule->assignDisplayGroup($this->displayGroupFactory->getById($displayGroupId));
        }

        if ($schedule->dayPartId != \Xibo\Entity\Schedule::$DAY_PART_ALWAYS) {
            // Handle the dates
            $fromDt = $this->getSanitizer()->getDate('fromDt');
            $toDt = $this->getSanitizer()->getDate('toDt');
            $recurrenceRange = $this->getSanitizer()->getDate('recurrenceRange');

            if ($fromDt === null)
                throw new \InvalidArgumentException(__('Please enter a from date'));

            $this->getLog()->debug('Times received are: FromDt=' . $this->getDate()->getLocalDate($fromDt) . '. ToDt=' . $this->getDate()->getLocalDate($toDt) . '. recurrenceRange=' . $this->getDate()->getLocalDate($recurrenceRange));

            // In some circumstances we want to trim the seconds from the provided dates.
            // this happens when the date format provided does not include seconds and when the add
            // event comes from the UI.
            if (!($this->isApi() || str_contains($this->getConfig()->GetSetting('DATE_FORMAT'), 's'))) {
                $this->getLog()->debug('Date format does not include seconds, removing them');
                $schedule->fromDt = $fromDt->setTime($fromDt->hour, $fromDt->minute, 0)->format('U');

                // If we have a toDt
                if ($toDt !== null)
                    $schedule->toDt = $toDt->setTime($toDt->hour, $toDt->minute, 0)->format('U');

                if ($recurrenceRange != null)
                    $schedule->recurrenceRange = $recurrenceRange->setTime($recurrenceRange->hour, $recurrenceRange->minute, 0)->format('U');
            } else {
                $schedule->fromDt = $fromDt->format('U');

                if ($toDt !== null)
                    $schedule->toDt = $toDt->format('U');

                if ($recurrenceRange != null)
                    $schedule->recurrenceRange = $recurrenceRange->format('U');
            }

            $this->getLog()->debug('Processed start is: FromDt=' . $fromDt->toRssString());
        }

        // Ready to do the add
        $schedule->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory, $this->dayPartFactory)->setDateService($this->getDate());
        $schedule->save();

        // Return
        $this->getState()->hydrate([
            'message' => __('Edited Event'),
            'id' => $schedule->eventId,
            'data' => $schedule
        ]);
    }

    /**
     * Shows the DeleteEvent form
     * @param int $eventId
     */
    function deleteForm($eventId)
    {
        $schedule = $this->scheduleFactory->getById($eventId);
        $schedule->load();

        if (!$this->isEventEditable($schedule->displayGroups))
            throw new AccessDeniedException();

        $this->getState()->template = 'schedule-form-delete';
        $this->getState()->setData([
            'event' => $schedule,
            'help' => $this->getHelp()->link('Schedule', 'Delete')
        ]);
    }

    /**
     * Deletes an Event from all displays
     * @param int $eventId
     *
     * @SWG\Delete(
     *  path="/schedule/{eventId}",
     *  operationId="scheduleDelete",
     *  tags={"schedule"},
     *  summary="Delete Event",
     *  description="Delete a Scheduled Event",
     *  @SWG\Parameter(
     *      name="eventId",
     *      in="path",
     *      description="The Scheduled Event ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function delete($eventId)
    {
        $schedule = $this->scheduleFactory->getById($eventId);
        $schedule->load();

        if (!$this->isEventEditable($schedule->displayGroups))
            throw new AccessDeniedException();

        $schedule->setChildObjectDependencies($this->displayFactory, $this->layoutFactory, $this->mediaFactory, $this->scheduleFactory, $this->dayPartFactory);
        $schedule->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => __('Deleted Event')
        ]);
    }

    /**
     * Is this event editable?
     * @param array[\Xibo\Entity\DisplayGroup] $displayGroups
     * @return bool
     */
    private function isEventEditable($displayGroups)
    {
        $scheduleWithView = ($this->getConfig()->GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes');

        // Work out if this event is editable or not. To do this we need to compare the permissions
        // of each display group this event is associated with
        foreach ($displayGroups as $displayGroup) {
            /* @var \Xibo\Entity\DisplayGroup $\Xibo\Entity\DisplayGroup */

            // Can schedule with view, but no view permissions
            if ($scheduleWithView && !$this->getUser()->checkViewable($displayGroup))
                return false;

            // Can't schedule with view, but no edit permissions
            if (!$scheduleWithView && !$this->getUser()->checkEditable($displayGroup))
                return false;
        }

        return true;
    }

    /**
     * Schedule Now Form
     * @param string $from The object that called this form
     * @param int $id The Id
     */
    public function scheduleNowForm($from, $id)
    {
        $groups = array();
        $displays = array();
        $scheduleWithView = ($this->getConfig()->GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes');

        foreach ($this->displayGroupFactory->query(null, ['isDisplaySpecific' => -1]) as $displayGroup) {
            /* @var \Xibo\Entity\DisplayGroup $\Xibo\Entity\DisplayGroup */

            // Can't schedule with view, but no edit permissions
            if (!$scheduleWithView && !$this->getUser()->checkEditable($displayGroup))
                continue;

            if ($displayGroup->isDisplaySpecific == 1) {
                $displays[] = $displayGroup;
            } else {
                $groups[] = $displayGroup;
            }
        }

        $this->getState()->template = 'schedule-form-now';
        $this->getState()->setData([
            'campaignId' => (($from == 'Campaign') ? $id : 0),
            'displayGroupId' => (($from == 'DisplayGroup') ? $id : 0),
            'displays' => $displays,
            'displayGroups' => $groups,
            'campaigns' => $this->campaignFactory->query(null, ['isLayoutSpecific' => -1]),
            'help' => $this->getHelp()->link('Schedule', 'ScheduleNow')
        ]);
    }
}