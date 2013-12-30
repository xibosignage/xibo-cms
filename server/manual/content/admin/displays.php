<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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
<a name="Displays" id="Displays"></a><h1>Displays</h1>

<p>Displays are how you output your layouts with <?php echo PRODUCT_NAME; ?>.</p>
<p>Each client application registered with the server creates a new display record in <?php echo PRODUCT_NAME; ?> server. You can then choose a 
default layout for that display, schedule further layouts on the display and control who has access to the display, 
as well as monitor its status from the Display Management page.</p>

<p>The Display Manamagement page is accessed from the Nagivation Bar by clicking on "Displays > Displays"</p>

<p><img class="img-thumbnail" alt="SA Displays" src="content/admin/sa_displays.png"></p>

<a name="Display_Edit" id="Display_Edit"></a><h3>Display Edit</h3>
<p>After a new display client is registered with <?php echo PRODUCT_NAME; ?> server, you need to perform Edit granting license to the client 
to work.</p>
<p> Click on the 'Edit" button of the Display loads its editing window. You can then proceed to change the settings for
the chosen Display. </p>                                                                                         

<p><img alt="Display Edit" src="content/admin/sa_display_edit.png"
style="display: block; text-align: center; margin-left: auto; margin-right: auto"
width="493" height="231"></p> 

<a name="Display_Delete" id="Display_Delete"></a><h3>Display Delete</h3>
<p>After a display client is registered and licensed with <?php echo PRODUCT_NAME; ?> server, you can "unlicense" a client which will prevent it from connecting
to the server.</p>
<p> Click on the 'Delete" button of the Display you want to unlicense; and the below form is loaded. </p>                                                                                         

<p><img alt="Display Delete" src="content/admin/sa_display_delete.png"
style="display: block; text-align: center; margin-left: auto; margin-right: auto"
width="357" height="217"></p>

<p>Note: Delete a display cannot be undone. The client needs to re-regisiter and liscense before it is allowed to connect to the server again.</p>

<a name="Media_Inventory" id="Media_Inventory"></a><h3>Media Inventory</h3>
<p>When you schedule new content, or change existing content, it is helpful to know that the displays have updated with the new 
information. In a <?php echo PRODUCT_NAME; ?> server and client system, the client applications will connect in to the server periodically 
and update itself on the media items that they have stored locally. Media Inventory allows you to look at a glance to check if 
your updates were pulled by the clients yet.</p>
<p>On the display management page, you'll see a column "Status". The status light can be one of either green, amber or red.</p>
<p>When you make a change that affects the output of the server to a given client (for example if you modify a layout scheduled 
on that client, schedule a new layout or change the default layout), the status light will immediately goes red. That signifies 
that as far as the server is aware there are updates pending for that client and it has not received them yet.</p>
<p>The client will then connect up to the server on a schedule and will read the new information that is available. If there 
are new files to download (for example if you modified a layout or scheduled something completely new), the status light will 
turn amber while the files are being downloaded.</p>
<p>Once the client is satisfied that all files have been downloaded, it will send an inventory of all the files it has active 
in it is cache back to the server, and the server will change the status light to green.</p>
<p>If you are curious to see what files the client is actively monitoring, you can click the "Media Inventory" button and a popup 
will appear showing the status of each file, along with the last time the client checked it was still stored correctly on disk. 
You will also see any files that the client is in the process of downloading. (Note here that what you will not see is files 
that the client needs to download, but that it is unaware of at the present time. For example. If you schedule a new layout 
and immediately go to the Media Inventory before the client has connected up to the server, you'll see the status light is red, 
but the content of the media inventory will not show the new files that are required. Once the client connects, those new files 
will be included in the inventory automatically.)</p>	   

<p><img alt="Display Media Inventory" src="content/admin/sa_display_media_inventory.png"
style="display: block; text-align: center; margin-left: auto; margin-right: auto"
width="418" height="268"></p>

<a name="Display_Group_Member" id="Display_Group_Member"></a><h3>Group Members</h3>
<p>To find the Group that the Display is belonged to, click on the "Group Members" on the corresponding row.</p>

<p><img alt="SA Display Group Edit" src="content/admin/sa_display_groups_edit.png"
style="display: block; text-align: center; margin-left: auto; margin-right: auto"
width="408" height="218"></p>


<h2>Wake On LAN (WOL)</h2>

<h3>Introducing Wake On Lan for Display Clients</h3>
<p>This section will look at the Wake On Lan (WOL) feature of <?php echo PRODUCT_NAME; ?>.</p>

<p>There has been a lot of interest over the years <?php echo PRODUCT_NAME; ?> has been running for a solution to be "green", "save power" and 
generally not have the <?php echo PRODUCT_NAME; ?> display screen solution on unnecessarily.</p>

<p>The WOL function is intended to be used for display clients that are addressable by the server, by this we mean that there 
has to be a clear addressable network route between the server and the client that needs to wake up. It is also required that
WOL is turned on in any necessary settings on the client PC.</p>

<p>The WOL configuration happens on a display by display basis from the Edit Display form. Each display has new settings for:</p>
<ul>
<li><strong>Enable Wake On LAN</strong> - (Turn WOL on/off for the display)</li>
<li><strong>Wake On LAN Time</strong> - (Specify a time for this display to wake, using the 24 hr clock)</li>
<li><strong>BroadCast Address</strong> - (The BroadCast address of the client, if unknown use the IP address and fill in the CIDR field)</li>
<li><strong>Wake On LAN Secure On</strong> - (The SecureOn password for the client, if supported)</li>
<li><strong>Wake On LAN CIDR</strong> - (The CIDR subnet mask, if the BroadCast Address is unknown)</li>
</ul>

<p>Once the display has these settings it can be woken up in 2 ways:</p>
<h3>Wake On LAN Now</h3>

<p><img alt="SA Display WOL" src="content/admin/sa_display_wol.png"
style="display: block; text-align: center; margin-left: auto; margin-right: auto"
width="308" height="158"></p>

<p>Each display has the "Wake On LAN" button which can be used to send a wake on LAN command on demand. Clicking the button displays 
a form for confirmation, once this is pressed the command is send immediately to the client.</p>

<h3>Wake On LAN Time - Maintenance Script</h3>
<p>In addition to the WOL now function, the <?php echo PRODUCT_NAME; ?> maintenance module has been altered to send a WOL packet to any display which has 
a "Wake On LAN Time" specified. It will only send the command once, as the time window is passed.</p>

<p>The maintenance script has to be running for this to work correctly. Maintenance instructions can be found on the 
<a title="Maintenance Instructions" href="settings.php">Settings -> Maintenance</a>.</p>

<h3>Putting the client to sleep</h3>
<p>There are a few different options for putting the client to sleep - such as a scheduled task. However, the next article in this series 
will look at an option built into <?php echo PRODUCT_NAME; ?>. The "Shell Command" module.</p>

<p>Note: WOL is not routable. The <?php echo PRODUCT_NAME; ?> server is unable to see clients over the internet that are behind NAT,  
or on a different subnet.</p>

