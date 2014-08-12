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

class scheduleDAO extends baseDAO 
{
    private $db;
    private $user;
    
    private $lastEventID;
    private $eventsList;

    /**
     * Constructor
     */
    function __construct(database $db, user $user) 
    {
        $this->db =& $db;
        $this->user =& $user;
        
        require_once('lib/data/schedule.data.class.php');
                
        return true;
    }

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
        Theme::Set('groups', $groups);
        Theme::Set('displays', $displays);

        // Render the Theme and output
        Theme::Render('schedule_page');
    }
    
    /**
     * Generates the calendar that we draw events on
     * @return 
     */
    function GenerateCalendar() {

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
            $SQL.= "       campaign.Campaign, ";
            $SQL.= "       GROUP_CONCAT(displaygroup.DisplayGroup) AS DisplayGroups ";
            $SQL.= "  FROM schedule_detail ";
            $SQL.= "  INNER JOIN schedule ON schedule_detail.EventID = schedule.EventID ";
            $SQL.= "  INNER JOIN campaign ON campaign.CampaignID = schedule.CampaignID ";
            $SQL.= "  INNER JOIN displaygroup ON displaygroup.DisplayGroupID = schedule_detail.DisplayGroupID ";
            $SQL.= " WHERE 1=1 ";
            $SQL.= "   AND schedule_detail.DisplayGroupID IN (:ids) ";

            // Events that fall inside the two dates
            $SQL.= "   AND schedule_detail.ToDT > :start ";
            $SQL.= "   AND schedule_detail.FromDT < :end ";

            // Grouping
            $SQL.= "GROUP BY schedule.EventID, ";
            $SQL.= "       schedule_detail.FromDT, ";
            $SQL.= "       schedule_detail.ToDT,";
            $SQL.= "       schedule.DisplayGroupIDs, ";
            $SQL.= "       schedule.is_priority, ";
            $SQL.= "       campaign.Campaign ";

            // Ordering
            $SQL.= " ORDER BY schedule_detail.FromDT DESC";

            Debug::LogEntry('audit', $SQL, get_class(), __FUNCTION__);
        
            $sth = $dbh->prepare($SQL);
            $sth->execute(array(
                    'start' => $start,
                    'end' => $end,
                    'ids' => implode(',', $displayGroupIds)
                ));

            $events = array();

            foreach ($sth->fetchAll() as $row) {

                // Event Permissions
                $editable = $this->IsEventEditable(explode(',', $row['DisplayGroupIDs']));

                // Event Title
                $title = sprintf(__('%s scheduled on %s'), Kit::ValidateParam($row['Campaign'], _STRING), Kit::ValidateParam($row['DisplayGroups'], _STRING));

                // Event URL
                $url = ($editable) ? sprintf('index.php?p=schedule&q=EditEventForm&EventID=%d', $row['EventID']) : '#';

                // Classes used to distinguish between events
                // "class": "event-warning","class": "event-success","class": "event-info","class": "event-inverse","class": "event-special","class": "event-important",
                $class = "event-success";

                // Is this event editable?
                if (!$editable)
                    $class = 'event-inverse';

                $events[] = array(
                    'id' => $row['EventID'],
                    'title' => $title,
                    'url' => $url,
                    'class' => 'XiboFormButton ' . $class,
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
    
    private function EventFormLayoutFilter($campaignId = '') {
        $msgName = __('Layout');
        
        // Default values?
        if (Kit::IsFilterPinned('scheduleEvent', 'LayoutFilter'))
        {
            $filterPinned = 'checked';
            $filterName = Session::Get('scheduleEvent', 'Name');
        }
        else 
        {
            $filterPinned = '';
            $filterName = '';
        }

        $pinTranslated = __('Pin?');

        $form = <<<HTML
        <div class="XiboFilterInner">     
            <form onsubmit="return false">
                <input type="hidden" name="p" value="schedule">
                <input type="hidden" name="q" value="EventFormLayout">
                <input type="hidden" name="CampaignID" value="$campaignId">
                <table>
                    <tr>
                        <td>$msgName</td>
                        <td><input type="text" name="name" value="$filterName"></td>
                        <td>
                            <label for="XiboFilterPinned">$pinTranslated</label>
                            <input id="XiboFilterPinned" name="XiboFilterPinned" type="checkbox" class="XiboFilter" $filterPinned />
                        </td>
                    </tr>
                </table>
            </form>
        </div>
HTML;
        
        $id = Kit::uniqueId();
        $pager = ResponseManager::Pager($id);

        $xiboGrid = <<<HTML
        <div class="XiboGrid" id="$id">
            <div class="XiboFilter">
                $form
            </div>
            <div class="XiboData"></div>
            $pager
        </div>
HTML;
        
        return $xiboGrid;
    }
    
    public function EventFormLayout()
    {
        $user =& $this->user;
        $response = new ResponseManager();

        // Layout filter?
        $layoutName = Kit::GetParam('name', _POST, _STRING, '');
        $campaignId = Kit::GetParam('CampaignID', _POST, _INT);
        setSession('scheduleEvent', 'LayoutFilter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
        setSession('scheduleEvent', 'Name', $layoutName);
        
        // Layout list
        $layouts = $user->CampaignList($layoutName, false /* isRetired */);
        
        // Show a list of layouts we have permission to jump to
        $output  = '<table class="table table-bordered">';
        $output .= '    <thead>';
        $output .= '    <tr>';
        $output .= '    <th>' . __('Name') . '</th>';
        $output .= '    <th>' . __('Type') . '</th>';
        $output .= '    </tr>';
        $output .= '    </thead>';
        $output .= '    <tbody>';

        $count = 0;
        $found = false;
        foreach($layouts as $layout)
        {
            if (!$layout['edit'] == 1)
                continue;

            // We have permission to edit this layout
            $output .= '<tr>';
            $output .= '    <td>' . $layout['campaign'] . '</td>';
            $output .= '    <td>' . (($layout['islayoutspecific'] == 1) ? __('Layout') : __('Campaign')) . '</td>';
            $output .= '    <td><input type="radio" name="CampaignID" value="' . $layout['campaignid'] . '" ' . (($layout['campaignid'] == $campaignId) ? 'checked' : '') . ' /></td>';
            $output .= '</tr>';
            
            if (!$found)
            {
                $count++;
                
                if ($layout['campaignid'] == $campaignId)
                    $found = true;
            }
        }
        
        if ($count > 0 && $found)
        {
            // Work out what page we should be on.
            $response->pageNumber = floor($count / 5);
        }

        $output .= '    </tbody>';
        $output .= '</table>';

        $response->SetGridResponse($output);
        $response->paging = true;
        $response->pageSize = 5;
        $response->Respond();
    }
    
    private function EventFormDisplayFilter($displayGroupIds)
    {
        $msgName = __('Display');
        
        // Default values?
        if (Kit::IsFilterPinned('scheduleEvent', 'EventFormDisplayFilter'))
        {
            $filterPinned = 'checked';
            $filterName = Session::Get('scheduleEvent', 'DisplayName');
        }
        else 
        {
            $filterPinned = '';
            $filterName = '';
        }

        $pinTranslated = __('Pin?');
        $checkAllTranslated = __('Check All');

        // Serialize the list of display group ids
        $displayGroupIdsSerialized = "";
        foreach ($displayGroupIds as $displayGroupId)
            $displayGroupIdsSerialized .= '<input type="hidden" name="DisplayGroupIDs[]" value="' . $displayGroupId . '">';

        $form = <<<HTML
        <div class="XiboFilterInner">     
            <div class="scheduleFormCheckAll pull-right"><label for"checkAll"><input type="checkbox" name="checkAll">$checkAllTranslated</label></div>
            <form onsubmit="return false">
                <input type="hidden" name="p" value="schedule">
                <input type="hidden" name="q" value="EventFormDisplay">
                $displayGroupIdsSerialized
                <table>
                    <tr>
                        <td>$msgName</td>
                        <td><input class="form-control" type="text" name="name" value="$filterName"></td>
                        <td>
                            <label for="XiboFilterPinned">$pinTranslated</label>
                            <input id="XiboFilterPinned" name="XiboFilterPinned" type="checkbox" class="XiboFilter" $filterPinned />
                        </td>
                    </tr>
                </table>
            </form>
        </div>
HTML;
        
        $id = Kit::uniqueId();
        $pager = ResponseManager::Pager($id);

        $xiboGrid = <<<HTML
        <div class="XiboGrid" id="$id">
            <div class="XiboFilter">
                $form
            </div>
            <div class="XiboData"></div>
            $pager
        </div>
HTML;
        
        return $xiboGrid;
    }
    
    public function EventFormDisplay()
    {
        $user =& $this->user;
        $response = new ResponseManager();

        // Filter
        $displayName = Kit::GetParam('name', _POST, _STRING, '');
        $displayGroupIds = Kit::GetParam('DisplayGroupIDs', _POST, _ARRAY);
        setSession('scheduleEvent', 'EventFormDisplayFilter', Kit::GetParam('XiboFilterPinned', _REQUEST, _CHECKBOX, 'off'));
        setSession('scheduleEvent', 'DisplayName', $displayName);
        
        // Layout list
        $displays = $user->DisplayGroupList(-1, $displayName);
        
        // Show a list of layouts we have permission to jump to
        $output = '<table class="table table-bordered">';
        $output .= '    <thead>';
        $output .= '    <tr>';
        $output .= '    <th>' . __('Name') . '</th>';
        $output .= '    <th>' . __('Type') . '</th>';
        $output .= '    <th data-sorter="false"></th>';
        $output .= '    </tr>';
        $output .= '    </thead>';
        $output .= '    <tbody>';

        foreach($displays as $display)
        {
            if ($display['edit'] != 1 && Config::GetSetting('SCHEDULE_WITH_VIEW_PERMISSION') == 'No')
                continue;

            // We have permission to edit this layout
            $output .= '<tr>';
            $output .= '    <td>' . $display['displaygroup'] . '</td>';
            $output .= '    <td>' . (($display['isdisplayspecific'] == 1) ? __('Display') : __('Group')) . '</td>';
            $output .= '    <td><input type="checkbox" name="DisplayGroupIDs[]" value="' . $display['displaygroupid'] . '" ' . ((in_array($display['displaygroupid'], $displayGroupIds) ? ' checked' : '')) . '/></td>';
            $output .= '</tr>';
        }

        $output .= '    </tbody>';
        $output .= '</table>';

        $response->SetGridResponse($output);
        $response->paging = true;
        $response->pageSize = 5;
        $response->callBack = 'displayGridCallback';
        $response->Respond();
    }
        
    /**
     * Shows a form to add an event
     *  will default to the current date if non is provided
     * @return 
     */
    function AddEventForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $date = Kit::GetParam('date', _GET, _INT, mktime(date('H'), 0, 0, date('m'), date('d'), date('Y')));
        $dateText = date("d/m/Y H:i", $date);
        $toDateText = date("d/m/Y H:i", $date + 86400);
        $displayGroupIds = Kit::GetParam('DisplayGroupIDs', _SESSION, _ARRAY);

        // Filter forms for selecting layouts and displays
        $layoutFilter = $this->EventFormLayoutFilter();
        $displayFilter = $this->EventFormDisplayFilter($displayGroupIds);

        $token_id = uniqid();
        $token_field = '<input type="hidden" name="token_id" value="' . $token_id . '" />';
        $token = Kit::Token($token_id);
        
        Theme::Set('form_id', 'AddEventForm');
        Theme::Set('form_action', 'index.php?p=schedule&q=AddEvent');
        Theme::Set('form_meta', $token_field . $token);

        // Filter forms
        Theme::Set('layout_filter', $layoutFilter);
        Theme::Set('display_filter', $displayFilter);

        Theme::Set('recurrence_field_list', array(
                array('id' => 'null', 'name' => __('None')),
                array('id' => 'Hour', 'name' => __('Hourly')),
                array('id' => 'Day', 'name' => __('Daily')),
                array('id' => 'Week', 'name' => __('Weekly')),
                array('id' => 'Month', 'name' => __('Monthly')),
                array('id' => 'Year', 'name' => __('Yearly'))
            ));

        $response->SetFormRequestResponse(Theme::RenderReturn('schedule_form_add_event'), __('Schedule Event'), '800px', '600px');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=Add')");
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Next'), '$("#AddEventForm").attr("action", $("#AddEventForm").attr("action") + "&next=1").submit()');
        $response->AddButton(__('Save'), '$("#AddEventForm").attr("action", $("#AddEventForm").attr("action") + "&next=0").submit()');
        $response->callBack = 'setupScheduleForm';
        $response->dialogClass = 'modal-big';
        $response->Respond();
    }
    
    /**
     * Shows a form to add an event
     *  will default to the current date if non is provided
     * @return 
     */
    function EditEventForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $eventID = Kit::GetParam('EventID', _GET, _INT, 0);

        if ($eventID == 0) 
            trigger_error('No event selected.', E_USER_ERROR);

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
        $recToDT = Kit::ValidateParam($row['recurrence_range'], _STRING);
        $campaignId = Kit::ValidateParam($row['CampaignID'], _STRING);
        $isPriority = (Kit::ValidateParam($row['is_priority'], _CHECKBOX) == 1) ? 'checked' : '';
        $displayOrder = Kit::ValidateParam($row['DisplayOrder'], _INT);

        $fromDtText = date("d/m/Y H:i", $fromDT);
        $toDtText = date("d/m/Y H:i", $toDT);
        $recToDtText = '';
        $recToTimeText = '';

        if ($recType != '')
        {
            $recToDtText = date("d/m/Y H:i", $recToDT);
        }

        // Check that we have permission to edit this event.
        if (!$this->IsEventEditable($displayGroupIds))
            trigger_error(__('You do not have permission to edit this event.'), E_USER_ERROR);
        
        // Filter forms for selecting layouts and displays
        $layoutFilter = $this->EventFormLayoutFilter($campaignId);
        $displayFilter = $this->EventFormDisplayFilter($displayGroupIds);

        $token_id = uniqid();
        $token_field = '<input type="hidden" name="token_id" value="' . $token_id . '" />';
        $token = Kit::Token($token_id);

        Theme::Set('form_id', 'EditEventForm');
        Theme::Set('form_action', 'index.php?p=schedule&q=EditEvent');
        Theme::Set('form_meta', $token_field . $token . '<input type="hidden" id="EventID" name="EventID" value="' . $eventID . '" />');

        // Filter forms
        Theme::Set('layout_filter', $layoutFilter);
        Theme::Set('display_filter', $displayFilter);

        // Values
        Theme::Set('starttime', $fromDtText);
        Theme::Set('endtime', $toDtText);
        Theme::Set('display_order', $displayOrder);
        Theme::Set('is_priority', $isPriority);

        Theme::Set('recurrence_field_list', array(
                array('id' => 'null', 'name' => __('None')),
                array('id' => 'Hour', 'name' => __('Hourly')),
                array('id' => 'Day', 'name' => __('Daily')),
                array('id' => 'Week', 'name' => __('Weekly')),
                array('id' => 'Month', 'name' => __('Monthly')),
                array('id' => 'Year', 'name' => __('Yearly'))
            ));
        Theme::Set('rec_type', $recType);
        Theme::Set('rec_detail', $recDetail);
        Theme::Set('rec_range', $recToDtText);
        
        $response->SetFormRequestResponse(Theme::RenderReturn('schedule_form_edit_event'), __('Edit Event'), '800px', '600px');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=Edit')");
        $response->AddButton(__('Delete'), 'XiboFormRender("index.php?p=schedule&q=DeleteForm&EventID=' . $eventID . '")');
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#EditEventForm").attr("action", $("#EditEventForm").attr("action") + "&next=0").submit()');
        $response->callBack = 'setupScheduleForm';
        $response->dialogClass = 'modal-big';
        $response->Respond();
    }
    
    /**
     * Add Event
     * @return 
     */
    public function AddEvent() 
    {
        // Check the token
        if (!Kit::CheckToken(Kit::GetParam('token_id', _POST, _STRING)))
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db                 =& $this->db;
        $user               =& $this->user;
        $response           = new ResponseManager();
        $datemanager        = new DateManager($db);

        $campaignId           = Kit::GetParam('CampaignID', _POST, _INT, 0);
        $fromDT             = Kit::GetParam('iso_starttime', _POST, _STRING);
        $toDT               = Kit::GetParam('iso_endtime', _POST, _STRING);
        $displayGroupIDs    = Kit::GetParam('DisplayGroupIDs', _POST, _ARRAY);
        $isPriority         = Kit::GetParam('is_priority', _POST, _CHECKBOX);

        $rec_type           = Kit::GetParam('rec_type', _POST, _STRING);
        $rec_detail         = Kit::GetParam('rec_detail', _POST, _INT);
        $recToDT            = Kit::GetParam('iso_rec_range', _POST, _STRING);
        
        $userid             = Kit::GetParam('userid', _SESSION, _INT);
        $displayOrder = Kit::GetParam('DisplayOrder', _POST, _INT);

        $isNextButton = Kit::GetParam('next', _GET, _BOOL, false);
        
        Debug::LogEntry('audit', 'From DT: ' . $fromDT);
        Debug::LogEntry('audit', 'To DT: ' . $toDT);
        
        $fromDT = $datemanager->GetDateFromString($fromDT);
        $toDT = $datemanager->GetDateFromString($toDT);

        if ($recToDT != '')
            $recToDT = $datemanager->GetDateFromString($recToDT);
        
        // Validate layout
        if ($campaignId == 0)
        {
            trigger_error(__("No layout selected"), E_USER_ERROR);
        }
        
        // check that at least one display has been selected
        if ($displayGroupIDs == '') 
        {
            trigger_error(__("No displays selected"), E_USER_ERROR);
        }
        
        // validate the dates
        if ($toDT < $fromDT) 
        {
            trigger_error(__('Can not have an end time earlier than your start time'), E_USER_ERROR);   
        }
        if ($fromDT < (time()- 86400)) 
        {
            trigger_error(__("Your start time is in the past. Cannot schedule events in the past"), E_USER_ERROR);
        }

        // Check recurrence dT is in the future or empty
        if (($recToDT != '') && ($recToDT < (time()- 86400))) 
        {
            trigger_error(__("Your repeat until date is in the past. Cannot schedule events to repeat in to the past"), E_USER_ERROR);
        }
        
        // Ready to do the add 
        $scheduleObject = new Schedule($db);
        
        if (!$scheduleObject->Add($displayGroupIDs, $fromDT, $toDT, $campaignId, $rec_type, $rec_detail, $recToDT, $isPriority, $userid, $displayOrder))
        {
            trigger_error($scheduleObject->GetErrorMessage(), E_USER_ERROR);
        }
        
        $response->SetFormSubmitResponse(__("The Event has been Added."));
        $response->callBack = 'CallGenerateCalendar';
                if ($isNextButton)
                    $response->keepOpen = true;
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
        
        $db                 =& $this->db;
        $user               =& $this->user;
        $response           = new ResponseManager();
        $datemanager        = new DateManager($db);

        $eventID            = Kit::GetParam('EventID', _POST, _INT, 0);
        $eventDetailID      = Kit::GetParam('EventDetailID', _POST, _INT, 0);
        $campaignId         = Kit::GetParam('CampaignID', _POST, _INT, 0);
        $fromDT             = Kit::GetParam('iso_starttime', _POST, _STRING);
        $toDT               = Kit::GetParam('iso_endtime', _POST, _STRING);
        $displayGroupIDs    = Kit::GetParam('DisplayGroupIDs', _POST, _ARRAY);
        $isPriority         = Kit::GetParam('is_priority', _POST, _CHECKBOX);

        $rec_type           = Kit::GetParam('rec_type', _POST, _STRING);
        $rec_detail         = Kit::GetParam('rec_detail', _POST, _INT);
        $recToDT            = Kit::GetParam('iso_rec_range', _POST, _STRING);
        
        $userid             = Kit::GetParam('userid', _SESSION, _INT);
                $displayOrder = Kit::GetParam('DisplayOrder', _POST, _INT);
        
        if ($eventID == 0) 
            trigger_error('No event selected.', E_USER_ERROR);
        
        Debug::LogEntry('audit', 'From DT: ' . $fromDT);
        Debug::LogEntry('audit', 'To DT: ' . $toDT);
        
        $fromDT = $datemanager->GetDateFromString($fromDT);
        $toDT = $datemanager->GetDateFromString($toDT);

        if ($recToDT != '')
            $recToDT = $datemanager->GetDateFromString($recToDT);

        // Validate layout
        if ($campaignId == 0)
        {
            trigger_error(__("No layout selected"), E_USER_ERROR);
        }
        
        // check that at least one display has been selected
        if ($displayGroupIDs == '') 
        {
            trigger_error(__("No displays selected"), E_USER_ERROR);
        }
        
        // validate the dates
        if ($toDT < $fromDT) 
        {
            trigger_error(__('Can not have an end time earlier than your start time'), E_USER_ERROR);   
        }
        
        // Check recurrance dT is in the future or empty
        if (($recToDT != '') && ($recToDT < (time()-86400))) 
        {
            trigger_error(__("Your repeat until date is in the past. Cannot schedule events to repeat in to the past"), E_USER_ERROR);
        }
        
        // Ready to do the edit 
        $scheduleObject = new Schedule($db);
        
        if (!$scheduleObject->Edit($eventID, $eventDetailID, $displayGroupIDs, $fromDT, $toDT, $campaignId, $rec_type, $rec_detail, $recToDT, $isPriority, $userid, $displayOrder))
        {
            trigger_error($scheduleObject->GetErrorMessage(), E_USER_ERROR);
        }
        
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
        $eventDetailID = Kit::GetParam('EventDetailID', _GET, _INT, 0);
        
        if ($eventID == 0) 
            trigger_error(__('No event selected.'), E_USER_ERROR);
        
        Theme::Set('form_id', 'DeleteEventForm');
        Theme::Set('form_action', 'index.php?p=schedule&q=DeleteEvent');
        Theme::Set('form_meta', '<input type="hidden" name="EventID" value="' . $eventID . '" /><input type="hidden" name="EventDetailID" value="' . $eventDetailID . '" />');
        Theme::Set('form_fields', array(FormManager::AddMessage(__('Are you sure you want to delete this event from <b>all</b> displays? If you only want to delete this item from certain displays, please deselect the displays in the edit dialogue and click Save.'))));

        $response->SetFormRequestResponse(NULL, __('Delete Event.'), '480px', '240px');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=Delete')");
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
        $eventDetailID = Kit::GetParam('EventDetailID', _POST, _INT, 0);
        
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
        $dateText = date("d/m/Y", $date);

        // We might have a layout id, or a display id
        $campaignId = Kit::GetParam('CampaignID', _GET, _INT, 0);
        $displayGroupIds = Kit::GetParam('displayGroupId', _GET, _ARRAY);
        
        // Show a form for adding a display profile.
        Theme::Set('form_class', 'XiboScheduleForm');
        Theme::Set('form_id', 'ScheduleNowForm');
        Theme::Set('form_action', 'index.php?p=schedule&q=ScheduleNow');

        $formFields = array();
        $formFields[] = FormManager::AddText('hours', __('Hours'), NULL, 
            __('Hours this event should be scheduled for'), 'h', '');

        $formFields[] = FormManager::AddText('minutes', __('Minutes'), NULL, 
            __('Minutes this event should be scheduled for'), 'h', '');

        $formFields[] = FormManager::AddText('seconds', __('Seconds'), NULL, 
            __('Seconds this event should be scheduled for'), 'h', '');

        $formFields[] = FormManager::AddCombo(
                    'CampaignID', 
                    __('Campaign / Layout'), 
                    Kit::GetParam('CampaignID', _GET, _INT, 0),
                    $user->CampaignList(),
                    'campaignid',
                    'campaign',
                    __('Select which Layout or Campaign this event will show.'), 
                    'c');

        $formFields[] = FormManager::AddNumber('DisplayOrder', __('Display Order'), 0, 
            __('Should this event have an order?'), 'o', '');

        $formFields[] = FormManager::AddCheckbox('is_priority', __('Priority?'), 
            NULL, __('Sets whether or not this event has priority. If set the event will be show in preference to other events.'), 
            'p');

        Theme::Set('form_fields', $formFields);
        Theme::Set('append', $this->EventFormDisplayFilter($displayGroupIds));

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
        $datemanager = new DateManager($db);

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
