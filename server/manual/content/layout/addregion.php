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
<h1>Regions</h1>
<p>Regions are defined areas on the Layout that can hold sets of content (called Timelines). Regions can be moved around inside the Layout using drag and drop, and resized using the Resize Handle in the Lower Right hand corner of the Region.</p>

<p><img class="img-thumbnail" alt="Layout Designer Screenshot" src="content/layout/layout_designer_region_resize.png"></p>

<p>With each change to a Region a "Save Position" button will appear at the top of the Layout. This must be clicked to Save the Changes that have been made.</p>

<p class="alert alert-info">If a Layout has been created from a Template it will most likely have a full screen Region pre-created - Regions are shown on the Layout Designer as semi-transparent white overlays.</p>

<p class="alert alert-danger">The Display clients have limited support for overlapping Regions - for the best compatibility please sure the Regions do not overlap.</p>

<h2 id="Adding_Regions">Adding Regions</h2>

<p>Regions are added using the Layout Designer Options Menu, pictured below.</p>

<p><img class="img-thumbnail" alt="Layout Designer Screenshot" src="content/layout/layout_designer_options_menu.png"></p>

<p>Once the menu item is clicked a new region appears and is ready to be moved or resized by the designer.</p>

<p><img class="img-thumbnail" alt="New Region Added" src="content/layout/Ss_layout_designer_add_region.png"></p>


<h2 id="Region Menu">Region Menu</h2>
<p>Each Region has its own menu of Actions - similar to the Action menu found on the Layout Table. The Action menu for Regions always appears at the top right of the Region and also shows the Width, Height and Coordinates.</p>

<p><img class="img-thumbnail" alt="Layout Designer Screenshot - Add Region" src="content/layout/layout_designer_region_menu.png"></p>

<dl class="dl-horizontal">
	<dt>Edit Timeline</dt>
	<dd>Assign content to this Region or change the sequence of existing content.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Options</dt>
	<dd>Assign the Region a name and manually adjust its width, height and coordinates.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Delete</dt>
	<dd>Completely remove this Region and all its associated content.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Permissions</dt>
	<dd>Control which users and user groups can view/edit/delete this Region.</dd>
</dl>

<p class="alert alert-info">Ideally Regions that are indended for Video content should be at the same aspect ratio as the indented content.</p>


<h2 id="Removing_Regions">Deleting a Region</h2>
<p>Note that you will loose any media items contained in the region that are not in the library (eg Text, RSS Tickers, Embedded HTML).</p>

<p><img class="img-thumbnail" class="img-thumbnail" alt="Layout Designer Screenshot - Delete Region" src="content/layout/Ss_layout_designer_delete_region.png"></p>

<h2>Options</h2>
<p>The Region options form allows for naming a region, precise sizing and positioning and exit transitions.</p>
 
<p><img class="img-thumbnail" alt="Layout Region Options" src="content/layout/Ss_layout_region_options.png"></p>

<p class="alert alert-info">To get a full screen Region go into the Region options and select "Set Full Screen".</p>

<h2 id="Region_Permission">Permissions</h2>
<p>The owner of the layout has full control on how the new layout is to be shared. A globally shared layout may have one of its layout region access rights being disabled for any other user edit. Right click within the region and select "Permissions" to define the selected region access rights to other users of the <?php echo PRODUCT_NAME; ?> CMS</p>
