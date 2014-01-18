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
<h1>User Groups <small>Groups, Permissions and Memberships</small></h1>
		
<p>The User Group Admininistration page is accessed using the Administration menu item and selecting the User Group sub menu, pictured below.</p>
		
<p><img class="img-thumbnail" alt="Group Administration Page" src="content/users/group_admin_page.png"></p>

<p>The table view shows all User Groups in the system (that the current user has access to) and the option to modify those user groups. Each action option is described below.</p>
	   
<dl class="dl-horizontal">
	<dt>Group Name</dt>
	<dd>The group name uniquely identifys the group. It can be seen against each user in the "User Admin" page.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Add Group Button</dt>
	<dd>Opens up the Add User Group Form allowing a new group to be added.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Edit / Delete Buttons</dt>
	<dd>The Edit button allows groups to be edited.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Group Members Button</dt>
	<dd>Modify member list of the group.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Page Security Button</dt>
	<dd>The components security that are assigned/unassigned to the group determine the permissions of the users belonging to that group.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Menu Security Button</dt>
	<dd>The components security that are assigned/unassigned to the group determine the permissions of the users belonging to that group.</dd>
</dl>
		

		<a name="Adding_Group" id="Adding_Group"></a><h2>Adding a Group</h2>
		<p>To add a group click on the "Add Group" button found at the top left of this page.</p>

	  	<p><img class="img-thumbnail" alt="Group Add" src="content/users/group_admin_add.png"></p>

		
		<h3>When should a group be added?</h3>
		<p>A group should be added when there is a new set of components required for a particular group of users.
		It could be that you want certain users to only have access to certain components or that certain users 
		shouldnt be able to share their content, playlists and schedules with each other.</p>
		

    	<a name="Edit_Group" id="Edit_Group"></a><h2>Edit a Group</h2>
		<p>To edit a group click on the edit button on the row belonging to the group you wish to edit.</p>
	
  	  	<p><img class="img-thumbnail" alt="Group Edit" src="content/users/group_admin_edit.png"></p>

    	<a name="Delete_Group" id="Delete_Group"></a><h2>Delete a Group</h2>
		<p>To delete a group click on the delete button on the row belonging to the group you wish to be deleted.</p>
	
  	  	<p><img class="img-thumbnail" alt="Group Delete" src="content/users/group_admin_delete.png"></p>

<h2 id="Group_Member">Group Members <small>Users assigned to groups</small></h2>
<p>Each user account in the CMS can be assigned to one or more user groups. To manage the members of a particular user group, select the user groups action menu in the table and click the "Group Members" menu item. Once selected a form will open showing the membership information of that group.</p>

<p>Users that appear in the left hand column are already assigned to the selected group and users in the right hand column can be assigned.</p>

<p><img class="img-thumbnail" alt="Group Member" src="content/users/group_member.png"></p>

<p class="alert alert-info">You can drag of double click on the memeber to include or exclude from the current group.</p>