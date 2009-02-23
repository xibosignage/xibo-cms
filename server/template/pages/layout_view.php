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
		<div id="form_header_left"></div>
		<div id="form_header_right"></div>
	</div>
	
	<div id="form_body">
		<div class="SecondNav">
			<!-- Maybe at a later date we could have these buttons generated from the DB - and therefore passed through the security system ? -->
			<ul>
				<li><a title="Add a new Layout" class="XiboFormButton" href="index.php?p=layout&q=displayForm" ><span>Add Layout</span></a></li>
				<li><a title="Show Layout Help" href="#" onclick="toggle_div_view('notes')"><span>Help</span></a></li>
				<li><a title="Show Filter" href="#" onclick="ToggleFilterView('LayoutFilter')"><span>Filter</span></a></li>
			</ul>
		</div>
		<div id="notes" style="display:none;">
			<h4>Layout Help <a href="http://www.xibo.org.uk/manual/index.php?p=content/layout/overview" target="_blank" alt="The Manual">(Click for Manual)</a></h4>
			<div id="helptabs">
				<ul id="help" class="tabs-nav"><!-- the class on this element has to remain the same -->
					<li><a href="#add_div" class="help_tab"><span>Add/Edit</span></a></li>	
					<li><a href="#design_div" class="help_tab"><span>Design</span></a></li>	
					<li><a href="#tips_div" class="help_tab"><span>Tips</span></a></li>
					<li><a href="#terms_div" class="help_tab"><span>Terms</span></a></li>	
				</ul>

				<div id="add_div">
					<h5>When to add a layout, and when to design an existing one?</h5>
					<p>A Layout should be added when you have some <strong>new</strong> content you want shown together on a different background
					or for a different screen type.<br />
					To add to content that is already on a layout and being shown together on the display network you should design the layout.</p>
					
					<h5>To add a Layout</h5>
					<p>To add a layout click on the Add Layout button (green plus sign). A form will appear - enter the details 
					in this form. All required fields are marked with a *.</p>
					
					<h5>To edit a Layout</h5>
					<p>To edit a layout use the fitler or page navigation buttons to find it in the table list and then click the
					edit button found in the action column. <br />
					<strong>Note:</strong> If there is no edit button then you do not
					have permission to edit that layout.</p>
					
					<h5>For adding and editing</h5>
					<p>The layout name be unique - you cannot have two layouts with the same name. <br />
					The description field is for any more information.<br />
					The Shared option determines which other Xibo users can use this layout.</p>
				</div>
				<div id="design_div">
					<h5>Designing a Layout</h5>
					<p>Designing a layout involves adding regions and content to it. Layouts can have many types of content -
					 content from the library (media, text, rss, etc) or content specific to the layout (tickers, text, webpages).</p>
					
					<h5>When should I design a layout?</h5>
					<p>Layouts should be designed either just after they have been created, or whenever content needs to
					be added or changed.</p>
				</div>
				<div id="tips_div">
					<h5>What is a layout?</h5>
					<p>A layout is the concept used to group together content items into a visual style so that they can be 
					scheduled for a display to show. A layout allows you to create slides which display your content. 
					A layout can be made up of multiple regions, holding your media items. The table below shows you all the 
					layouts that you have created along with any others that have been shared by other users on the system.</p>
					
					<h5>The other buttons</h5>
					<p>The action buttons allow you to edit that layout and media.</p>
					
					<h5>Filter Form and Data table</h5>
					<p>You can use the filter form to search for the layout that you would like to work on. Altering values
					in the filter form will alter the layouts shown in the data table below.</p>
					<p>The table headings can be used to sort the data table. Clicking on a heading will sort the table by that
					heading. This can be done in conjunction with a filtered search. Holding down the shift key while 
					sorting on headings will progressively sort the data table.</p>
					<p><strong>E.g.</strong> Select Shared "Public" from the filter form, and then click on the description data
					table heading. The data table is now only showing the "Public" playlists sorted by description.</p>
				</div>
				<div id="terms_div">
					<p><strong>Name</strong> is the name of the layout that was assigned when it was created. This can be edited by clicking 
					on the edit action button in the table</p>
					<p><strong>Description</strong> provides information about the layout. This can be edited by clicking on the edit action 
					button in the table</p>
					<p><strong># Slides</strong> indicates the number of slides on the layout.</p>
					<p><strong>h:mi:ss</strong> this gives you the runtime of the playlist.</p>
					<p><strong>Shared</strong> this states whether a playlist has been made public or private. Public layouts are 
					accesible for all users to schedule where as private playlists can only be scheduled by yourself or an administrator</p>
					<p><strong>Ownership</strong> states the user who has created the layout</p>
					<p><strong>Action</strong> provides buttons for the actions that are available to you for each layout you own.</p>
				</div>
			</div>
		</div>
		<?php $this->LayoutFilter(); ?>	
	</div>	
		
	<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>
</div>	
			