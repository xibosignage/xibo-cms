<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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
	 * @return 
	 * @param $db Object
	 */
    function __construct(database $db, user $user) 
	{
		$this->db 			=& $db;
		$this->user 		=& $user;
		
		require_once('lib/data/schedule.data.class.php');
				
		return true;
    }
    
    function on_page_load() 
	{
    	return '';
	}
	
	function echo_page_heading() 
	{
		echo 'Schedule';
		return true;
	}
	
	function displayPage() 
	{
		$db 	=& $this->db;
		$user 	=& $this->user;
		
		include_once("template/pages/schedule_view.php");
		
		return false;
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

            $displayGroupIDs	= Kit::GetParam('DisplayGroupIDs', _GET, _ARRAY, Kit::GetParam('DisplayGroupIDs', _SESSION, _ARRAY));
            $year		= Kit::GetParam('year', _POST, _INT, date('Y', time()));
            $month		= Kit::GetParam('month', _POST, _INT, date('m', time()));
            $day		= Kit::GetParam('day', _POST, _INT, date('d', time()));
            $date 		= mktime(0, 0, 0, $month, $day, $year);

            // Get the first day of the month
            $month_start 	= mktime(0, 0, 0, $month, 1, $year);

            // Get friendly month name
            $month_name 	= date('M', $month_start);

            // Figure out which day of the week the month starts on.
            $month_start_day 	= date('D', $month_start);

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
                $offset_correction	= array_slice($num_days_last_array, -$offset, $offset);
                $new_count		= array_merge($offset_correction, $num_days_array);
                $offset_count		= count($offset_correction);
            }
            else
            {
                    // The else statement is to prevent building the $offset array.
                $offset_count           = 0;
                $new_count		= $num_days_array;
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
            $weeks 		= array_chunk($new_count, 7);

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
            $i 		= 0;
            $weekNo	= 0;

            // Load this months events into an array
            $monthEvents	= $this->GetEventsForMonth($month, $year, $displayGroupIDs);

            Debug::LogEntry($db, 'audit', 'Number of weeks to render: ' . count($weeks), '', 'GenerateMonth');

            // Use the number of weeks in this month to work out how much space each week should get, and where to position it
            $monthHeightOffsetPerWeek = 100 / count($weeks);

            // Render a week at a time
            foreach($weeks AS $week)
            {
                // Count of the available days in this week.
                $count		= 7;

                $events1	= '';
                $events2	= '';
                $events3	= '';
                $events4	= '';
                $weekRow 	= '';
                $weekGrid 	= '';
                $this->lastEventID[0] = 0;
                $this->lastEventID[1] = 0;
                $this->lastEventID[2] = 0;
                $monthTop	= $weekNo * $monthHeightOffsetPerWeek;

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
                        $linkClass	= 'days';
                        $link		= "index.php?p=schedule&q=AddEventForm&date=$currentDay";
                        $dayLink  	= '<a class="day_label XiboFormButton" href="' . $link . '">' . $d . '</a>';

                        if(mktime(0,0,0,date("m"),date("d"),date("Y")) == mktime(0, 0, 0, $month, $d, $year))
                        {
                            $linkClass = 'today';
                        }

                        $weekRow 	.= '<td class="DayTitle">' . $dayLink . '</td>';
                        $weekGrid 	.= '<td class="DayGrid XiboFormButton" href="' . $link . '"></td>';

                        // These days belong in this month, so see if we have any events for day
                        $events1	.= $this->BuildEventTdForDay($monthEvents, 0, $d, $count);
                        $events2	.= $this->BuildEventTdForDay($monthEvents, 1, $d, $count);
                        $events3	.= $this->BuildEventTdForDay($monthEvents, 2, $d, $count);

                        // Are there any extra events to fit into this day that didnt have a space
                        if (isset($monthEvents[3][$d]))
                        {
                            $events4	.= '<td colspan="1"><a href="index.php?p=schedule&q=DayHover&date=' . $currentDay . '" class="XiboFormButton">' . sprintf(__('+ %d more'), $monthEvents[3][$d]) . '</a></td>';
                        }
                        else
                        {
                            $events4	.= '<td colspan="1"></td>';
                        }
                    }
                    else if($outset > 0)
                    {
                        Debug::LogEntry($db, 'audit', 'Outset is ' . $outset . ' and i is ' . $i, '', 'GenerateMonth');

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

        /**
         * Day Hover
         * @return
         */
        public function DayHover()
        {
            $db                 =& $this->db;
            $response           = new ResponseManager();

            $displayGroupIDs	= Kit::GetParam('DisplayGroupIDs', _GET, _ARRAY, Kit::GetParam('DisplayGroupIDs', _SESSION, _ARRAY));
            $date		= Kit::GetParam('date', _GET, _INT, 0);
            $output             = '';

            if ($date == 0)
                trigger_error(__('You must supply a day'));

            // Query for all events that sit in this day
            $events = $this->GetEventsForDay($date, $displayGroupIDs);

            $output .= '<div class="info_table">';
            $output .= '    <table style="width:100%">';
            $output .= '        <thead>';
            $output .= sprintf('            <th>%s</th>', __('Start'));
            $output .= sprintf('            <th>%s</th>', __('Finish'));
            $output .= sprintf('            <th>%s</th>', __('Layout'));
            $output .= '            <th></th>';
            $output .= '        </thead>';
            $output .= '        <tbody>';

            foreach($events as $event)
            {
                // We just want a list.
                $editLink = $event->editPermission == true ? sprintf('class="XiboFormButton" href="%s"', $event->layoutUri) : 'class="UnEditableEvent"';

                $output .= '<tr>';
                $output .= '<td>' . date('Y-m-d H:i:s', $event->fromDT) . '</td>';
                $output .= '<td>' . date('Y-m-d H:i:s', $event->toDT) . '</td>';
                $output .= '<td>' . $event->layout . '</td>';
                $output .= sprintf('<td><button %s>%s</button></td>', $editLink, __('Edit'));
                $output .= '</tr>';
            }

            $output .= '        </tbody>';
            $output .= '    </table>';
            $output .= '</div>';

            $response->SetFormRequestResponse($output, __('Events for Day'), '650', '450');
            $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=General')");
            $response->AddButton(__('Close'), 'XiboDialogClose()');
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
            $events 		= '';
            $calEvent[0]	= 'CalEvent1';
            $calEvent[1]	= 'CalEvent2';
            $calEvent[2]	= 'CalEvent3';

            if (isset($monthEvents[$index][$d]))
            {
                // Is this the same event as one we have already added
                $event	= $monthEvents[$index][$d];

                if ($this->lastEventID[$index] != $event->eventDetailID)
                {
                    // We should only go up to the max number of days left in the week.
                    $spanningDays 	= $event->spanningDays;

                    // Now we know if this is a single day event or not we need to set up some styles
                    $tdClass    = $spanningDays == 1 ? 'Event' : 'LongEvent';
                    $timePrefix	= $spanningDays == 1 ? date("H:i", $event->fromDT) : '';
                    $editLink	= $event->editPermission == true ? sprintf('class="XiboFormButton" href="%s"',$event->layoutUri) : 'class="UnEditableEvent"';

                    $layoutUri	= sprintf('<div class="%s %s" title="Display Group: %s"><a %s title="%s">%s %s</a></div>', $tdClass, $calEvent[$index], $event->displayGroup, $editLink, $event->layout, $timePrefix, $event->layout);

                    // We should subtract any days ahead of the start date from the spanning days
                    $spanningDays 	= $d - $event->startDayNo > 0 ? $spanningDays - ($d - $event->startDayNo) : $spanningDays;
                    $spanningDays 	= $spanningDays > $count ? $count : $spanningDays;

                    $events 	= sprintf('<td colspan="%d">%s</td>', $spanningDays, $layoutUri);
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
            $db 		=& $this->db;
            $user		=& $this->user;
            $events 		= array();
            $this->eventsList   = array();
            $thisMonth		= mktime(0, 0, 0, $month, 1, $year);
            $nextMonth		= mktime(0, 0, 0, $month + 1, 1, $year);
            $daysInMonth	= cal_days_in_month(0, $month, $year);

            $displayGroups	= implode(',', $displayGroupIDs);

            if ($displayGroups == '') return;

            // Query for all events between the dates
            $SQL = "";
            $SQL.= "SELECT schedule_detail.schedule_detailID, ";
            $SQL.= "       schedule_detail.FromDT, ";
            $SQL.= "       schedule_detail.ToDT,";
            $SQL.= "       GREATEST(schedule_detail.FromDT, $thisMonth) AS AdjustedFromDT,";
            $SQL.= "       LEAST(schedule_detail.ToDT, $nextMonth) AS AdjustedToDT,";
            $SQL.= "       layout.layout, ";
            $SQL.= "       schedule_detail.userid, ";
            $SQL.= "       schedule_detail.is_priority, ";
            $SQL.= "       schedule_detail.EventID, ";
            $SQL.= "       schedule_detail.ToDT - schedule_detail.FromDT AS duration, ";
            $SQL.= "       (LEAST(schedule_detail.ToDT, $nextMonth)) - (GREATEST(schedule_detail.FromDT, $thisMonth)) AS AdjustedDuration, ";
            $SQL.= "       displaygroup.DisplayGroup, ";
            $SQL.= "       displaygroup.DisplayGroupID, ";
	    $SQL.= "       schedule.DisplayGroupIDs ";
            $SQL.= "  FROM schedule_detail ";
            $SQL.= "  INNER JOIN layout ON layout.layoutID = schedule_detail.layoutID ";
            $SQL.= "  INNER JOIN displaygroup ON displaygroup.DisplayGroupID = schedule_detail.DisplayGroupID ";
            $SQL.= "  INNER JOIN schedule ON schedule_detail.EventID = schedule.EventID ";
            $SQL.= " WHERE 1=1 ";
            $SQL.= sprintf("   AND schedule_detail.DisplayGroupID IN (%s) ", $db->escape_string($displayGroups));

            // Events that fall inside the two dates
            $SQL.= "   AND schedule_detail.ToDT > $thisMonth ";
            $SQL.= "   AND schedule_detail.FromDT < $nextMonth ";

            //Ordering
            $SQL.= " ORDER BY schedule_detail.ToDT - schedule_detail.FromDT DESC, 2,3";
		
            Debug::LogEntry($db, 'audit', $SQL);

            if (!$result = $db->query($SQL))
            {
                    trigger_error($db->error());
                    trigger_error(__('Error getting events for date.'), E_USER_ERROR);
            }

            // Number of events
            Debug::LogEntry($db, 'audit', 'Number of events: ' . $db->num_rows($result));

            while($row = $db->get_assoc_row($result))
            {
                $eventDetailID	= Kit::ValidateParam($row['schedule_detailID'], _INT);
                $eventID	= Kit::ValidateParam($row['EventID'], _INT);
                $fromDT		= Kit::ValidateParam($row['AdjustedFromDT'], _INT);
                $toDT		= Kit::ValidateParam($row['AdjustedToDT'], _INT);
                $layout		= Kit::ValidateParam($row['layout'], _STRING);
                $displayGroup	= Kit::ValidateParam($row['DisplayGroup'], _STRING);
                $displayGroupID	= Kit::ValidateParam($row['DisplayGroupID'], _INT);
                $eventDGIDs	= Kit::ValidateParam($row['DisplayGroupIDs'], _STRING);
                $eventDGIDs 	= explode(',', $eventDGIDs);

                if (!in_array($displayGroupID, $user->DisplayGroupAuth())) continue;

                // How many days does this event span?
                $spanningDays	= ($toDT - $fromDT) / (60 * 60 * 24);
                $spanningDays	= $spanningDays < 1 ? 1 : $spanningDays;
                $spanningDays   = $spanningDays > 31 ? 31 : $spanningDays;

                $dayNo		= (int) date('d', $fromDT);
                $layoutUri		= sprintf('index.php?p=schedule&q=EditEventForm&EventID=%d&EventDetailID=%d"', $eventID, $eventDetailID);

                Debug::LogEntry($db, 'audit', sprintf('Creating Event Object for ScheduleDetailID %d. The DayNo for this event is %d', $eventDetailID, $dayNo));

                // Create a new Event from these details
                $event			= new Event();
                $event->eventID		= $eventID;
                $event->eventDetailID	= $eventDetailID;
                $event->fromDT		= $fromDT;
                $event->toDT		= $toDT;
                $event->layout		= $layout;
                $event->displayGroup	= $displayGroup;
                $event->layoutUri	= $layoutUri;
                $event->spanningDays	= ceil($spanningDays);
                $event->startDayNo	= $dayNo;
                $event->editPermission	= $this->IsEventEditable($eventDGIDs);
                $this->eventsList[]     = $event;

                // Store this event in the lowest slot it will fit in.
                // only look from the start day of this event
                $located	= false;
                $locatedOn	= 0;

                if (!isset($events[$locatedOn][$dayNo]))
                {
                    // Start day empty on event row 1
                    $located 	= true;

                    // Look to see if there are enough free slots to cover the event duration
                    for ($i = $dayNo; $i <= $spanningDays; $i++)
                    {
                        if (isset($events[$locatedOn][$i]))
                        {
                            $located	= false;
                            break;
                        }
                    }

                    // If we are located by this point, that means we can fill in these blocks
                    if ($located)
                    {
                        Debug::LogEntry($db, 'audit', sprintf('Located ScheduleDetailID %d in Position %d', $eventDetailID, $locatedOn));

                        for ($i = $dayNo; $i < $dayNo + $spanningDays; $i++)
                        {
                                $events[$locatedOn][$i] = $event;
                        }
                    }
                }

                $locatedOn	= 1;

                if (!$located && !isset($events[$locatedOn][$dayNo]))
                {
                    // Start day empty on event row 2
                    $located 	= true;

                    // Look to see if there are enough free slots to cover the event duration
                    for ($i = $dayNo; $i <= $spanningDays; $i++)
                    {
                        if (isset($events[$locatedOn][$i]))
                        {
                            $located	= false;
                            break;
                        }
                    }

                    // If we are located by this point, that means we can fill in these blocks
                    if ($located)
                    {
                        Debug::LogEntry($db, 'audit', sprintf('Located ScheduleDetailID %d in Position %d', $eventDetailID, $locatedOn));

                        for ($i = $dayNo; $i < $dayNo + $spanningDays; $i++)
                        {
                            $events[$locatedOn][$i] = $event;
                        }
                    }
                }

                $locatedOn	= 2;

                if (!$located && !isset($events[$locatedOn][$dayNo]))
                {
                    // Start day empty on event row 3
                    $located 	= true;

                    // Look to see if there are enough free slots to cover the event duration
                    for ($i = $dayNo; $i <= $spanningDays; $i++)
                    {
                        if (isset($events[$locatedOn][$i]))
                        {
                            $located	= false;
                            break;
                        }
                    }

                    // If we are located by this point, that means we can fill in these blocks
                    if ($located)
                    {
                        Debug::LogEntry($db, 'audit', sprintf('Located ScheduleDetailID %d in Position %d', $eventDetailID, $locatedOn));

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

                    Debug::LogEntry($db, 'audit', sprintf('No space for event with start day no %d and spanning days %d', $dayNo, $spanningDays));
                }
            }

            Debug::LogEntry($db, 'audit', 'Built Month Array');
            Debug::LogEntry($db, 'audit', var_export($events, true));

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
		$db 			=& $this->db;
		$user			=& $this->user;
		$events 		= '';
		$nextWeek		= $date + (60 * 60 * 24 * 7);
		
		$displayGroups	= implode(',', $displayGroupIDs);
		
		if ($displayGroups == '') return;
		
		// Query for all events between the dates
		$SQL = "";
        $SQL.= "SELECT schedule_detail.schedule_detailID, ";
        $SQL.= "       schedule_detail.FromDT, ";
        $SQL.= "       schedule_detail.ToDT,";
        $SQL.= "       layout.layout, ";
        $SQL.= "       schedule_detail.userid, ";
        $SQL.= "       schedule_detail.is_priority, ";
        $SQL.= "       schedule_detail.EventID ";
        $SQL.= "  FROM schedule_detail ";
        $SQL.= "  INNER JOIN layout ON layout.layoutID = schedule_detail.layoutID ";
        $SQL.= " WHERE 1=1 ";
        $SQL.= sprintf("   AND schedule_detail.DisplayGroupID IN (%s) ", $db->escape_string($displayGroups));
        
        // Events that fall inside the two dates
        $SQL.= "   AND schedule_detail.FromDT > $date ";
        $SQL.= "   AND schedule_detail.FromDT <= $nextWeek ";
        
        //Ordering
        $SQL.= " ORDER BY 2,3";	
		
		Debug::LogEntry($db, 'audit', $SQL);
		
		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__('Error getting events for date.'), E_USER_ERROR);
		}

		// Number of events
		Debug::LogEntry($db, 'audit', 'Number of events: ' . $db->num_rows($result));
		
		// Define some colors:
		$color[1] 	= 'CalEvent1';
		$color[2] 	= 'CalEvent2';
		$color[3] 	= 'CalEvent3';
		
        $count 		= 1;
		$rows 		= array('', '', '', '');
		$day		= 1;
		
		while($row = $db->get_assoc_row($result))
		{
			if ($count > 3) $count = 1;
			
			$eventDetailID	= Kit::ValidateParam($row['schedule_detailID'], _INT);
			$eventID		= Kit::ValidateParam($row['EventID'], _INT);
			$fromDT			= Kit::ValidateParam($row['FromDT'], _INT);
			$toDT			= Kit::ValidateParam($row['ToDT'], _INT);
			$layout			= Kit::ValidateParam($row['layout'], _STRING);
			$layout			= sprintf('<a class="XiboFormButton" href="index.php?p=schedule&q=EditEventForm&EventID=%d&EventDetailID=%d" title="%s">%s</a>', $eventID, $eventDetailID, __('Edit Event'), $layout);
			
			// How many days does this event span?
			$spanningDays	= ($toDT - $fromDT) / (60 * 60 * 24);
			$spanningDays	= $spanningDays < 1 ? 1 : $spanningDays;
			
			$dayNo			= ($fromDT - $date) / (60 * 60 * 24);
			$dayNo			= $dayNo < 1 ? 1 : $dayNo;
			
			// Fill in the days with no events?
			
			$rows[$count]	.= '<td colspan="' . $spanningDays . '"><div class="Event ' . $color[$count] . '">' . $layout . '</div></td>';
			
			$count++;
		}
		
		$events	.= '<tr>' . $rows[1] . '</tr>';
		$events	.= '<tr>' . $rows[2] . '</tr>';
		$events	.= '<tr>' . $rows[3] . '</tr>';
		
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
            $db 		=& $this->db;
            $user		=& $this->user;
            $events 		= array();
            $fromDt		= $date;
            $toDt		= $date + (60 * 60 * 24);

            $displayGroups	= implode(',', $displayGroupIDs);

            if ($displayGroups == '') return;

            // Query for all events between the dates
            $SQL = "";
            $SQL.= "SELECT schedule_detail.schedule_detailID, ";
            $SQL.= "       schedule_detail.FromDT, ";
            $SQL.= "       schedule_detail.ToDT,";
            $SQL.= "       GREATEST(schedule_detail.FromDT, $fromDt) AS AdjustedFromDT,";
            $SQL.= "       LEAST(schedule_detail.ToDT, $toDt) AS AdjustedToDT,";
            $SQL.= "       layout.layout, ";
            $SQL.= "       schedule_detail.userid, ";
            $SQL.= "       schedule_detail.is_priority, ";
            $SQL.= "       schedule_detail.EventID, ";
            $SQL.= "       schedule_detail.ToDT - schedule_detail.FromDT AS duration, ";
            $SQL.= "       (GREATEST(schedule_detail.ToDT, $toDt)) - (LEAST(schedule_detail.FromDT, $fromDt)) AS AdjustedDuration, ";
            $SQL.= "       displaygroup.DisplayGroup, ";
            $SQL.= "       displaygroup.DisplayGroupID, ";
	    $SQL.= "       schedule.DisplayGroupIDs ";
            $SQL.= "  FROM schedule_detail ";
            $SQL.= "  INNER JOIN layout ON layout.layoutID = schedule_detail.layoutID ";


            $SQL.= "  INNER JOIN displaygroup ON displaygroup.DisplayGroupID = schedule_detail.DisplayGroupID ";
            $SQL.= "  INNER JOIN schedule ON schedule_detail.EventID = schedule.EventID ";
            $SQL.= " WHERE 1=1 ";
            $SQL.= sprintf("   AND schedule_detail.DisplayGroupID IN (%s) ", $db->escape_string($displayGroups));

            // Events that fall inside the two dates
            $SQL.= "   AND schedule_detail.ToDT > $fromDt ";
            $SQL.= "   AND schedule_detail.FromDT <= $toDt ";

            //Ordering
            $SQL .= " ORDER BY schedule_detail.FromDT ASC, layout.layout ASC";

            Debug::LogEntry($db, 'audit', $SQL);

            if (!$result = $db->query($SQL))
            {
                    trigger_error($db->error());
                    trigger_error(__('Error getting events for date.'), E_USER_ERROR);
            }

            // Number of events
            Debug::LogEntry($db, 'audit', 'Number of events: ' . $db->num_rows($result));

            while($row = $db->get_assoc_row($result))
            {
                $eventDetailID	= Kit::ValidateParam($row['schedule_detailID'], _INT);
                $eventID	= Kit::ValidateParam($row['EventID'], _INT);
                $fromDT		= Kit::ValidateParam($row['AdjustedFromDT'], _INT);
                $toDT		= Kit::ValidateParam($row['AdjustedToDT'], _INT);
                $layout		= Kit::ValidateParam($row['layout'], _STRING);
                $displayGroup	= Kit::ValidateParam($row['DisplayGroup'], _STRING);
                $displayGroupID	= Kit::ValidateParam($row['DisplayGroupID'], _INT);
                $eventDGIDs	= Kit::ValidateParam($row['DisplayGroupIDs'], _STRING);
                $eventDGIDs 	= explode(',', $eventDGIDs);

                if (!in_array($displayGroupID, $user->DisplayGroupAuth())) continue;

                // How many days does this event span?
                $spanningDays	= ($toDT - $fromDT) / (60 * 60 * 24);
                $spanningDays	= $spanningDays < 1 ? 1 : $spanningDays;

                $dayNo		= (int) date('d', $fromDT);
                $layoutUri	= sprintf('index.php?p=schedule&q=EditEventForm&EventID=%d&EventDetailID=%d"', $eventID, $eventDetailID);

                Debug::LogEntry($db, 'audit', sprintf('Creating Event Object for ScheduleDetailID %d. The DayNo for this event is %d', $eventDetailID, $dayNo));

                // Create a new Event from these details
                $event			= new Event();
                $event->eventID		= $eventID;
                $event->eventDetailID	= $eventDetailID;
                $event->fromDT		= $fromDT;
                $event->toDT		= $toDT;
                $event->layout		= $layout;
                $event->displayGroup	= $displayGroup;
                $event->layoutUri	= $layoutUri;
                $event->spanningDays	= ceil($spanningDays);
                $event->startDayNo	= $dayNo;
                $event->editPermission	= $this->IsEventEditable($eventDGIDs);
                $events[]               = $event;
            }

            Debug::LogEntry($db, 'audit', 'Built Day Array');
            Debug::LogEntry($db, 'audit', var_export($events, true));

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
		$db 			=& $this->db;
		$user			=& $this->user;
		$events 		= '';
		$nextDay		= $date + (60 * 60 * 24);
		
		$displayGroups	= implode(',', $displayGroupIDs);
		
		if ($displayGroups == '') return;
		
		// Query for all events between the dates
		$SQL = "";
        $SQL.= "SELECT schedule_detail.schedule_detailID, ";
        $SQL.= "       schedule_detail.FromDT, ";
        $SQL.= "       schedule_detail.ToDT,";
        $SQL.= "       layout.layout, ";
        $SQL.= "       schedule_detail.userid, ";
        $SQL.= "       schedule_detail.is_priority, ";
        $SQL.= "       schedule_detail.EventID ";
        $SQL.= "  FROM schedule_detail ";
        $SQL.= "  INNER JOIN layout ON layout.layoutID = schedule_detail.layoutID ";
        $SQL.= " WHERE 1=1 ";
        $SQL.= sprintf("   AND schedule_detail.DisplayGroupID IN (%s) ", $db->escape_string($displayGroups));
        
        // Events that fall inside the two dates
        $SQL.= "   AND schedule_detail.FromDT > $date ";
        $SQL.= "   AND schedule_detail.FromDT <= $nextDay ";
        
        //Ordering
        $SQL.= " ORDER BY 2,3";	
		
		Debug::LogEntry($db, 'audit', $SQL);
		
		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__('Error getting events for date.'), E_USER_ERROR);
		}

		// Number of events
		Debug::LogEntry($db, 'audit', 'Number of events: ' . $db->num_rows($result));
		
		// Define some colors:
		$color[1] = 'CalEvent1';
		$color[2] = 'CalEvent2';
		$color[3] = 'CalEvent3';
		
        $count = 1;
		
		while($row = $db->get_assoc_row($result))
		{
			if ($count > 3) $count = 1;
			
			$top		= 20 * $count;
			
			$eventDetailID	= Kit::ValidateParam($row['schedule_detailID'], _INT);
			$eventID		= Kit::ValidateParam($row['EventID'], _INT);
			$fromDT			= Kit::ValidateParam($row['FromDT'], _INT);
			$toDT			= Kit::ValidateParam($row['ToDT'], _INT);
			$layout			= Kit::ValidateParam($row['layout'], _STRING);
			$layout			= sprintf('<a class="XiboFormButton" href="index.php?p=schedule&q=EditEventForm&EventID=%d&EventDetailID=%d" title="%s">%s</a>', $eventID, $eventDetailID, __('Edit Event'), $layout);
			
			if($currentWeekDayNo == 1) $events .= '<tr>';
			
			$events 		.= '<td><div class="Event ' . $color[$count] . '">' . $layout . '</div></td>';
			
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
	 * Shows a list of display groups and displays
	 * @return 
	 */
	public function DisplayFilter()
	{
		$db 			=& $this->db;
		$user			=& $this->user;
		
		$filterForm = <<<END
		<div id="DisplayListFilter">
			<form onsubmit="return false">
				<input type="hidden" name="p" value="schedule">
				<input type="hidden" name="q" value="DisplayList">
				<input class="DisplayListInput" type="text" name="name" />
			</form>
		</div>
END;
		
		$id = uniqid();
		
		$xiboGrid = <<<HTML
		<div class="XiboGrid" id="$id">
			<div class="XiboFilter">
				$filterForm
			</div>
			<div class="XiboData">
			
			</div>
		</div>
HTML;
		echo $xiboGrid;
	}
	
	/**
	 * Shows a list of displays
	 * @return 
	 */
	public function DisplayList()
	{
		$db 				=& $this->db;
		$user				=& $this->user;
		
		$response			= new ResponseManager();
		$displayGroupIDs	= Kit::GetParam('DisplayGroupIDs', _SESSION, _ARRAY);
		$output				= '';
					
		$output				= $this->UnorderedListofDisplays(true, $displayGroupIDs);
		
		$response->SetGridResponse($output);
		$response->callBack = 'DisplayListRender';
		$response->Respond();
	}
	
	/**
	 * Outputs an unordered list of displays optionally with a form
	 * @return 
	 * @param $outputForm Object
	 */
	private function UnorderedListofDisplays($outputForm, $displayGroupIDs)
	{
		$db 				=& $this->db;
		$user				=& $this->user;
		$output				= '';
		$name				= Kit::GetParam('name', _POST, _STRING);
		
		// Get a list of display groups
		$SQL  = "SELECT displaygroup.DisplayGroupID, displaygroup.DisplayGroup, IsDisplaySpecific ";
		$SQL .= "  FROM displaygroup ";
		if ($name != '')
		{
			$SQL .= sprintf(" WHERE displaygroup.DisplayGroup LIKE '%%%s%%' ", $db->escape_string($name));
		}
		$SQL .= " ORDER BY IsDisplaySpecific, displaygroup.DisplayGroup ";
		
		Debug::LogEntry($db, 'audit', $SQL, 'Schedule', 'UnorderedListofDisplays');


		if(!($results = $db->query($SQL))) 
		{
			trigger_error($db->error());
			trigger_error(__("Can not list Display Groups"), E_USER_ERROR);
		}
		
		if ($db->num_rows($results) == 0)
			trigger_error(__('No Display Groups'), E_USER_ERROR);
			
		if ($outputForm) $output .= '<form id="DisplayList" class="DisplayListForm">';
                $output         .= __('Groups');
		$output 	.= '<ul class="DisplayList">';
		$nested 	= false;
		
		while($row = $db->get_assoc_row($results))
		{
			$displayGroupID		= Kit::ValidateParam($row['DisplayGroupID'], _INT);
			$isDisplaySpecific	= Kit::ValidateParam($row['IsDisplaySpecific'], _INT);
			$displayGroup		= Kit::ValidateParam($row['DisplayGroup'], _STRING);
			$checked 			= (in_array($displayGroupID, $displayGroupIDs)) ? 'checked' : '';
			
			// Determine if we are authed against this group.
			if (!in_array($displayGroupID, $user->DisplayGroupAuth())) continue;
			
			// Do we need to nest yet? We only nest display specific groups
			if ($isDisplaySpecific == 1 && !$nested)
			{
				// Start a new UL to display these
				$output .= '</ul>' . __('Displays') . '<br/><ul class="DisplayList">';
				
				$nested = true;
			}
			
			$output .= '<li>';
			$output .= '<label>' . $displayGroup . '</label><input type="checkbox" name="DisplayGroupIDs[]" value="' . $displayGroupID . '" ' . $checked . '/>';
			$output .= '</li>';
		}
		
		if ($nested) $output .= '  </ul></li>';
		$output .= '</ul>';
		if ($outputForm) $output .= '</form>';
		
		return $output;
	}
	
	/**
	 * Shows a form to add an event
	 *  will default to the current date if non is provided
	 * @return 
	 */
	function AddEventForm()
	{
		$db 			=& $this->db;
		$user			=& $this->user;
		$response		= new ResponseManager();
		
		$date			= Kit::GetParam('date', _GET, _INT, mktime(date('H'), 0, 0, date('m'), date('d'), date('Y')));
		$dateText		= date("d/m/Y", $date);
		$displayGroupIDs	= Kit::GetParam('DisplayGroupIDs', _SESSION, _ARRAY);
		
		// Layout list
                $layouts = $user->LayoutList();
		$layout_list 	= Kit::SelectList('layoutid', $layouts, 'layoutid', 'layout');
		
		$outputForm		= false;
		$displayList	= $this->UnorderedListofDisplays($outputForm, $displayGroupIDs);
		
		$form 		= <<<END
			<form id="AddEventForm" class="XiboForm" action="index.php?p=schedule&q=AddEvent" method="post">
				<table style="width:100%;">
					<tr>
						<td><label for="starttime" title="Select the start time for this event">Start Time<span class="required">*</span></label></td>
						<td>
							<input id="starttime" class="date-pick required" type="text" size="12" name="starttime" value="$dateText" />
							<input id="sTime" class="required" type="text" size="12" name="sTime" value="00:00" />
						</td>
						<td rowspan="4">
                                                        <div class="FormDisplayList">
							$displayList
                                                        </div>
						</td>
					</tr>
					<tr>
						<td><label for="endtime" title="Select the end time for this event">End Time<span class="required">*</span></label></td>
						<td>
							<input id="endtime" class="date-pick required" type="text" size="12" name="endtime" value="" />
							<input id="eTime" class="required" type="text" size="12" name="eTime" value="00:00" />
						</td>
					</tr>
					<tr>
						<td><label for="layoutid" title="Select which layout this event will show.">Layout<span class="required">*</span></label></td>
						<td>$layout_list</td>
					</tr>
					<tr>
						<td><label title="Sets whether or not this event has priority. If set the event will be show in preferance to other events." for="cb_is_priority">Priority</label></td>
						<td><input type="checkbox" id="cb_is_priority" name="is_priority" value="1" title="Sets whether or not this event has priority. If set the event will be show in preference to other events."></td>
					</tr>
END;

		//recurrance part of the form
		$days 		= 60*60*24;
		$rec_type 	= listcontent("null|None,Hour|Hourly,Day|Daily,Week|Weekly,Month|Monthly,Year|Yearly", "rec_type");
		$rec_detail	= listcontent("1|1,2|2,3|3,4|4,5|5,6|6,7|7,8|8,9|9,10|10,11|11,12|12,13|13,14|14", "rec_detail");
		$rec_range 	= '<input class="date-pick" type="text" id="rec_range" name="rec_range" size="12" />';
		
		$form .= <<<END
		<tr>
			<td colspan="3">
				<fieldset title="If this event occurs again (e.g. repeats) on a schedule">
					<legend>Recurrence Information</label>
					<table>
						<tr>
							<td><label for="rec_type" title="What type of repeating is required">Repeats</label></td>
							<td>$rec_type</td>
						</tr>
						<tr>
							<td><label for="rec_detail" title="How often does this event repeat">Repeat every</label></td>
							<td><input class="number" type="text" name="rec_detail" value="1" /></td>
						</tr>
						<tr>
							<td><label for="rec_range" title="When should this event stop repeating?">Until</label></td>
							<td>$rec_range
                                                        <input id="repeatTime" type="text" size="12" name="repeatTime" value="00:00" />
                                                        </td>
						</tr>
					</table>
				</fieldset>
			</td>
		</tr>
END;

		$form .= <<<END
				</table>
			</form>
END;
		
		$response->SetFormRequestResponse($form, __('Schedule an Event'), '700px', '400px');
		$response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=General')");
		$response->AddButton(__('Cancel'), 'XiboDialogClose()');
		$response->AddButton(__('Save'), '$("#AddEventForm").submit()');
		$response->callBack = 'setupScheduleForm';
		$response->Respond();
	}
	
	/**
	 * Shows a form to add an event
	 *  will default to the current date if non is provided
	 * @return 
	 */
	function EditEventForm()
	{
		$db 		=& $this->db;
		$user		=& $this->user;
		$response	= new ResponseManager();
		
		$eventID		= Kit::GetParam('EventID', _GET, _INT, 0);
		$eventDetailID	= Kit::GetParam('EventDetailID', _GET, _INT, 0);
		
		if ($eventID == 0) trigger_error('No event selected.', E_USER_ERROR);
		
		// Get the relevant details for this event
		$SQL = "";
        $SQL.= "SELECT schedule.FromDT, ";
        $SQL.= "       schedule.ToDT,";
        $SQL.= "       schedule.LayoutID, ";
        $SQL.= "       schedule.userid, ";
        $SQL.= "       schedule.is_priority, ";
        $SQL.= "       schedule.DisplayGroupIDs, ";
        $SQL.= "       schedule.recurrence_type, ";
        $SQL.= "       schedule.recurrence_detail, ";
        $SQL.= "       schedule.recurrence_range, ";
        $SQL.= "       schedule.EventID ";
        $SQL.= "  FROM schedule ";
        $SQL.= "  INNER JOIN schedule_detail ON schedule.EventID = schedule_detail.EventID ";
        $SQL.= "  INNER JOIN layout ON layout.layoutID = schedule.layoutID ";
        $SQL.= " WHERE 1=1 ";
        $SQL.= sprintf("   AND schedule.EventID = %d", $eventID);
        $SQL.= sprintf("   AND schedule_detail.schedule_detailID = %d", $eventDetailID);
        
		Debug::LogEntry($db, 'audit', $SQL);
		
		if (!$result = $db->query($SQL))
		{
			trigger_error($db->error());
			trigger_error(__('Error getting details for this event.'), E_USER_ERROR);
		}
		
		$row 				= $db->get_assoc_row($result);
		
		$eventID	= Kit::ValidateParam($row['EventID'], _INT);
		$fromDT		= Kit::ValidateParam($row['FromDT'], _INT);
		$toDT		= Kit::ValidateParam($row['ToDT'], _INT);
		$displayGroupIDs	= Kit::ValidateParam($row['DisplayGroupIDs'], _STRING);
		$recType	= Kit::ValidateParam($row['recurrence_type'], _STRING);
		$recDetail	= Kit::ValidateParam($row['recurrence_detail'], _STRING);
		$recToDT	= Kit::ValidateParam($row['recurrence_range'], _STRING);
		$displayGroupIDs 	= explode(',', $displayGroupIDs);
		$layoutID	= Kit::ValidateParam($row['LayoutID'], _STRING);
        $isPriority = Kit::ValidateParam($row['is_priority'], _CHECKBOX);

        if ($isPriority == 1)
        {
            $isPriority = 'checked';
        }
        else
        {
            $isPriority = '';
        }
		
		$fromDtText	= date("d/m/Y", $fromDT);
		$fromTimeText	= date("H:i", $fromDT);
		$toDtText	= date("d/m/Y", $toDT);
		$toTimeText	= date("H:i", $toDT);
		$recToDtText	= '';
                $recToTimeText = '';
		
		if ($recType != '')
		{
			$recToDtText		= date("d/m/Y", $recToDT);
			$recToTimeText		= date("H:i", $recToDT);
		}
		
		// Check that we have permission to edit this event.
		if (!$this->IsEventEditable($displayGroupIDs))
		{
			trigger_error(__('You do not have permission to edit this event.'));
			return;
		}
		
		// need to do some user checking here
		// Layout list
                $layouts = $user->LayoutList();
		$layout_list 	= Kit::SelectList('layoutid', $layouts, 'layoutid', 'layout', $layoutID);
		
		$outputForm		= false;
		$displayList	= $this->UnorderedListofDisplays($outputForm, $displayGroupIDs);
		
		$form 		= <<<END
			<form id="EditEventForm" class="XiboForm" action="index.php?p=schedule&q=EditEvent" method="post">
				<input type="hidden" id="EventID" name="EventID" value="$eventID" />
				<input type="hidden" id="EventDetailID" name="EventDetailID" value="$eventDetailID" />
				<table style="width:100%;">
					<tr>
						<td><label for="starttime" title="Select the start time for this event">Start Time<span class="required">*</span></label></td>
						<td>
							<input id="starttime" class="date-pick required" type="text" size="12" name="starttime" value="$fromDtText" />
							<input id="sTime" class="required" type="text" size="12" name="sTime" value="$fromTimeText" />
						</td>
						<td rowspan="4">
                                                    <div class="FormDisplayList">
                                                    $displayList
                                                    </div>
						</td>
					</tr>
					<tr>
						<td><label for="endtime" title="Select the end time for this event">End Time<span class="required">*</span></label></td>
						<td>
							<input id="endtime" class="date-pick required" type="text" size="12" name="endtime" value="$toDtText" />
							<input id="eTime" class="required" type="text" size="12" name="eTime" value="$toTimeText" />
						</td>
					</tr>
					<tr>
						<td><label for="layoutid" title="Select which layout this event will show.">Layout<span class="required">*</span></label></td>
						<td>$layout_list</td>
					</tr>
					<tr>
						<td><label title="Sets whether or not this event has priority. If set the event will be show in preferance to other events." for="cb_is_priority">Priority</label></td>
						<td><input type="checkbox" id="cb_is_priority" name="is_priority" value="1" $isPriority title="Sets whether or not this event has priority. If set the event will be show in preference to other events."></td>
					</tr>
END;

		// Recurrance part of the form
		$days 		= 60*60*24;
		$rec_type 	= listcontent("null|None,Hour|Hourly,Day|Daily,Week|Weekly,Month|Monthly,Year|Yearly", "rec_type", $recType);
		$rec_range 	= '<input class="date-pick" type="text" id="rec_range" name="rec_range" value="' . $recToDtText . '" size="12" />';
		
		$form .= <<<END
		<tr>
			<td colspan="4">
				<fieldset title="If this event occurs again (e.g. repeats) on a schedule">
					<legend>Recurrence Information</label>
					<table>
						<tr>
							<td><label for="rec_type" title="What type of repeating is required">Repeats</label></td>
							<td>$rec_type</td>
						</tr>
						<tr>
							<td><label for="rec_detail" title="How often does this event repeat">Repeat every</label></td>
							<td><input class="number" type="text" name="rec_detail" value="$recDetail" /></td>
						</tr>
						<tr>
							<td><label for="rec_range" title="When should this event stop repeating?">Until</label></td>
							<td>$rec_range
                                                        <input id="repeatTime" type="text" size="12" name="repeatTime" value="$recToTimeText" />
                                                        </td>
						</tr>
					</table>
				</fieldset>
			</td>
		</tr>
		</table>
	</form>
END;
		
		$response->SetFormRequestResponse($form, __('Edit Scheduled Event'), '700px', '400px');
		$response->focusInFirstInput = false;
		$response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=General')");
		$response->AddButton(__('Delete'), sprintf('XiboFormRender("index.php?p=schedule&q=DeleteForm&EventID=%d&EventDetailID=%d")', $eventID, $eventDetailID));
		$response->AddButton(__('Cancel'), 'XiboDialogClose()');
		$response->AddButton(__('Save'), '$("#EditEventForm").submit()');
		$response->callBack = 'setupScheduleForm';
		$response->Respond();
	}
	
	/**
	 * Add Event
	 * @return 
	 */
	public function AddEvent() 
	{
		$db                 =& $this->db;
		$user               =& $this->user;
		$response           = new ResponseManager();
		$datemanager        = new DateManager($db);

		$layoutid           = Kit::GetParam('layoutid', _POST, _INT, 0);
		$fromDT             = Kit::GetParam('starttime', _POST, _STRING);
		$toDT               = Kit::GetParam('endtime', _POST, _STRING);
		$fromTime           = Kit::GetParam('sTime', _POST, _STRING, '00:00');
		$toTime             = Kit::GetParam('eTime', _POST, _STRING, '00:00');
		$displayGroupIDs    = Kit::GetParam('DisplayGroupIDs', _POST, _ARRAY);
		$isPriority         = Kit::GetParam('is_priority', _POST, _CHECKBOX);

		$rec_type           = Kit::GetParam('rec_type', _POST, _STRING);
		$rec_detail         = Kit::GetParam('rec_detail', _POST, _INT);
		$recToDT            = Kit::GetParam('rec_range', _POST, _STRING);
		$repeatTime            = Kit::GetParam('repeatTime', _POST, _STRING, '00:00');
		
		$userid             = Kit::GetParam('userid', _SESSION, _INT);
		
		Debug::LogEntry($db, 'audit', 'From DT: ' . $fromDT);
		Debug::LogEntry($db, 'audit', 'To DT: ' . $toDT);
		
		// Validate the times
		if (!strstr($fromTime, ':') || !strstr($toTime, ':'))
		{
			trigger_error(__('Times must be in the format 00:00'), E_USER_ERROR);
		}
		
		$fromDT     = $datemanager->GetDateFromUS($fromDT, $fromTime);
		$toDT       = $datemanager->GetDateFromUS($toDT, $toTime);

                if ($recToDT != '')
                    $recToDT = $datemanager->GetDateFromUS($recToDT, $repeatTime);
		
		// Validate layout
		if ($layoutid == 0) 
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
		
		if (!$scheduleObject->Add($displayGroupIDs, $fromDT, $toDT, $layoutid, $rec_type, $rec_detail, $recToDT, $isPriority, $userid)) 
		{
			trigger_error($scheduleObject->GetErrorMessage(), E_USER_ERROR);
		}
		
		$response->SetFormSubmitResponse(__("The Event has been Added."));
		$response->callBack = 'CallGenerateCalendar';
		$response->Respond();
	}
	
	/**
	 * Edits an event
	 * @return 
	 */
	public function EditEvent()
	{
		$db 				=& $this->db;
		$user				=& $this->user;
		$response			= new ResponseManager();
		$datemanager		= new DateManager($db);

		$eventID			= Kit::GetParam('EventID', _POST, _INT, 0);
		$eventDetailID		= Kit::GetParam('EventDetailID', _POST, _INT, 0);
		$layoutid			= Kit::GetParam('layoutid', _POST, _INT, 0);
		$fromDT				= Kit::GetParam('starttime', _POST, _STRING);
		$toDT				= Kit::GetParam('endtime', _POST, _STRING);
		$fromTime			= Kit::GetParam('sTime', _POST, _STRING, '00:00');
		$toTime				= Kit::GetParam('eTime', _POST, _STRING, '00:00');
		$displayGroupIDs	= Kit::GetParam('DisplayGroupIDs', _POST, _ARRAY);
		$isPriority			= Kit::GetParam('is_priority', _POST, _CHECKBOX);

		$rec_type			= Kit::GetParam('rec_type', _POST, _STRING);
		$rec_detail			= Kit::GetParam('rec_detail', _POST, _INT);
		$recToDT			= Kit::GetParam('rec_range', _POST, _STRING);
		$repeatTime			= Kit::GetParam('repeatTime', _POST, _STRING, '00:00');
		
		$userid 			= Kit::GetParam('userid', _SESSION, _INT);
		
		if ($eventID == 0) trigger_error('No event selected.', E_USER_ERROR);
		
		Debug::LogEntry($db, 'audit', 'From DT: ' . $fromDT);
		Debug::LogEntry($db, 'audit', 'To DT: ' . $toDT);
		
		// Validate the times
		if (!strstr($fromTime, ':') || !strstr($toTime, ':'))
		{
			trigger_error(__('Times must be in the format 00:00'), E_USER_ERROR);
		}
		
		$fromDT     = $datemanager->GetDateFromUS($fromDT, $fromTime);
		$toDT       = $datemanager->GetDateFromUS($toDT, $toTime);
		if ($recToDT != '')
                    $recToDT = $datemanager->GetDateFromUS($recToDT, $repeatTime);

		// Validate layout
		if ($layoutid == 0) 
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
		
		if (!$scheduleObject->Edit($eventID, $eventDetailID, $displayGroupIDs, $fromDT, $toDT, $layoutid, $rec_type, $rec_detail, $recToDT, $isPriority, $userid)) 
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
		$db 				=& $this->db;
		$user				=& $this->user;
		$response			= new ResponseManager();
		
		$eventID			= Kit::GetParam('EventID', _GET, _INT, 0);
		$eventDetailID		= Kit::GetParam('EventDetailID', _GET, _INT, 0);
		
		if ($eventID == 0) trigger_error('No event selected.', E_USER_ERROR);
		
        $strQuestion = __('Are you sure you want to delete this event from <b>all</b> displays?');
        $strAdvice = __('If you only want to delete this item from certain displays, please deselect the displays in the previous dialogue and click Save.');

		$form = <<<END
		<form id="DeleteEventForm" class="XiboForm" action="index.php?p=schedule&q=DeleteEvent">
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
		$response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=General')");
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
		$db 				=& $this->db;
		$user				=& $this->user;
		$response			= new ResponseManager();
		
		$eventID			= Kit::GetParam('EventID', _POST, _INT, 0);
		$eventDetailID		= Kit::GetParam('EventDetailID', _POST, _INT, 0);
		
		if ($eventID == 0) trigger_error('No event selected.', E_USER_ERROR);
		
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
	
	function generate_day_view() 
	{
		$db =& $this->db;
		
		$date		= $this->start_date;
		
		//For the day view the date actually wants to be the beginning of the day month year given
		$date		= mktime(0,0,0,date("m",$date),date("d", $date),date("Y",$date));
		
		$next_day	= mktime(0,0,0,date("m",$date),date("d", $date)+1,date("Y",$date));
		
		$query_date = date("Y-m-d H:i:s", $date);
		$query_next_day = date("Y-m-d H:i:s", $next_day);
		
		$day_text = date("d-m-Y", $date);
		
		// This is a view of a single day (with all events listed in a table)
		$SQL = "";
        $SQL.= "SELECT schedule_detail.schedule_detailID, ";
        $SQL.= "       schedule_detail.FromDT, ";
        $SQL.= "       schedule_detail.ToDT,";
        $SQL.= "       layout.layout, ";
        $SQL.= "       schedule_detail.userid, ";
        $SQL.= "       schedule_detail.is_priority ";
        $SQL.= "  FROM schedule_detail ";
        $SQL.= "  INNER JOIN layout ON layout.layoutID = schedule_detail.layoutID ";
        $SQL.= " WHERE 1=1 ";
        $SQL.= "   AND schedule_detail.DisplayGroupID = $this->displayid ";
        
        //Events that fall inside the two dates
        $SQL.= "   AND schedule_detail.FromDT < $date ";
        $SQL.= "   AND schedule_detail.ToDT   >  $next_day";
        
        //Ordering
        $SQL.= " ORDER BY 2,3";

        $result = $db->query($SQL) or trigger_error($db->error(), E_USER_ERROR);
		
		$table_html = <<<END
	<h3>Schedule for $day_text</h3>
    <div class="scrollingWindow" style="height:255px">
    <table class="day_view" style="width: 98%">
    	<tr>
    		<th>Event</th>
    		<th>Start</th>
    		<th>End</th>
END;
		
		for ($i=0; $i<24; $i++) 
		{
			
			$h = $i;
			if ($i<10) $h = "0$i";
			
			$full_time = mktime(0+$i,0,0,date("m",$date),date("d", $date),date("Y",$date));	
			
    		$table_html .= "<th>$h</th>";
		}
		
    	$table_html .= "</tr>";    
      	
      	  
        while($row = $db->get_row($result))
		{
            $schedule_detailid 	= $row[0];
            $starttime 			= date("H:i",$row[2]);
            $endtime 			= date("H:i",$row[3]);
            $name 				= $row[3];
            $times 				= date("H:i",$row[2])."-".date("H:i",$row[3]);
            $userid 			= $row[4];
            $is_priority 		= $row[5];
            
			$start_row = $starttime;
			if($row[2]<$date) 
			{
				$start_row = "Earlier Day";
			}
			
			$end_row = $endtime;
			if ($row[3]>$next_day) 
			{
				$end_row = "Later Day";
			}
			
            $table_html .= <<<END
        <tr>
        	<td>$name</td>
        	<td>$start_row</td>
        	<td>$end_row</td>
END;
			
			/*
			 * For each event we want to work out:
			 * 1. when it will start (and therefore the colspan on the first td)
			 * 2. how many hour periods it coverd (and therefore the colspan on the colored td)
			 * 3. how many cols are left
			 */
			$total_cols = 24;
			$start_hour = date("H",$row[2]);
			$end_hour   = date("H",$row[3]);
			
			/*
	         * We need to work out:
	         * 1. whether the record we've got is within the dates supplied (OR)
	         * 2. whether the record is outside of these dates
	         */
            if ($row[2] < $date) 
			{ //if 'the event start date' < 'the we are on'
            	$start_hour = 0;
            }
            
			//if the event goes over this day, then the end hour will be the total_cols shown
            if ($row[3] >= $next_day) 
			{
            	$end_hour = $total_cols;
            }
			
			/*
			 * We are ready to work it out
			 */
			if ($start_hour != 0) 
			{ //if the start_hour is 0 we dont bother with the beginning
				for($i=0;$i<$start_hour;$i++) 
				{ //go from the first column, until the start hour
					$table_html .= "<td></td>";
					$total_cols--;
				}
			}
			
			$colspan = 0;
			for ($i = $start_hour; $i < $end_hour; $i++) 
			{
				$colspan++;
				$total_cols--;
			}
			
			if ($colspan == 0) $colspan = "";
			
			$link = "<a class='XiboFormButton event' title='Load this event into the form for editing' href='index.php?p=schedule&sp=edit&q=display_form&id=$schedule_detailid&date=$this->start_date&displayid=$this->displayid'>Edit</a>";
			$class = "busy";
			
			if ($userid != $_SESSION['userid']) 
			{
				$class = "busy_no_edit";
			}
			if ($userid != $_SESSION['userid']&& $_SESSION['usertype']!=1) 
			{
				$link = "";
			}
			if ($is_priority == 1) 
			{
				$class = "busy_has_priority";
			}
			
			$table_html .= "<td class='$class' colspan='$colspan'>" .
					"$link</td>";
			
			if ($total_cols != 1) 
			{
				while ($total_cols > 0) 
				{
					$total_cols--;
					$table_html .= "<td></td>";
				}
			}
			
			$table_html .= "</tr>";
        }
		
		$table_html .= "</table></div>";
        
        return $table_html;
	}
	
	/**
	 * Is this event editable?
	 * @return 
	 * @param $eventDGIDs Object
	 */
	private function IsEventEditable($eventDGIDs)
	{
		$db 			=& $this->db;
		$user			=& $this->user;
		
		// Work out if this event is editable or not. To do this we need to compare the permissions
		// of each display group this event is associated with
		foreach ($eventDGIDs as $dgID)
		{
			if (!in_array($dgID, $user->DisplayGroupAuth()))
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
        $layoutId = Kit::GetParam('layoutid', _GET, _INT, 0);
        $displayGroupIds = Kit::GetParam('displayGroupId', _GET, _ARRAY);

        // Layout list
        $layouts = $user->LayoutList();
        $layoutList = Kit::SelectList('layoutid', $layouts, 'layoutid', 'layout', $layoutId);

        $outputForm = false;
        $displayList = $this->UnorderedListofDisplays($outputForm, $displayGroupIds);

        $form = <<<END
            <form id="ScheduleNowForm" class="XiboForm" action="index.php?p=schedule&q=ScheduleNow" method="post">
                <table style="width:100%;">
                    <tr>
                        <td><label for="duration" title="How long should this event be scheduled for">Duration<span class="required">*</span></label></td>
                        <td>H: <input type="text" name="hours" id="hours" size="2" class="number">
                        M: <input type="text" name="minutes" id="minutes" size="2" class="number">
                        S: <input type="text" name="seconds" id="seconds" size="2" class="number"></td>
                        <td rowspan="4">
                            <div class="FormDisplayList">
                            $displayList
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="layoutid" title="Select which layout this event will show.">Layout<span class="required">*</span></label></td>
                        <td>$layoutList</td>
                    </tr>
                    <tr>
                        <td><label title="Sets whether or not this event has priority. If set the event will be show in preferance to other events." for="cb_is_priority">Priority</label></td>
                        <td><input type="checkbox" id="cb_is_priority" name="is_priority" value="1" title="Sets whether or not this event has priority. If set the event will be show in preference to other events."></td>
                    </tr>
                </table>
            </form>
END;

        $response->SetFormRequestResponse($form, __('Schedule Now'), '700px', '400px');
        $response->AddButton(__('Help'), "XiboHelpRender('index.php?p=help&q=Display&Topic=Schedule&Category=General')");
        $response->AddButton(__('Cancel'), 'XiboDialogClose()');
        $response->AddButton(__('Save'), '$("#ScheduleNowForm").submit()');
        $response->Respond();
    }

    public function ScheduleNow()
    {
        $db =& $this->db;
        $user =& $this->user;
        $response = new ResponseManager();
        $datemanager = new DateManager($db);

        $layoutId = Kit::GetParam('layoutid', _POST, _INT, 0);
        $displayGroupIds = Kit::GetParam('DisplayGroupIDs', _POST, _ARRAY);
        $isPriority = Kit::GetParam('is_priority', _POST, _CHECKBOX);
        $fromDt = time();

        $hours = Kit::GetParam('hours', _POST, _INT, 0);
        $minutes = Kit::GetParam('minutes', _POST, _INT, 0);
        $seconds = Kit::GetParam('seconds', _POST, _INT, 0);
        $duration = ($hours * 3600) + ($minutes * 60) + $seconds;

        // Validate
        if ($layoutId == 0)
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

        if (!$scheduleObject->Add($displayGroupIds, $fromDt, $toDt, $layoutId, '', '', '', $isPriority, $this->user->userid))
            trigger_error($scheduleObject->GetErrorMessage(), E_USER_ERROR);

        $response->SetFormSubmitResponse(__('The Event has been Scheduled'));
        $response->Respond();
    }
}
?>
