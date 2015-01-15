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
<h1>User Administration</h1>

<p>This section refers to Users Adminstration from the built in "General" user module. For information on any of the User module plugins refer
to the appropriate page within this section.</p>

<h2>User Administration Page</h2>
<p>The user admin page can be accessed from either the "Users" dashboard button - or the "Administration > Users" nagivation menu button. 
An example of the user page is show below.</p>
		
<p><img class="img-thumbnail" alt="User Administration Page" src="content/users/user_admin_page.png"></p>
	   
	    <ul>
			<li>
				<h3>User Name</h3>
				<p>This is the name the user will have to enter to log into <?php echo PRODUCT_NAME; ?>.</p>
			</li>
			<li>
				<h3>Homepage</h3>
				<p>Once a user has successfully entered their user name and password they will be taken to the homepage set here. The homepage
				is automatically generated from the selections on the edit user form.</p>
			</li>
			<li>
				<h3>Email</h3>
				<p>The users email address will be used to send them important information from <?php echo PRODUCT_NAME; ?>. For example if they forget their password.</p>
			</li>
			<li>
				<h3>Add User Button</h3>
				<p>Loads the "Add User" form - the details on the form will need to be entered before a user can be added to <?php echo PRODUCT_NAME; ?>.</p>
			</li>
			<li>
				<h3>Edit / Delete Buttons</h3>
				<p>Opens the edit or delete form respectively. Allows the modification of user details after they have been created.</p>
			</li>
			<li>
				<h3>Page Security Button</h3>
				<p>Define user access right to the various pages on the server.</p>
			</li>
			<li>
				<h3>Menu Security Button</h3>
				<p>Show or hide menu access for the specific user.</p>
			</li>
			<li>
				<h3>Set Homepage Button</h3>
				<p>Set user Homepage to either dashboard or mediamanager.</p>
			</li>
		</ul>

		<a name="Add_User" id="Add_User"></a><h2>Adding Users - The Add Form</h2>
		<p>To add a user click on the "Add User" button found at the top left of the User Administration page. Fill in <strong>all</strong> the 
		fields and click "Save" to add the user.</p>

		<p><img alt="Add User Form" src="content/users/user_admin_add.png"
		style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	    width="558" height="328"></p>
     
    	<a name="Edit_User" id="Edit_User"></a><h2>Editing Users - The Edit Form</h2>
		<p>To edit a user click on the "Edit" button on the row belonging to the user for editing. Correct the details on the form as necessary
		and click save to commit those changes.</p>

	  	<p><img alt="Edit User Form" src="content/users/user_admin_edit.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   	width="559" height="328"></p>
     	
		<a name="Delete_User" id="Delete_User"></a><h2>Deleting Users</h2>
		<p>To delete a user click on the delete button on the row belonging to the unwanted user. A confirm form will be opened up.</p>
		<p><strong>Note:</strong> Deleting a user that has media would create "orphaned" content, playlist and schedule records if that user has
		been active in the system. For this reason any "orphaned" items will be associated with the "<?php echo PRODUCT_NAME; ?>_admin" user created during the install process.</p>
	
	
<h2 id="Users_MyApplications">My Applications <small>Authourised 3rd party applications</small></h2>
<p><?php echo PRODUCT_NAME; ?> contains an API that allows 3rd party applications to connect and consume its data. An example of this is the standalone file upload tool provided with the package. Before using an application each user must authourise the application to act on their behalf within the CMS.</p>

<p>Once an application is authourised it will be listed in the users "My Applications" form.</p>

<p><img class="img-thumbnail" alt="My Applications" src="content/users/users_myapplications.png"></p>

<p class="alert alert-danger">At the current time the CMS does not provide a method of revoking access to an application.</p>

<p class="alert alert-info">An authourised user can view the applications for each user using the action menu "Applications"</p>