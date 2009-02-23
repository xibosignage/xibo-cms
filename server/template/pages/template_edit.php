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
			<div class="leftbuttons">
				<div class="buttons">
					<a id="help_button" onclick="toggle_div_view('notes')"><div class="button_text">Help</div></a>
				</div>	
		<div id="notes" style="display:none;">
					<h4>Layout Help</h4>
					<div id="helptabs">
						<ul id="help" class="tabs-nav"><!-- the class on this element has to remain the same -->
							<li><a href="#tips_div" class="help_tab"><span>Tips</span></a></li>
							<li><a href="#terms_div" class="help_tab"><span>Tags</span></a></li>	
						</ul>

						<div id="tips_div">
							<p>Editing a Layout gives you complete control over the code used to generate it on screen. </p>
							<p>You can use a combination of HTML and CSS to generate your display layout. These follow all the stand rules used for building a website.</p>
							<p>It is recommended that you use a text editor to construct your code and then copy and paste it into the tables on the page.</p>
							<p>The preview player allows you to navigate the presentations available to you and the slides available in each presentation. The preview window autosizes to your screen resolution. For true representation
							of how a presentation will appear on a screen, it is wise to set the resolution as close to the Display resolution as possible.</p>
						</div>
						<div id="terms_div">
							<p>Layouts use some designated tags that Xibo uses to identify certain custom elements. These are specified below</p>
							<p><strong>Name</strong> identifies the layout so that people can add it to a presentation.</p>
							<p><strong>Number of Positions</strong> identifies how many pieces of content can be added to this layout.</p>
							<p><strong>&lt;position name="1"&gt;</strong> is the tag that is used to define a position on the layout, where the number corresponds to
							one of the layout positions. So if you have 4 positions, you can have 4 of these tags, each with it's own position number 1,2,3 or 4.</p>
						
						</div>
					</div>
				</div>	
				</div>
			<?php $this->layout_form("index.php?p=layout&q=edit", "index.php?p=layout&sp=view", "index.php?p=layout&sp=edit", ""); ?>
		</div>

	</div>
	
	<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>
</div>	