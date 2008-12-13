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
		<div class="leftbuttons">
				<div class="buttons">
					<a id="help_button"  href="#" onclick="toggle_div_view('notes')"><div class="button_text">Help</div></a>
				</div>	
					
				<div id="notes" style="display:none;">
					<h4>Settings Help <a href="http://www.xibo.org.uk/manual/index.php?p=content/config/settings" target="_blank" alt="The Manual">(Click for Manual)</a></h4>
					<div id="helptabs">
						<ul id="help" class="tabs-nav"><!-- the class on this element has to remain the same -->
							<li><a href="#tips_div" class="help_tab"><span>Tips</span></a></li>
							<li><a href="#terms_div" class="help_tab"><span>Tabs</span></a></li>	
						</ul>

						<div id="tips_div">
							<p>This page provides you with all the settings options to configure Xibo to your environment. Each tab provides a set of forms
							where you can alter default settings and paths.</p>
							<p>The items in the boxes provide you with details of your PHP settings on the server with Xibo installed.</p>
							<p>Settings should only be modified by the administrator of the system, as incorrect settings could cause system
							stablility issues.</p>
						</div>
						<div id="terms_div">
							<p><strong>Content</strong> this tab allows you to set default content item settings, such as a default lenght and ratio. This
							helps Xibo if the file does not contain duration information.</p>
							<p><strong>Default</strong> settings sets the default options on some forms to always appear and be applied unless altered. You can also change
							your time zone in this section.</p>
							<p><strong>Error</strong> tab allows you to alter how Xibo handles errors. You can assign a mailbox to send errors and turn the error and audit 
							lgos on and off.</p>
							<p><strong>General</strong> settings provides details of your current settings in your PHP.ini file on your server. It also allows you to setup
							whether you are collecting statics off your Xibo displays.</p>
							<p><strong>Path</strong> settings allows you to specify particular directory locations for where Xibo will store and look for items. It also allows
							you to specify where you have downloaded additional plugins like openflash charts.</p>
							<p><strong>User</strong> this section is important if you intent to use a different authentication module to allow your users to log in to the system
							you will only need this section if you are familiar with an Ldap authentication module.</p>
							<p><strong>Install Issues</strong> highlights some settings issues that stop some Xibo functions from functioning correctly on your network.</p>
						</div>
					</div>
				</div>
			</div>	
		<div id="settings">
			<?php $this->display_settings(); ?>
		</div>
	</div>
	
	<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>
</div>	