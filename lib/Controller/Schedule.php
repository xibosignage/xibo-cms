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
use Xibo\Entity\DisplayGroup;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Date;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;


class Schedule extends Base
{
    function displayPage()
    {
        // We need to provide a list of displays
        $displayGroupIds = $this->getSession()->get('displayGroupIds');
        $groups = array();
        $displays = array();

        foreach (DisplayGroupFactory::query(null, ['isDisplaySpecific' => -1]) as $display) {
            /* @var DisplayGroup $display */
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

        $displayGroupIds = Sanitize::getIntArray('displayGroupIds');
        $start = Sanitize::getInt('from', 1000) / 1000;
        $end = Sanitize::getInt('to', 1000) / 1000;

        // if we have some displayGroupIds then add them to the session info so we can default everything else.
        $this->getSession()->set('displayGroupIds', $displayGroupIds);

        if (count($displayGroupIds) <= 0) {
            $this->getApp()->response()->body(json_encode(array('success' => 1, 'result' => [])));
            return;
        }

        $events = array();
        $filter = [
            'useDetail' => 1,
            'fromDt' => $start,
            'toDt' => $end,
            'displayGroupIds' => array_diff($displayGroupIds, [-1])
        ];

        foreach (ScheduleFactory::query('schedule_detail.FromDT', $filter) as $row) {
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

            // Event Title
            $title = sprintf(__('%s scheduled on %s'), $row->campaign, $displayGroupList);

            // Event URL
            $url = ($editable) ? $this->urlFor('schedule.edit.form', ['id' => $row->eventId]) : '#';

            // Classes used to distinguish between events
            //$class = 'event-warning';

            // Event is on a single display
            if (count($row->displayGroups) <= 1) {
                $class = 'event-info';
                $extra = 'single-display';
            } else {
                $class = "event-success";
                $extra = 'multi-display';
            }

            if ($row->recurrenceType != '') {
                $class = 'event-special';
                $extra = 'recurring';
            }

            // Priority event
            if ($row->isPriority == 1) {
                $class = 'event-important';
                $extra = 'priority';
            }

            // Is this event editable?
            if (!$editable) {
                $class = 'event-inverse';
                $extra = 'view-only';
            }

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
             *      property="event",
             *      ref="#/definitions/Schedule"
             *  )
             * )
             */
            $events[] = array(
                'id' => $row->eventId,
                'title' => $title,
                'url' => $url,
                'class' => 'XiboFormButton ' . $class,
                'extra' => $extra,
                'start' => $row->fromDt * 1000,
                'end' => $row->toDt * 1000,
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
        $scheduleWithView = (Config::GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes');

        foreach (DisplayGroupFactory::query(['displayGroup'], ['isDisplaySpecific' => -1]) as $displayGroup) {
            /* @var DisplayGroup $displayGroup */

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
            'campaigns' => CampaignFactory::query(null, ['isLayoutSpecific' => -1]),
            'displayGroupIds' => $this->getSession()->get('displayGroupIds'),
            'help' => Help::Link('Schedule', 'Add')
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
     *      name="campaignId",
     *      in="formData",
     *      description="The Campaign ID to use for this Event. If a Layout is needed then the Campaign specific ID for that Layout should be used.",
     *      type="integer",
     *      required=true
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
     *      description="A 0|1 flag indicating whether this event should be considered priority",
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
     *      required=true
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
        Log::debug('Add Schedule');

        $schedule = new \Xibo\Entity\Schedule();
        $schedule->userId = $this->getUser()->userId;
        $schedule->campaignId = Sanitize::getInt('campaignId');
        $schedule->displayOrder = Sanitize::getInt('displayOrder', 0);
        $schedule->isPriority = Sanitize::getCheckbox('isPriority');
        $schedule->recurrenceType = Sanitize::getString('recurrenceType');
        $schedule->recurrenceDetail = Sanitize::getInt('recurrenceDetail');

        foreach (Sanitize::getIntArray('displayGroupIds') as $displayGroupId) {
            $schedule->assignDisplayGroup(DisplayGroupFactory::getById($displayGroupId));
        }

        // Handle the dates
        $fromDt = Sanitize::getDate('fromDt');
        $toDt = Sanitize::getDate('toDt');
        $recurrenceRange = Sanitize::getDate('recurrenceRange');

        Log::debug('Times received are: FromDt=' . Date::getLocalDate($fromDt) . '. ToDt=' . Date::getLocalDate($toDt) . '. recurrenceRange=' . Date::getLocalDate($recurrenceRange));

        // Set on schedule object
        $schedule->fromDt = $fromDt->setTime($fromDt->hour, $fromDt->minute, 0)->format('U');
        $schedule->toDt = $toDt->setTime($toDt->hour, $toDt->minute, 0)->format('U');

        if ($recurrenceRange != null)
            $schedule->recurrenceRange = $recurrenceRange->setTime($recurrenceRange->hour, $recurrenceRange->minute, 0)->format('U');

        // Ready to do the add
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
        $schedule = ScheduleFactory::getById($eventId);
        $schedule->load();

        if (!$this->isEventEditable($schedule->displayGroups))
            throw new AccessDeniedException();

        // Fix the event dates for display
        $schedule->fromDt = Date::getLocalDate($schedule->fromDt);
        $schedule->toDt = Date::getLocalDate($schedule->toDt);

        if ($schedule->recurrenceRange != null)
            $schedule->recurrenceRange = Date::getLocalDate($schedule->recurrenceRange);

        $groups = array();
        $displays = array();
        $scheduleWithView = (Config::GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes');

        foreach (DisplayGroupFactory::query(null, ['isDisplaySpecific' => -1]) as $displayGroup) {
            /* @var DisplayGroup $displayGroup */

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
            'campaigns' => CampaignFactory::query(null, ['isLayoutSpecific' => -1]),
            'displayGroupIds' => array_map(function($element) {
                return $element->displayGroupId;
            }, $schedule->displayGroups),
            'help' => Help::Link('Schedule', 'Edit')
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
     *      name="eventID",
     *      in="path",
     *      description="The Scheduled Event ID",
     *      type="integer",
     *      required=true
     *  ),
     *  @SWG\Parameter(
     *      name="campaignId",
     *      in="formData",
     *      description="The Campaign ID to use for this Event. If a Layout is needed then the Campaign specific ID for that Layout should be used.",
     *      type="integer",
     *      required=true
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
     *      description="A 0|1 flag indicating whether this event should be considered priority",
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
     *      required=true
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
     *      name="recurrentDetail",
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
     *   @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Schedule")
     *  )
     * )
     */
    public function edit($eventId)
    {
        $schedule = ScheduleFactory::getById($eventId);
        $schedule->load();

        if (!$this->isEventEditable($schedule->displayGroups))
            throw new AccessDeniedException();

        $schedule->campaignId = Sanitize::getInt('campaignId');
        $schedule->displayOrder = Sanitize::getInt('displayOrder');
        $schedule->isPriority = Sanitize::getCheckbox('isPriority');
        $schedule->recurrenceType = Sanitize::getString('recurrenceType');
        $schedule->recurrenceDetail = Sanitize::getInt('recurrenceDetail');
        $schedule->displayGroups = [];

        foreach (Sanitize::getIntArray('displayGroupIds') as $displayGroupId) {
            $schedule->assignDisplayGroup(DisplayGroupFactory::getById($displayGroupId));
        }

        // Handle the dates
        $fromDt = Sanitize::getDate('fromDt');
        $toDt = Sanitize::getDate('toDt');
        $recurrenceRange = Sanitize::getDate('recurrenceRange');

        Log::debug('Times received are: FromDt=' . Date::getLocalDate($fromDt) . '. ToDt=' . Date::getLocalDate($toDt) . '. recurrenceRange=' . Date::getLocalDate($recurrenceRange));

        // Set on schedule object
        $schedule->fromDt = $fromDt->setTime($fromDt->hour, $fromDt->minute, 0)->format('U');
        $schedule->toDt = $toDt->setTime($toDt->hour, $toDt->minute, 0)->format('U');

        if ($recurrenceRange != null)
            $schedule->recurrenceRange = $recurrenceRange->setTime($recurrenceRange->hour, $recurrenceRange->minute, 0)->format('U');

        // Ready to do the add
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
        $schedule = ScheduleFactory::getById($eventId);
        $schedule->load();

        if (!$this->isEventEditable($schedule->displayGroups))
            throw new AccessDeniedException();

        $this->getState()->template = 'schedule-form-delete';
        $this->getState()->setData([
            'event' => $schedule,
            'help' => Help::Link('Schedule', 'Delete')
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
        $schedule = ScheduleFactory::getById($eventId);
        $schedule->load();

        if (!$this->isEventEditable($schedule->displayGroups))
            throw new AccessDeniedException();

        $schedule->delete();

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => __('Deleted Event')
        ]);
    }

    /**
     * Is this event editable?
     * @param array[DisplayGroup] $displayGroups
     * @return bool
     */
    private function isEventEditable($displayGroups)
    {
        $scheduleWithView = (Config::GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes');

        // Work out if this event is editable or not. To do this we need to compare the permissions
        // of each display group this event is associated with
        foreach ($displayGroups as $displayGroup) {
            /* @var DisplayGroup $displayGroup */

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
        $scheduleWithView = (Config::GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes');

        foreach (DisplayGroupFactory::query(null, ['isDisplaySpecific' => -1]) as $displayGroup) {
            /* @var DisplayGroup $displayGroup */

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
            'campaigns' => CampaignFactory::query(null, ['isLayoutSpecific' => -1]),
            'help' => Help::Link('Schedule', 'ScheduleNow')
        ]);
    }
}