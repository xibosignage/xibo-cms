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

class scheduleDAO 
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
        $db     =& $this->db;
        
        // Configure the theme
        $id = uniqid();
        Theme::Set('id', $id);
        Theme::Set('form_meta', '<input type="hidden" name="p" value="schedule"><input type="hidden" name="q" value="DisplayList">');
        Theme::Set('filter_id', 'XiboFilterPinned' . uniqid('filter'));
        Theme::Set('pager', ResponseManager::Pager($id));

        // Render the Theme and output
        Theme::Render('schedule_page');
    }
    
    /**
     * Shows a list of displays
     */
    public function DisplayList()
    {
        $user =& $this->user;

        $response = new ResponseManager();
        $displayGroupIDs = Kit::GetParam('DisplayGroupIDs', _SESSION, _ARRAY);

        $filter_name = Kit::GetParam('filter_name', _POST, _STRING);

        // Build 2 lists
        $groups = array();
        $displays = array();

        foreach ($user->DisplayGroupList(-1 /*IsDisplaySpecific*/, $filter_name) as $display) {

            $display['checked_text'] = (in_array($display['displaygroupid'], $displayGroupIDs)) ? 'checked' : '';

            if ($display['isdisplayspecific'] == 1) {
                $displays[] = $display;
            }
            else {
                $groups[] = $display;
            }
        }

        Theme::Set('id', 'DisplayList');
        Theme::Set('group_list_items', $groups);
        Theme::Set('display_list_items', $displays);

        $output = Theme::RenderReturn('schedule_page_display_list');

        $response->SetGridResponse($output);
        $response->callBack = 'DisplayListRender';
        $response->Respond();
    }
    
    /**
     * Generates the calendar that we draw events on
     * @return 
     */
    function GenerateCalendar()
    {
        $view               = Kit::GetParam('view', _POST, _WORD, 'month');
        $displayGroupIDs    = Kit::GetParam('DisplayGroupIDs', _GET, _ARRAY);
        
        // if we have some displaygroupids then add them to the session info so we can default everything else.
        Session::Set('DisplayGroupIDs', $displayGroupIDs);
        
        if ($view == 'month')
        {
            $this->GenerateMonth();
        }
        else if ($view == 'day')
        {
            $this->GenerateDay();
        }
        else
        {
            trigger_error(__('The Calendar doesnt support this view.'), E_USER_ERROR);
        }
        
        return true;
    }
    
    /**
     * Generates the calendar in month view
     * @return 
     */
    function GenerateMonth() 
    {
            $db                 =& $this->db;
            $response           = new ResponseManager();

            $displayGroupIDs = Kit::GetParam('DisplayGroupIDs', _GET, _ARRAY, Kit::GetParam('DisplayGroupIDs', _SESSION, _ARRAY));
            $date = DateManager::GetDateFromString(Kit::GetParam('date', _POST, _STRING));

            // Extract the month and the year
            $month = date('m', $date);
            $year = date('Y', $date);

            Debug::LogEntry('audit', 'Month: ' . $month . ' Year: ' . $year . ' [' . $date . ']' . ' [Raw Date:' . Kit::GetParam('date', _POST, _STRING) . ']');

            // Get the first day of the month
            $month_start    = mktime(0, 0, 0, $month, 1, $year);

            // Get friendly month name
            $month_name     = date('M', $month_start);

            // Figure out which day of the week the month starts on.
            $month_start_day    = date('D', $month_start);

            switch($month_start_day)
            {
                case "Sun": $offset = 0; break;
                case "Mon": $offset = 1; break;
                case "Tue": $offset = 2; break;
                case "Wed": $offset = 3; break;
                case "Thu": $offset = 4; break;
                case "Fri": $offset = 5; break;
                case "Sat": $offset = 6; break;
            }

            // determine how many days are in the last month.
            if($month == 1)
            {
               $num_days_last = cal_days_in_month(0, 12, ($year -1));
            }
            else
            {
               $num_days_last = cal_days_in_month(0, ($month -1), $year);
            }

            // determine how many days are in the current month.
            $num_days_current = cal_days_in_month(0, $month, $year);

            // Build an array for the current days in the month
            for($i = 1; $i <= $num_days_current; $i++)
            {
                $num_days_array[] = $i;
            }

            // Build an array for the number of days in last month
            for($i = 1; $i <= $num_days_last; $i++)
            {
                $num_days_last_array[] = $i;
            }

            // If the $offset from the starting day of the week happens to be Sunday, $offset would be 0,
            // so don't need an offset correction.
            if($offset > 0)
            {
                $offset_correction  = array_slice($num_days_last_array, -$offset, $offset);
                $new_count      = array_merge($offset_correction, $num_days_array);
                $offset_count       = count($offset_correction);
            }
            else
            {
                    // The else statement is to prevent building the $offset array.
                $offset_count           = 0;
                $new_count      = $num_days_array;
            }

            // count how many days we have with the two previous arrays merged together
            $current_num = count($new_count);

            // Since we will have 5 HTML table rows (TR) with 7 table data entries (TD) we need to fill in 35 TDs
            // so, we will have to figure out how many days to appened to the end of the final array to make it 35 days.
            if($current_num > 35)
            {
               $num_weeks = 6;
               $outset = (42 - $current_num);
            }
            elseif($current_num < 35)
            {
               $num_weeks = 5;
               $outset = (35 - $current_num);
            }

            if($current_num == 35)
            {
               $num_weeks = 5;
               $outset = 0;
            }

            // Outset Correction
            for($i = 1; $i <= $outset; $i++)
            {
               $new_count[] = $i;
            }

            // Now let's "chunk" the $all_days array into weeks. Each week has 7 days so we will array_chunk it into 7 days.
            $weeks      = array_chunk($new_count, 7);

            // Build the heading portion of the calendar table
            if($num_weeks == 5)
            {
                $calendar  = '<div class="gridContainer">';
            }
            else
            {
                $calendar  = '<div class="gridContainer6Weeks">';
            }
            $calendar .= ' <div class="calendarContainer">';
            $calendar .= '  <div class="eventsContainer">';
            $calendar .= '  <table class="WeekDays">';
            $calendar .= '   <tr>';
            $calendar .= '    <th>S</th><th>M</th><th>T</th><th>W</th><th>T</th><th>F</th><th>S</th>';
            $calendar .= '   </tr>';
            $calendar .= '  </table>';

            if($num_weeks == 5)
            {
                $calendar .= '  <div class="monthContainer">';
            }
            else
            {
                $calendar  .= '  <div class="monthContainer6Weeks">';
            }

            // Now we break each key of the array  into a week and create a new table row for each
            // week with the days of that week in the table data
            $i      = 0;
            $weekNo = 0;

            // Load this months events into an array
            $monthEvents    = $this->GetEventsForMonth($month, $year, $displayGroupIDs);

            Debug::LogEntry('audit', 'Number of weeks to render: ' . count($weeks), '', 'GenerateMonth');

            // Use the number of weeks in this month to work out how much space each week should get, and where to position it
            $monthHeightOffsetPerWeek = 100 / count($weeks);

            // Render a week at a time
            foreach($weeks AS $week)
            {
                // Count of the available days in this week.
                $count      = 7;

                $events1    = '';
                $events2    = '';
                $events3    = '';
                $events4    = '';
                $weekRow    = '';
                $weekGrid   = '';
                $this->lastEventID[0] = 0;
                $this->lastEventID[1] = 0;
                $this->lastEventID[2] = 0;
                $monthTop   = $weekNo * $monthHeightOffsetPerWeek;

                foreach($week as $d)
                {
                    // This is each day
                    $currentDay = mktime(0, 0, 0, $month, $d, $year);

                    if($i < $offset_count)
                    {
                        $weekRow  .= "<td class=\"DayTitle nonmonthdays\">$d</td>";
                        $weekGrid .= "<td class=\"DayGrid nonmonthdays\"></td>";

                        $events1  .= '<td class="nonmonthdays" colspan="1"></td>';
                        $events2  .= '<td class="nonmonthdays" colspan="1"></td>';
                        $events3  .= '<td class="nonmonthdays" colspan="1"></td>';
                        $events4  .= '<td class="nonmonthdays" colspan="1"></td>';
                    }
                    else if(($i >= $offset_count) && ($i < ($num_weeks * 7) - $outset))
                    {
                        // Link for Heading and Cell
                        $linkClass  = 'days';
                        $link       = "index.php?p=schedule&q=AddEventForm&date=$currentDay";
                        $dayLink    = '<a class="day_label XiboFormButton" href="' . $link . '">' . $d . '</a>';

                        if(mktime(0,0,0,date("m"),date("d"),date("Y")) == mktime(0, 0, 0, $month, $d, $year))
                        {
                            $linkClass = 'today';
                        }

                        $weekRow    .= '<td class="DayTitle">' . $dayLink . '</td>';
                        $weekGrid   .= '<td class="DayGrid XiboFormButton" href="' . $link . '"></td>';

                        // These days belong in this month, so see if we have any events for day
                        $events1    .= $this->BuildEventTdForDay($monthEvents, 0, $d, $count);
                        $events2    .= $this->BuildEventTdForDay($monthEvents, 1, $d, $count);
                        $events3    .= $this->BuildEventTdForDay($monthEvents, 2, $d, $count);

                        // Are there any extra events to fit into this day that didnt have a space
                        if (isset($monthEvents[3][$d]))
                        {
                            $events4    .= '<td colspan="1"><a href="index.php?p=schedule&q=DayViewFilter&date=' . $currentDay . '" class="XiboFormButton">' . sprintf(__('+ %d more'), $monthEvents[3][$d]) . '</a></td>';
                        }
                        else
                        {
                            $events4    .= '<td colspan="1"></td>';
                        }
                    }
                    else if($outset > 0)
                    {
                        Debug::LogEntry('audit', 'Outset is ' . $outset . ' and i is ' . $i, '', 'GenerateMonth');

                        // Days that do not belond in this month
                        if(($i >= ($num_weeks * 7) - $outset))
                        {
                            $weekRow  .= "<td class=\"DayTitle nonmonthdays\">$d</td>";
                            $weekGrid .= "<td class=\"DayGrid nonmonthdays\"></td>";
                            $events1  .= '<td class="nonmonthdays" colspan="1"></td>';
                            $events2  .= '<td class="nonmonthdays" colspan="1"></td>';
                            $events3  .= '<td class="nonmonthdays" colspan="1"></td>';
                            $events4  .= '<td class="nonmonthdays" colspan="1"></td>';
                        }
                    }

                    $i++;

                    // Decrement the Available Days
                    $count--;
                }

                $weekNo++;

                $calendar .= '   <div class="MonthRow" style="top:' . $monthTop . '%; height:' . $monthHeightOffsetPerWeek . '%">';
                $calendar .= '    <table class="WeekRow" cellspacing="0" cellpadding="0">';
                $calendar .= '     <tr>';
                $calendar .= $weekGrid;
                $calendar .= '     </tr>';
                $calendar .= '    </table>';
                $calendar .= '    <table class="EventsRow" cellspacing="0" cellpadding="0">';
                $calendar .= '     <tr>';
                $calendar .= $weekRow;
                $calendar .= '     </tr>';
                $calendar .= '     <tr>';
                $calendar .= $events1;
                $calendar .= '     </tr>';
                $calendar .= '     <tr>';
                $calendar .= $events2;
                $calendar .= '     </tr>';
                $calendar .= '     <tr>';
                $calendar .= $events3;
                $calendar .= '     </tr>';
                $calendar .= '     <tr>';
                $calendar .= $events4;
                $calendar .= '     </tr>';
                $calendar .= '    </table>';
                $calendar .= '   </div>';
            }

            // Close the calendar table
            $calendar .= '  </div>';
            $calendar .= '  </div>';
            $calendar .= ' </table>';
            $calendar .= '</div>';
            $calendar .= '</div>';

            $response->SetGridResponse($calendar);
            $response->Respond();
    }

        

    public function DayViewFilter()
    {
        $db                 =& $this->db;
        $response           = new ResponseManager();
        $date = Kit::GetParam('date', _GET, _INT, 0);
        $dateString = date('Y-m-d', $date);
        
        if ($date == 0)
            trigger_error(__('You must supply a day'), E_USER_ERROR);

        $filterForm = <<<END
            <div class="FilterDiv" id="DayViewFilter">
                <form onsubmit="return false">
                    <input type="hidden" name="p" value="schedule">
                    <input type="hidden" name="q" value="DayView">
                    <input type="hidden" name="date" value="$date">
                </form>
            </div>
END;

        $id = uniqid();
        $pager = ResponseManager::Pager($id);

        $xiboGrid = <<<HTML
        <div class="XiboGrid" id="$id">
            <div class="XiboFilter">
                $filterForm
            </div>
            $pager
            <div class="XiboData">

            </div>
        </div>
HTML;
        
        $response->SetFormRequestResponse($xiboGrid, sprintf(__('Events for %s'), $dateString), '850', '450');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=General')");
        $response->AddButton(__('Delete All'), 'XiboFormRender("index.php?p=schedule&q=DeleteDayForm&date=' . $date . '")');
        $response->AddButton(__('Close'), 'XiboDialogClose()');
        $response->Respond();
    }
        
    /**
     * Day Hover
     * @return
     */
    public function DayView()
    {
        $response = new ResponseManager();

        $displayGroupIDs = Kit::GetParam('DisplayGroupIDs', _POST, _ARRAY, Kit::GetParam('DisplayGroupIDs', _SESSION, _ARRAY));
        $date = Kit::GetParam('date', _POST, _INT, 0);
        $output = '';

        if ($date == 0)
            trigger_error(__('You must supply a day'));

        // Query for all events that sit in this day
        $events = $this->GetEventsForDay($date, $displayGroupIDs);

        $output = '    <table class="table">';
        $output .= '        <thead>';
        $output .= '            <th>' . __('Display / Group') . '</th>';
        $output .= '            <th>' . __('Name') . '</th>';
        $output .= '            <th>' . __('Layout') . '</th>';
        $output .= '            <th>' . __('Start') . '</th>';
        $output .= '            <th>' . __('Finish') . '</th>';
        $output .= '            <th>' . __('Action') . '</th>';
        $output .= '        </thead>';
        $output .= '        <tbody>';

        foreach($events as $event)
        {
            // We just want a list.
            $editLink = $event->editPermission == true ? sprintf('class="XiboFormButton" href="%s"', $event->layoutUri) : 'class="UnEditableEvent"';
            $delLink = $event->editPermission == true ? sprintf('class="XiboFormButton" href="%s"', $event->deleteUri) : 'class="UnEditableEvent"';

            $output .= '<tr>';
            $output .= '<td>' . (($event->isdisplayspecific == 1) ? __('Display') : __('Group')) . '</td>';
            $output .= '<td>' . $event->displayGroup . '</td>';
            $output .= '<td>' . $event->layout . '</td>';
            $output .= '<td>' . date('Y-m-d H:i:s', $event->fromDT) . '</td>';
            $output .= '<td>' . date('Y-m-d H:i:s', $event->toDT) . '</td>';
            $output .= sprintf('<td><button %s>%s</button><button %s>%s</button></td>', $editLink, __('Edit'), $delLink, __('Delete'));
            $output .= '</tr>';
        }

        $output .= '        </tbody>';
        $output .= '    </table>';
        
        $response->SetGridResponse($output);
        $response->focusInFirstInput = false;
        $response->Respond();
    }
    
    /**
     * BuildEventTdForDay
     * @return 
     * @param $monthEvents Object
     * @param $index Object
     * @param $d Object
     * @param $count Object
     */
    private function BuildEventTdForDay($monthEvents, $index, $d, $count)
    {
            $events         = '';
            $calEvent[0]    = 'CalEvent1';
            $calEvent[1]    = 'CalEvent2';
            $calEvent[2]    = 'CalEvent3';

            if (isset($monthEvents[$index][$d]))
            {
                // Is this the same event as one we have already added
                $event  = $monthEvents[$index][$d];

                if ($this->lastEventID[$index] != $event->eventDetailID)
                {
                    // We should only go up to the max number of days left in the week.
                    $spanningDays   = $event->spanningDays;

                    // Now we know if this is a single day event or not we need to set up some styles
                    $tdClass    = $spanningDays == 1 ? 'Event' : 'LongEvent';
                    $timePrefix = $spanningDays == 1 ? date("H:i", $event->fromDT) : '';
                    $editLink   = $event->editPermission == true ? sprintf('class="XiboFormButton" href="%s"',$event->layoutUri) : 'class="UnEditableEvent"';

                    $layoutUri  = sprintf('<div class="%s %s" title="Display Group: %s"><a %s title="%s">%s %s</a></div>', $tdClass, $calEvent[$index], $event->displayGroup, $editLink, $event->layout, $timePrefix, $event->layout);

                    // We should subtract any days ahead of the start date from the spanning days
                    $spanningDays   = $d - $event->startDayNo > 0 ? $spanningDays - ($d - $event->startDayNo) : $spanningDays;
                    $spanningDays   = $spanningDays > $count ? $count : $spanningDays;

                    $events     = sprintf('<td colspan="%d">%s</td>', $spanningDays, $layoutUri);
                }

                // Make sure we dont try to add this one again
                $this->lastEventID[$index] = $event->eventDetailID;
            }
            else
            {
                // Put in an empty TD for this event
                $events = '<td colspan="1"></td>';
            }

            return $events;
    }
    
    /**
     * Generates a single day of the schedule
     * @return 
     */
    public function GenerateDay()
    {
        
    }
    
    /**
     * Gets all the events for a months. Returns an array of days in the month / event ID's
     * @return 
     * @param $month Object
     * @param $year Object
     * @param $displayGroupIDs Object
     */
    private function GetEventsForMonth($month, $year, $displayGroupIDs)
    {
            $db         =& $this->db;
            $user       =& $this->user;
            $events         = array();
            $this->eventsList   = array();
            $thisMonth      = mktime(0, 0, 0, $month, 1, $year);
            $nextMonth      = mktime(0, 0, 0, $month + 1, 1, $year);
            $daysInMonth    = cal_days_in_month(0, $month, $year);

            $displayGroups  = implode(',', $displayGroupIDs);

            if ($displayGroups == '') return;

            // Query for all events between the dates
            $SQL = "";
            $SQL.= "SELECT schedule_detail.schedule_detailID, ";
            $SQL.= "       schedule_detail.FromDT, ";
            $SQL.= "       schedule_detail.ToDT,";
            $SQL.= "       GREATEST(schedule_detail.FromDT, $thisMonth) AS AdjustedFromDT,";
            $SQL.= "       LEAST(schedule_detail.ToDT, $nextMonth) AS AdjustedToDT,";
            $SQL.= "       campaign.Campaign, ";
            $SQL.= "       schedule_detail.userid, ";
            $SQL.= "       schedule_detail.is_priority, ";
            $SQL.= "       schedule_detail.EventID, ";
            $SQL.= "       schedule_detail.ToDT - schedule_detail.FromDT AS duration, ";
            $SQL.= "       (LEAST(schedule_detail.ToDT, $nextMonth)) - (GREATEST(schedule_detail.FromDT, $thisMonth)) AS AdjustedDuration, ";
            $SQL.= "       displaygroup.DisplayGroup, ";
            $SQL.= "       displaygroup.DisplayGroupID, ";
        $SQL.= "       schedule.DisplayGroupIDs ";
            $SQL.= "  FROM schedule_detail ";
            $SQL.= "  INNER JOIN campaign ON campaign.CampaignID = schedule_detail.CampaignID ";
            $SQL.= "  INNER JOIN displaygroup ON displaygroup.DisplayGroupID = schedule_detail.DisplayGroupID ";
            $SQL.= "  INNER JOIN schedule ON schedule_detail.EventID = schedule.EventID ";
            $SQL.= " WHERE 1=1 ";
            $SQL.= sprintf("   AND schedule_detail.DisplayGroupID IN (%s) ", $db->escape_string($displayGroups));

            // Events that fall inside the two dates
            $SQL.= "   AND schedule_detail.ToDT > $thisMonth ";
            $SQL.= "   AND schedule_detail.FromDT < $nextMonth ";

            //Ordering
            $SQL.= " ORDER BY schedule_detail.ToDT - schedule_detail.FromDT DESC, 2,3";
        
            Debug::LogEntry('audit', $SQL);

            if (!$result = $db->query($SQL))
            {
                    trigger_error($db->error());
                    trigger_error(__('Error getting events for date.'), E_USER_ERROR);
            }

            // Number of events
            Debug::LogEntry('audit', 'Number of events: ' . $db->num_rows($result));

            while($row = $db->get_assoc_row($result))
            {
                $eventDetailID  = Kit::ValidateParam($row['schedule_detailID'], _INT);
                $eventID    = Kit::ValidateParam($row['EventID'], _INT);
                $fromDT     = Kit::ValidateParam($row['AdjustedFromDT'], _INT);
                $toDT       = Kit::ValidateParam($row['AdjustedToDT'], _INT);
                $layout     = Kit::ValidateParam($row['Campaign'], _STRING);
                $displayGroup   = Kit::ValidateParam($row['DisplayGroup'], _STRING);
                $displayGroupID = Kit::ValidateParam($row['DisplayGroupID'], _INT);
                $eventDGIDs = Kit::ValidateParam($row['DisplayGroupIDs'], _STRING);
                $eventDGIDs     = explode(',', $eventDGIDs);

                // Make sure this user can view this display group
                $auth = $user->DisplayGroupAuth($displayGroupID, true);
                if (!$auth->view) 
                    continue;

                // How many days does this event span?
                $spanningDays   = ($toDT - $fromDT) / (60 * 60 * 24);
                $spanningDays   = $spanningDays < 1 ? 1 : $spanningDays;
                $spanningDays   = $spanningDays > 31 ? 31 : $spanningDays;

                $dayNo      = (int) date('d', $fromDT);
                $layoutUri      = sprintf('index.php?p=schedule&q=EditEventForm&EventID=%d&EventDetailID=%d"', $eventID, $eventDetailID);

                Debug::LogEntry('audit', sprintf('Creating Event Object for ScheduleDetailID %d. The DayNo for this event is %d', $eventDetailID, $dayNo));

                // Create a new Event from these details
                $event          = new Event();
                $event->eventID     = $eventID;
                $event->eventDetailID   = $eventDetailID;
                $event->fromDT      = $fromDT;
                $event->toDT        = $toDT;
                $event->layout      = $layout;
                $event->displayGroup    = $displayGroup;
                $event->layoutUri   = $layoutUri;
                $event->spanningDays    = ceil($spanningDays);
                $event->startDayNo  = $dayNo;
                $event->editPermission  = $this->IsEventEditable($eventDGIDs);
                $this->eventsList[]     = $event;

                // Store this event in the lowest slot it will fit in.
                // only look from the start day of this event
                $located    = false;
                $locatedOn  = 0;

                if (!isset($events[$locatedOn][$dayNo]))
                {
                    // Start day empty on event row 1
                    $located    = true;

                    // Look to see if there are enough free slots to cover the event duration
                    for ($i = $dayNo; $i <= $spanningDays; $i++)
                    {
                        if (isset($events[$locatedOn][$i]))
                        {
                            $located    = false;
                            break;
                        }
                    }

                    // If we are located by this point, that means we can fill in these blocks
                    if ($located)
                    {
                        Debug::LogEntry('audit', sprintf('Located ScheduleDetailID %d in Position %d', $eventDetailID, $locatedOn));

                        for ($i = $dayNo; $i < $dayNo + $spanningDays; $i++)
                        {
                                $events[$locatedOn][$i] = $event;
                        }
                    }
                }

                $locatedOn  = 1;

                if (!$located && !isset($events[$locatedOn][$dayNo]))
                {
                    // Start day empty on event row 2
                    $located    = true;

                    // Look to see if there are enough free slots to cover the event duration
                    for ($i = $dayNo; $i <= $spanningDays; $i++)
                    {
                        if (isset($events[$locatedOn][$i]))
                        {
                            $located    = false;
                            break;
                        }
                    }

                    // If we are located by this point, that means we can fill in these blocks
                    if ($located)
                    {
                        Debug::LogEntry('audit', sprintf('Located ScheduleDetailID %d in Position %d', $eventDetailID, $locatedOn));

                        for ($i = $dayNo; $i < $dayNo + $spanningDays; $i++)
                        {
                            $events[$locatedOn][$i] = $event;
                        }
                    }
                }

                $locatedOn  = 2;

                if (!$located && !isset($events[$locatedOn][$dayNo]))
                {
                    // Start day empty on event row 3
                    $located    = true;

                    // Look to see if there are enough free slots to cover the event duration
                    for ($i = $dayNo; $i <= $spanningDays; $i++)
                    {
                        if (isset($events[$locatedOn][$i]))
                        {
                            $located    = false;
                            break;
                        }
                    }

                    // If we are located by this point, that means we can fill in these blocks
                    if ($located)
                    {
                        Debug::LogEntry('audit', sprintf('Located ScheduleDetailID %d in Position %d', $eventDetailID, $locatedOn));

                        for ($i = $dayNo; $i < $dayNo + $spanningDays; $i++)
                        {
                            $events[$locatedOn][$i] = $event;
                        }
                    }
                }

                if (!$located)
                {
                    // Record a +1 event for this day
                    if (!isset($events[3][$dayNo]))
                        $events[3][$dayNo] = 0;
                        
                    $events[3][$dayNo] = $events[3][$dayNo] + 1;

                    Debug::LogEntry('audit', sprintf('No space for event with start day no %d and spanning days %d', $dayNo, $spanningDays));
                }
            }

            //Debug::LogEntry('audit', 'Built Month Array');
            //Debug::LogEntry('audit', var_export($events, true));

            return $events;
    }
    
    /**
     * Gets all the events for a week for the given displaygroups
     * @return 
     * @param $date Timestamp The starting day of the week
     * @param $displayGroupIDs Object
     */
    private function GetEventsForWeek($date, $currentWeekDayNo, $displayGroupIDs)
    {
        $db             =& $this->db;
        $user           =& $this->user;
        $events         = '';
        $nextWeek       = $date + (60 * 60 * 24 * 7);
        
        $displayGroups  = implode(',', $displayGroupIDs);
        
        if ($displayGroups == '') return;
        
        // Query for all events between the dates
        $SQL = "";
        $SQL.= "SELECT schedule_detail.schedule_detailID, ";
        $SQL.= "       schedule_detail.FromDT, ";
        $SQL.= "       schedule_detail.ToDT,";
        $SQL.= "       campaign.Campaign, ";
        $SQL.= "       schedule_detail.userid, ";
        $SQL.= "       schedule_detail.is_priority, ";
        $SQL.= "       schedule_detail.EventID ";
        $SQL.= "  FROM schedule_detail ";
        $SQL.= "  INNER JOIN campaign ON campaign.CampaignID = schedule_detail.CampaignID ";
        $SQL.= " WHERE 1=1 ";
        $SQL.= sprintf("   AND schedule_detail.DisplayGroupID IN (%s) ", $db->escape_string($displayGroups));
        
        // Events that fall inside the two dates
        $SQL.= "   AND schedule_detail.FromDT > $date ";
        $SQL.= "   AND schedule_detail.FromDT <= $nextWeek ";
        
        //Ordering
        $SQL.= " ORDER BY 2,3"; 
        
        Debug::LogEntry('audit', $SQL);
        
        if (!$result = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting events for date.'), E_USER_ERROR);
        }

        // Number of events
        Debug::LogEntry('audit', 'Number of events: ' . $db->num_rows($result));
        
        // Define some colors:
        $color[1]   = 'CalEvent1';
        $color[2]   = 'CalEvent2';
        $color[3]   = 'CalEvent3';
        
        $count      = 1;
        $rows       = array('', '', '', '');
        $day        = 1;
        
        while($row = $db->get_assoc_row($result))
        {
            if ($count > 3) $count = 1;
            
            $eventDetailID  = Kit::ValidateParam($row['schedule_detailID'], _INT);
            $eventID        = Kit::ValidateParam($row['EventID'], _INT);
            $fromDT         = Kit::ValidateParam($row['FromDT'], _INT);
            $toDT           = Kit::ValidateParam($row['ToDT'], _INT);
            $layout         = Kit::ValidateParam($row['Campaign'], _STRING);
            $layout         = sprintf('<a class="XiboFormButton" href="index.php?p=schedule&q=EditEventForm&EventID=%d&EventDetailID=%d" title="%s">%s</a>', $eventID, $eventDetailID, __('Edit Event'), $layout);
            
            // How many days does this event span?
            $spanningDays   = ($toDT - $fromDT) / (60 * 60 * 24);
            $spanningDays   = $spanningDays < 1 ? 1 : $spanningDays;
            
            $dayNo          = ($fromDT - $date) / (60 * 60 * 24);
            $dayNo          = $dayNo < 1 ? 1 : $dayNo;
            
            // Fill in the days with no events?
            
            $rows[$count]   .= '<td colspan="' . $spanningDays . '"><div class="Event ' . $color[$count] . '">' . $layout . '</div></td>';
            
            $count++;
        }
        
        $events .= '<tr>' . $rows[1] . '</tr>';
        $events .= '<tr>' . $rows[2] . '</tr>';
        $events .= '<tr>' . $rows[3] . '</tr>';
        
        return $events;
    }

        /**
         * GetEventsForDay
         * returns an array of events for the provided date.
         * @param date $date
         * @param array $displayGroupIDs
         */
        private function GetEventsForDay($date, $displayGroupIDs)
        {
            $db         =& $this->db;
            $user       =& $this->user;
            $events         = array();
            $fromDt     = $date;
            $toDt       = $date + (60 * 60 * 24);

            $displayGroups  = implode(',', $displayGroupIDs);

            if ($displayGroups == '') return;

            // Query for all events between the dates
            $SQL = "";
            $SQL.= "SELECT schedule_detail.schedule_detailID, ";
            $SQL.= "       schedule_detail.FromDT, ";
            $SQL.= "       schedule_detail.ToDT,";
            $SQL.= "       GREATEST(schedule_detail.FromDT, $fromDt) AS AdjustedFromDT,";
            $SQL.= "       LEAST(schedule_detail.ToDT, $toDt) AS AdjustedToDT,";
            $SQL.= "       campaign.Campaign, ";
            $SQL.= "       schedule_detail.userid, ";
            $SQL.= "       schedule_detail.is_priority, ";
            $SQL.= "       schedule_detail.EventID, ";
            $SQL.= "       schedule_detail.ToDT - schedule_detail.FromDT AS duration, ";
            $SQL.= "       (GREATEST(schedule_detail.ToDT, $toDt)) - (LEAST(schedule_detail.FromDT, $fromDt)) AS AdjustedDuration, ";
            $SQL.= "       displaygroup.DisplayGroup, ";
            $SQL.= "       displaygroup.DisplayGroupID, ";
        $SQL.= "       schedule.DisplayGroupIDs, ";
        $SQL.= "       displaygroup.IsDisplaySpecific ";
            $SQL.= "  FROM schedule_detail ";
            $SQL.= "  INNER JOIN campaign ON campaign.CampaignID = schedule_detail.CampaignID ";


            $SQL.= "  INNER JOIN displaygroup ON displaygroup.DisplayGroupID = schedule_detail.DisplayGroupID ";
            $SQL.= "  INNER JOIN schedule ON schedule_detail.EventID = schedule.EventID ";
            $SQL.= " WHERE 1=1 ";
            $SQL.= sprintf("   AND schedule_detail.DisplayGroupID IN (%s) ", $db->escape_string($displayGroups));

            // Events that fall inside the two dates
            $SQL.= "   AND schedule_detail.ToDT > $fromDt ";
            $SQL.= "   AND schedule_detail.FromDT <= $toDt ";

            //Ordering
            $SQL .= " ORDER BY schedule_detail.FromDT ASC, campaign.Campaign ASC";

            Debug::LogEntry('audit', $SQL);

            if (!$result = $db->query($SQL))
            {
                    trigger_error($db->error());
                    trigger_error(__('Error getting events for date.'), E_USER_ERROR);
            }

            // Number of events
            Debug::LogEntry('audit', 'Number of events: ' . $db->num_rows($result));

            while($row = $db->get_assoc_row($result))
            {
                $eventDetailID  = Kit::ValidateParam($row['schedule_detailID'], _INT);
                $eventID    = Kit::ValidateParam($row['EventID'], _INT);
                $fromDT     = Kit::ValidateParam($row['AdjustedFromDT'], _INT);
                $toDT       = Kit::ValidateParam($row['AdjustedToDT'], _INT);
                $layout     = Kit::ValidateParam($row['Campaign'], _STRING);
                $displayGroup   = Kit::ValidateParam($row['DisplayGroup'], _STRING);
                $displayGroupID = Kit::ValidateParam($row['DisplayGroupID'], _INT);
                $eventDGIDs = Kit::ValidateParam($row['DisplayGroupIDs'], _STRING);
                $eventDGIDs     = explode(',', $eventDGIDs);

                if (!$user->DisplayGroupAuth($displayGroupID)) continue;

                // How many days does this event span?
                $spanningDays   = ($toDT - $fromDT) / (60 * 60 * 24);
                $spanningDays   = $spanningDays < 1 ? 1 : $spanningDays;

                $dayNo      = (int) date('d', $fromDT);
                $layoutUri  = sprintf('index.php?p=schedule&q=EditEventForm&EventID=%d&EventDetailID=%d"', $eventID, $eventDetailID);
                $deleteUri  = sprintf('index.php?p=schedule&q=DeleteForm&EventID=%d&EventDetailID=%d"', $eventID, $eventDetailID);

                Debug::LogEntry('audit', sprintf('Creating Event Object for ScheduleDetailID %d. The DayNo for this event is %d', $eventDetailID, $dayNo));

                // Create a new Event from these details
                $event          = new Event();
                $event->eventID     = $eventID;
                $event->eventDetailID   = $eventDetailID;
                $event->fromDT      = $fromDT;
                $event->toDT        = $toDT;
                $event->layout      = $layout;
                $event->displayGroup    = $displayGroup;
                $event->layoutUri   = $layoutUri;
                $event->deleteUri       = $deleteUri;
                $event->spanningDays    = ceil($spanningDays);
                $event->startDayNo  = $dayNo;
                $event->editPermission  = $this->IsEventEditable($eventDGIDs);
                $event->isdisplayspecific = Kit::ValidateParam($row['IsDisplaySpecific'], _INT);
                $events[]               = $event;
            }

            Debug::LogEntry('audit', 'Built Day Array');
            Debug::LogEntry('audit', var_export($events, true));

            return $events;
        }
    
    /**
     * Gets all the events starting on a date for the given displaygroups
     * @return 
     * @param $date Object
     * @param $displayGroupIDs Object
     */
    private function GetEventsStartingOnDay($date, $currentWeekDayNo, $displayGroupIDs)
    {
        $db             =& $this->db;
        $user           =& $this->user;
        $events         = '';
        $nextDay        = $date + (60 * 60 * 24);
        
        $displayGroups  = implode(',', $displayGroupIDs);
        
        if ($displayGroups == '') return;
        
        // Query for all events between the dates
        $SQL = "";
        $SQL.= "SELECT schedule_detail.schedule_detailID, ";
        $SQL.= "       schedule_detail.FromDT, ";
        $SQL.= "       schedule_detail.ToDT,";
        $SQL.= "       campaign.Campaign, ";
        $SQL.= "       schedule_detail.userid, ";
        $SQL.= "       schedule_detail.is_priority, ";
        $SQL.= "       schedule_detail.EventID ";
        $SQL.= "  FROM schedule_detail ";
        $SQL.= "  INNER JOIN campaign ON campaign.CampaignID = schedule_detail.CampaignID ";
        $SQL.= " WHERE 1=1 ";
        $SQL.= sprintf("   AND schedule_detail.DisplayGroupID IN (%s) ", $db->escape_string($displayGroups));
        
        // Events that fall inside the two dates
        $SQL.= "   AND schedule_detail.FromDT > $date ";
        $SQL.= "   AND schedule_detail.FromDT <= $nextDay ";
        
        //Ordering
        $SQL.= " ORDER BY 2,3"; 
        
        Debug::LogEntry('audit', $SQL);
        
        if (!$result = $db->query($SQL))
        {
            trigger_error($db->error());
            trigger_error(__('Error getting events for date.'), E_USER_ERROR);
        }

        // Number of events
        Debug::LogEntry('audit', 'Number of events: ' . $db->num_rows($result));
        
        // Define some colors:
        $color[1] = 'CalEvent1';
        $color[2] = 'CalEvent2';
        $color[3] = 'CalEvent3';
        
        $count = 1;
        
        while($row = $db->get_assoc_row($result))
        {
            if ($count > 3) $count = 1;
            
            $top        = 20 * $count;
            
            $eventDetailID  = Kit::ValidateParam($row['schedule_detailID'], _INT);
            $eventID        = Kit::ValidateParam($row['EventID'], _INT);
            $fromDT         = Kit::ValidateParam($row['FromDT'], _INT);
            $toDT           = Kit::ValidateParam($row['ToDT'], _INT);
            $layout         = Kit::ValidateParam($row['campaign'], _STRING);
            $layout         = sprintf('<a class="XiboFormButton" href="index.php?p=schedule&q=EditEventForm&EventID=%d&EventDetailID=%d" title="%s">%s</a>', $eventID, $eventDetailID, __('Edit Event'), $layout);
            
            if($currentWeekDayNo == 1) $events .= '<tr>';
            
            $events         .= '<td><div class="Event ' . $color[$count] . '">' . $layout . '</div></td>';
            
            if($currentWeekDayNo == 7) $events .= '</tr>';
    
            $count++;
        }
        
        // Did we add any?
        if ($db->num_rows($result) == 0)
        {
            if($currentWeekDayNo == 1) $events .= '<tr>';
            $events .= '<td></td>';
            if($currentWeekDayNo == 7) $events .= '</tr>';
        }
        
        return $events;
    }
    
    
    
    /**
     * Outputs an unordered list of displays optionally with a form
     * @return 
     * @param $outputForm Object
     */
    private function UnorderedListofDisplays($outputForm, $displayGroupIDs)
    {
        $db                 =& $this->db;
        $user               =& $this->user;
        $output             = '';
        $name               = Kit::GetParam('name', _POST, _STRING);
        
        // Get a list of display groups
        $SQL  = "SELECT displaygroup.DisplayGroupID, displaygroup.DisplayGroup, IsDisplaySpecific ";
        $SQL .= "  FROM displaygroup ";
        if ($name != '')
        {
            $SQL .= sprintf(" WHERE displaygroup.DisplayGroup LIKE '%%%s%%' ", $db->escape_string($name));
        }
        $SQL .= " ORDER BY IsDisplaySpecific, displaygroup.DisplayGroup ";
        
        Debug::LogEntry('audit', $SQL, 'Schedule', 'UnorderedListofDisplays');


        if(!($results = $db->query($SQL))) 
        {
            trigger_error($db->error());
            trigger_error(__("Can not list Display Groups"), E_USER_ERROR);
        }
        
        if ($db->num_rows($results) == 0)
            trigger_error(__('No Display Groups'), E_USER_ERROR);
            
        if ($outputForm) $output .= '<form id="DisplayList" class="DisplayListForm">';
        $output         .= __('Groups');
        $output     .= '<ul class="DisplayList">';
        $nested     = false;
        
        while($row = $db->get_assoc_row($results))
        {
            $displayGroupID     = Kit::ValidateParam($row['DisplayGroupID'], _INT);
            $isDisplaySpecific  = Kit::ValidateParam($row['IsDisplaySpecific'], _INT);
            $displayGroup       = Kit::ValidateParam($row['DisplayGroup'], _STRING);
            $checked            = (in_array($displayGroupID, $displayGroupIDs)) ? 'checked' : '';
            
            // Determine if we are authed against this group.
            $auth = $this->user->DisplayGroupAuth($displayGroupID, true);

                        if (!$auth->view)
                            continue;
            
            // Do we need to nest yet? We only nest display specific groups
            if ($isDisplaySpecific == 1 && !$nested)
            {
                // Start a new UL to display these
                $output .= '</ul>' . __('Displays') . '<br/><ul class="DisplayList">';
                
                $nested = true;
            }
            
            $output .= '<li>';
            $output .= '<label class="checkbox">' . $displayGroup . '<input type="checkbox" name="DisplayGroupIDs[]" value="' . $displayGroupID . '" ' . $checked . '/></label>';
            $output .= '</li>';
        }
        
        if ($nested) $output .= '  </ul></li>';
        $output .= '</ul>';
        if ($outputForm) $output .= '</form>';
        
        return $output;
    }
    
    private function EventFormLayoutFilter($campaignId = '')
    {
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
        $eventDetailID = Kit::GetParam('EventDetailID', _GET, _INT, 0);

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
        $SQL.= "       schedule_detail.DisplayOrder ";
        $SQL.= "  FROM schedule ";
        $SQL.= "  INNER JOIN schedule_detail ON schedule.EventID = schedule_detail.EventID ";
        $SQL.= " WHERE 1=1 ";
        $SQL.= sprintf("   AND schedule.EventID = %d", $eventID);
        $SQL.= sprintf("   AND schedule_detail.schedule_detailID = %d", $eventDetailID);
        
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
        Theme::Set('form_meta', $token_field . $token . '<input type="hidden" id="EventID" name="EventID" value="' . $eventID . '" /><input type="hidden" id="EventDetailID" name="EventDetailID" value="' . $eventDetailID . '" />');

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

        // Check recurrance dT is in the future or empty
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
    function DeleteForm() 
    {
        $db                 =& $this->db;
        $user               =& $this->user;
        $response           = new ResponseManager();
        
        $eventID            = Kit::GetParam('EventID', _GET, _INT, 0);
        $eventDetailID      = Kit::GetParam('EventDetailID', _GET, _INT, 0);
        
        if ($eventID == 0) 
            trigger_error(__('No event selected.'), E_USER_ERROR);
        
        $strQuestion = __('Are you sure you want to delete this event from <b>all</b> displays?');
        $strAdvice = __('If you only want to delete this item from certain displays, please deselect the displays in the edit dialogue and click Save.');
        $token = Kit::Token();

        $form = <<<END
        <form id="DeleteEventForm" class="XiboForm" action="index.php?p=schedule&q=DeleteEvent">
            $token
            <input type="hidden" name="EventID" value="$eventID" />
            <input type="hidden" name="EventDetailID" value="$eventDetailID" />
            <table>
                <tr>
                    <td>$strQuestion</td>
                </tr>
                <tr>
                    <td>$strAdvice</td>
                </tr>
            </table>    
        </form>
END;

        $response->SetFormRequestResponse($form, __('Delete Event.'), '480px', '240px');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=Delete')");
        $response->AddButton(__('No'), 'XiboDialogClose()');
        $response->AddButton(__('Yes'), '$("#DeleteEventForm").submit()');
        $response->callBack = 'setupScheduleForm';
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
        
        $db                 =& $this->db;
        $user               =& $this->user;
        $response           = new ResponseManager();
        
        $eventID            = Kit::GetParam('EventID', _POST, _INT, 0);
        $eventDetailID      = Kit::GetParam('EventDetailID', _POST, _INT, 0);
        
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
    private function IsEventEditable($eventDGIDs)
    {
        $db             =& $this->db;
        $user           =& $this->user;
        
        // Work out if this event is editable or not. To do this we need to compare the permissions
        // of each display group this event is associated with
        foreach ($eventDGIDs as $dgID)
        {
            if (!$user->DisplayGroupAuth($dgID))
            {
                return false;
            }
        }
        
        return true;
    }

    public function ScheduleNowForm()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $date = time();
        $dateText = date("d/m/Y", $date);

        // We might have a layout id, or a display id
        $campaignId = Kit::GetParam('CampaignID', _GET, _INT, 0);
        $displayGroupIds = Kit::GetParam('displayGroupId', _GET, _ARRAY);

        // Layout list
        $layouts = $user->CampaignList();
        $layoutList = Kit::SelectList('CampaignID', $layouts, 'campaignid', 'campaign', $campaignId);
        
        $outputForm = false;
        $displayList = $this->UnorderedListofDisplays($outputForm, $displayGroupIds);

        $token = Kit::Token();

        Theme::Set('form_id', 'ScheduleNowForm');
        Theme::Set('form_action', 'index.php?p=schedule&q=ScheduleNow');
        Theme::Set('form_meta', $token);

        // Filter forms
        Theme::Set('display_list', $displayList);
        Theme::Set('layout_list', $layoutList);


        $response->SetFormRequestResponse(Theme::RenderReturn('schedule_form_schedule_now'), __('Schedule Now'), '700px', '400px');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=ScheduleNow')");
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#ScheduleNowForm").submit()');
        $response->Respond();
    }

    public function ScheduleNow()
    {
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
    
    /**
    * Shows the DeleteEvent form
    * @return 
    */
   function DeleteDayForm() 
   {
        $response = new ResponseManager();
        $date = Kit::GetParam('date', _GET, _INT, 0);
        $dateString = date('Y-m-d', $date);
        
        if ($date == 0)
            trigger_error (__('Day not selected'), E_USER_ERROR);

        $strQuestion = __('Are you sure you want to delete all events that intersect this day from <b>all</b> displays?');
        $strAdvice = __('This action cannot be undone.');
        $token = Kit::Token();

        $form = <<<END
<form id="DeleteDayForm" class="XiboForm" action="index.php?p=schedule&q=DeleteDay">
    $token
    <input type="hidden" name="date" value="$date">
    <table>
        <tr>
            <td>$strQuestion</td>
        </tr>
        <tr>
            <td>$strAdvice</td>
        </tr>
    </table>    
</form>
END;

        $response->SetFormRequestResponse($form, sprintf(__('Delete %s'), $dateString), '480px', '240px');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=Delete')");
        $response->AddButton(__('No'), 'XiboFormRender("index.php?p=schedule&q=DayViewFilter&date=' . $date . '")');
        $response->AddButton(__('Yes'), '$("#DeleteDayForm").submit()');
        $response->Respond();
    }
    
    /**
    * Deletes an Event from all displays
    * @return 
    */
    public function DeleteDay()
    {
        // Check the token
        if (!Kit::CheckToken())
            trigger_error(__('Sorry the form has expired. Please refresh.'), E_USER_ERROR);
        
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();

        $displayGroupIds = Kit::GetParam('DisplayGroupIDs', _POST, _ARRAY, Kit::GetParam('DisplayGroupIDs', _SESSION, _ARRAY));
        $date = Kit::GetParam('date', _POST, _INT, 0);
        $dateString = date('Y-m-d', $date);
        
        if ($date == 0)
            trigger_error (__('Day not selected'), E_USER_ERROR);
        
        $events = $this->GetEventsForDay($date, $displayGroupIds);
        
        // Create an object to use for the delete
        $scheduleObject = new Schedule($db);
        
        foreach($events as $event)
        {
            if ($event->editPermission)
            {
                // Delete the entire schedule.
                if (!$scheduleObject->Delete($event->eventID)) 
                    trigger_error($scheduleObject->GetErrorMessage(), E_USER_ERROR);
            }
        }
        
        $response->SetFormSubmitResponse(sprintf(__('All events for %s have been deleted'), $dateString));
        $response->callBack = 'CallGenerateCalendar';
        $response->Respond();
    }    
}
?>
