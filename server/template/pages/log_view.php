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
			<div class="buttons">

			</div>
			<div class="leftbuttons">
				<div class="buttons">
					<a id="refresh_button" href='#' onclick="exec_filter('filter_form','data_table')"><div class="button_text">Refresh</div></a>
					<a id="help_button" href="#" onclick="toggle_div_view('notes')"><div class="button_text">Help</div></a>
				</div>	
			
				<div id="notes" style="display:none;">
					<h4>Error Help</h4>
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
				
				</div><!-- end of notes div -->
			</div>
			<div id="playlist_filterform_div">
				<?php $this->log_info(); ?>
				<?php /* Not sure how to put this in yet
					$this->display_log_settings(); */?>
			</div>
		
			<!-- Do not rename this div -->
			<div id="paging">
				<form>
				<img src="img/forms/first.png" class="first"/>
				<img src="img/forms/previous.png" class="prev"/>
				<input type="text" class="pagedisplay" readonly size="5"/>
				<img src="img/forms/next.png" class="next"/>
				<img src="img/forms/last.png" class="last"/>
				<select class="pagesize">
					<option selected="selected" value="10">10</option>
					<option value="20">20</option>
					<option value="30">30</option>
					<option  value="40">40</option>
				</select>
			</form>
			</div>
		
			<div id="data_table"></div>
		</div>
	
		<div id="form_footer">
			<div id="form_footer_left">
			</div>
			<div id="form_footer_right">
			</div>
		</div>
	</div>
</div>	
