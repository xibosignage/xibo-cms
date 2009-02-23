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
		<div class="SecondNav">
			<!-- Maybe at a later date we could have these buttons generated from the DB - and therefore passed through the security system ? -->
			<ul>
				<?php
					if ($_SESSION['usertype']==1)
					{
						echo '<li><a title="Add User" class="XiboFormButton" href="index.php?p=user&q=DisplayForm"><span>Add User</span></a></li>';
					}
				?>
				<li><a title="Show User Help" href="#" onclick="toggle_div_view('notes')"><span>Help</span></a></li>
				<li><a title="Show Filter" href="#" onclick="ToggleFilterView('UserFilter')"><span>Filter</span></a></li>
			</ul>
		</div>
		<div id="notes" style="display:none;">
			<h4>User Help <a href="http://www.xibo.org.uk/manual/index.php?p=content/users/overview" target="_blank" alt="The Manual">(Click for Manual)</a></h4>
			<div id="helptabs">
				<ul id="help" class="tabs-nav"><!-- the class on this element has to remain the same -->
					<li><a href="#tips_div" class="help_tab"><span>Tips</span></a></li>
					<li><a href="#terms_div" class="help_tab"><span>Terms</span></a></li>	
				</ul>

				<div id="tips_div">
					<p>Xibo has it's own User module that you can use to allow access to the system. Xibo allows you to manage indvidual
					users priveleges and basic registered details.</p>
					<p>The action buttons allow you to edit to edit a persons login details and override their password.</p>
					<p>Users is only available to administrators of the system.</p>
				</div>
				<div id="terms_div">
					<p><strong>Name</strong> is the username of the profile/</p>
					<p><strong>Email</strong> provides a contact point for forgotten passwords, and any Xibo notifications.</p>
				</div>
			</div>
		</div>
		<?php $this->UserFilter(); ?>
	</div>

<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>
</div>	