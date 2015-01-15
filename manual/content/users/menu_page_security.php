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
<h1>Page &amp; Menu Security</h1>

<p><?php echo PRODUCT_NAME; ?> system adminstrator has full control on access right for each user/group on the system. The security components 
that are assigned/unassigned to the user/group determine the permissions of the individual or 
users belonging to that group.</p>

<p>The list of components available is managed by the <?php echo PRODUCT_NAME; ?> software and cannot be changed.</p>

<p>Editing which components are assigned to is done by ticking the checkboxes next to the assignment to be changed.<br />
Note: It is a toggle operation, therefore if the assignment is "unassigned" it will become "assigned" and visa versa.
Currently assigned rows are indicated with a green mark and unassigned with a red mark.</p>	  

<h3 id="Page_Security">Page Security</h3>

<p>Click "Page Security" button for the user you want to edit. A "Page Security" form is loaded for editing.</p>

<p><img class="img-thumbnail" alt="User Page Security" src="content/users/user_page_security.png"></p>

<p>User access to the following pages can be individually enabled/disabled. Check one or more items and click "Assign/Unassign" button to toggle.</p>
<ul>
	<li>Schedule</li>
	<li>Homepage and Login</li>
	<li>Layouts</li>
	<li>Content</li>
	<li>Displays</li>
	<li>Users and Groups</li>
	<li>Report</li>
	<li>License and Settings</li>
	<li>Updates</li>
	<li>Template</li>
	<li>Web Services</li>
	<li>DataSets</li>
</ul>

<h3 id="Menu_Security">Menu Security</h3>

<p>To add a user click on the "Add User" button found at the top left of the User Administration page. Fill in <strong>all</strong> the 
fields and click "Save" to add the user.</p>

<p><img class="img-thumbnail" alt="User Menu Security" src="content/users/user_menu_security.png"></p>

<p>User view to the following menus can be individually enabled/disabled. First select the Navigation Menu, then check one or more of the sub-category menu items and click "Assign/Unassign" button to toggle.</p>

<ul>
	<li>Administration Menu</li>
	<li>Advanced Menu</li>
	<li>Dashboard</li>
	<li>Design Menu</li>
	<li>Display Menu</li>
	<li>Library Menu</li>
	<li>Top Nav</li>
</ul>
