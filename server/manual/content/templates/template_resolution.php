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
<h1>Resolutions</h1>
<p>Layouts are designed for a particular resolution and scaled down in the designer so they easily fit on the admin PC screen. <?php echo PRODUCT_NAME; ?> has defined a set of the most common resolutions for the designer and for the target display.</p>

<p>The Layout Designer can be zoomed so that a larger design area is available - this does not effect the final display client resolution.</p>

<p>Resolutions can be viewed from the Resolution Administration page under the Design menu, Resolutions sub menu.</p>

<p><img class="img-thumbnail" alt="Resolution Administration" src="content/templates/template_resolution.png"></p>

<p class="alert alert-info"><?php echo PRODUCT_NAME; ?> will natively scale all content based on the Aspect Ratio - it is not necessary to add a new Resolution for all hardware resolutions in your signage network.</p>

<h2>Add / Edit Resolution</h2>
<p>It is possible to add a new Resolution or edit an existing one.</p>
<p><img class="img-thumbnail" alt="Resolution Form" src="content/templates/template_edit_resolution.png"></p>

<p class="alert alert-warning">The Designer Width / Height will always be automatically calculated to fit within the Layout Designer.</p>

<h2>Choosing the right resolution</h2> 
<p><?php echo PRODUCT_NAME; ?> comes with a selection of default resolutions that cater for most digital signage applications (e.g. LCD TVs, projectors, 
portrait screens).</p>

<p class="alert alert-info">The <?php echo PRODUCT_NAME; ?> client will make its best effort to fit whatever shape layout you choose on to the screen. However sending a client a layout in a 4:3 aspect ratio when it is connected to a 16:9 TV wastes two bars on either side of your content.</p>

<p>You should choose a resolution closest to the screen you will be showing the layout on.</p>

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

<h3>Portrait Displays</h3>
<p>There are also specialist versions of the most common resolutions that are turned on their side. Called "Portrait" displays, 
they are taller than they are wide.</p>