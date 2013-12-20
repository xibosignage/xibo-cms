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
	<a name="Image" id="Image"></a><h2>Image</h2>

	<p>This form allows you to add an image to the timeline. Currently JPEG, PNG, BMP and GIF files are supported. 
	Transparency is supported in PNG and GIF files, (but NOT if used as background images).</p>

	<p>Add an image</p>
	<ul>
		<li>Click the "Add Image" icon</li>
		<li>A new dialogue will appear:

		<p><img alt="Ss_layout_designer_add_image" src="content/layout/Ss_layout_designer_add_image.png"
			style="display: block; text-align: center; margin-left: auto; margin-right: auto"
			width="458" height="288" border="1px"></p></li>

		<li>Click "Browse" and navigate to the directory to choose image.</li>
		<li>Select the image file you want to upload from your computer. Click OK. This will proceed to upload the 
			content directly to the <?php echo PRODUCT_NAME; ?> servers.</li>
		<li>While the file uploads, give the image a name for use inside <?php echo PRODUCT_NAME; ?>. Type the name in the "Name" box.</li>
		<li>Finally enter a duration in seconds that the image should remain in the region until the next media item should appear.<br />
			<i>Note that if this is the only media item in a region, then this is the minimum amount of time the image will be shown 
			for as the total time shown will be dictated by the total run time of the longest-running region on the layout.</i></li>
		<li>Click "Save"</li>
	</ul>
