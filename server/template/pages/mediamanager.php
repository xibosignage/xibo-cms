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
			<div id="dashbuttons">

				<?php button("layout&q=RegionOptions&layoutid=$layoutid&regionid=$regionid", '', 'Region Options', "<img class='dash_button' src='img/dashboard/edit_content.png'><span class='dash_text'>Edit $layout</span>", '', 'playlist_button', "dashicons", "return init_button(this,'Region Options','',region_options_callback)") ?>

				<div class="dashicons">
					<a id="help_button" target="_blank" alt="The Manual" href="manual/index.html">
					<img class="dash_button" src="img/dashboard/help.png"/>
					<span class="dash_text">Manual</span></a>
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