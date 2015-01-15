<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and / or modify
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
?>
<h2>Schedule Events</h2>
<p>Events are Layouts or Campaigns assigned to Displays at specific dates and times. The Calendar view is used to launch the Add / Edit Event Form.</p>

<p><img class="img-thumbnail" alt="Schedule Event" src="content/schedule/schedule_event.png"></p>

<h3 id="Recurrence">Recurring Events</h3>

<p>The <?php echo PRODUCT_NAME; ?> scheduler features the ability to create recurring events. To set recurrence for events, select the required "Repeats" value from the list and the appropriate additional fields will be revealed.</p>

<p>A scheduled event can be repeated at defined interval (hourly, daily, weekly, monthly or yearly) until the specified date / time.</p>

<h3 id="Priority_Schedule">Priority Events</h3>

<p>Priority schedules are only available to users with Administrator privileges. Priority schedules allow you to add a layout to the schedule that overrides all the other ones that are scheduled. That could be useful for displaying temporary important notices, or overriding the schedule for a specific event without having to cancel the layouts that would normally be running at that time.</p>

<p>When adding a new schedule, or when editing an existing schedule, tick the "Priority" box to make the schedule override the others. You may schedule multiple priority layouts at once. They will be shown in a cycle in the same way as non-priority layouts would be.</p>
	

<h2>Deleting an event</h2>
<p>To delete an event, first open the Schedule Form and then select the "Delete" button from the button bar on the form. The Delete Event form will open allowing confirmation of the Delete.</p>

<p><img class="img-thumbnail" alt="Schedule Event" src="content/schedule/schedule_delete_event.png"></p>
