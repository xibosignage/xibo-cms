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
 * along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
 */ 
class scheduleDAO {

	private $db;
	private $sub_page;
	private $ret_page;
	private $start_date;
	
	private $has_permission = true;
	
	private $displayid;
	private $display;
	private $layoutid;
	private $schedule_detailid;
	private $is_priority;
	private $eventid;
	
	private $last_day;
	private $last_day_pad = 0;
	
	/* For edit */
	private $starttime;
	private $endtime;
	private $total_length;
	
	//recurrence vars
	private $rec_type;
	private $rec_detail;
	private $rec_range;
	
	//report class
	private $report;

	/**
	 * Constructor
	 * @return 
	 * @param $db Object
	 */
    function scheduleDAO(database $db) {
    	$this->db =& $db;
		
		//work out the page we are on   	
    	if (!isset($_GET['sp'])) {
    		$this->sub_page = 'month';
    	}
    	else {
    		$this->sub_page = $_GET['sp'];
    	}
		
		//if the date isnt set then get the default one
    	if(!isset($_REQUEST['date'])){
		   $this->start_date = mktime(0,0,0,date('m'), date('d'), date('Y'));
		} 
		else {
		   $this->start_date = clean_input($_REQUEST['date'], VAR_FOR_SQL, $db);
		}
		
		if (isset($_SESSION['layoutid'])) {
			$this->layoutid = $_SESSION['layoutid'];
		}
		else {
			$this->layoutid = 0;
		}
		
		if (isset($_REQUEST['displayid'])) {
			$this->displayid = clean_input($_REQUEST['displayid'], VAR_FOR_SQL, $db);
		}
		else {
			// Get the first licensed display from the table...
			$SQL = "SELECT displayid FROM display WHERE licensed = 1";
			if (!$result = $db->query($SQL)) 
			{
				trigger_error($db->error());
				trigger_error("No licensed Displays", E_USER_ERROR);
			}
			if ($db->num_rows($result) == 0) 
			{
				trigger_error("No licensed Displays", E_USER_ERROR);
			}
			//we have seen this display before, so check the licensed value
			$row = $db->get_row($result);
			$this->displayid = $row[0];
		}
		
		//get the layoutdisplayid if it is present
		if (isset($_REQUEST['schedule_detailid'])) {
			$this->schedule_detailid = clean_input($_REQUEST['schedule_detailid'], VAR_FOR_SQL, $db);
		}
		
		//get the display name
		$SQL = "SELECT display FROM display WHERE displayid = $this->displayid";
		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Can not get the display name", E_USER_ERROR);
		}
		$row = $db->get_row($results);
		
		$this->display = $row[0];
		
		if ($this->sub_page == 'edit') {
			/*
			 * Expect an ID
			 */
			$this->schedule_detailid = clean_input($_REQUEST['id'], VAR_FOR_SQL, $db);
			
			//we need to get out info from the old DB
			$SQL = "";
			$SQL.= "SELECT UNIX_TIMESTAMP(schedule_detail.starttime) AS starttime, ";
			$SQL.= "       UNIX_TIMESTAMP(schedule_detail.endtime) AS endtime,";
			$SQL.= "       schedule.layoutID,";
			$SQL.= "       schedule_detail.is_priority,";
			$SQL.= "       layout.duration AS total_length, ";
			$SQL.= "       schedule_detail.userID, ";
			$SQL.= "       schedule_detail.eventID, ";
			$SQL.= "       schedule.recurrence_type, ";
			$SQL.= "       schedule.recurrence_detail, ";
			$SQL.= "       UNIX_TIMESTAMP(schedule.recurrence_range) AS recurrence_range ";
			$SQL.= "  FROM schedule_detail ";
			$SQL.= "  INNER JOIN layout ON layout.layoutID = schedule_detail.layoutID ";
			$SQL.= "  INNER JOIN schedule ON schedule.eventID = schedule_detail.eventID ";
			$SQL.= " WHERE 1=1 ";
			$SQL.= "   AND schedule_detail.schedule_detailID = $this->schedule_detailid ";
			$SQL.= " GROUP BY schedule_detail.starttime, schedule_detail.endtime, layout.layoutID ";

			$result = $db->query($SQL) or trigger_error($db->error(), E_USER_ERROR);
			$row = $db->get_row($result);

			$this->starttime		= $row[0];
			$this->endtime			= $row[1];
			$this->layoutid			= $row[2];
			$this->is_priority		= $row[3];
			$this->total_length		= $row[4];
			$this->eventid			= $row[6];
			$this->rec_type			= $row[7];
			$this->rec_detail		= $row[8];
			$this->rec_range		= $row[9];
			
			//we also need to get the recurrence information for this event
			if ($this->rec_range == "") $this->rec_range = $this->starttime + (60*60*24*30);
			
			//If this user doesnt own this event OR they do not have the appropriate permissions for this event, disallow them the view
			if ($row[5] != $_SESSION['userid'] && $_SESSION['usertype']!=1) {
				$this->has_permission = false;
			}
		}
		
		if ($this->sub_page == 'add') {
			$this->starttime	= clean_input($_REQUEST['starttime'], VAR_FOR_SQL, $db);
			
			//tweak the end time to be + one hour
			$this->endtime		= clean_input($_REQUEST['endtime'], VAR_FOR_SQL, $db) + 3600;
			
			//set the recurrence range to be starttime + 30 days
			$this->rec_range = $this->starttime + (60*60*24*30);
		}
		
		if (isset($_REQUEST['ret_page'])) {
			$this->ret_page = $_REQUEST['ret_page'];
		}
		else {
			$this->ret_page = "day";
		}
    }
    
    function on_page_load() {
    	return "";
	}
	
	function echo_page_heading() {
		echo "Schedule";
		return true;
	}
	
	function displayPage() {
		$db =& $this->db;
		
		if (!$this->has_permission) {
			displayMessage(MSG_MODE_MANUAL, "You do not have permissions to access this page");
			return false;
		}

		switch ($this->sub_page) {
				
			case 'month':
				include("lib/app/display.class.php");
				$displayDAO = new displayDAO($db);
				
				include ("template/pages/schedule_month_view.php");
				break;
			
			case 'day':
				//day view tabs are generated inside
				include ("template/pages/schedule_day_view.php");

				break;
			
			default:
				break;
		}
		
		return false;
	}
	
	function display_form () {
		$db =& $this->db;
		global $user;
		//ajax request handler
		$arh = new AjaxRequest();
					
		$start	= $this->starttime;
		$end	= $this->endtime;
		
		//set the action for the form
		$action = "index.php?p=schedule&q=add";			
		if ($this->schedule_detailid != "") {
			//assume an edit
			$action = "index.php?p=schedule&q=edit";
		}
		
		// Help icons for the form
		$helpButton = HelpButton("content/schedule/adding", true);
		$nameHelp	= HelpIcon("The Name of the Layout - (1 - 50 characters)", true);
		
		// Params		
		$start_time_select	= $this->datetime_select($start, 'starttime');
		$end_time_select	= $this->datetime_select($end, 'endtime');
		
		$userid = $_SESSION['userid'];
		//need to do some user checking here
		$sql  = "SELECT layoutID, layout, permissionID, userID ";
		$sql .= "  FROM layout WHERE retired = 0";
		$sql .= " ORDER BY layout ";
		
		$layout_list 	= dropdownlist($sql, "layoutid", $this->layoutid, "", false, true);
		$display_select = $this->display_boxes($this->eventid);
		
		$form = <<<END
	<form class="dialog_form" action="$action" method="post">
		<input type="hidden" name="displayid" value="$this->displayid">
		<input type="hidden" name="schedule_detailid" value="$this->schedule_detailid">
		<table style="width:100%;">
			<tr>
				<td><label for="starttime" title="Select the start time for this event">Start Time<span class="required">*</span></label></td>
				<td>$start_time_select</td>
				<td rowspan="3">
					Displays: <br />
					$display_select
				</td>
			</tr>
			<tr>
				<td><label for="endtime" title="Select the end time for this event">End Time<span class="required">*</span></label></td>
				<td>$end_time_select</td>
			</tr>
			<tr>
				<td><label for="layoutid" title="Select which layout this event will show.">Layout<span class="required">*</span></label></td>
				<td>$layout_list</td>
			</tr>
END;
		
		//Admin ability to set events to be priority events
		if ($_SESSION['usertype']==1 && $this->sub_page == 'edit') { //only through edit??
			
			//do we check the box or not
			$checked = "";
			if ($this->is_priority == 1) {
				$checked = "checked";
			}
		
			$form .= <<<END
			<tr>
				<td><label title="Sets whether or not this event has priority. If set the event will be show in preferance to other events." for="cb_is_priority">Priority</label></td>
				<td><input type="checkbox" id="cb_is_priority" name="is_priority" value="1" $checked title="Sets whether or not this event has priority. If set the event will be show in preferance to other events."></td>
			</tr>
END;
		}
		
		//
		//recurrance part of the form
		//
		$days = 60*60*24;
		$rec_type 	= listcontent("null|None,Hour|Hourly,Day|Daily,Week|Weekly,Month|Monthly,Year|Yearly", "rec_type", $this->rec_type);
		$rec_detail	= listcontent("1|1,2|2,3|3,4|4,5|5,6|6,7|7,8|8,9|9,10|10,11|11,12|12,13|13,14|14", "rec_detail", $this->rec_detail);
		$rec_range 	= $this->datetime_select($this->rec_range,"rec_range");
		
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
							<td>$rec_detail</td>
						</tr>
						<tr>
							<td><label for="rec_range" title="When should this event stop repeating?">Until</label></td>
							<td>$rec_range</td>
						</tr>
					</table>
				</fieldset>
			</td>
		</tr>
END;
		
		//
		// Sort out the extra things we need for an edit
		//
		$edit_link = "";
		if ($this->schedule_detailid != "") {
			//edit specific output
			$edit_link = <<<END
			<input title="Opens up the delete options for this event." href="index.php?p=schedule&q=delete_form&schedule_detailid=$this->schedule_detailid&displayid=$this->displayid"
				onclick="return init_button(this,'Delete Event',exec_filter_callback, init_callback)" type="button" value="Delete" />
END;
			
			$form .= <<<END
			<tr>
				<td colspan="2">
					<input id="radio_all" type="radio" name="linkupdate" value="all" checked>
					<label for="radio_all">Update events for all displays in this series</label>
					<input id="radio_single" type="radio" name="linkupdate" value="single">
					<label for="radio_single">Update event only for this display</label>
				</td>
			</tr>
END;
		}

		$form .= <<<END
			<tr>
				<td></td>
				<td>
					<input type="submit" value="Save" />
					$edit_link
					<input id="btnCancel" type="button" title="No / Cancel" onclick="$(this).parent().parent().dialogClose();return false; " value="Cancel" />	
					$helpButton
				</td>
			</tr>
		</table>
	</form>
END;
		
		//also output the day view (will need to be made to return a string)
		$form .= $this->generate_day_view();
		
		//output
		$arh->decode_response(true,$form);
		
		return true;
	}
	
	function delete_form() {
		$db =& $this->db;
		
		//ajax request handler
		$arh = new AjaxRequest();
		
		$form = <<<END
<form class="dialog_form" action="index.php?p=schedule&q=remove">
	<input type="hidden" name="schedule_detailid" value="$this->schedule_detailid" />
	<input type="hidden" name="displayid" value="$this->displayid" />
	<table>
		<tr>
			<td>Are you sure you want to delete this event?</td>
		</tr>
		<tr>
			<td colspan="2">
				<input id="radio_all" type="radio" name="linkupdate" value="all" checked>
				<label for="radio_all">Delete event for all displays in this series</label>
				<input id="radio_single" type="radio" name="linkupdate" value="single">
				<label for="radio_single">Delete event for this display only</label>
			</td>
		</tr>
		<tr>
			<td><input type="submit" value="Delete"></td>
		</tr>		
	</table>	
</form>
END;
		$arh->decode_response(true,$form);
	}
	
	function display_boxes($linked_events) {
		$db =& $this->db;
		
		$lic_displays = DISPLAYS; //the licensed displays
		
		if ($linked_events != "") { //if there is a linked event given (i.e. we are on an edit)
			$linked_displayids = $this->getDisplaysForEvent($linked_events);
		}
		else {
			$linked_displayids[] = $this->displayid;
		}
			
		$SQL = <<<SQL
		SELECT	displayid, 
				display 
		FROM	display 
		WHERE	licensed = 1
		ORDER BY display	
SQL;
	
		//get the displays
		if (!$result = $db->query($SQL)) {
			trigger_error("Can not get the displays from the database.", E_USER_ERROR);
		}
	
		if($db->num_rows($result) < 1) {
			$boxes = "No displays available";
			return $boxes;
		}
		
		$input_fields = "";
		
		while ($row = $db->get_row($result)) {
		
			$displayid = $row[0];
			$display = $row[1];
			
			if (in_array($displayid, $linked_displayids)) {
				$input_fields .= "<option value='$displayid' selected>$display</option>";
			}
			else {
				$input_fields .= "<option value='$displayid'>$display</option>";
			}
		}
		$boxes = <<<END
		<select name='displayids[]' MULTIPLE SIZE=6>
		$input_fields
END;
		return $boxes;
	}
	
	function generate_calendar() {
		$db =& $this->db;
		
		// Gather variables from
		// user input and break them
		// down for usage in our script
		if(!isset($_REQUEST['date'])){
		   $date = mktime(0,0,0,date('m'), date('d'), date('Y'));
		} else {
		   $date = clean_input($_REQUEST['date'], VAR_FOR_SQL, $db);
		}
		
		$day	= date('d', $date);
		$month	= date('m', $date);
		$year	= date('Y', $date);
		
		// Get the first day of the month
		$month_start = mktime(0,0,0,$month, 1, $year);
		
		// Get friendly month name
		$month_name = date('M', $month_start);
		
		// Figure out which day of the week
		// the month starts on.
		$month_start_day = date('D', $month_start);
		
		switch($month_start_day){
		    case "Sun": $offset = 0; break;
		    case "Mon": $offset = 1; break;
		    case "Tue": $offset = 2; break;
		    case "Wed": $offset = 3; break;
		    case "Thu": $offset = 4; break;
		    case "Fri": $offset = 5; break;
		    case "Sat": $offset = 6; break;
		}
		
		// determine how many days are in the last month.
		if($month == 1){
		   $num_days_last = cal_days_in_month(0, 12, ($year -1));
		} else {
		   $num_days_last = cal_days_in_month(0, ($month -1), $year);
		}
		
		// determine how many days are in the current month.
		$num_days_current = cal_days_in_month(0, $month, $year);
		
		// Build an array for the current days
		// in the month
		for($i = 1; $i <= $num_days_current; $i++){
		    $num_days_array[] = $i;
		}
		
		// Build an array for the number of days
		// in last month
		for($i = 1; $i <= $num_days_last; $i++){
		    $num_days_last_array[] = $i;
		}
		
		// If the $offset from the starting day of the
		// week happens to be Sunday, $offset would be 0,
		// so don't need an offset correction.
		if($offset > 0){
		    $offset_correction	= array_slice($num_days_last_array, -$offset, $offset);
		    $new_count			= array_merge($offset_correction, $num_days_array);
		    $offset_count		= count($offset_correction);
		}
		else { // The else statement is to prevent building the $offset array.
		    $offset_count	= 0;
		    $new_count		= $num_days_array;
		}
		
		// count how many days we have with the two
		// previous arrays merged together
		$current_num = count($new_count);
		
		// Since we will have 5 HTML table rows (TR)
		// with 7 table data entries (TD)
		// we need to fill in 35 TDs
		// so, we will have to figure out
		// how many days to appened to the end
		// of the final array to make it 35 days.
		if($current_num > 35){
		   $num_weeks = 6;
		   $outset = (42 - $current_num);
		} elseif($current_num < 35){
		   $num_weeks = 5;
		   $outset = (35 - $current_num);
		}
		if($current_num == 35){
		   $num_weeks = 5;
		   $outset = 0;
		}
		// Outset Correction
		for($i = 1; $i <= $outset; $i++){
		   $new_count[] = $i;
		}
		
		// Now let's "chunk" the $all_days array
		// into weeks. Each week has 7 days
		// so we will array_chunk it into 7 days.
		$weeks = array_chunk($new_count, 7);
		
		
		// Build Previous and Next Links
		$previous_link = "<a href=\"index.php?p=schedule&sp=month&displayid=$this->displayid&date=";
		if($month == 1){
		   $previous_link .= mktime(0,0,0,12,$day,($year -1));
		} else {
		   $previous_link .= mktime(0,0,0,($month -1),$day,$year);
		}
		$previous_link .= "\"><< Prev</a>";
		
		$next_link = "<a href=\"index.php?p=schedule&sp=month&displayid=$this->displayid&date=";
		if($month == 12){
		   $next_link .= mktime(0,0,0,1,$day,($year + 1));
		} else {
		   $next_link .= mktime(0,0,0,($month +1),$day,$year);
		}
		$next_link .= "\">Next >></a>";
		
		// Build the heading portion of the calendar table
		echo "<table class=\"calendar\">\n".
		     "<tr class='month_nav'>\n".
			     "<td colspan=\"7\">\n".
				     "<table class='calendar_inner'>\n".
				     "<tr>\n".
				     "<td colspan=\"2\">$previous_link</td>\n".
				     "<td colspan=\"3\">$month_name $year</td>\n".
				     "<td colspan=\"2\">$next_link</td>\n".
				     "</tr>\n".
				     "</table>\n".
			     "</td>\n".
		     "<tr class='heading'>\n".
				"<td>S</td><td>M</td><td>T</td><td>W</td><td>T</td><td>F</td><td>S</td>\n".
		     "</tr>\n";
		
		// Now we break each key of the array 
		// into a week and create a new table row for each
		// week with the days of that week in the table data
		
		$i = 0;
		foreach($weeks AS $week) {
	    	echo "<tr>\n";
			$count = 0; //so we know which day we are on
			
	    	foreach($week as $d) {
	    		$count++;
				
	        	if($i < $offset_count) {
	            	echo "<td class=\"nonmonthdays\">$d</td>\n";
	        	}
	        	
	        	if(($i >= $offset_count) && ($i < ($num_weeks * 7) - $outset)) {
	            	/*Prepare the day link*/
					$day_clicked = mktime(date("H"),date("i"),0,$month,$d,$year);
					
					$day_link = "<a class='day_label' onclick=\"day_clicked('$day_clicked', $this->displayid)\">$d</a>";
	           		$day_link .= $this->get_events_between_dates(mktime(0,0,0,$month,$d,$year),mktime(0,0,0,$month,$d+1,$year),$count);
	           		
					//add the double click listeners					
	           		if(mktime(0,0,0,date("m"),date("d"),date("Y")) == mktime(0,0,0,$month,$d,$year)) {
	               		echo "<td ondblclick=\"day_clicked('$day_clicked', $this->displayid)\" class=\"today\">$day_link</a></td>\n";
	           		} 
	           		else {
	               		echo "<td ondblclick=\"day_clicked('$day_clicked', $this->displayid)\" class=\"days\">$day_link</td>\n";
	           		}
	        	} 
	        	elseif($outset > 0) {
	            	
	            	if(($i >= ($num_weeks * 7) - $outset)){
	               		echo "<td class=\"nonmonthdays\">$d</td>\n";
	           		}
	        	}
	        	$i++;
	      	}
	      	echo "</tr>\n";   
		}
		
		// Close out your table and that's it!
		echo '</table>';
	}
	
	function generate_day_view() {
		$db =& $this->db;
		
		$date		= $this->start_date;
		
		//For the day view the date actually wants to be the beginning of the day month year given
		$date		= mktime(0,0,0,date("m",$date),date("d", $date),date("Y",$date));
		
		$next_day	= mktime(0,0,0,date("m",$date),date("d", $date)+1,date("Y",$date));
		
		$query_date = date("Y-m-d H:i:s", $date);
		$query_next_day = date("Y-m-d H:i:s", $next_day);
		
		$day_text = date("d-m-Y", $date);
		
		/**
		 * This is a view of a single day (with all events listed in a table)
		 */
		$SQL = "";
        $SQL.= "SELECT schedule_detail.schedule_detailID, ";
        $SQL.= "       schedule_detail.starttime, ";
        $SQL.= "       schedule_detail.endtime,";
        $SQL.= "       layout.layout, ";
        $SQL.= "       UNIX_TIMESTAMP(schedule_detail.starttime) as timestart,";
        $SQL.= "       UNIX_TIMESTAMP(schedule_detail.endtime) as timeend,";
        $SQL.= "       schedule_detail.userid, ";
        $SQL.= "       schedule_detail.is_priority ";
        $SQL.= "  FROM schedule_detail ";
        $SQL.= "  INNER JOIN layout ON layout.layoutID = schedule_detail.layoutID ";
        $SQL.= " WHERE 1=1 ";
        $SQL.= "   AND schedule_detail.displayid = $this->displayid ";
        
        //Events that fall inside the two dates
        $SQL.= "   AND schedule_detail.starttime < '$query_next_day' ";
        $SQL.= "   AND schedule_detail.endtime   >  '$query_date'";
        
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
		
		for ($i=0; $i<24; $i++) {
			
			$h = $i;
			if ($i<10) $h = "0$i";
			
			$full_time = mktime(0+$i,0,0,date("m",$date),date("d", $date),date("Y",$date));	
			
    		$table_html .= "<th>$h</th>";
		}
		
    	$table_html .= "</tr>";    
      	
      	  
        while($row = $db->get_row($result)){
            $schedule_detailid = $row[0];
            $starttime = date("H:i",$row[4]);
            $endtime = date("H:i",$row[5]);
            $name = $row[3];
            $times = date("H:i",$row[4])."-".date("H:i",$row[5]);
            $userid = $row[6];
            $is_priority = $row[7];
            
			$start_row = $starttime;
			if($row[4]<$date) {
				$start_row = "Earlier Day";
			}
			
			$end_row = $endtime;
			if ($row[5]>$next_day) {
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
			$start_hour = date("H",$row[4]);
			$end_hour   = date("H",$row[5]);
			
			/*
	         * We need to work out:
	         * 1. whether the record we've got is within the dates supplied (OR)
	         * 2. whether the record is outside of these dates
	         */
            if ($row[4] < $date) { //if 'the event start date' < 'the we are on'
            	$start_hour = 0;
            }
            
			//if the event goes over this day, then the end hour will be the total_cols shown
            if ($row[5] >= $next_day) {
            	$end_hour = $total_cols;
            }
			
			/*
			 * We are ready to work it out
			 */
			if ($start_hour != 0) { //if the start_hour is 0 we dont bother with the beginning
				for($i=0;$i<$start_hour;$i++) { //go from the first column, until the start hour
					$table_html .= "<td></td>";
					$total_cols--;
				}
			}
			
			$colspan = 0;
			for ($i = $start_hour; $i < $end_hour; $i++) {
				$colspan++;
				$total_cols--;
			}
			
			if ($colspan == 0) $colspan = "";
			
			$onclick = "onclick=\"return init_button(this,'Edit Event',exec_filter_callback, init_callback)\"";
			$link = "<a class='event' title='Load this event into the form for editing' href='index.php?p=schedule&sp=edit&q=display_form&id=$schedule_detailid&date=$this->start_date&displayid=$this->displayid' $onclick>Edit</a>";
			$class = "busy";
			
			if ($userid != $_SESSION['userid']) {
				$class = "busy_no_edit";
			}
			if ($userid != $_SESSION['userid']&& $_SESSION['usertype']!=1) {
				$link = "";
			}
			if ($is_priority == 1) {
				$class = "busy_has_priority";
			}
			
			$table_html .= "<td class='$class' colspan='$colspan'>" .
					"$link</td>";
			
			if ($total_cols != 1) {
				while ($total_cols > 0) {
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
	 * Gets the events for a specified date (returned as HTML string)
	 */
	function get_events_between_dates($date_start, $date_end, $day_number_in_week) {
		$db =& $this->db;
		
		$last_day = $this->last_day;
		
		$date = date("Y-m-d H:i:s", $date_start);
		$next_day = date("Y-m-d H:i:s", $date_end);
		$prev_day = date("Y-m-d H:i:s",mktime(0,0,0,date("m",$date_start),date("d", $date_start)-1,date("Y",$date_start)));
	
		$html_events_string = "";
		
		$SQL = "";
        $SQL.= "SELECT schedule_detail.schedule_detailID, ";
        $SQL.= "       schedule_detail.starttime, ";
        $SQL.= "       schedule_detail.endtime,";
        $SQL.= "       layout.layout, ";
        $SQL.= "       UNIX_TIMESTAMP(schedule_detail.starttime) as timestart,";
        $SQL.= "       UNIX_TIMESTAMP(schedule_detail.endtime) as timeend, ";
        $SQL.= "       CASE WHEN schedule_detail.starttime < '$date' OR schedule_detail.endtime" .
        		" > '$next_day' THEN 1 ELSE 0 END AS Prev_day, ";
        $SQL.= "       schedule_detail.userid ";
        $SQL.= "  FROM schedule_detail ";
        $SQL.= "  INNER JOIN layout ON layout.layoutID = schedule_detail.layoutID ";
        $SQL.= " WHERE 1=1 ";
        $SQL.= "   AND schedule_detail.displayid = $this->displayid ";
        
        //Events that fall inside the two dates
        $SQL.= "   AND schedule_detail.starttime < '$next_day' ";
        $SQL.= "   AND schedule_detail.endtime   >  '$date'";
        
        //Ordering
        $SQL.= " ORDER BY 7 DESC, 2,3";

		//echo $SQL;
		
        $result = $db->query($SQL) or trigger_error($db->error(), E_USER_ERROR);
        
		/*
		 * Define some colors:
		 */
		$color[1] = "style='background:#09A4F8;border-left: 1px solid #09A4F8'";
		$color[2] = "style='background:#8AB9BA;border-left: 1px solid #8AB9BA'";
		$color[3] = "style='background:#86A2BA;border-left: 1px solid #86A2BA'";
		
		$multi_day_count = 0;
        $count = 1;
		$pad = true;
		
		//debug
		/*if ($date == '2007-06-16 00:00:00') {
		echo "<pre>";print_r($last_day);echo "</pre>";}*/
		
        while(($row = $db->get_row($result)) && $count < 4) {
			/* Info for this day */
            $schedule_detailid = $row[0];
            $starttime = $row[4];
            $endtime = $row[5];
            $name = $row[3];
            $times = date("H:i",$row[4])."-".date("H:i",$row[5]);
			$multi_day = $row[6];
			$userid = $row[7];
			$event_text = $name;
			
			/* Are our events spanning multiple days?*/
            if ($multi_day == 1) {
				$multi_day_count++;

				if ($day_number_in_week != 1 && $pad) {
					/*
					 * How can we tell if the last day had any padding on it!
					 * If it did, we want to add however much padding the last day had, again - we can make use of a class variable here i think
					 * otherwise we would have to re-run the query again
					 */
					if (!isset($last_day['multi_day_count'])) $last_day['multi_day_count'] = 0;
					 /*
					  * If we have events spanning multiple days We want to look back one day, for any events that span multiple days that ended on the last day
					  * - if there were any, we will need to pad out our current event (so that they line up again)
					  */
					
					/*if ($last_day[$count]['layoutdisplayid']==$layoutdisplayid && $count <= $last_day['multi_day_count']) {
						//do nothing
						$event_text = "-";
					}*/
					
					if ($count < $last_day['multi_day_count']) {
					
						if (isset($last_day[$count]['schedule_detailid'])) {							
							//we now know that there was an event in this slot last time around!
							if ($last_day[$count]['schedule_detailid']==$schedule_detailid) {
								//do nothing
								//$event_text = "-";
							}
							elseif ($last_day[$count]['end'] < $date_start) {
								//if it ended, we want to pad out this event
								//unless the current event does not belong to the next one down!
								if ($last_day[$count]['schedule_detailid']==0) {
									$html_events_string .= "<a class='pad'>Pad</a>";
									
									//$event_text = "-";
									
									$this->last_day[$count]['schedule_detailid'] = 0;
									$count++;
								}
								
								if ($layoutdisplayid == $last_day[$count+1]['schedule_detailid']) {
									$html_events_string .= "<a class='pad'>Pad</a>";
									
									$this->last_day[$count]['schedule_detailid'] = 0;
									$count++;
								}
							}
						}	
					}
				}
				
				$link = "href='index.php?p=schedule&sp=edit&q=display_form&id=$schedule_detailid&date=$date_start&displayid=$this->displayid'";
				$onclick = "onclick=\"return init_button(this,'Edit Event',exec_filter_callback, edit_event_callback)\"";
				
				if ($userid != $_SESSION['userid'] && $_SESSION['usertype']!=1) {
					$link = " ";
					$onclick = "onclick=\"alert('Owned by another user')\"";
				}
				$html_events_string .= "<a class='long_event' ".$color[$count]." $link $onclick>";
				$html_events_string .= "$event_text";
			
				//record the current days events
				$this->last_day[$count]['schedule_detailid'] = $schedule_detailid;
				$this->last_day[$count]['start'] = $starttime;
				$this->last_day[$count]['end'] = $endtime;
				
				$this->last_day['multi_day_count']=$multi_day_count;
            }
            else { //no spanning, event is contained within this day
				$link = "href='index.php?p=schedule&sp=edit&q=display_form&id=$schedule_detailid&date=$date_start&displayid=$this->displayid'";
				$onclick = "onclick=\"return init_button(this,'Edit Event',exec_filter_callback, edit_event_callback)\"";
			
				if ($userid != $_SESSION['userid'] && $_SESSION['usertype']!=1) {
					$link = "";
					$onclick = "onclick=\"alert('Owned by another user')\"";
				}
				
				$html_events_string .= "<a class='event' $link $onclick>";
            	$html_events_string .= date("H:i",$row[4])." - ".$name;
            }
			$html_events_string .= "</a>";
            
            $count++;
        }

        $num_rows = $db->num_rows($result);
        if ($num_rows > 3) {
        	$num_rows = $num_rows - 3;	
        
        	$html_events_string .= "+$num_rows More";
        }

		return $html_events_string;
	}
	
	/**
	 * Displays a Date Time selector
	 *  params:
	 * 		datetime = the default datetime
	 * 		selectName = the select name
	 */
	function datetime_select($datetime, $selectName, $dropdowns = false) {
        //$datetime is in TIMESTAMP format

        $y = date("Y", $datetime);
        $m = date("m", $datetime);
        $d = date("d", $datetime);
        $h = date("H", $datetime);
        $mi = date("i", $datetime);
        $s = date("s", $datetime);
		
		$date = date("d/m/Y", $datetime);
		$date_name = $selectName . "_date";
        
        $d_name = $selectName . "_d";
        $m_name = $selectName . "_m";
        $y_name = $selectName . "_y";
        $h_name = $selectName . "_h";
        $i_name = $selectName . "_i";

		$return ="";
		
		if ($dropdowns) {
		
			/* Days */
			$return.= "<select class='date' name=".$d_name.">";
	        for ($i = 1; $i <= 31; $i++) {
	        	
	        	$value = $i;
	        	if ($value < 10) $value = "0$value";
	        		
	        	if ($i == $d) {
	        		$return.= "<option value=$i selected>$value</option>";
	        	}
	        	else {
	        		$return.= "<option value=$i>$value</option>";
	        	}
	        }
	        $return.= "</select>";
			
			/* Months */
			$return.= "<select class='date' name=".$m_name.">";
	        for ($i = 1; $i <= 12; $i++) {
	        	
	        	$value = $i;
	        	if ($value < 10) $value = "0$value";
	        		
	        	if ($i == $m) {
	        		$return.= "<option value=$i selected>$value</option>";
	        	}
	        	else {
	        		$return.= "<option value=$i>$value</option>";
	        	}
	        }
	        $return.= "</select>";
	        
	        /* Years */
			$return.= "<select class='date' name=".$y_name.">";
			$count = 1; $i = $y;
	        while ($count < 3) {

	        	if ($i == $y) {
	        		$return.= "<option value=$i selected>$i</option>";
	        	}
	        	else {
	        		$return.= "<option value=$i>$i</option>";
	        	}
	        	
	        	$i++;
	        	$count++;
	        }
	        $return.= "</select>";
		}
		else {
			$return .= "<input type=\"text\" id=\"$selectName\" class=\"date-pick\" value=\"$date\" name=\"$date_name\">";
		}
			
		$return.="<div class='hour_select'><input class=\"date\" type=\"text\" value=\"$h\" name=\"$h_name\">: ";
		$return.="<input class=\"date\" type=\"text\" value=\"$mi\" name=\"$i_name\"></div>";

        return $return;
    }
    
    function add() {
		$db =& $this->db;
		
		//ajax request handler
		$arh = new AjaxRequest();

		$userid 				= $_SESSION['userid'];
		$layoutid				= clean_input($_POST['layoutid'], VAR_FOR_SQL, $db);
		$_SESSION['layoutid']	= $layoutid; //set the session to default layout forms to this layout
		
		//Validate layout
		if ($layoutid == "") {
			$arh->decode_response(false,"No layout selected");
		}
		
		//check that at least one display has been selected
		if (!isset($_POST['displayids'])) {
			$arh->decode_response(false,"No display selected");
		}
		$displayid_array = $_POST['displayids'];
		
		//get the dates and times
		$start_date = explode("/",$_POST['starttime_date']); //		dd/mm/yyyy
		$start_h	= $_POST['starttime_h'];
		$start_i	= $_POST['starttime_i'];
		
		$starttime_timestamp = strtotime($start_date[1] . "/" . $start_date[0] . "/" . $start_date[2] . " ".$start_h.":".$start_i);
		$starttime = date("Y-m-d H:i:s", $starttime_timestamp);

		$end_date = explode("/",$_POST['endtime_date']); //			dd/mm/yyyy
		$end_h = $_POST['endtime_h'];
		$end_i = $_POST['endtime_i'];
		
		$endtime_timestamp = strtotime($end_date[1] . "/" . $end_date[0] . "/" . $end_date[2] . " ".$end_h.":".$end_i);
		$endtime = date("Y-m-d H:i:s", $endtime_timestamp);
		
		//validate the dates
		if ($endtime_timestamp < $starttime_timestamp) {
			$arh->decode_response(false, "Can not have an end time earlier than your start time");	
		}
		if ($starttime_timestamp < (time()- 86400)) {
			$arh->decode_response(false, "$starttime is in the past. <br/>Can not schedule events in the past");
		}
		
		//
		//recurrence
		//
		$rec_type 	= $_REQUEST['rec_type'];
		$rec_detail	= $_REQUEST['rec_detail'];
		$rec_range_array = explode("/",$_REQUEST['rec_range_date']); // dd/mm/yyyy
		$rec_range_h = $_REQUEST['rec_range_h'];
		$rec_range_i = $_REQUEST['rec_range_i'];
		
		$rec_range_timestamp = strtotime($rec_range_array[1] . "/" . $rec_range_array[0] . "/" . $rec_range_array[2] . " ".$rec_range_h.":".$rec_range_i);
		$rec_range = date("Y-m-d H:i:s", $rec_range_timestamp);
		
		//
		// we are all set to enter this record into the schedule table
		//
		$displayid_list = implode(",",$displayid_array); //make the displayid_list from the selected displays.
		$count 			= count($displayid_array); //count how many there are for the message
		
		//if there is no recurrence then NULL those fields for this insert
		if ($rec_type == "null") {
			$SQL = "INSERT INTO schedule (layoutid, displayID_list, start, end, userID, is_priority) ";
			$SQL .= " VALUES ($layoutid, '$displayid_list', '$starttime', '$endtime', $userid, 0) ";
		}
		else {
			$SQL = "INSERT INTO schedule (layoutid, displayID_list, start, end, userID, is_priority, recurrence_type, recurrence_detail, recurrence_range) ";
			$SQL .= " VALUES ($layoutid, '$displayid_list', '$starttime', '$endtime', $userid, 0, '$rec_type', '$rec_detail', '$rec_range') ";
		}
		
		if (!$eventid = $db->insert_query($SQL)) {
			trigger_error("Cant insert into the schedule" . $db->error());
			$arh->decode_response(false,"Cant insert into the schedule");
		}
		
		//
		// assign the relevent layoutdisplay records for this event
		//
		$this->setlayoutDisplayRecords($eventid);
		
		//we are done
		$arh->response(AJAX_SUCCESS_REFRESH,"The layout has been assigned on $count displays at ".$starttime);

		return false;
	}
	
	function edit() {
		$db =& $this->db;
		
		//ajax request handler
		$arh = new AjaxRequest();
		
		$userid 				= $_SESSION['userid'];
		
		//check that at least one display has been selected
		if (!isset($_POST['displayids'])) {
			$arh->decode_response(false,"No display selected");
		}
		$displayid_array	= $_POST['displayids'];
		
		$schedule_detailid	= clean_input($_POST['schedule_detailid'], VAR_FOR_SQL, $db);
		$layoutid			= clean_input($_POST['layoutid'], VAR_FOR_SQL, $db);

		//
		// Get the link update setting, this is quite important as it effects how we edit the event
		//
		$linkupdate			= $_REQUEST['linkupdate']; //this is either all or single
		
		$is_priority = 0;
		if (isset($_POST['is_priority'])) {
			$is_priority = 1;
		}
		
		//Validation
		if ($layoutid == "") {
			$arh->decode_response(false,"No layout selected");
		}
		
		$_SESSION['layoutid'] = $layoutid;
		
		//get the dates and times
		$start_date = explode("/",$_POST['starttime_date']); //		dd/mm/yyyy
		$start_h = $_POST['starttime_h'];
		$start_i = $_POST['starttime_i'];
		
		$starttime_timestamp = strtotime($start_date[1] . "/" . $start_date[0] . "/" . $start_date[2] . " ".$start_h.":".$start_i);
		$starttime = date("Y-m-d H:i:s", $starttime_timestamp);
		
		$end_date = explode("/",$_POST['endtime_date']); //			dd/mm/yyyy
		$end_h = $_POST['endtime_h'];
		$end_i = $_POST['endtime_i'];
		
		$endtime_timestamp = strtotime($end_date[1] . "/" . $end_date[0] . "/" . $end_date[2] . " ".$end_h.":".$end_i);
		$endtime = date("Y-m-d H:i:s", $endtime_timestamp);
		
		//Validation
		if ($endtime_timestamp < $starttime_timestamp) {
			$arh->decode_response(false,"Can not have an end time earlier than your start time");	
		}
		
		//we only want to check this if the starttime has been edited
		$SQL = "SELECT UNIX_TIMESTAMP(starttime), displayid FROM schedule_detail WHERE schedule_detailid = $schedule_detailid ";
		if (!$results = $db->query($SQL)) trigger_error($db->error(),E_USER_ERROR);
		
		$row = $db->get_row($results);
		$original_start 	= $row[0];
		$pd_displayid 		= $row[1];
		
		if ($starttime_timestamp < (time()- 86400) && $original_start != $starttime_timestamp) {
			$arh->decode_response(false,"Can not schedule events in the past");
		}
		
		//
		//get the linked_event id for this record
		//
		$SQL =  "SELECT schedule_detail.eventID, UNIX_TIMESTAMP(schedule.start) AS start, schedule.start, schedule.end ";
		$SQL .= " FROM schedule_detail INNER JOIN schedule ON schedule.eventID = schedule_detail.eventID ";
		$SQL .= " WHERE schedule_detailid = $schedule_detailid ";
		
		if(!$res_dups = $db->query($SQL)) {
			$arh->decode_response(false,"Can not get duplicate events");			
		}
		
		$row = $db->get_row($res_dups);
		$linked_event_id 	= $row[0]; //the event id to update with
		$t_schedule_start 	= $row[1]; //the event id to update with
		$schedule_start 	= $row[2]; //the event id to update with
		$schedule_end 		= $row[3]; //the event id to update with
		
		$displayid_list = implode(",",$displayid_array); //make the displayid_list from the selected displays.
		
		//
		//recurrence
		//
		$rec_type 	= $_REQUEST['rec_type'];
		$rec_detail	= $_REQUEST['rec_detail'];
		$rec_range_array = explode("/",$_REQUEST['rec_range_date']); // dd/mm/yyyy
		$rec_range_h = $_REQUEST['rec_range_h'];
		$rec_range_i = $_REQUEST['rec_range_i'];
		
		$rec_range_timestamp = strtotime($rec_range_array[1] . "/" . $rec_range_array[0] . "/" . $rec_range_array[2] . " ".$rec_range_h.":".$rec_range_i);
		$rec_range = date("Y-m-d H:i:s", $rec_range_timestamp);
		
		//
		//Construct the update
		// We have some choices: $linkupdate is either all or single
		//		all: we can just update the schedule
		//		single: Clone the schedule for only this layoutdisplays display id,
		//				update the old schedule - removing this displayid from it
		
		
		// If the starttime from the layoutdisplay is the same as the start time for this schedule
		if ($original_start != $t_schedule_start || $linkupdate == "single") {
			//we split the record
			if ($linkupdate == "single") {
				//we want to update the schedule record, but remove this display from the list
				//remove $displayid from $displayid_list
				foreach($displayid_list as $displayid_orig) {
					if ($pd_displayid != $displayid_orig) {
						$displayid_list_new[] = $displayid_orig;
					}
				}
				
				//make a new list from the altered array
				$displayid_list_new = implode(",", $displayid_list_new);
			}
			else {
				$pd_displayid = $displayid_list;
			}
			
			$SQL = "INSERT INTO schedule (layoutID, displayID_list, start, end, userID, is_priority, recurrence_type, recurrence_detail, recurrence_range) ";
			$SQL .= " VALUES ($layoutid, '$pd_displayid', '$starttime', '$endtime', $userid, $is_priority, '$rec_type', '$rec_detail', '$rec_range') ";
			
			if (!$eventid = $db->insert_query($SQL)) {
				trigger_error("Cant insert into the schedule" . $db->error());
				$arh->decode_response(false,"Cant insert into the schedule");
			}
			
			//
			// assign the relevent layoutdisplay records for this event
			//
			$this->setlayoutDisplayRecords($eventid);
			
			if ($linkupdate == "single") {
				//update the old record with the new display list
				$displayid_list == $displayid_list_new;
			}
			if ($original_start != $t_schedule_start) {
				//update the old record with the range of this records start date and the orignial start and end times
				$rec_range = $starttime;
				$starttime = $schedule_start;
				$endtime = $schedule_end;								
			}
		}
		
		//we should be all set to update the original record now
		$SQL = " UPDATE schedule SET start = '$starttime', end = '$endtime', displayID_list = '$displayid_list', layoutID = $layoutid, ";
		$SQL .= " is_priority = $is_priority, ";
		if ($rec_type == "null") {
			$SQL .= " recurrence_type = NULL, recurrence_detail = NULL, recurrence_range = NULL";
		}
		else {
			$SQL .= " recurrence_type = '$rec_type', recurrence_detail = '$rec_detail', recurrence_range = '$rec_range'";
		}
		$SQL .= " WHERE eventID = $linked_event_id ";			
		
		if (!$db->query($SQL)) {
			trigger_error("Cant Update into the schedule" . $db->error());
			$arh->decode_response(false,"Cant update the schedule");
		}
		
		//
		// assign the relevent layoutdisplay records for this event
		//
		$this->setlayoutDisplayRecords($linked_event_id);
				
		$arh->response(AJAX_SUCCESS_REFRESH,"The Event has been edited.");
		return false;
	}

	function remove() {
		$db =& $this->db;
		
		//ajax request handler
		$arh = new AjaxRequest();

		$displayid			= clean_input($_REQUEST['displayid'], VAR_FOR_SQL, $db);
		$schedule_detailid	= clean_input($_REQUEST['schedule_detailid'], VAR_FOR_SQL, $db);
		
		$linkupdate			= $_REQUEST['linkupdate']; //this is either all or single
		
		//get the linked_event id for this record
		$SQL =  "SELECT schedule_detail.eventID, displayID_list FROM schedule_detail INNER JOIN schedule ON schedule_detail.eventID = schedule.eventID ";
		$SQL .= "WHERE schedule_detailid = $schedule_detailid ";
		
		if(!$res_dups = $db->query($SQL)) {
			$arh->decode_response(false,"Can not get duplicate events");			
		}
		
		$row = $db->get_row($res_dups);
		
		$linked_event_id = $row[0]; //the event id to update with
		$displayid_list	 = explode(",",$row[1]);
		
		switch ($linkupdate) {
			
			case "all":
				//we want to delete all the layout display records with the linked_events of $linked_event_id
				$SQL = "DELETE FROM schedule_detail WHERE eventID = $linked_event_id ";
				if (!$db->query($SQL)) {
					trigger_error("Error removing all layout display records. " .$db->error());
					$arh->decode_response(false, "Error removing this event");
				}
				
				//and then we want to delete the schedule record itself
				$SQL = "DELETE FROM schedule WHERE eventID = $linked_event_id ";
				if (!$db->query($SQL)) {
					trigger_error("Error removing one schedule record. " .$db->error());
					$arh->decode_response(false, "Error removing this event");
				}
				
				break;
				
			case "single":
				//we want to update the schedule record, but remove this display from the list
				//remove $displayid from $displayid_list
				foreach($displayid_list as $displayid_orig) {
					if ($displayid != $displayid_orig) {
						$displayid_list_new[] = $displayid_orig;
					}
				}
				
				//make a new list from the altered array
				$displayid_list_new = implode(",", $displayid_list_new);
				
				//might leave a bogus schedule record - should really split the schedule when any recurrance is happening...
				
				//run the update
				$SQL = "UPDATE schedule SET displayID_list = '$displayid_list_new' WHERE eventID = $linked_event_id ";
				if (!$db->query($SQL)) {
					trigger_error("Error updating one schedule record. " .$db->error());
					$arh->decode_response(false, "Error removing this event");
				}
				
				//then delete the layoutdisplay record with this schedule_detailid
				$SQL = "DELETE FROM schedule_detail WHERE schedule_detailid = $schedule_detailid ";
				if (!$db->query($SQL)) {
					trigger_error("Error removing one layout display record. " .$db->error());
					$arh->decode_response(false, "Error removing this event");
				}
			
				break;
		}

		$arh->response(AJAX_SUCCESS_REFRESH,"The event has been Removed.");

		return false;
	}
	
	function setlayoutDisplayRecords($eventid) {
		$db =& $this->db;
		
		//run a query to get info about this particular event
		$SQL = <<<END
		SELECT layoutID,
			displayID_list,
			UNIX_TIMESTAMP(start) AS start,
			UNIX_TIMESTAMP(end) AS end,
			userID,
			is_priority,
			recurrence_type,
			recurrence_detail,
			UNIX_TIMESTAMP(recurrence_range) AS recurrence_range
		FROM schedule
		WHERE eventID = $eventid
END;

		if (!$results = $db->query($SQL)) {
			trigger_error($db->error());
			trigger_error("Cant get Event Information", E_USER_ERROR);
		}
		
		//get the rows
		$row = $db->get_row($results);
		
		$layoutid = $row[0];
		$displayid_list = $row[1];
		$t_start	= $row[2];
		$t_end		= $row[3];
		$userid 	= $row[4];
		$ispriority = $row[5];
		$rec_type	= $row[6];
		$rec_detail = $row[7];
		$t_rec_range= $row[8];
		
		$displayid_array = explode(",", $displayid_list);
		
		//first delete all the schedule_detail records for this event
		$SQL = "DELETE FROM schedule_detail WHERE eventID = $eventid ";
		$db->query($SQL) or trigger_error($db->error(),E_USER_ERROR);

		//we now need to deal with inserting the schedule_detail records, one for each display / recurrence
		foreach ($displayid_array as $displayid) {

			//we have no recurrence and therefore set the dates and enter one schedule_detail per display
			$start 	= date("Y-m-d H:i:s", $t_start);
			$end 	= date("Y-m-d H:i:s", $t_end);
			
			//do the insert
			$sql = "INSERT INTO schedule_detail (displayID, layoutID, starttime, endtime, userID, is_priority, eventID)";
			$sql .= "VALUES ($displayid, $layoutid, '$start', '$end', $userid, $ispriority, $eventid)";

			$db->query($sql) or trigger_error($db->error(),E_USER_ERROR);
				
			if ($rec_type != "") {
				//set the temp starts
				$t_start_temp 	= $t_start;
				$t_end_temp 	= $t_end;
				
				//loop until we have added the recurring events for the schedule
				while ($t_start_temp < $t_rec_range) {
					//add the appropriate time to the start and end
					switch ($rec_type) {
						case 'Hour':
							$t_start_temp = mktime(date("H", $t_start_temp)+$rec_detail, date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp), date("Y", $t_start_temp));
							$t_end_temp = mktime(date("H", $t_end_temp)+$rec_detail, date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp), date("Y", $t_end_temp));
							break;
							
						case 'Day':
							$t_start_temp = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp)+$rec_detail, date("Y", $t_start_temp));
							$t_end_temp = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp)+$rec_detail, date("Y", $t_end_temp));
							break;
							
						case 'Week':
							$t_start_temp = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp)+($rec_detail*7), date("Y", $t_start_temp));
							$t_end_temp = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp)+($rec_detail*7), date("Y", $t_end_temp));
							break;
							
						case 'Month':
							$t_start_temp = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp)+$rec_detail ,date("d", $t_start_temp), date("Y", $t_start_temp));
							$t_end_temp = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp)+$rec_detail ,date("d", $t_end_temp), date("Y", $t_end_temp));
							break;
							
						case 'Year':
							$t_start_temp = mktime(date("H", $t_start_temp), date("i", $t_start_temp), date("s", $t_start_temp) ,date("m", $t_start_temp) ,date("d", $t_start_temp), date("Y", $t_start_temp)+$rec_detail);
							$t_end_temp = mktime(date("H", $t_end_temp), date("i", $t_end_temp), date("s", $t_end_temp) ,date("m", $t_end_temp) ,date("d", $t_end_temp), date("Y", $t_end_temp)+$rec_detail);
							break;
					}
					
					//after we have added the appropriate amount, are we still valid
					if ($t_start_temp > $t_rec_range) {
						break;
					}
					
					//convert them to dates for the insert
					$start 	= date("Y-m-d H:i:s", $t_start_temp);
					$end 	= date("Y-m-d H:i:s", $t_end_temp);
					
					//do the insert
					$sql = "INSERT INTO schedule_detail (displayid, layoutID, starttime, endtime, userID, is_priority, eventID)";
					$sql .= "VALUES ($displayid, $layoutid, '$start', '$end', $userid, $ispriority, $eventid)";
		
					$db->query($sql) or trigger_error($db->error(),E_USER_ERROR);
				}
			}
		}
	}
	
	/**
	 * Shows whats currently on (i.e. which layouts are currently being send to which displays
	 * @return 
	 */
	function whats_on() {
		$db =& $this->db;
		
		global $user;
		
		//validate displays so we get a realistic view of the table
		include_once("lib/app/display.class.php");
		$display = new displayDAO($db);
		$display->validateDisplays();
		
		$currentdate = date("Y-m-d H:i:s");
		
		$SQL  = "SELECT display.display, ";
		$SQL .= "	layout.layout, ";
		$SQL .= "	UNIX_TIMESTAMP(schedule_detail.starttime) AS starttime, ";
		$SQL .= "   UNIX_TIMESTAMP(schedule_detail.endtime) 	AS endtime, ";
		$SQL .= "	schedule_detail.userid, ";
		$SQL .= "	display.loggedin	AS loggedin ";
		$SQL .= "FROM schedule_detail ";
		$SQL .= " INNER JOIN layout ON layout.layoutID = schedule_detail.layoutID ";
		$SQL .= " INNER JOIN display ON display.displayid = schedule_detail.displayid ";
		$SQL .= "WHERE display.licensed = 1 ";
		$SQL.= "   AND schedule_detail.starttime < '$currentdate' ";
        $SQL.= "   AND schedule_detail.endtime   >  '$currentdate'";
		$SQL .= " ORDER BY display.display, layout.layout ";
		
		if(!$results = $db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error("Cannot get current schedule information", E_USER_ERROR);
		} 
		
		$table = <<<END
		<h2>Currently Scheduled</h2>
		<div class="scrollingWindow">
		<table style="width:98%;">
			<tr>
				<th>Display</th>
				<th>Active</th>
				<th>Layout</th>
				<th>Event Start</th>
				<th>Event End</th>
				<th>Scheduled By</th>
			</tr>
END;
		echo $table;

		while ($row = $db->get_row($results)) {
		
			$display = $row[0];
			$layout = $row[1];
			
			$starttime = $row[2];
			if ($starttime!="") $starttime = date("d-m-y H:i",$starttime);
			
			$endtime = $row[3];
			if ($endtime != "") $endtime = date("d-m-y H:i",$endtime);
			
			$loggedin = $row[5];
			if($loggedin==1) {
				$loggedin="<img src=\"img/act.gif\">";
			}
			else {
				$loggedin="<img src=\"img/disact.gif\">";
			}
			
			$username = $user->getNameFromID($row[4]);
			
			$table = <<<END
			<tr>
				<td>$display</td>
				<td>$loggedin</td>
				<td>$layout</td>
				<td>$starttime</td>
				<td>$endtime</td>
				<td>$username</td>
			</tr>
END;
			echo $table;
		}
		
		echo "</table></div>";
	
		return true;
	}
	
	function getDisplaysForEvent($eventid) {
		$db =& $this->db;
		
		//we need to get all the displayid's that are assigned to this linked event
		$SQL = <<<SQL
			SELECT DISTINCT displayID FROM schedule_detail WHERE eventID = $eventid
SQL;
		//get all the displays ids that are for this linked event
		if (!$result = $db->query($SQL)) {
			trigger_error("Can not get the displays from the database.", E_USER_ERROR);
		}
		
		while ($row = $db->get_row($result)) {
			//make a comma seperated list of display ids
			$linked_displayids[] = $row[0];
		}
		
		return $linked_displayids;
	}
}
?>