<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
<h1>Layout Designer <small>Creating content for your displays</small></h1>

<p>The Layout Designer is the heart of content creation in <?php echo PRODUCT_NAME; ?>. Each time a new Layout is created, or an existing one needs a design change, the Layout Designer is used.</p>

<p><?php echo PRODUCT_NAME; ?> makes Layout Design simple through the use of drag and drop, interactive previewing and design flexibility. When Designing a Layout from scratch all of the default templates come with one Full Screen Empty region, pictured below.</p>

<p><img class="img-thumbnail" alt="Layout Designer Screenshot" src="content/layout/Ss_layout_designer.png"></p>

<p class="alert alert-info">This region can be resized and more added to give extra placed content can be queued for display.</p>

<p class="alert alert-warning">All Layouts are designed using the aspect ratio and then resized accordingly on the display client. The Layout Designer has a small design resolution so that the Design can occur without panning around the browser with scroll bars. The small design window does not matter and is seamlessly resized without losses on each Display Client. However, it is important that the Aspect Ratio is chosen correctly.</p>

<h2 id="Options_Menu">Options Menu</h2>
<p>At the top left of the screen below the navigation bar is the options menu. This provides access to the higher level functions in the designer.</p>

<p><img class="img-thumbnail" alt="Layout Designer Screenshot" src="content/layout/layout_designer_options_menu.png"></p>

<dl class="dl-horizontal">
	<dt>Add Region</dt>
	<dd>Adds a new Region to the Layout that can then be positioned (drag/drop) and resized. It can then have content assigned to it.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Background</dt>
	<dd>Change the Background image, colour and aspect ratio.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Properties</dt>
	<dd>Adjust the Layout Properties, such as the name, description and tags.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Schedule Now</dt>
	<dd>Schedule the Layout onto one or more Displays directly from the Layout Designer.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Save Template</dt>
	<dd>Want to use the design again? It can be saved as a Template from here. Templates only save the aspect ratio, background and region positions, not the actual content.</dd>
</dl>

<p class="alert alert-info">For more information about regions, please continue in the <a href="index.php?toc=layouts&p=layout/addregion">regions</a> section.</p>

<h2 id="Background">Layout Background <small>Change the background image, colour and aspect ratio.</small></h2>

<p>Changing the Background is done from the Options menu in the Layout Designer. Once the menu item is selected the following form is shown.</p>

<p><img class="img-thumbnail" alt="Change the Background" src="content/layout/Ss_layout_designer_background.png"></p>

<dl class="dl-horizontal">
	<dt>Background Colour</dt>
	<dd>Select a colour from the list of available background colours.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Background Image</dt>
	<dd>Choose a background image that has been uploaded already. If a new image needs to be added select the "Add Image" button from the button bar at the bottom of the form.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Resolution</dt>
	<dd>Choose the aspect ratio of the layout. These are pre-defined in the Resolutions section.</dd>
</dl>

<p class="alert alert-info">It is advisable to choose a background colour as a fall back, even when using a background image.</p>


<h2 id="Jump_List">Jump List</h2>

<p>The Layout Designer Jump List provides navigation between all Layouts the current user has permissions to edit, without having to jump out to the Layout Administration page each and every time. It is accessible from an icon at the bottom right corner of the browser window, entitled "Layout Jump List".</p>


<p><img alt="Layout Designer Jump" src="content/layout/ss_layout_designer_jumpto.png"></p>

<p>Once clicked, it presents a list of Layout names - clicking the name will load corresponding layout into the designer window.</p>
