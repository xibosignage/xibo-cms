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
<h1 id="Scheduling">Overview</h1>
<p><?php echo PRODUCT_NAME; ?> has a sophisticated scheduling system allowing for scheduling Layouts and Campaigns across Displays and Display Groups. This is achieved through Schedule Events and visualised on the Calendar.</p>

<p>Events have the following properties</p>
<ul>
	<li>Events spanning two dates - no maximum or minimum time</li>
	<li>Recurring events</li>
	<li>Priority events</li>
	<li>Schedule an event for more that one Display / Display Group at once</li>
</ul>
	
<p class="alert alert-info">If at any time there are no layouts scheduled to run, the default layout for the Display will be run automatically.</p>


<h2 id="Calendar_View">Calendar View</h2>
<p>The Calendar view is accessed using the Schedule menu item.</p>

<p><img class="img-thumbnail" alt="Calendar" src="content/schedule/calendar_month_view.png"></p>

<p>From the calendar schedule chart you can:</p>
<ul>
	<li>View all the events scheduled for the selected displays managed by <?php echo PRODUCT_NAME; ?>.</li>
	<li>Schedule new events</li>
	<li>Edit existing events</li>
</ul>

<p class="alert alert-info">When a Display Client has more than one Layout scheduled to it at one time it will automatically alternate between the Layouts in the schedule.</p>
	