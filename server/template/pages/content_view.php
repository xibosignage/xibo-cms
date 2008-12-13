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
			<div class="buttons">
				<a id="add_button" href="index.php?p=content&q=displayForms&sp=add" alt="Add Content"><div class="button_text">Add Content</div></a>
			</div>
			<div class="leftbuttons">
				<div class="buttons">
				<a id="toggle_button" onclick="toggle_div_view('content_filterform_div')"><div class="button_text">Filter</div></a>
				<a id="help_button" onclick="toggle_div_view('notes')"><div class="button_text">Help</div></a>
			</div>
			<div id="notes" style="display:none;">
					<h4>Content Help <a href="http://www.xibo.org.uk/manual/index.php?p=content/content/overview" target="_blank" alt="The Manual">(Click for Manual)</a></h4>
					<div id="helptabs">
						<ul id="help" class="tabs-nav"><!-- the class on this element has to remain the same -->
							<li><a href="#add_help_div" class="help_tab"><span>Add/Edit</span></a></li>	
							<li><a href="#tips_div" class="help_tab"><span>Tips</span></a></li>
							<li><a href="#terms_div" class="help_tab"><span>Terms</span></a></li>	
						</ul>

						<div id="add_help_div">
							<h5>What is content?</h5>
							<p>Xibo uses content to on layotus. The content library is a store of all the content that
							has been used on layouts in the past, and content to be used on new layouts.</p>

							<h5>When should content be added?</h5>
							<p>From this content library page, content should be added before it is needed. However
							content can also be added when creating layouts using the layout designer.</p>
							
							<h5>To add content</h5>
							<p>Click on the top left hand "Add Content" button. The form presented has 5 content options as
							tabs across the top. To select the appropriate type of content click on the tab required. Enter the details 
							in this form. All required fields are marked with a *.</p>
							
							<h5>To edit content</h5>
							<p>Click on the "Edit" button in the action column.<br />
							<strong>Note:</strong> If there is no edit button then you do not
							have permission to edit that content.</p>
							
						</div>
						<div id="tips_div">
							<p>A content item is piece of media that you wish to display. This can be may different types of media, such as Videos, Flash animations, RSS feeds, Pictures and more.</p>
							<p>The table comprises of all the content items that you have uploaded or created. You can also see any other content items that a user has shared within this table</p>
							<p>You can use the filter form to search for the content item that you would like to modify.</p>
							<p>Xibo does it's best to auto complete items information, but depending on the source this information may not be available.</p>
							<p>Xibo's media support will be constantly expanding to cater for more functionality.<p>
						</div>
						<div id="terms_div">
							<p><strong>Content</strong> is used to define the different items that can be added to the database. This can be in the form of media files, websites, RSS files etc..</p>
							<p><strong>WMV</strong> stands for Windows Media Video files. These files allow full motion video to be shown on your display</p>
							<p><strong>JPG</strong> is the common type of file used for still photos</p>
							<p><strong>PPT</strong> Powerpoint files can also be media types. If you wish to set an automated powerpoint presentation to display all your slides you must configure this prior to upload. Powerpoint
							support is only available under Internet Explorer and with client machines that have MS Office installed.</p>
							<p><strong>SWF</strong> these are flash animation files.</p>
							<p><strong>RSS</strong> you can set an RSS news feed to be displayed. You can also add styling so your information has extra visual impact.</p>
							<p><strong>URL</strong> this will take a live website and display it on the screen</p>
							<p><strong>Text</strong> text can be used to add information or notes to your display. These can have a style associated with them.</p>
						</div>
					</div>
			</div>	
			</div>
	<div id="content_filterform_div">
		<?php $this->displayFilter(); ?>
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
<div id="data_table"></div>

	</div>	
	<div id="form_footer">
		<div id="form_footer_left">
		</div>
		<div id="form_footer_right">
		</div>
	</div>
</div>		