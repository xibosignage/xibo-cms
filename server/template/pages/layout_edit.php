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
		<div class='buttons'>
			<a id="background_button" href="<?php echo $this->EditBackgroundHref(); ?>" title="Background"><div class="button_text">Background</div></a> 
			<a id="edit_button" href="<?php echo $this->EditPropertiesHref(); ?>" title="Layout Properties"><div class="button_text">Properties</div></a> 		
		</div>
		<div class="leftbuttons">
			<div class="buttons">
				<a id='pres_button' href='index.php?p=layout' alt='Cancel Edit'><div class="button_text">Layout List</div></a>
				<a id="help_button" href="#" onclick="toggle_div_view('notes')"><div class="button_text">Help</div></a>
			</div>	
			
			<div id="notes" style="display:none;">
				<h4>Layout Design Help <a href="http://www.xibo.co.uk/manual/index.php?p=content/layout/design" target="_blank" alt="The Manual">(Click for Manual)</a></h4>
					<div id="helptabs">
					
						<ul id="help" class="tabs-nav">
							<li><a href="#add_div" class="help_tab"><span>Regions</span></a></li>
							<li><a href="#bg_div" class="help_tab"><span>Background</span></a></li>
							<li><a href="#edit_div" class="help_tab"><span>Properties</span></a></li>
							<li><a href="#tips_div" class="help_tab"><span>Tips</span></a></li>	
						</ul>

						<div id="add_div">
							<h5>Adding a region</h5>
							<p>To add a region right click on the background of the layout and select "Add Region".</p>
							
							<h5>When should I add a new region?</h5>
							<p>New regions should be added when a new container for media is required on the screen.</p>
							
							<h5>Moving and Resizing</h5>
							<p>Regions can be moved and resized using drag / drop.</p>
							
							<h5>Adding/Editing Media</h5>
							<p>Media is contained within the regions on the layout. To view a regions media either double click on the region
							or right click and select "Options".</p>

						</div>
						<div id="bg_div">
							<h5>Editing the Background</h5>
							<p>To edit the background either right click on the layout and select "Edit Background" or click on the Background
							button in the top left.</p>
							
							<h5>When should I edit the background?</h5>
							<p>The layout background should be edited if you want a different background color or image. Also if you want to change the
							resolution of the layout.</p>

							<p>A list of images available in Xibo will be shown in the list. New images can be added from the Content Page.</p>
						</div>
						<div id="edit_div">
							<h5>Editing the Layout details</h5>
							<p>To edit the Layout details click on the "Properties" button or right click on the layout background.</p>
							<p>The Layout name be unique - you cannot have two Layouts called the same thing. <br />
							The description field is for any more information.<br />
							The Shared option determines which other Xibo users can use this Layout.</p>
							
							<h5>The Layouts page</h5>
							<p>Editing the Layout details can also be done on the Layout page. To return to the Layout page
							click on the "Layouts" button.</p>
						</div>
						
						<div id="tips_div">
							<h5>Editing Layouts</h5>
							<p>Editing a Layout allows you to arrange the regions and media on the screen.</p>
							<p>You can use the <strong>Properties</strong> button to alter the Layout name, description and it's permissions<p>
							
							<h5>Drag and Drop</h5>
							<p>You can move regions around the Layout by moving dragging them to the new location.</p>
							<p>Media in regions can also be arranged on the timeline by drag and drop.</p>
						</div>
					</div>
				
			</div><!-- end of notes div -->
		</div>
			
		<div class="slidetable">
			<div class="title">
				<h4>Layout Design - <?php echo $this->layout ?></h4>
			</div>
			<div class="formbody">
				<?php $this->RenderDesigner(); ?>
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

