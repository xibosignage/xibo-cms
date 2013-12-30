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
		
		<h1>User Groups and Group Permissions</h1>
		
		<h2>Group Admin</h2>
		<p>The Group Admin page can only be accessed from the "Administration > Groups" navigation menu. Below figure shows the Group Admin page.</p>
		
	  	<p><img alt="Group Administration Page" src="content/users/group_admin_page.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   	width="795" height="246"></p>
	   
		<ul>
			<li>
				<h3>Group Name</h3>
				<p>The group name uniquely identifys the group. It can be seen against each user in the "User Admin" page.</p>
			</li>
			<li>
				<h3>Add Group Button</h3>
				<p>Opens up the Add User Group Form allowing a new group to be added.</p>
			</li>
			<li>
				<h3>Edit / Delete Buttons</h3>
				<p>The Edit button allows groups to be edited.</p>
			</li>
			<li>
				<h3>Group Members Button</h3>
				<p>Modify member list of the group.</p>
			</li>
			<li>
				<h3>Page Security Button</h3>
		 		<p>The components security that are assigned/unassigned to the group determine the permissions of the users belonging to that group.</p>
				<p>Refer to <a href="menu_page_security.php">Page &amp; Menu Security</a> for more information.</p>
			</li>
			<li>
				<h3>Menu Security Button</h3>
		 		<p>The components security that are assigned/unassigned to the group determine the permissions of the users belonging to that group.</p>
				<p>Refer to <a href="menu_page_security.php">Page &amp; Menu Security</a> for more information.</p>
			</li>
		</ul>

		<a name="Adding_Group" id="Adding_Group"></a><h2>Adding a Group</h2>
		<p>To add a group click on the "Add Group" button found at the top left of this page.</p>

	  	<p><img alt="Group Add" src="group_admin_add.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   	width="410" height="190"></p>

		
		<h3>When should a group be added?</h3>
		<p>A group should be added when there is a new set of components required for a particular group of users.
		It could be that you want certain users to only have access to certain components or that certain users 
		shouldnt be able to share their content, playlists and schedules with each other.</p>
		

    	<a name="Edit_Group" id="Edit_Group"></a><h2>Edit a Group</h2>
		<p>To edit a group click on the edit button on the row belonging to the group you wish to edit.</p>
	
  	  	<p><img alt="Group Edit" src="group_admin_edit.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   	width="410" height="190"></p>

    	<a name="Delete_Group" id="Delete_Group"></a><h2>Delete a Group</h2>
		<p>To delete a group click on the delete button on the row belonging to the group you wish to be deleted.</p>
	
  	  	<p><img alt="Group Delete" src="group_admin_delete.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   	width="407" height="187"></p>

<h2 id="Group_Member">Group Members <small>Users assigned to groups</small></h2>
<p>Each user account in the CMS can be assigned to one or more user groups. To manage the members of a particular user group, select the user groups action menu in the table and click the "Group Members" menu item. Once selected a form will open showing the membership information of that group.</p>

<p>Users that appear in the left hand column are already assigned to the selected group and users in the right hand column can be assigned.</p>

<p><img class="img-thumbnail" alt="Group Member" src="content/users/group_member.png"></p>

<p class="alert alert-info">You can drag of double click on the memeber to include or exclude from the current group.</p>