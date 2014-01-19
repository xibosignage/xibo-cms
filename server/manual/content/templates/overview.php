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
<h1>Templates</h1>
<p>Templates allow a user to save a layouts generic properties so that it can be used to create the same layout with different content. A number of default templates are provided which are a single full screen Layout at a specific Aspect Ratio.</p>

<p>Templates are accessed using the Design top navigation menu item and the "Templates" sub-menu item.</p>

<p><img class="img-thumbnail" alt="Template Administration" src="content/templates/template_standard.png"></p>

<h2>Adding a new Template</h2>
<p>Templates are added from the Layout Designer Options Menu. The template will automatically contain the Layout size and Region positions.</p>

<p><img class="img-thumbnail" alt="Template Administration" src="content/layout/layout_designer_options_menu.png"></p>
<p><img class="img-thumbnail" alt="Template Administration" src="content/templates/template_save_from_layout.png"></p>


<h2>Choosing the right template</h2> 
<p><?php echo PRODUCT_NAME; ?> comes with a selection of default templates that caters for most digital signage applications (e.g. LCD TVs, projectors, 
portrait screens).</p>

<p>The <?php echo PRODUCT_NAME; ?> client will make its best effort to fit whatever shape layout you choose on to the screen. However sending a client 
a layout in a 4:3 aspect ratio when it is connected to a 16:9 TV wastes two bars on either side of your content.</p>

<p>You should choose a template with the closest aspect ratio to the screen you will be showing the layout on. 
Here's a list of typical displays and the template to choose:</p>

<dl>
	<dt>LCD/Plasma Wide screen TV</dt>
	<dd>LCD TVs tend to be either 16:9 aspect ratio. Try "Full Screen 16:9" first. If you find there are slim black bars to the top and bottom of your full screen content, try switching to "Full Screen 16:10".</dd>
</dl>

<dl>
	<dt>Wide screen Projectors</dt>
	<dd>Wide screen projectors can be 16:9 or 16:10 so you'll need to try both and see which fits best.</dd>
</dl>

<dl>
	<dt>Wide screen Computer Monitors</dt>
	<dd>Wide screen Computer Monitors are usually 16:10 but there are a few 16:9 ones out there. Try "Full Screen 16:10" first.</dd>
</dl>

<dl>
	<dt>Computer Monitors</dt>
	<dd>Non-wide screen computer monitors are usually 4:3 aspect ratio. Try "Full Screen 4:3".</dd>
</dl>

<dl>
	<dt>CRT Televisions</dt>
	<dd>Old TVs tend to be either 4:3 or 3:2 aspect ratio. Try "Full Screen 4:3" or "Full Screen 3:2" and see which fits best.</dd>
</dl>

<p>There are also specialist versions of all the above displays that are turned on their side. Called "Portrait" displays, 
they are taller than they are wide. <?php echo PRODUCT_NAME; ?> supports all the above aspect ratios for portrait screens - 2:3, 3:4, 9:16, 10:16. 
Consult your display manufacturer to find out which aspect ratio to choose.</p>
