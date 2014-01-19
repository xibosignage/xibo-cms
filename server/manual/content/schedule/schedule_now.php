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
<h1 id="Schedule_Now">Schedule Now</h1>
<p>Schedule Now functionality is available throughout the CMS and provides a quick way to Schedule a Campaign or Layout for a specific amount of time.</p>

<p>This is typically used for displaying temporary notices on the signage system.</p>
    
<p><img class="img-thumbnail" alt="Schedule Now" src="content/schedule/schedule_now.png"></p>
    
<dl class="dl-horizontal">
  <dt>Duration</dt>
  <dd>How long should this Layout / Campaign be in the Schedule?</dd>
</dl>

<dl class="dl-horizontal">
  <dt>Campaign / Layout</dt>
  <dd>The Campaign or Layout for this event.</dd>
</dl>

<dl class="dl-horizontal">
  <dt>Display Order</dt>
  <dd>If there are other Events scheduled at the same time, which order should this event play in.</dd>
</dl>

<dl class="dl-horizontal">
  <dt>Priority</dt>
  <dd>Should this event have precedence over other non-priority events.</dd>
</dl>

<dl class="dl-horizontal">
  <dt>Groups / Displays</dt>
  <dd>The Displays to schedule this event on.</dd>
</dl>
