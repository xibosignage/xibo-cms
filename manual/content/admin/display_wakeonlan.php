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
<h1>Wake On LAN (WOL)</h1>

<p>This section will look at the Wake On Lan (WOL) feature of <?php echo PRODUCT_NAME; ?>.</p>

<p>There has been a lot of interest over the years <?php echo PRODUCT_NAME; ?> has been running for a solution to be "green", "save power" and 
generally not have the <?php echo PRODUCT_NAME; ?> display screen solution on unnecessarily.</p>

<p>The WOL function is intended to be used for display clients that are addressable by the server, by this we mean that there 
has to be a clear addressable network route between the server and the client that needs to wake up. It is also required that
WOL is turned on in any necessary settings on the client PC.</p>

<p>The WOL configuration happens on a display by display basis from the Edit Display form. Each display has new settings for:</p>
<dl>
	<dt>Enable Wake On LAN</dt>
	<dd>Turn WOL on/off for the display</dd>
</dl>
<dl>
	<dt>Wake On LAN Time</dt>
	<dd>Specify a time for this display to wake, using the 24 hr clock</dd>
</dl>
<dl>
	<dt>BroadCast Address</dt>
	<dd>The BroadCast address of the client, if unknown use the IP address and fill in the CIDR field</dd>
</dl>
<dl>
	<dt>Wake On LAN Secure On</dt>
	<dd>The SecureOn password for the client, if supported</dd>
</dl>
<dl>
	<dt>Wake On LAN CIDR</dt>
	<dd>The CIDR subnet mask, if the BroadCast Address is unknown</dd>
</dl>

<p>Once the display has these settings it can be woken up in 2 ways:</p>


<h3>Wake On LAN Now</h3>

<p><img class="img-thumbnail" alt="Send WOL Form" src="content/admin/sa_display_wol.png"></p>

<p>Each display has the "Wake On LAN" button which can be used to send a wake on LAN command on demand. Clicking the button displays 
a form for confirmation, once this is pressed the command is send immediately to the client.</p>

<h3>Wake On LAN Time - Maintenance Script</h3>
<p>In addition to the WOL now function, the <?php echo PRODUCT_NAME; ?> maintenance module has been altered to send a WOL packet to any display which has 
a "Wake On LAN Time" specified. It will only send the command once, as the time window is passed.</p>

<p>The maintenance script has to be running for this to work correctly. Maintenance instructions can be found on the 
<a title="Maintenance Instructions" href="index.php?toc=getting_started&p=admin/settings#Maintenance">Settings -> Maintenance</a>.</p>

<h3>Putting the client to sleep</h3>
<p>There are a few different options for putting the client to sleep - such as a scheduled task. However, the next article in this series 
will look at an option built into <?php echo PRODUCT_NAME; ?>. The "Shell Command" module.</p>

<p class="alert alert-info">WOL is not routable. The <?php echo PRODUCT_NAME; ?> server is unable to see clients over the internet that are behind NAT,  
or on a different subnet.</p>