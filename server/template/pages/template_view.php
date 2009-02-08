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
				<li><a title="Show Template Help" href="#" onclick="toggle_div_view('notes')"><span>Help</span></a></li>
				<li><a title="Show Filter" href="#" onclick="ToggleFilterView('TemplateFilter')"><span>Filter</span></a></li>
			</ul>
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
		<?php $this->TemplateFilter(); ?>
	</div>
	
	<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>
</div>	