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

<a name="Display_Group" id="Display_Group"></a><h1>Display Groups</h1>
<p>A group should be added when there is a new set of components required for a particular group of users.
It could be that you want certain users to only have access to certain components or that certain users 
should not be able to share their content, playlists and schedules with each other.</p>

<p><strong>Components</strong> refer to parts of <?php echo PRODUCT_NAME; ?>, e.g. Content, or Playlists.</p>


<a name="Adding_a_Group" id="Adding_a_Group"></a><h3>Adding a Group</h3>
<p>Click the navigation menu "Displays > Display Groups" to go to Display Groups page. To add a group click on 
the "Add Group" button found at the top of the Displays list.</p>

<a name="Edit_Group" id="Edit_Group"></a><h3>Edit a Group</h3>
<p>To edit a group click on the edit button on the row belonging to the group you wish to edit.</p>

<p><img alt="SA Display Group" src="content/admin/sa_display_groups.png"
style="display: block; text-align: center; margin-left: auto; margin-right: auto"
width="621" height="226"></p>

<p><strong>Name</strong> is a unique identifier for a group.</p>	   

<a name="Delete_Group" id="Delete_Group"></a><h3>Delete a Group</h3>
<p>To delete a group click on the delete button on the row belonging to the group you wish to delete.</p>

<a name="Group_Component_Security" id="Group_Component_Security"></a><h3>Group / Component Security</h3>
<p>When editing a group the components that are assigned / unassigned to that group are show. These
determine the permissions of the users belonging to that group.</p>
<p>You should always have at least one group.</p>

<a name="Group_Members" id="Group_Members"></a><h3>Group Members</h3>

<p>Click on "Group Members" load the "Manage Membership" form for editing. All the Displays on the system will be shown. 
You can assign or remove group members.</p>

<p><img alt="SA Display Group Members" src="content/admin/sa_display_group_members.png"
style="display: block; text-align: center; margin-left: auto; margin-right: auto"
width="408" height="270"></p>

