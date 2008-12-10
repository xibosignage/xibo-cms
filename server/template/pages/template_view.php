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
				
			</div>
			<div class="leftbuttons">
				<div class="buttons">
					<a id="toggle_button" onclick="toggle_div_view('playlist_filterform_div')"><div class="button_text">Filter</div></a>
					<a id="help_button" onclick="toggle_div_view('notes')"><div class="button_text">Help</div></a>
				</div>	
					<div id="notes" style="display:none;">
						<h4>Template Help <a href="manual/index.html" target="_blank" alt="The Manual">(Click for Manual)</a></h4>
						<div id="helptabs">
							<ul id="help" class="tabs-nav"><!-- the class on this element has to remain the same -->
								<li><a href="#tips_div" class="help_tab"><span>Tips</span></a></li>
							</ul>

							<div id="tips_div">
								<p>A template is a stored layout without any media assigned to it. It is used when creating a new layout.</p>
								<p>Templates can be created from the Layout Designer.</p>
								<p>Templates are for a particular screen resolution.</p>
							</div>
						</div>
					</div>	
			</div>
			<div id="playlist_filterform_div">
				<?php $this->template_filter(); ?>
			</div>
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
			<div id="data_table">

			</div>
	</div>
</div>
	
	<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>
</div>	