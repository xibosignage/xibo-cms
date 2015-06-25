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
use baseDAO;
use Kit;
use Xibo\Entity\DisplayGroup;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Date;
use Xibo\Helper\Form;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;


class Schedule extends Base
{
    function displayPage()
    {
        // We need to provide a list of displays
        $displayGroupIds = Sanitize::getIntArray('displayGroupIds');
        $groups = array();
        $displays = array();

        foreach (DisplayGroupFactory::query() as $display) {
            /* @var DisplayGroup $display */

            $display->selected = (in_array($display->displayGroupId, $displayGroupIds));

            if ($display->isDisplaySpecific == 1) {
                $displays[] = $display;
            } else {
                $groups[] = $display;
            }
        }

        $data = [
            'allSelected' => in_array(-1, $displayGroupIds),
            'groups' => $groups,
            'displays' => $displays
        ];

        // Render the Theme and output
        $this->getState()->template = 'schedule-page';
        $this->getState()->setData($data);
    }

    /**
     * Generates the calendar that we draw events on
     */
    function eventData()
    {
        $this->getApp()->response()->header('Content-Type', 'application/json');

        $displayGroupIds = Sanitize::getIntArray('DisplayGroupIDs');
        $start = Sanitize::getInt('from', 1000) / 1000;
        $end = Sanitize::getInt('to', 1000) / 1000;

        // if we have some displaygroupids then add them to the session info so we can default everything else.
        $this->getSession()->set('DisplayGroupIDs', $displayGroupIds);

        if (count($displayGroupIds) <= 0) {
            die(json_encode(array('success' => 1, 'result' => array())));
        }

        $events = array();
        $filter = [
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

        echo json_encode(array('success' => 1, 'result' => $events));
        $this->setNoOutput();
    }

    /**
     * Shows a form to add an event
     */
    function addForm()
    {
        $groups = array();
        $displays = array();
        $scheduleWithView = (Config::GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes');

        foreach (DisplayGroupFactory::query() as $displayGroup) {
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
            'campaigns' => CampaignFactory::query(),
            'displayGroupIds' => $this->getSession()->get('displayGroupIds'),
            'help' => Help::Link('Schedule', 'Add')
        ]);
    }

    /**
     * Add Event
     */
    public function add()
    {
        $schedule = new \Xibo\Entity\Schedule();
        $schedule->userId = $this->getUser()->userId;
        $schedule->campaignId = Sanitize::getInt('campaignId');
        $schedule->displayOrder = Sanitize::getInt('displayOrder');
        $schedule->isPriority = Sanitize::getCheckbox('isPriority');
        $schedule->recurrenceType = Sanitize::getString('recurrenceType');
        $schedule->recurrenceDetail = Sanitize::getString('recurrenceDetail');

        foreach (Sanitize::getIntArray('displayGroupIds') as $displayGroupId) {
            $schedule->assignDisplayGroup(DisplayGroupFactory::getById($displayGroupId));
        }

        // Handle the dates
        $fromDt = Sanitize::getString('fromDt');
        $toDt = Sanitize::getString('toDt');
        $recurrenceRange = Sanitize::getString('recurrenceRange');

        Log::debug('Times received are: FromDt=' . $fromDt . '. ToDt=' . $toDt . '. recurrenceRange=' . $recurrenceRange);

        // Convert our dates
        $fromDt = Date::getTimestampFromString($fromDt);
        $toDt = Date::getTimestampFromString($toDt);

        if ($recurrenceRange != '')
            $recurrenceRange = Date::getTimestampFromString($recurrenceRange);

        Log::debug('Converted Times received are: FromDt=' . $fromDt . '. ToDt=' . $toDt . '. recurrenceRange=' . $recurrenceRange);

        $schedule->fromDt = $fromDt;
        $schedule->toDt = $toDt;
        $schedule->recurrenceRange = $recurrenceRange;

        // Ready to do the add
        $schedule->save();

        // Return
        $this->getState()->hydrate([
            'message' => __('Added Event'),
            'id' => $schedule->eventId,
            'data' => [$schedule]
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

        $groups = array();
        $displays = array();
        $scheduleWithView = (Config::GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes');

        foreach (DisplayGroupFactory::query() as $displayGroup) {
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
            'campaigns' => CampaignFactory::query(),
            'displayGroupIds' => $this->getSession()->get('displayGroupIds'),
            'help' => Help::Link('Schedule', 'Edit')
        ]);
    }

    /**
     * Edits an event
     * @param int $eventId
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
        $schedule->recurrenceDetail = Sanitize::getString('recurrenceDetail');
        $schedule->displayGroups = [];

        foreach (Sanitize::getIntArray('displayGroupIds') as $displayGroupId) {
            $schedule->assignDisplayGroup(DisplayGroupFactory::getById($displayGroupId));
        }

        // Handle the dates
        $fromDt = Sanitize::getString('fromDt');
        $toDt = Sanitize::getString('toDt');
        $recurrenceRange = Sanitize::getString('recurrenceRange');

        Log::debug('Times received are: FromDt=' . $fromDt . '. ToDt=' . $toDt . '. recurrenceRange=' . $recurrenceRange);

        // Convert our dates
        $fromDt = Date::getTimestampFromString($fromDt);
        $toDt = Date::getTimestampFromString($toDt);

        if ($recurrenceRange != '')
            $recurrenceRange = Date::getTimestampFromString($recurrenceRange);

        Log::debug('Converted Times received are: FromDt=' . $fromDt . '. ToDt=' . $toDt . '. recurrenceRange=' . $recurrenceRange);

        $schedule->fromDt = $fromDt;
        $schedule->toDt = $toDt;
        $schedule->recurrenceRange = $recurrenceRange;

        // Ready to do the add
        $schedule->save();

        // Return
        $this->getState()->hydrate([
            'message' => __('Edited Event'),
            'id' => $schedule->eventId,
            'data' => [$schedule]
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

        foreach (DisplayGroupFactory::query() as $displayGroup) {
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
            'campaigns' => CampaignFactory::query(),
            'displayGroupIds' => $this->getSession()->get('displayGroupIds'),
            'help' => Help::Link('Schedule', 'ScheduleNow')
        ]);
    }
}