<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
?>
<h1 id="shellcommand">Shell Command</h1>
<p>The Shell Command module is used to instruct the Display Client to execute a command outside of the <?php echo PRODUCT_NAME; ?> environment, using the operating system shell.</p>

<p>The Shell Command is executed when the Region Timeline item is shown on the Layout. Layouts can be scheduled in order to execute Shell Commands at specific times.</p>

<p class="alert alert-warning">Requires root access on Android - Android uses the Linux command box.</p>

<p><img class="img-thumbnail" alt="Shell Command Form" src="content/layout/Ss_layout_designer_add_shellcommand.png"></p>

<dl class="dl-horizontal">
	<dt>Windows Command</dt>
	<dd>The Shell Command for Windows. Will be executed using <code>cmd.exe</code>.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Linux Command</dt>
	<dd>The Shell Command for Linux.</dd>
</dl>

<p class="alert alert-info">Shell Commands do not have a duration, they are executed once and then expire automatically.</p>
	
<h2>User Contributed Example Usage</h2>
<p>In <?php echo PRODUCT_NAME; ?> client, Shell Command adds ability to control system power management options, and run external commands based
on the layout's activity. This allows a great deal of flexibility in power options on client-side</p>

<p>e.g. you can have a client hibernate and wake up at noon every day to present lunch related signage. Before it hibernates 
it can turn off the TV/Display.</p> 

<p>Using shell command, user can create a special template called "turn off display", which can be scheduled to turn off displays.
With <?php echo PRODUCT_NAME; ?> client attached to a display, there is a couple of API calls that can be used to ask ACPI to send calls to the 
video card to trigger monitor power suspension.</P>

<p>HDMI-CEC: This is a bus that is implemented on nearly all new large screen TVs that have HDMI connectors. This bus (which 
is physically connected within normal HDMI cables) supports control signals that can perform power-on, power off, 
volume adjust, selection of video source and many of the features that are accessible via the TV's remote control. It can 
also control most other hardware on the HDMI bus.</p>  


<p>In <?php echo PRODUCT_NAME; ?> client, user can disallow power management from timing out the display EXCEPT when the "turn off display" layout is being
used - this covers cases where <?php echo PRODUCT_NAME; ?> is using a regular computer monitor (and Windows already provides power management).</p>

<p>By adding two options to <?php echo PRODUCT_NAME; ?> client i.e. "command to run to turn off display" and "command to run to turn on display". 
These would be used to run custom executables. This would allow full customization of actions on a per-client basis, 
such as running a program to send RS-232 commands, or a batch file that sets a BIOS wakeup timer and then shuts down the system. 
Potentially %parameters% can be made available to the command-line, such as the number of minutes until the next scheduled 
non-"turn off display" template, which would allow full server-side control of these options once the initial set-up is done.</p>
 
<p>The Windows <a href="http://msdn.microsoft.com/en-us/library/aa373233(v=vs.85).aspx">SetThreadExecutionState</a> function can be used to prevent display time out. It is designed for apps such as video or presentations, which is exactly what <?php echo PRODUCT_NAME; ?> is.</p>
 
<p><?php echo PRODUCT_NAME; ?> client would need to check for transitions between the "turn off display" template and non-"turn off display" templates, and then act appropriately (eg, there is a procedure to run for the transition to "off" and another to run for "on"). At this time, calls to SetThreadExecutionState and any custom commands (if entered) would be run.</p>