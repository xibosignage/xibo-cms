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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

require_once('lib/data/schedule.data.class.php');

class scheduleDAO extends baseDAO {    

    function displayPage() 
    {
        Theme::Set('event_add_url', 'index.php?p=schedule&q=AddEventForm');

        // We need to provide a list of displays
        $displayGroupIds = Kit::GetParam('displayGroupIds', _SESSION, _ARRAY);
        $groups = array();
        $displays = array();

        foreach ($this->user->DisplayGroupList(-1 /*IsDisplaySpecific*/) as $display) {

            $display['checked_text'] = (in_array($display['displaygroupid'], $displayGroupIds)) ? ' selected' : '';

            if ($display['isdisplayspecific'] == 1) {
                $displays[] = $display;
            }
            else {
                $groups[] = $display;
            }
        }
        
        Theme::Set('id', 'DisplayList');
        Theme::Set('allSelected', in_array(-1, $displayGroupIds));
        Theme::Set('groups', $groups);
        Theme::Set('displays', $displays);
        Theme::Set('calendarType', Config::GetSetting('CALENDAR_TYPE'));

        // Render the Theme and output
        Theme::Render('schedule_page');
    }
    
    /**
     * Generates the calendar that we draw events on
     */
    function GenerateCalendar()
    {
        $displayGroupIds = Kit::GetParam('DisplayGroupIDs', _GET, _ARRAY);
        $start = Kit::GetParam('from', _REQUEST, _INT) / 1000;
        $end = Kit::GetParam('to', _REQUEST, _INT) / 1000;

        // if we have some displaygroupids then add them to the session info so we can default everything else.
        Session::Set('DisplayGroupIDs', $displayGroupIds);

        if (count($displayGroupIds) <= 0)
            die(json_encode(array('success' => 1, 'result' => array())));
        
        // Get Events between the provided dates
        try {
            $dbh = PDOConnect::init();

            // Query for all events between the dates
            $SQL = "";
            $SQL.= "SELECT schedule.EventID, ";
            $SQL.= "       schedule_detail.FromDT, ";
            $SQL.= "       schedule_detail.ToDT,";
            $SQL.= "       schedule.DisplayGroupIDs, ";
            $SQL.= "       schedule.is_priority, ";
            $SQL.= "       schedule.recurrence_type, ";
            $SQL.= "       campaign.Campaign, ";
            $SQL.= "       GROUP_CONCAT(displaygroup.DisplayGroup) AS DisplayGroups ";
            $SQL.= "  FROM schedule_detail ";
            $SQL.= "  INNER JOIN schedule ON schedule_detail.EventID = schedule.EventID ";
            $SQL.= "  INNER JOIN campaign ON campaign.CampaignID = schedule.CampaignID ";
            $SQL.= "  INNER JOIN displaygroup ON displaygroup.DisplayGroupID = schedule_detail.DisplayGroupID ";
            $SQL.= " WHERE 1=1 ";

            // If we have minus 1, then show all
            if (in_array(-1, $displayGroupIds)) {
                // Get all display groups this user has permission to view
                $displayGroupIdsThisUser = $this->user->DisplayGroupList(-1);

                foreach ($displayGroupIdsThisUser as $row) {
                    $displayGroupIds[] = $row['displaygroupid'];
                }
            }

            $sanitized = array();
            foreach ($displayGroupIds as $displayGroupId) {
                $sanitized[] = sprintf("'%d'", $displayGroupId);
            }

            $SQL .= "   AND schedule_detail.DisplayGroupID IN (" . implode(',', $sanitized) . ")";

            // Events that fall inside the two dates
            $SQL.= "   AND schedule_detail.ToDT > :start ";
            $SQL.= "   AND schedule_detail.FromDT < :end ";

            // Grouping
            $SQL.= "GROUP BY schedule.EventID, ";
            $SQL.= "       schedule_detail.FromDT, ";
            $SQL.= "       schedule_detail.ToDT,";
            $SQL.= "       schedule.DisplayGroupIDs, ";
            $SQL.= "       schedule.is_priority, ";
            $SQL.= "       schedule.recurrence_type, ";
            $SQL.= "       campaign.Campaign ";

            // Ordering
            $SQL.= " ORDER BY schedule_detail.FromDT DESC";

            $params = array(
                    'start' => $start,
                    'end' => $end
                );

            Debug::sql($SQL, $params);
        
            $sth = $dbh->prepare($SQL);
            $sth->execute($params);

            $events = array();

            foreach ($sth->fetchAll() as $row) {

                $eventDisplayGroupIds = explode(',', $row['DisplayGroupIDs']);

                // Event Permissions
                $editable = $this->IsEventEditable($eventDisplayGroupIds);

                // Event Title
                $title = sprintf(__('%s scheduled on %s'), Kit::ValidateParam($row['Campaign'], _STRING), Kit::ValidateParam($row['DisplayGroups'], _STRING));

                // Event URL
                $url = ($editable) ? sprintf('index.php?p=schedule&q=EditEventForm&EventID=%d', $row['EventID']) : '#';

                // Classes used to distinguish between events
                //$class = 'event-warning';
            
                // Event is on a single display
                if (count($eventDisplayGroupIds) <= 1) {
                    $class = 'event-info';
                    $extra = 'single-display';
                }
                else {
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
            die;
        }
        catch (Exception $e) {
            
            Debug::LogEntry('error', $e->getMessage(), get_class(), __FUNCTION__);
        
            die(json_encode(array('success' => 0, 'error' => __('Unable to get events'))));
        }
    }
        
    /**
     * Shows a form to add an event
     * @return 
     */
    function AddEventForm() {

        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $displayGroupIds = Kit::GetParam('displayGroupIds', _SESSION, _ARRAY);

        $token_id = uniqid();
        $token_field = '<input type="hidden" name="token_id" value="' . $token_id . '" />';
        $token = Kit::Token($token_id);
        
        Theme::Set('form_id', 'AddEventForm');
        Theme::Set('form_action', 'index.php?p=schedule&q=AddEvent');
        Theme::Set('form_meta', $token_field . $token);

        // Two tabs
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'));
        $tabs[] = FormManager::AddTab('repeats', __('Repeats'));
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

        foreach ($this->user->DisplayGroupList(-1 /*IsDisplaySpecific*/) as $display) {

            // Can schedule with view, but no view permissions
            if ($scheduleWithView && $display['view'] != 1)
                continue;

            // Can't schedule with view, but no edit permissions
            if (!$scheduleWithView && $display['edit'] != 1)
                continue;

            $display['checked_text'] = (in_array($display['displaygroupid'], $displayGroupIds)) ? ' selected' : '';

            if ($display['isdisplayspecific'] == 1) {
                $displays[] = $display;
            }
            else {
                $groups[] = $display;
            }
        }

        $formFields['general'][] = FormManager::AddMultiCombo(
                    'DisplayGroupIDs[]', 
                    __('Display'), 
                    $displayGroupIds,
                    array('group' => $groups, 'display' => $displays),
                    'displaygroupid',
                    'displaygroup',
                    __('Please select one or more displays / groups for this event to be shown on.'), 
                    'd', '', true, '', '', '', $optionGroups, array(array('name' => 'data-live-search', 'value' => "true"), array('name' => 'data-selected-text-format', 'value' => "count > 4")));

        // Time controls
        $formFields['general'][] = FormManager::AddText('starttimeControl', __('Start Time'), NULL, 
            __('Select the start time for this event'), 's', 'required');

        $formFields['general'][] = FormManager::AddText('endtimeControl', __('End Time'), NULL, 
            __('Select the end time for this event'), 'e', 'required');

        // Add two hidden fields to always carry the ISO date
        $formFields['general'][] = FormManager::AddHidden('starttime', NULL);
        $formFields['general'][] = FormManager::AddHidden('endtime', NULL);
        
        // Generate a list of layouts.
        $layouts = $user->CampaignList(NULL, false /* isRetired */, false /* show Empty */);
        
        $optionGroups = array(
            array('id' => 'campaign', 'label' => __('Campaigns')),
            array('id' => 'layout', 'label' => __('Layouts'))
            );

        $layoutOptions = array();
        $campaignOptions = array();

        foreach($layouts as $layout) {

            if ($layout['islayoutspecific'] == 1) {
                $layoutOptions[] = array(
                        'id' => $layout['campaignid'],
                        'value' => $layout['campaign']
                    );
            }
            else {
                $campaignOptions[] = array(
                        'id' => $layout['campaignid'],
                        'value' => $layout['campaign']
                    );
            }
        }

        $formFields['general'][] = FormManager::AddCombo(
                    'CampaignID', 
                    __('Layout / Campaign'), 
                    NULL,
                    array('campaign' => $campaignOptions, 'layout' => $layoutOptions),
                    'id',
                    'value',
                    __('Please select a Layout or Campaign for this Event to show'), 
                    'l', '', true, '', '', '', $optionGroups);

        $formFields['general'][] = FormManager::AddNumber('DisplayOrder', __('Display Order'), NULL, 
            __('Please select the order this event should appear in relation to others when there is more than one event scheduled'), 'o');

        $formFields['general'][] = FormManager::AddCheckbox('is_priority', __('Priority'), 
            NULL, __('Sets whether or not this event has priority. If set the event will be show in preference to other events.'), 
            'p');

        $formFields['repeats'][] = FormManager::AddCombo(
                    'rec_type', 
                    __('Repeats'), 
                    NULL,
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

        $formFields['repeats'][] = FormManager::AddNumber('rec_detail', __('Repeat every'), NULL, 
            __('How often does this event repeat?'), 'o', '', 'repeat-control-group');

        $formFields['repeats'][] = FormManager::AddText('rec_rangeControl', __('Until'), NULL, 
            __('When should this event stop repeating?'), 'u', '', 'repeat-control-group');
        
        $formFields['repeats'][] = FormManager::AddHidden('rec_range', NULL);

        // Set some field dependencies
        $response->AddFieldAction('rec_type', 'init', '', array('.repeat-control-group' => array('display' => 'none')));
        $response->AddFieldAction('rec_type', 'init', '', array('.repeat-control-group' => array('display' => 'block')), "not");
        $response->AddFieldAction('rec_type', 'change', '', array('.repeat-control-group' => array('display' => 'none')));
        $response->AddFieldAction('rec_type', 'change', '', array('.repeat-control-group' => array('display' => 'block')), "not");

        Theme::Set('form_fields_general', $formFields['general']);
        Theme::Set('form_fields_repeats', $formFields['repeats']);

        $response->SetFormRequestResponse(NULL, __('Schedule Event'), '800px', '600px');
        $response->callBack = 'setupScheduleForm';
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=Add')");
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Next'), '$("#AddEventForm").attr("action", $("#AddEventForm").attr("action") + "&next=1").submit()');
        $response->AddButton(__('Save'), '$("#AddEventForm").attr("action", $("#AddEventForm").attr("action") + "&next=0").submit()');
        $response->Respond();
    }

    /**
     * Add Event
     * @return 
     */
    public function AddEvent() {
        // Check the token
        if (!Kit::CheckToken(Kit::GetParam('token_id', _POST, _STRING)))
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $campaignId = Kit::GetParam('CampaignID', _POST, _INT, 0);
        $fromDT = Kit::GetParam('starttime', _POST, _STRING);
        $toDT = Kit::GetParam('endtime', _POST, _STRING);
        $displayGroupIDs = Kit::GetParam('DisplayGroupIDs', _POST, _ARRAY);
        $isPriority = Kit::GetParam('is_priority', _POST, _CHECKBOX);

        $repeatType = Kit::GetParam('rec_type', _POST, _STRING);
        $repeatInterval = Kit::GetParam('rec_detail', _POST, _INT);
        $repeatToDt = Kit::GetParam('rec_range', _POST, _STRING);
        
        $displayOrder = Kit::GetParam('DisplayOrder', _POST, _INT);
        $isNextButton = Kit::GetParam('next', _GET, _BOOL, false);

        Debug::Audit('Times received are: FromDt=' . $fromDT . '. ToDt=' . $toDT . '. RepeatToDt=' . $repeatToDt);

        // Convert our dates
        $fromDT = DateManager::getTimestampFromString($fromDT);
        $toDT = DateManager::getTimestampFromString($toDT);

        if ($repeatToDt != '')
            $repeatToDt = DateManager::getTimestampFromString($repeatToDt);

        Debug::Audit('Converted Times received are: FromDt=' . $fromDT . '. ToDt=' . $toDT . '. RepeatToDt=' . $repeatToDt);
        
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
        if (!$scheduleObject->Add($displayGroupIDs, $fromDT, $toDT, $campaignId, $repeatType, $repeatInterval, $repeatToDt, $isPriority, $this->user->userid, $displayOrder))
            trigger_error($scheduleObject->GetErrorMessage(), E_USER_ERROR);
        
        $response->SetFormSubmitResponse(__("The Event has been Added."));
        $response->callBack = 'CallGenerateCalendar';
        if ($isNextButton)
            $response->keepOpen = true;
        $response->Respond();
    }
    
    /**
     * Shows a form to add an event
     *  will default to the current date if non is provided
     * @return 
     */
    function EditEventForm() {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $eventID = Kit::GetParam('EventID', _GET, _INT, 0);

        if ($eventID == 0) 
            trigger_error(__('No event selected.'), E_USER_ERROR);

        // Get the relevant details for this event
        $SQL = "";
        $SQL.= "SELECT schedule.FromDT, ";
        $SQL.= "       schedule.ToDT,";
        $SQL.= "       schedule.CampaignID, ";
        $SQL.= "       schedule.userid, ";
        $SQL.= "       schedule.is_priority, ";
        $SQL.= "       schedule.DisplayGroupIDs, ";
        $SQL.= "       schedule.recurrence_type, ";
        $SQL.= "       schedule.recurrence_detail, ";
        $SQL.= "       schedule.recurrence_range, ";
        $SQL.= "       schedule.EventID, ";
        $SQL.= "       schedule.DisplayOrder ";
        $SQL.= "  FROM schedule ";
        $SQL.= " WHERE 1=1 ";
        $SQL.= sprintf("   AND schedule.EventID = %d", $eventID);
        
        Debug::LogEntry('audit', $SQL);

        if (!$result = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting details for this event.'), E_USER_ERROR);
        }

        $row = $db->get_assoc_row($result);

        $fromDT = Kit::ValidateParam($row['FromDT'], _INT);
        $toDT = Kit::ValidateParam($row['ToDT'], _INT);
        $displayGroupIds = explode(',', Kit::ValidateParam($row['DisplayGroupIDs'], _STRING));
        $recType = Kit::ValidateParam($row['recurrence_type'], _STRING);
        $recDetail = Kit::ValidateParam($row['recurrence_detail'], _STRING);
        $recToDT = Kit::ValidateParam($row['recurrence_range'], _INT);
        $campaignId = Kit::ValidateParam($row['CampaignID'], _STRING);
        $isPriority = Kit::ValidateParam($row['is_priority'], _INT);
        $displayOrder = Kit::ValidateParam($row['DisplayOrder'], _INT);

        // Check that we have permission to edit this event.
        if (!$this->IsEventEditable($displayGroupIds))
            trigger_error(__('You do not have permission to edit this event.'), E_USER_ERROR);
        
        $token_id = uniqid();
        $token_field = '<input type="hidden" name="token_id" value="' . $token_id . '" />';
        $token = Kit::Token($token_id);

        Theme::Set('form_id', 'EditEventForm');
        Theme::Set('form_action', 'index.php?p=schedule&q=EditEvent');
        Theme::Set('form_meta', $token_field . $token . '<input type="hidden" id="EventID" name="EventID" value="' . $eventID . '" />');

        // Two tabs
        $tabs = array();
        $tabs[] = FormManager::AddTab('general', __('General'));
        $tabs[] = FormManager::AddTab('repeats', __('Repeats'));
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

        foreach ($this->user->DisplayGroupList(-1 /*IsDisplaySpecific*/) as $display) {

            // Can schedule with view, but no view permissions
            if ($scheduleWithView && $display['view'] != 1)
                continue;

            // Can't schedule with view, but no edit permissions
            if (!$scheduleWithView && $display['edit'] != 1)
                continue;

            $display['checked_text'] = (in_array($display['displaygroupid'], $displayGroupIds)) ? ' selected' : '';

            if ($display['isdisplayspecific'] == 1) {
                $displays[] = $display;
            }
            else {
                $groups[] = $display;
            }
        }

        $formFields['general'][] = FormManager::AddMultiCombo(
            'DisplayGroupIDs[]', 
            __('Display'), 
            $displayGroupIds,
            array('group' => $groups, 'display' => $displays),
            'displaygroupid',
            'displaygroup',
            __('Please select one or more displays / groups for this event to be shown on.'), 
            'd', '', true, '', '', '', $optionGroups, array(array('name' => 'data-live-search', 'value' => "true"), array('name' => 'data-selected-text-format', 'value' => "count > 4")));

        // Time controls
        $formFields['general'][] = FormManager::AddText('starttimeControl', __('Start Time'), DateManager::getLocalDate($fromDT), 
            __('Select the start time for this event'), 's', 'required');

        $formFields['general'][] = FormManager::AddText('endtimeControl', __('End Time'), DateManager::getLocalDate($toDT), 
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

        foreach($layouts as $layout) {

            if ($layout['islayoutspecific'] == 1) {
                $layoutOptions[] = array(
                        'id' => $layout['campaignid'],
                        'value' => $layout['campaign']
                    );
            }
            else {
                $campaignOptions[] = array(
                        'id' => $layout['campaignid'],
                        'value' => $layout['campaign']
                    );
            }
        }

        $formFields['general'][] = FormManager::AddCombo(
                    'CampaignID', 
                    __('Layout / Campaign'), 
                    $campaignId,
                    array('campaign' => $campaignOptions, 'layout' => $layoutOptions),
                    'id',
                    'value',
                    __('Please select a Layout or Campaign for this Event to show'), 
                    'l', '', true, '', '', '', $optionGroups);

        $formFields['general'][] = FormManager::AddNumber('DisplayOrder', __('Display Order'), $displayOrder, 
            __('Please select the order this event should appear in relation to others when there is more than one event scheduled'), 'o');

        $formFields['general'][] = FormManager::AddCheckbox('is_priority', __('Priority'), 
            $isPriority, __('Sets whether or not this event has priority. If set the event will be show in preference to other events.'), 
            'p');

        $formFields['repeats'][] = FormManager::AddCombo(
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

        $formFields['repeats'][] = FormManager::AddNumber('rec_detail', __('Repeat every'), $recDetail, 
            __('How often does this event repeat?'), 'o', '', 'repeat-control-group');

        $formFields['repeats'][] = FormManager::AddText('rec_rangeControl', __('Until'), ((($recToDT == 0) ? '' : DateManager::getLocalDate($recToDT))),
            __('When should this event stop repeating?'), 'u', '', 'repeat-control-group');
        
        $formFields['repeats'][] = FormManager::AddHidden('rec_range', DateManager::getLocalDate($recToDT, "Y-m-d H:i"));

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
        $response->Respond();
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
        
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $eventId = Kit::GetParam('EventID', _POST, _INT, 0);
        $campaignId = Kit::GetParam('CampaignID', _POST, _INT, 0);
        $fromDT = Kit::GetParam('starttime', _POST, _STRING);
        $toDT = Kit::GetParam('endtime', _POST, _STRING);
        $displayGroupIDs = Kit::GetParam('DisplayGroupIDs', _POST, _ARRAY);
        $isPriority = Kit::GetParam('is_priority', _POST, _CHECKBOX);

        $repeatType = Kit::GetParam('rec_type', _POST, _STRING);
        $repeatInterval = Kit::GetParam('rec_detail', _POST, _INT);
        $repeatToDt = Kit::GetParam('rec_range', _POST, _STRING);
        
        $displayOrder = Kit::GetParam('DisplayOrder', _POST, _INT);
        $isNextButton = Kit::GetParam('next', _GET, _BOOL, false);
        
        // Convert our ISO strings
        $fromDT = DateManager::getTimestampFromString($fromDT);
        $toDT = DateManager::getTimestampFromString($toDT);

        if ($repeatToDt != '')
            $repeatToDt = DateManager::getTimestampFromString($repeatToDt);

        Debug::Audit('Times received are: FromDt=' . $fromDT . '. ToDt=' . $toDT . '. RepeatToDt=' . $repeatToDt);

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
        if (($repeatToDt != '') && ($repeatToDt < (time()- 86400)))
            trigger_error(__("Your repeat until date is in the past. Cannot schedule events to repeat in to the past"), E_USER_ERROR);
        
        
        // Ready to do the edit 
        $scheduleObject = new Schedule($db);
        if (!$scheduleObject->Edit($eventId, $displayGroupIDs, $fromDT, $toDT, $campaignId, $repeatType, $repeatInterval, $repeatToDt, $isPriority, $this->user->userid, $displayOrder))
            trigger_error($scheduleObject->GetErrorMessage(), E_USER_ERROR);
        
        $response->SetFormSubmitResponse(__("The Event has been Modified."));
        $response->callBack = 'CallGenerateCalendar';
        $response->Respond();
    }
    
    /**
     * Shows the DeleteEvent form
     * @return 
     */
    function DeleteForm() {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        
        $eventID = Kit::GetParam('EventID', _GET, _INT, 0);
        
        if ($eventID == 0) 
            trigger_error(__('No event selected.'), E_USER_ERROR);
        
        Theme::Set('form_id', 'DeleteEventForm');
        Theme::Set('form_action', 'index.php?p=schedule&q=DeleteEvent');
        Theme::Set('form_meta', '<input type="hidden" name="EventID" value="' . $eventID . '" />');
        Theme::Set('form_fields', array(FormManager::AddMessage(__('Are you sure you want to delete this event from <b>all</b> displays? If you only want to delete this item from certain displays, please deselect the displays in the edit dialogue and click Save.'))));

        $response->SetFormRequestResponse(NULL, __('Delete Event.'), '480px', '240px');
        $response->AddButton(__('Help'), 'XiboHelpRender("' . HelpManager::Link('Schedule', 'Delete') . '")');
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#DeleteEventForm").submit()');
        $response->Respond();
    }
    
    /**
     * Deletes an Event from all displays
     * @return 
     */
    public function DeleteEvent()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        
        $eventID = Kit::GetParam('EventID', _POST, _INT, 0);
        
        if ($eventID == 0) 
            trigger_error(__('No event selected.'), E_USER_ERROR);
        
        // Create an object to use for the delete
        $scheduleObject = new Schedule($db);
        
        // Delete the entire schedule.
        if (!$scheduleObject->Delete($eventID)) 
        {
            trigger_error($scheduleObject->GetErrorMessage(), E_USER_ERROR);
        }
        
        $response->SetFormSubmitResponse(__("The Event has been Deleted."));
        $response->callBack = 'CallGenerateCalendar';
        $response->Respond();
    }
    
    /**
     * Is this event editable?
     * @return 
     * @param $eventDGIDs Object
     */
    private function IsEventEditable($eventDGIDs) {
        
        $scheduleWithView = (Config::GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes');
        
        // Work out if this event is editable or not. To do this we need to compare the permissions
        // of each display group this event is associated with
        foreach ($eventDGIDs as $dgID) {
            // Permissions for display group
            $auth = $this->user->DisplayGroupAuth($dgID, true);

            // Can schedule with view, but no view permissions
            if ($scheduleWithView && !$auth->view)
                return false;

            // Can't schedule with view, but no edit permissions
            if (!$scheduleWithView && !$auth->edit)
                return false;
        }
        
        return true;
    }

    public function ScheduleNowForm() {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $date = time();

        // We might have a layout id, or a display id
        $campaignId = Kit::GetParam('CampaignID', _GET, _INT, 0);
        $displayGroupIds = Kit::GetParam('displayGroupId', _GET, _ARRAY);
        
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

        foreach($layouts as $layout) {

            if ($layout['islayoutspecific'] == 1) {
                $layoutOptions[] = array(
                        'id' => $layout['campaignid'],
                        'value' => $layout['campaign']
                    );
            }
            else {
                $campaignOptions[] = array(
                        'id' => $layout['campaignid'],
                        'value' => $layout['campaign']
                    );
            }
        }

        $formFields[] = FormManager::AddCombo(
                    'CampaignID', 
                    __('Layout'), 
                    $campaignId,
                    array('campaign' => $campaignOptions, 'layout' => $layoutOptions),
                    'id',
                    'value',
                    __('Please select a Layout or Campaign for this Event to show'), 
                    'l', '', true, '', '', '', $optionGroups);

        $formFields[] = FormManager::AddText('hours', __('Hours'), NULL, 
            __('Hours this event should be scheduled for'), 'h', '');

        $formFields[] = FormManager::AddText('minutes', __('Minutes'), NULL, 
            __('Minutes this event should be scheduled for'), 'h', '');

        $formFields[] = FormManager::AddText('seconds', __('Seconds'), NULL, 
            __('Seconds this event should be scheduled for'), 'h', '');

        // List of Display Groups
        $optionGroups = array(
            array('id' => 'group', 'label' => __('Groups')),
            array('id' => 'display', 'label' => __('Displays'))
            );

        $groups = array();
        $displays = array();
        $scheduleWithView = (Config::GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'Yes');

        foreach ($this->user->DisplayGroupList(-1 /*IsDisplaySpecific*/) as $display) {

            // Can schedule with view, but no view permissions
            if ($scheduleWithView && $display['view'] != 1)
                continue;

            // Can't schedule with view, but no edit permissions
            if (!$scheduleWithView && $display['edit'] != 1)
                continue;

            $display['checked_text'] = (in_array($display['displaygroupid'], $displayGroupIds)) ? ' selected' : '';

            if ($display['isdisplayspecific'] == 1) {
                $displays[] = $display;
            }
            else {
                $groups[] = $display;
            }
        }

        $formFields[] = FormManager::AddMultiCombo(
                    'DisplayGroupIDs[]', 
                    __('Display'), 
                    $displayGroupIds,
                    array('group' => $groups, 'display' => $displays),
                    'displaygroupid',
                    'displaygroup',
                    __('Please select one or more displays / groups for this event to be shown on.'), 
                    'd', '', true, '', '', '', $optionGroups, array(array('name' => 'data-live-search', 'value' => "true"), array('name' => 'data-selected-text-format', 'value' => "count > 4")));

        $formFields[] = FormManager::AddNumber('DisplayOrder', __('Display Order'), 0, 
            __('Should this event have an order?'), 'o', '');

        $formFields[] = FormManager::AddCheckbox('is_priority', __('Priority?'), 
            NULL, __('Sets whether or not this event has priority. If set the event will be show in preference to other events.'), 
            'p');

        Theme::Set('form_fields', $formFields);

        $response->SetFormRequestResponse(NULL, __('Schedule Now'), '700px', '400px');
        $response->callBack = 'setupScheduleNowForm';
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=ScheduleNow')");
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#ScheduleNowForm").submit()');
        $response->Respond();
    }

    public function ScheduleNow() {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $campaignId = Kit::GetParam('CampaignID', _POST, _INT, 0);
        $displayGroupIds = Kit::GetParam('DisplayGroupIDs', _POST, _ARRAY);
        $isPriority = Kit::GetParam('is_priority', _POST, _CHECKBOX);
        $fromDt = time();

        $hours = Kit::GetParam('hours', _POST, _INT, 0);
        $minutes = Kit::GetParam('minutes', _POST, _INT, 0);
        $seconds = Kit::GetParam('seconds', _POST, _INT, 0);
        $duration = ($hours * 3600) + ($minutes * 60) + $seconds;
        $displayOrder = Kit::GetParam('DisplayOrder', _POST, _INT);

        // Validate
        if ($campaignId == 0)
            trigger_error(__('No layout selected'), E_USER_ERROR);

        if ($duration == 0)
            trigger_error(__('You must enter a duration'), E_USER_ERROR);

        // check that at least one display has been selected
        if ($displayGroupIds == '')
            trigger_error(__('No displays selected'), E_USER_ERROR);

        if ($fromDt < (time()- 86400))
            trigger_error(__('Your start time is in the past. Cannot schedule events in the past'), E_USER_ERROR);

        $toDt = $fromDt + $duration;

        // Ready to do the add
        $scheduleObject = new Schedule($db);

        if (!$scheduleObject->Add($displayGroupIds, $fromDt, $toDt, $campaignId, '', '', '', $isPriority, $this->user->userid, $displayOrder))
            trigger_error($scheduleObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('The Event has been Scheduled'));
        $response->Respond();
    }  
}
?>
