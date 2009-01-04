<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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

<div id="form_container">
	<div id="form_header">
		<div id="form_header_left">
		</div>
		<div id="form_header_right">
		</div>
	</div>

	<div id="form_body">
		<div class="filter_form_container">
			<div class="buttons">
				<a id="add_button" class="XiboFormButton" href="index.php?p=group&q=GroupForm" alt="Add a Group"><div class="button_text">Add Group</div></a>
			</div>
			<div class="leftbuttons">
				<div class="buttons">
					<a id="help_button" onclick="toggle_div_view('notes')"><div class="button_text">Help</div></a>
				</div>	
					<div id="notes" style="display:none;">
						<h4>Group Help <a alt="The Manual" target="_blank" href="manual/index.php?p=content/users/groups">(Click for Manual)</a></h4>
						<div id="helptabs">
							<ul id="help" class="tabs-nav"><!-- the class on this element has to remain the same -->
								<li><a href="#add_div" class="help_tab"><span>Adding</span></a></li>
								<li><a href="#edit_div" class="help_tab"><span>Edit</span></a></li>
								<li><a href="#tips_div" class="help_tab"><span>Tips</span></a></li>
								<li><a href="#terms_div" class="help_tab"><span>Terms</span></a></li>	
							</ul>
							<div id="add_div">
								<h5>Adding a Group</h5>
								<p>To add a group click on the "Add Group" button found at the top left of this page.</p>
								
								<h5>When should a group be added?</h5>
								<p>A group should be added when there is a new set of components required for a particular group of users.
								It could be that you want certain users to only have access to certain components or that certain users 
								shouldnt be able to share their content, playlists and schedules with each other.</p>
							</div>
							<div id="edit_div">
								<h5>Edit a Group</h5>
								<p>To edit a group click on the edit button on the row belonging to the group you wish to edit.</p>
								
								<h5>Group / Component Security</h5>
								<p>When editing a group the components that are assigned / unassigned to that group are show. These
								determine the permissions of the users belonging to that group.</p>
							</div>
							<div id="tips_div">
								<p>You should always have at least one group.</p>
							</div>
							<div id="terms_div">
								<p><strong>Name</strong> is a unique identifier for a group.</p>
								<p><strong>Components</strong> refer to parts of Xibo, e.g. Content, or Playlists.</p>
							</div>
						</div>
					</div>	
			</div>
		</div>
		<?php $this->GroupGrid(); ?>
</div>
	
	<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>
</div>	