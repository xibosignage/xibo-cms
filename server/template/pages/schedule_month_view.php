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
?>
<div id="form_container">
	<div id="form_header">
		<div id="form_header_left">
		</div>
		<div id="form_header_right">
		</div>
	</div>
	
	<div id="form_body">
		<div id="schedule_form_container">
			<div class="leftbuttons">
				<div class="buttons">
					<a id="whatson_button" href="#"><div class="button_text">Broadcasting</div></a>
					<a id="help_button"  href="#" onclick="toggle_div_view('notes')"><div class="button_text">Help</div></a>
				</div>	
					
				<div id="notes" style="display:none;">
					<h4>Schedule Help <a href="http://www.xibo.co.uk/manual/index.php?p=content/schedule/overview" target="_blank" alt="The Manual">(Click for Manual)</a></h4>
					<div id="helptabs">
						<ul id="help" class="tabs-nav"><!-- the class on this element has to remain the same -->
							<li><a href="#cal_div" class="help_tab"><span>Calendar</span></a></li>
							<li><a href="#dayview_div" class="help_tab"><span>Add/Edit</span></a></li>
							<li><a href="#tips_div" class="help_tab"><span>Tips</span></a></li>
							<li><a href="#terms_div" class="help_tab"><span>Terms</span></a></li>	
						</ul>

						<div id="cal_div">
							<h5>Xibo Calendar</h5>
							<p>All scheduling in Xibo can be viewed through the calendar. It shows when playlists are scheduled and on which displays.</p>
							
							<h5>Navigating</h5>
							<p><strong>Dates</strong>: The calendar months can be navigated using the Prev and Next links. They are either side of
							the current month/year indicator and the top of the calendar.</p>
							
							<p><strong>Add</strong>: Events can be added by either <br />
								a) Clicking the heading for a particular day <br />
								b) Double clicking within the day cell <br />
							Either method will open the Add Event form
							</p>
							<p>
								<strong>Edit</strong>: Events can be edited by clicking on the event. This will open up the Edit Event form
							</p>
							
							<h5>Deleting</h5>
							<p>To delete an event first click on the event, and then press the delete button on the "Edit Event" form that opened.</p>
							
							<h5>Changing Displays</h5>
							<p>To change displays click on the required display screen above the calendar.</p>
							
						</div>
						<div id="dayview_div">
							<h5>Opening the Add/Edit form</h5>
							<p>See the "Calendar" section on Navigating for how to open the add/edit form</p>
							
							<h5>Event Details</h5>
							<p>The event details part of this form is found on the upper section. It features start and end dates, 
							playlist selections and display selections.</p>
							
							<p>To pick the correct date and time for start and end time click on the calendar icon next to the field and
							and calendar selector will open.</p>
							
							<p>If you selected the hours from the day page these will have been autopoulated for you to adjust.</p>
							
							<p>You can choose the playlist that you wish to display in this slot from the dropdown menu. This
							list provides you with all the playlists that you have created or have permission to add to the schedule.</p>
							
							<p>The "Save" button will save this event to Xibo with the data entered on the form.</p>
							
							<p>If editing an event the delete button will also be present, allowing the event to be deleted.</p>
							
							<h5>Recurrence</h5>
							<p>The Xibo scheduler features the ability to create recurring events, for example Playlist 1 shown between 10 and 11
							every day for the next two weeks.</p>
							
							<p>To set recurrence for events select the required "Repeats" value from the list and the appropriate additional fields
							will be revealed. Select as appropriate.</p>
							
							<h5>Day View</h5>
							<p>The day view allows you to see everything that is happening on a display for that day. It gives a far
							greater degree of information than the calendar month view. This visualisation makes it easy to see when 
							events will overlap and share time on the screen.</p>
							
							<p>There are a number of additional features for the day view.<br />
							Firstly events shown on the day view can be loaded into the form for editing by clicking on the blocked out time.<br />
							Secondly new events can be added by clicking on start and end times in the last row - and then add new. The start and end
							times will be preloaded in the form that appears.</p>
						</div>
						<div id="tips_div">
							<p>The month view allows you to see all the playlists that have been scheduled on the screen that is highlighted.</p>
							<p>Double clicking in day items section allows you to schedule an event for the whole day quickly.</p>
							<p>You can change which display you are looking at by clicking the relevent display at the top of the page.</p>
							<p>You can edit a specific event directly by clicking on it.</p>
						</div>
						<div id="terms_div">
							<p><strong>Day View</strong> provides a table of the hours within that day for the display specified.</p>
							<p><strong>Start time</strong> states the time at which the content has been scheduled</p>
							<p><strong>End time</strong> states the time at whcih the content will cease being shown</p>
							<p><strong>Day Tab</strong> refers to the blue section of each day on the view.</p>
							<p><strong>Day Items</strong> refers to the area of the day that lists what's being shown</p>
							<p><strong>What's On</strong> provides full details of the playlists being displayed on each display on the system.</p>						
						</div>
					</div>
				</div>	
			</div>
			<div id="displaybuttons">
			<?php echo $displayDAO->display_tabs($this->displayid, "index.php?p=schedule&date=$this->start_date"); ?>
			</div>	
			
			<?php $this->generate_calendar(); ?>
		</div>
	</div>

	<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>
	<div id="whatson_pane" style="position: absolute; display:none">
		<a href='#' id="whatson_refresh">Refresh</a> | <a href="#" id="whatson_close">Close</a>
		<div class="info_table">
			<?php $this->whats_on(); ?>
		</div>
	</div>
</div>	