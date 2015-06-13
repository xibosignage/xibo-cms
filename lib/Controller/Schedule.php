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
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Helper\Config;
use Xibo\Helper\Date;
use Xibo\Helper\Form;
use Xibo\Helper\Help;
use Xibo\Helper\Log;
use Xibo\Helper\Sanitize;
use Xibo\Helper\Session;
use Xibo\Helper\Theme;


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
        Session::Set('DisplayGroupIDs', $displayGroupIds);

        if (count($displayGroupIds) <= 0) {
            die(json_encode(array('success' => 1, 'result' => array())));
        }

        $events = array();
        $filter = [
            'fromDt' => $start,
            'toDt' => $end,
            'displayGroupIds' => $displayGroupIds
        ];

        foreach (ScheduleFactory::query('schedule_detail.FromDT', $filter) as $row) {
            /* @var \Xibo\Entity\Schedule $row */

            // Load the display groups
            $row->load();

            $displayGroupList = implode(',', array_reduce($row->displayGroups, function($object) {
                return $object->displayGroup;
            }));

            // Event Permissions
            $editable = $this->IsEventEditable($row->displayGroups);

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

            if ($row['recurrence_type'] != '') {
                $class = 'event-special';
                $extra = 'recurring';
            }

            // Priority event
            if ($row['is_priority'] == 1) {
                $class = 'event-important';
                $extra = 'priority';
            }

            // Is this event editable?
            if (!$editable) {
                $class = 'event-inverse';
                $extra = 'view-only';
            }

            $events[] = array(
                'id' => $row['EventID'],
                'title' => $title,
                'url' => $url,
                'class' => 'XiboFormButton ' . $class,
                'extra' => $extra,
                'start' => $row['FromDT'] * 1000,
                'end' => $row['ToDT'] * 1000
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
            'displayGroupIds' => Session::get('displayGroupIds'),
            'help' => Help::Link('Schedule', 'Add')
        ]);
    }

    /**
     * Add Event
     */
    public function add()
    {
        $schedule = new \Xibo\Entity\Schedule();


        $campaignId = \Kit::GetParam('CampaignID', _POST, _INT, 0);
        $fromDT = \Xibo\Helper\Sanitize::getString('starttime');
        $toDT = \Xibo\Helper\Sanitize::getString('endtime');
        $displayGroupIDs = \Kit::GetParam('DisplayGroupIDs', _POST, _ARRAY);
        $isPriority = \Xibo\Helper\Sanitize::getCheckbox('is_priority');

        $repeatType = \Xibo\Helper\Sanitize::getString('rec_type');
        $repeatInterval = \Xibo\Helper\Sanitize::getInt('rec_detail');
        $repeatToDt = \Xibo\Helper\Sanitize::getString('rec_range');

        $displayOrder = \Xibo\Helper\Sanitize::getInt('DisplayOrder');
        $isNextButton = \Kit::GetParam('next', _GET, _BOOL, false);

        Log::debug('Times received are: FromDt=' . $fromDT . '. ToDt=' . $toDT . '. RepeatToDt=' . $repeatToDt);

        // Convert our dates
        $fromDT = Date::getTimestampFromString($fromDT);
        $toDT = Date::getTimestampFromString($toDT);

        if ($repeatToDt != '')
            $repeatToDt = Date::getTimestampFromString($repeatToDt);

        Log::debug('Converted Times received are: FromDt=' . $fromDT . '. ToDt=' . $toDT . '. RepeatToDt=' . $repeatToDt);

        // Validate layout
        if ($campaignId == 0)
            trigger_error(__("No layout selected"), E_USER_ERROR);

        // check that at least one display has been selected
        if ($displayGroupIDs == '')
            trigger_error(__("No displays selected"), E_USER_ERROR);

        // validate the dates
        if ($toDT < $fromDT)
            trigger_error(__('Can not have an end time earlier than your start time'), E_USER_ERROR);

        if ($fromDT < (time() - 86400))
            trigger_error(__("Your start time is in the past. Cannot schedule events in the past"), E_USER_ERROR);

        // Check recurrence dT is in the future or empty
        if ($repeatType != '' && ($repeatToDt != '' && ($repeatToDt < (time() - 86400))))
            trigger_error(__("Your repeat until date is in the past. Cannot schedule events to repeat in to the past"), E_USER_ERROR);

        // Ready to do the add
        $scheduleObject = new Schedule($db);
        if (!$scheduleObject->Add($displayGroupIDs, $fromDT, $toDT, $campaignId, $repeatType, $repeatInterval, $repeatToDt, $isPriority, $this->getUser()->userId, $displayOrder))
            trigger_error($scheduleObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__("The Event has been Added."));
        $response->callBack = 'CallGenerateCalendar';
        if ($isNextButton)
            $response->keepOpen = true;

    }

    /**
     * Shows a form to add an event
     *  will default to the current date if non is provided
     * @return
     */
    function EditEventForm()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $eventID = \Kit::GetParam('EventID', _GET, _INT, 0);

        if ($eventID == 0)
            trigger_error(__('No event selected.'), E_USER_ERROR);

        // Get the relevant details for this event
        $SQL = "";
        $SQL .= "SELECT schedule.FromDT, ";
        $SQL .= "       schedule.ToDT,";
        $SQL .= "       schedule.CampaignID, ";
        $SQL .= "       schedule.userid, ";
        $SQL .= "       schedule.is_priority, ";
        $SQL .= "       schedule.DisplayGroupIDs, ";
        $SQL .= "       schedule.recurrence_type, ";
        $SQL .= "       schedule.recurrence_detail, ";
        $SQL .= "       schedule.recurrence_range, ";
        $SQL .= "       schedule.EventID, ";
        $SQL .= "       schedule.DisplayOrder ";
        $SQL .= "  FROM schedule ";
        $SQL .= " WHERE 1=1 ";
        $SQL .= sprintf("   AND schedule.EventID = %d", $eventID);

        Log::notice($SQL);

        if (!$result = $db->query($SQL)) {
            trigger_error($db->error());
            trigger_error(__('Error getting details for this event.'), E_USER_ERROR);
        }

        $row = $db->get_assoc_row($result);

        $fromDT = \Xibo\Helper\Sanitize::int($row['FromDT']);
        $toDT = \Xibo\Helper\Sanitize::int($row['ToDT']);
        $displayGroupIds = explode(',', \Xibo\Helper\Sanitize::string($row['DisplayGroupIDs']));
        $recType = \Xibo\Helper\Sanitize::string($row['recurrence_type']);
        $recDetail = \Xibo\Helper\Sanitize::string($row['recurrence_detail']);
        $recToDT = \Xibo\Helper\Sanitize::int($row['recurrence_range']);
        $campaignId = \Xibo\Helper\Sanitize::string($row['CampaignID']);
        $isPriority = \Xibo\Helper\Sanitize::int($row['is_priority']);
        $displayOrder = \Xibo\Helper\Sanitize::int($row['DisplayOrder']);

        // Check that we have permission to edit this event.
        if (!$this->IsEventEditable($displayGroupIds))
            trigger_error(__('You do not have permission to edit this event.'), E_USER_ERROR);

        $token_id = uniqid();
        $token_field = '<input type="hidden" name="token_id" value="' . $token_id . '" />';
        $token = \Kit::Token($token_id);

        Theme::Set('form_id', 'EditEventForm');
        Theme::Set('form_action', 'index.php?p=schedule&q=EditEvent');
        Theme::Set('form_meta', $token_field . $token . '<input type="hidden" id="EventID" name="EventID" value="' . $eventID . '" />');

        // Two tabs
        $tabs = array();
        $tabs[] = Form::AddTab('general', __('General'));
        $tabs[] = Form::AddTab('repeats', __('Repeats'));
        Theme::Set('form_tabs', $tabs);

        $formFields = array();

        // List of Display Groups
        $optionGroups = array(
            array('id' => 'group', 'label' => __('Groups')),
            array('id' => 'display', 'label' => __('Displays'))
        );

        $groups = array();
        $displays = array();
        $scheduleWithView = (Config::GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes');

        foreach ($this->getUser()->DisplayGroupList(-1 /*IsDisplaySpecific*/) as $display) {

            // Can schedule with view, but no view permissions
            if ($scheduleWithView && $display['view'] != 1)
                continue;

            // Can't schedule with view, but no edit permissions
            if (!$scheduleWithView && $display['edit'] != 1)
                continue;

            $display['checked_text'] = (in_array($display['displaygroupid'], $displayGroupIds)) ? ' selected' : '';

            if ($display['isdisplayspecific'] == 1) {
                $displays[] = $display;
            } else {
                $groups[] = $display;
            }
        }

        $formFields['general'][] = Form::AddMultiCombo(
            'DisplayGroupIDs[]',
            __('Display'),
            $displayGroupIds,
            array('group' => $groups, 'display' => $displays),
            'displaygroupid',
            'displaygroup',
            __('Please select one or more displays / groups for this event to be shown on.'),
            'd', '', true, '', '', '', $optionGroups, array(array('name' => 'data-live-search', 'value' => "true"), array('name' => 'data-selected-text-format', 'value' => "count > 4")));

        // Time controls
        $formFields['general'][] = Form::AddText('starttimeControl', __('Start Time'), Date::getLocalDate($fromDT),
            __('Select the start time for this event'), 's', 'required');

        $formFields['general'][] = Form::AddText('endtimeControl', __('End Time'), Date::getLocalDate($toDT),
            __('Select the end time for this event'), 'e', 'required');

        // Add two hidden fields to always carry the ISO date
        $formFields['general'][] = FormManager::AddHidden('starttime', DateManager::getLocalDate($fromDT, "Y-m-d H:i", false));
        $formFields['general'][] = FormManager::AddHidden('endtime', DateManager::getLocalDate($toDT, "Y-m-d H:i", false));

        // Generate a list of layouts.
        $layouts = $user->CampaignList(NULL, false /* isRetired */, false /* show Empty */);

        $optionGroups = array(
            array('id' => 'campaign', 'label' => __('Campaigns')),
            array('id' => 'layout', 'label' => __('Layouts'))
        );

        $layoutOptions = array();
        $campaignOptions = array();

        foreach ($layouts as $layout) {

            if ($layout['islayoutspecific'] == 1) {
                $layoutOptions[] = array(
                    'id' => $layout['campaignid'],
                    'value' => $layout['campaign']
                );
            } else {
                $campaignOptions[] = array(
                    'id' => $layout['campaignid'],
                    'value' => $layout['campaign']
                );
            }
        }

        $formFields['general'][] = Form::AddCombo(
            'CampaignID',
            __('Layout / Campaign'),
            $campaignId,
            array('campaign' => $campaignOptions, 'layout' => $layoutOptions),
            'id',
            'value',
            __('Please select a Layout or Campaign for this Event to show'),
            'l', '', true, '', '', '', $optionGroups);

        $formFields['general'][] = Form::AddNumber('DisplayOrder', __('Display Order'), $displayOrder,
            __('Please select the order this event should appear in relation to others when there is more than one event scheduled'), 'o');

        $formFields['general'][] = Form::AddCheckbox('is_priority', __('Priority'),
            $isPriority, __('Sets whether or not this event has priority. If set the event will be show in preference to other events.'),
            'p');

        $formFields['repeats'][] = Form::AddCombo(
            'rec_type',
            __('Repeats'),
            $recType,
            array(
                array('id' => '', 'name' => __('None')),
                array('id' => 'Minute', 'name' => __('Per Minute')),
                array('id' => 'Hour', 'name' => __('Hourly')),
                array('id' => 'Day', 'name' => __('Daily')),
                array('id' => 'Week', 'name' => __('Weekly')),
                array('id' => 'Month', 'name' => __('Monthly')),
                array('id' => 'Year', 'name' => __('Yearly'))
            ),
            'id',
            'name',
            __('What type of repeat is required?'),
            'r');

        $formFields['repeats'][] = Form::AddNumber('rec_detail', __('Repeat every'), $recDetail,
            __('How often does this event repeat?'), 'o', '', 'repeat-control-group');

        $formFields['repeats'][] = Form::AddText('rec_rangeControl', __('Until'), ((($recToDT == 0) ? '' : Date::getLocalDate($recToDT))),
            __('When should this event stop repeating?'), 'u', '', 'repeat-control-group');

        $formFields['repeats'][] = Form::AddHidden('rec_range', Date::getLocalDate($recToDT, "Y-m-d H:i"));

        // Set some field dependencies
        $response->AddFieldAction('rec_type', 'init', '', array('.repeat-control-group' => array('display' => 'none')));
        $response->AddFieldAction('rec_type', 'init', '', array('.repeat-control-group' => array('display' => 'block')), "not");
        $response->AddFieldAction('rec_type', 'change', '', array('.repeat-control-group' => array('display' => 'none')));
        $response->AddFieldAction('rec_type', 'change', '', array('.repeat-control-group' => array('display' => 'block')), "not");

        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_repeats', $formFields['repeats']);

        $response->SetFormRequestResponse(NULL, __('Edit Event'), '800px', '600px');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=Edit')");
        $response->AddButton(__('Delete'), 'XiboFormRender("index.php?p=schedule&q=DeleteForm&EventID=' . $eventID . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#EditEventForm").attr("action", $("#EditEventForm").attr("action") + "&next=0").submit()');
        $response->callBack = 'setupScheduleForm';

    }


    /**
     * Edits an event
     * @return
     */
    public function EditEvent()
    {
        // Check the token
        if (!Kit::CheckToken(Kit::GetParam('token_id', _POST, _STRING)))
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);


        $user = $this->getUser();
        $response = $this->getState();

        $eventId = \Kit::GetParam('EventID', _POST, _INT, 0);
        $campaignId = \Kit::GetParam('CampaignID', _POST, _INT, 0);
        $fromDT = \Xibo\Helper\Sanitize::getString('starttime');
        $toDT = \Xibo\Helper\Sanitize::getString('endtime');
        $displayGroupIDs = \Kit::GetParam('DisplayGroupIDs', _POST, _ARRAY);
        $isPriority = \Xibo\Helper\Sanitize::getCheckbox('is_priority');

        $repeatType = \Xibo\Helper\Sanitize::getString('rec_type');
        $repeatInterval = \Xibo\Helper\Sanitize::getInt('rec_detail');
        $repeatToDt = \Xibo\Helper\Sanitize::getString('rec_range');

        $displayOrder = \Xibo\Helper\Sanitize::getInt('DisplayOrder');
        $isNextButton = \Kit::GetParam('next', _GET, _BOOL, false);

        // Convert our ISO strings
        $fromDT = Date::getTimestampFromString($fromDT);
        $toDT = Date::getTimestampFromString($toDT);

        if ($repeatToDt != '')
            $repeatToDt = Date::getTimestampFromString($repeatToDt);

        Log::debug('Times received are: FromDt=' . $fromDT . '. ToDt=' . $toDT . '. RepeatToDt=' . $repeatToDt);

        // Validate layout
        if ($campaignId == 0)
            trigger_error(__("No layout selected"), E_USER_ERROR);

        // check that at least one display has been selected
        if ($displayGroupIDs == '')
            trigger_error(__("No displays selected"), E_USER_ERROR);

        // validate the dates
        if ($toDT < $fromDT)
            trigger_error(__('Can not have an end time earlier than your start time'), E_USER_ERROR);

        // Check recurrence dT is in the future or empty
        if (($repeatToDt != '') && ($repeatToDt < (time() - 86400)))
            trigger_error(__("Your repeat until date is in the past. Cannot schedule events to repeat in to the past"), E_USER_ERROR);


        // Ready to do the edit
        $scheduleObject = new Schedule($db);
        if (!$scheduleObject->Edit($eventId, $displayGroupIDs, $fromDT, $toDT, $campaignId, $repeatType, $repeatInterval, $repeatToDt, $isPriority, $this->getUser()->userId, $displayOrder))
            trigger_error($scheduleObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__("The Event has been Modified."));
        $response->callBack = 'CallGenerateCalendar';

    }

    /**
     * Shows the DeleteEvent form
     * @return
     */
    function DeleteForm()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $eventID = \Kit::GetParam('EventID', _GET, _INT, 0);

        if ($eventID == 0)
            trigger_error(__('No event selected.'), E_USER_ERROR);

        Theme::Set('form_id', 'DeleteEventForm');
        Theme::Set('form_action', 'index.php?p=schedule&q=DeleteEvent');
        Theme::Set('form_meta', '<input type="hidden" name="EventID" value="' . $eventID . '" />');
        Theme::Set('form_fields', array(Form::AddMessage(__('Are you sure you want to delete this event from <b>all</b> displays? If you only want to delete this item from certain displays, please deselect the displays in the edit dialogue and click Save.'))));

        $response->SetFormRequestResponse(NULL, __('Delete Event.'), '480px', '240px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . Help::Link('Schedule', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#DeleteEventForm").submit()');

    }

    /**
     * Deletes an Event from all displays
     * @return
     */
    public function DeleteEvent()
    {


        $user = $this->getUser();
        $response = $this->getState();

        $eventID = \Kit::GetParam('EventID', _POST, _INT, 0);

        if ($eventID == 0)
            trigger_error(__('No event selected.'), E_USER_ERROR);

        // Create an object to use for the delete
        $scheduleObject = new Schedule($db);

        // Delete the entire schedule.
        if (!$scheduleObject->Delete($eventID)) {
            trigger_error($scheduleObject->GetErrorMessage(), E_USER_ERROR);
        }

        $response->SetFormSubmitResponse(__("The Event has been Deleted."));
        $response->callBack = 'CallGenerateCalendar';

    }

    /**
     * Is this event editable?
     * @param array[DisplayGroup] $displayGroups
     * @return bool
     */
    private function IsEventEditable($displayGroups)
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

    public function ScheduleNowForm()
    {

        $user = $this->getUser();
        $response = $this->getState();

        $date = time();

        // We might have a layout id, or a display id
        $campaignId = \Kit::GetParam('CampaignID', _GET, _INT, 0);
        $displayGroupIds = \Kit::GetParam('displayGroupId', _GET, _ARRAY);

        Theme::Set('form_id', 'ScheduleNowForm');
        Theme::Set('form_action', 'index.php?p=schedule&q=ScheduleNow');

        $formFields = array();

        // Generate a list of layouts.
        $layouts = $user->CampaignList(NULL, false /* isRetired */, false /* show Empty */);

        $optionGroups = array(
            array('id' => 'campaign', 'label' => __('Campaigns')),
            array('id' => 'layout', 'label' => __('Layouts'))
        );

        $layoutOptions = array();
        $campaignOptions = array();

        foreach ($layouts as $layout) {

            if ($layout['islayoutspecific'] == 1) {
                $layoutOptions[] = array(
                    'id' => $layout['campaignid'],
                    'value' => $layout['campaign']
                );
            } else {
                $campaignOptions[] = array(
                    'id' => $layout['campaignid'],
                    'value' => $layout['campaign']
                );
            }
        }

        $formFields[] = Form::AddCombo(
            'CampaignID',
            __('Layout'),
            $campaignId,
            array('campaign' => $campaignOptions, 'layout' => $layoutOptions),
            'id',
            'value',
            __('Please select a Layout or Campaign for this Event to show'),
            'l', '', true, '', '', '', $optionGroups);

        $formFields[] = Form::AddText('hours', __('Hours'), NULL,
            __('Hours this event should be scheduled for'), 'h', '');

        $formFields[] = Form::AddText('minutes', __('Minutes'), NULL,
            __('Minutes this event should be scheduled for'), 'h', '');

        $formFields[] = Form::AddText('seconds', __('Seconds'), NULL,
            __('Seconds this event should be scheduled for'), 'h', '');

        // List of Display Groups
        $optionGroups = array(
            array('id' => 'group', 'label' => __('Groups')),
            array('id' => 'display', 'label' => __('Displays'))
        );

        $groups = array();
        $displays = array();
        $scheduleWithView = (Config::GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes');

        foreach ($this->getUser()->DisplayGroupList(-1 /*IsDisplaySpecific*/) as $display) {

            // Can schedule with view, but no view permissions
            if ($scheduleWithView && $display['view'] != 1)
                continue;

            // Can't schedule with view, but no edit permissions
            if (!$scheduleWithView && $display['edit'] != 1)
                continue;

            $display['checked_text'] = (in_array($display['displaygroupid'], $displayGroupIds)) ? ' selected' : '';

            if ($display['isdisplayspecific'] == 1) {
                $displays[] = $display;
            } else {
                $groups[] = $display;
            }
        }

        $formFields[] = Form::AddMultiCombo(
            'DisplayGroupIDs[]',
            __('Display'),
            $displayGroupIds,
            array('group' => $groups, 'display' => $displays),
            'displaygroupid',
            'displaygroup',
            __('Please select one or more displays / groups for this event to be shown on.'),
            'd', '', true, '', '', '', $optionGroups, array(array('name' => 'data-live-search', 'value' => "true"), array('name' => 'data-selected-text-format', 'value' => "count > 4")));

        $formFields[] = Form::AddNumber('DisplayOrder', __('Display Order'), 0,
            __('Should this event have an order?'), 'o', '');

        $formFields[] = Form::AddCheckbox('is_priority', __('Priority?'),
            NULL, __('Sets whether or not this event has priority. If set the event will be show in preference to other events.'),
            'p');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Schedule Now'), '700px', '400px');
        $response->callBack = 'setupScheduleNowForm';
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=ScheduleNow')");
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#ScheduleNowForm").submit()');

    }

    public function ScheduleNow()
    {


        $user = $this->getUser();
        $response = $this->getState();

        $campaignId = \Kit::GetParam('CampaignID', _POST, _INT, 0);
        $displayGroupIds = \Kit::GetParam('DisplayGroupIDs', _POST, _ARRAY);
        $isPriority = \Xibo\Helper\Sanitize::getCheckbox('is_priority');
        $fromDt = time();

        $hours = \Kit::GetParam('hours', _POST, _INT, 0);
        $minutes = \Kit::GetParam('minutes', _POST, _INT, 0);
        $seconds = \Kit::GetParam('seconds', _POST, _INT, 0);
        $duration = ($hours * 3600) + ($minutes * 60) + $seconds;
        $displayOrder = \Xibo\Helper\Sanitize::getInt('DisplayOrder');

        // Validate
        if ($campaignId == 0)
            trigger_error(__('No layout selected'), E_USER_ERROR);

        if ($duration == 0)
            trigger_error(__('You must enter a duration'), E_USER_ERROR);

        // check that at least one display has been selected
        if ($displayGroupIds == '')
            trigger_error(__('No displays selected'), E_USER_ERROR);

        if ($fromDt < (time() - 86400))
            trigger_error(__('Your start time is in the past. Cannot schedule events in the past'), E_USER_ERROR);

        $toDt = $fromDt + $duration;

        // Ready to do the add
        $scheduleObject = new Schedule($db);

        if (!$scheduleObject->Add($displayGroupIds, $fromDt, $toDt, $campaignId, '', '', '', $isPriority, $this->getUser()->userId, $displayOrder))
            trigger_error($scheduleObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('The Event has been Scheduled'));

    }
}

?>
