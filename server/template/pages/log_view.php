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
				<li><a title="Show Sessions Help" href="#" onclick="toggle_div_view('notes')"><span>Help</span></a></li>
				<li><a title="Show Filter" href="#" onclick="ToggleFilterView('LogFilter')"><span>Filter</span></a></li>
				<li><a title="Refresh the Results" href='#' onclick="XiboGridRender('LogGridId')"><span>Refresh</span></a></li>
			</ul>
		</div>
		
		<div id="notes" style="display:none;">
			<h4>Error Log Help</h4>
			<div id="helptabs">
			
				<ul id="help" class="tabs-nav">
					<li><a href="#tips_div" class="help_tab"><span>Tips</span></a></li>
					<li><a href="#terms_div" class="help_tab"><span>Terms</span></a></li>	
				</ul>

				<div id="tips_div">
					<p>The error log is used to help troubleshoot problems with Xibo. When you encounter an error it will be logged
					and listed in the system here. These error messages can help the xstreamedia team solve your problem.</p>
					<p>Truncating the log helps you to troubleshoot a problem by clearing the current error messages</p>
					<p>The page and sub page items helps locate where the error has been generated from.</p>
				</div>

				<div id="terms_div">
					<p><strong>Log Date</strong> states the date and time the error message was logged.</p>
					<p><strong>Page</strong> states the page that the error has been generated from.</p>
					<p><strong>Sub Page</strong> states the sub page that the error message has been generated from.</p>
					<p><strong>Message</strong> gives details of what error has occurred in the system.</p>
				</div>
			</div>
		</div>
		<?php $this->LogFilter(); ?>	
	</div>
	
	<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>
</div>