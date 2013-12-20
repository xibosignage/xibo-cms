<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
	<a name="Flash" id="Flash"></a><h2>Flash</h2>
	<p>You can upload your Flash swf files to show on a <?php echo PRODUCT_NAME; ?> layout.</p>

	<p>Add a Flash File</p>

	<ul>
		<li>Click the "Add Flash" icon</li>
		<li>A new dialogue will appear:

		<p><img alt="Ss_layout_designer_add_flash" src="content/layout/Ss_layout_designer_add_flash.png"
		style="display: block; text-align: center; margin-left: auto; margin-right: auto"
		width="458" height="288" border="1px"></p></li>
		
		<li>Click "Browse"</li>
		<li>Select the Flash file you want to upload from your computer. Click OK</li>
		<li>While the file uploads, give the flash file a name for use inside <?php echo PRODUCT_NAME; ?>. Type the name in the "Name" box.</li>
		<li>Finally enter a duration in seconds that you want the flash file to play for.<br />
		<i>Note that if this is the only media item in a region, then this is the minimum amount of time the presentation will be shown 
		    for as the total time shown will be dictated by the total run time of the longest-running region on the layout.</i></li>
		    
		<li>Click "Save"<br />
			<i>Note that the C# control used in the <?php echo PRODUCT_NAME; ?> .net client cannot render the background of Flash files transparently. 
			Flash is always rendered on a white background.</i></li>
	</ul>
