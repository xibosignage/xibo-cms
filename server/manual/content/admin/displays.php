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
<h1 id="Displays">Displays</h1>

<p>Displays are records to identify each physical device that should show content from the <?php echo PRODUCT_NAME; ?> CMS. Each display client application registered with the server creates a unique display record when registered.</p>

<p>Display records can be assigned a default layout, have a schedule of layouts and are used for status monitoring and access control. The main area for displays is the Display Administration page accessed from the Nagivation Bar by clicking on "Displays > Displays"</p>

<p><img class="img-thumbnail" alt="Display Administration" src="content/admin/sa_displays.png"></p>

<p>The display administration table gives an easy to understand overview of each display registered in the CMS.</p>

<dl class="dl-horizontal">
	<dt>Row Colour</dt>
	<dd>The status of the display. Red = missing content, Yellow = downloading missing content, Green = Up to Date.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>ID</dt>
	<dd>The internal ID for the display.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Licence</dt>
	<dd>A tick or cross showing whether the display has been granted a licence with the CMS. This prevents unauthourised displays being added to the CMS.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Display</dt>
	<dd>A friendly name for the display. This can be set during the registration process in the display client software.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Default Layout</dt>
	<dd>The default layout that will play when there are no other layouts/campaigns scheduled or when all scheduled layouts are invalid.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Interleave Default</dt>
	<dd>A tick or cross showing whether the default layout will play when there are other layouts scheduled.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Email Alert</dt>
	<dd>A tick or cross showing whether an email alert will be sent by the Maintenance module.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Logged In</dt>
	<dd>A tick or cross showing whether the display has logged in recently. The time out for the display is set on each display OR in the global settings field MAINTENANCE_ALERT_TOUT.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Last Accessed</dt>
	<dd>The date/time of last access.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>IP Address</dt>
	<dd>The IP address the display had the last time it reported its status through the "Media Inventory" status call.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Mac Address</dt>
	<dd>The Mac Address of the display (if the client software is capable of sending it).</dd>
</dl>

<h2 id="Display_ActionMenu">Action Menu <small>options for each display</small></h2>
<p>Displays can have a number of actions performed against them.</p>

<p><img class="img-thumbnail" alt="Display Action Menu" src="content/admin/display_action_menu.png"></p>

<dl class="dl-horizontal">
	<dt>Schedule Now</dt>
	<dd>A quick shortcut to scheduling a layout from the current time for a particular duration.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Media Inventory</dt>
	<dd>View the required media inventory and the current status of each item as reported by the display during the last check in.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Default Layout</dt>
	<dd>A quick shortcut to updating the default layout.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Edit</dt>
	<dd>The display edit form for adjusting all options available on the display.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Wake on LAN</dt>
	<dd>Send a Wake on LAN packet to the display - requires the Wake On LAN settings to be configured.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Delete</dt>
	<dd>The display delete form.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Display Groups</dt>
	<dd>Administration of the display groups this display belongs to.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Permissions</dt>
	<dd>Adjust the user/user group permissions on this display.</dd>
</dl>

<h2 id="Display_Edit">Display Edit</h2>

<p>Before the display will be allowed to download its schedule or required media it must be licenced with the CMS. After a display client is registered a new display record will appear in the Display Administration - it will then need to be edited to grant is access (getting a licence).</p>

<p>Click on the "Edit" button of the Display loads its editing window. You can then proceed to change the settings for the chosen Display.</p>                                                                                         

<p><img class="img-thumbnail" alt="Display Edit" src="content/admin/sa_display_edit.png"></p> 


<h2 id="Display_Delete">Display Delete</h2>

<p>Deleting a display will remove it from the CMS entirely and can only be performed if the display is not active.</p>

<p class="alert alert-info">A display can be unlicenced without deleting it using the Display Edit form.</p>

<p><img class="img-thumbnail" alt="Display Delete" src="content/admin/sa_display_delete.png"></p>

<p class="alert alert-warning">Deleting a display cannot be reversed. A display can be reconnected to the CMS by repeating the "register" procedure which will create a new display record.</p>


<h2 id="Media_Inventory">Media Inventory</h2>

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
You will also see any files that the client is in the process of downloading.</p>


<p><img class="img-thumbnail" alt="Display Media Inventory" src="content/admin/sa_display_media_inventory.png"></p>

<p class="alert alert-info">Note here that what you will not see is files that the client needs to download, but that it is unaware of at the present time. For example. If you schedule a new layout and immediately go to the Media Inventory before the client has connected up to the server, you'll see the status light is red, but the content of the media inventory will not show the new files that are required. Once the client connects, those new files will be included in the inventory automatically.</p>