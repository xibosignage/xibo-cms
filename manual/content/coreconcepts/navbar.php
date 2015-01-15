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
<h1>Navigation <small>Getting around</small></h1>

<p>The CMS has a simple 2 level navigation bar to help move around in a logical way. The top level navigation bar is always visible in the header of the user interface. This navigation menu provides access to all the core areas of the CMS.</p>

<p><img class="img-thumbnail" alt="The navigation bar" src="content/coreconcepts/navbar.png"></p>

<p>Most of the menu items at the top level will expose further options once selected, each will be discussed below.</p>

<dl class="dl-horizontal">
	<dt>Dashboard</dt>
	<dd>The dashboard link returns you to your <?php echo PRODUCT_NAME; ?> dashboard. The dashboard is personalised depending on who is currently logged in.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Schedule</dt>
	<dd>Calendar view to assign Layouts/Campaigns onto Displays. Discussed in the <a href="index.php?toc=scheduling&p=schedule/overview">Scheduling</a> section.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Design</dt>
	<dd>Layout administration for adding/editing and designing Layouts and Campaigns. Discussed in the <a href="index.php?toc=layouts&p=layout/overview">Layouts</a> section.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Library</dt>
	<dd>Media Administration in the library for all file based media, such as images and video. Discussed in the <a href="index.php?toc=library&p=content/overview">Library</a> section.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Displays</dt>
	<dd>Display Administration for managing any connected displays (Windows, Ubunut or Android). Discussed in the <a href="index.php?toc=user_and_display&p=users/overview">Users and Displays</a> section.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Administration</dt>
	<dd>Area for managing users and groups, adjusting settings and enabling additional functionality. Discussed in the <a href="index.php?toc=user_and_display&p=users/overview">Users and Displays</a> section.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Advanced</dt>
	<dd>Area for advanced trouble shooting and debugging. Discussed in the <a href="index.php?toc=troubleshooting&p=admin/overview">Troubleshooting</a> section.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Preferences</dt>
	<dd>User preferences for changing password, logging out and viewing the help.</dd>
</dl>

<p class="alert alert-info">You may not see all of these options when you log in - that is because these are shown based on the permissions you have been assigned. If you would like access to all areas please speak to your CMS administrator.</p>

<h2 id="Change_Password">Change Password</h2>

<p>A logged in user can change their Password using the Change Password Menu Item from the Preferences menu. The form will ask the user to confirm their existing password and enter a new one.</p>

<p><img class="img-thumbnail" alt="Change Password Form" src="content/coreconcepts/change_password_form.png"></p>
