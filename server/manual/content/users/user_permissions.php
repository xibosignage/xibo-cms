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
		
<h1>Permissions Model</h1>

<p>This section will take a look at the permissions model in <?php echo PRODUCT_NAME; ?>, which allows users and administrators to set view, edit and delete permissions on:</p>

<ul>
    <li>Library Media</li>
    <li>Layouts</li>
    <li>Regions in Layouts</li>
    <li>Media on Region Timelines</li>
    <li>DataSets</li>
    <li>Display Groups</li>
    <li>Displays</li>
</ul>

<p>All of these items have the permissions set in the same way, via a simple to use dialog showing groups and users. In addition to this all permissions are validated with each form load, or save button clicked so you can guarentee "real time" modifications to the permissions on items.</p>

<p><img class="img-thumbnail" alt="User Permissions" src="content/users/user_permissions.png"></p>

<p>Key facts:</p>
<ul>
<li>The Highest permission is used (if a user belongs to 2 groups, one has edit permissions and the other doesn't, 
the user will have edit permissions)</li>
<li>Permissions are tested with each transaction</li>
<li>Permissions on an item can only be changed by the owner or a super administrator (for displays and display groups that have 
no owner, this is super admin only)</li>
</ul>

<p>These permissions are then reflected in all tables, forms, lists and on the layout designer.</p> 


