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
<h1>Overview <small>Welcome to <?php echo PRODUCT_NAME; ?></small></h1>

<p><?php echo PRODUCT_NAME; ?> is a flexible and powerful application with a core "ethos" to digital signage that is important to understand from the very beginning.</p>

<p>A <?php echo PRODUCT_NAME; ?> solution is based on 4 things:</p>
<ul>
	<li>Displays</li>
	<li>Media</li>
	<li>Layouts</li>
	<li>Scheduling</li>
</ul>

<p>So what does all this mean? Basically <?php echo PRODUCT_NAME; ?> will allow multiple different displays to show media contained on multiple different screen layouts on a scheduled basis.</p>

<p>This manual has sections for all of these things, but lets take a quick look at each to get started.</p>

<dl class="dl-horizontal">
	<dt>Displays</dt>
	<dd>These represent the hardware connected to the TV/Projector/Tablet panel that is actually displaying the end content. In other words Displays drive the medium people will look at. Each Display is uniquely identified in the CMS so that they can have their own unique content, layout designs and schedules set to them. They can also be uniquely identified for reporting statistics.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Media</dt>
	<dd>Media content is the core of a <?php echo PRODUCT_NAME; ?> solution and many different types of content are supported. These are generally split into two categories. File based media that is uploaded and stored in the Library and Layout based media that doesn't have an associated file but is configured directly on a layout instead. An image or video would be Library Media and a RSS feed or some free Text would be Layout based media.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Layouts</dt>
	<dd>These are the design that is seen on the screen. <?php echo PRODUCT_NAME; ?> allows you to split the screen up into different Regions, each containing their own Timeline of media to play. A layout itself remains on screen until all the Timelines have finished playing through once, then an entirely fresh Layout can be loaded with different positioning and size of Regions, with different Timelines. This flexiblilty drives the dynamic nature of an <?php echo PRODUCT_NAME; ?> display screen.</dd>
</dl>
<dl class="dl-horizontal">
	<dt>Scheduling</dt>
	<dd>Once displays are registered, media content is uploaded and Layouts are designed it is time to put them all together and Schedule Layouts onto the Displays. Each Display will check for new Scheduled content periodically, and download Scheduled Items in advance of playback. Scheduling is highly flexible and supports scheduling to single Displays and Groups, single Layouts and Campaigns and Recurring Schedules. Each Display has a Default Layout that will be shown when nothing else is Scheduled.</dd>
</dl>

<h2>Content <small>What can <?php echo PRODUCT_NAME; ?> play?</h2>
<p><?php echo PRODUCT_NAME; ?> can play a wide range of file based content, as well as consuming content from the Internet.</p>
<ul>
	<li>Text</li>
	<li>Images</li>
	<li>Video</li>
	<li>PowerPoint (Windows Display Client only)</li>
	<li>Flash (Windows Display Client only)</li>
	<li>RSS / Atom</li>
	<li>CSV file (formatted)</li>
	<li>Web Pages</li>
	<li>Custom Embedded Content</li>
</ul>

<p>A full list of features are available on the <?php echo PRODUCT_NAME; ?> website.</p>

<h2>CMS <small>How can all this be managed?</small></h2>
<p>Management of a solution as flexible as <?php echo PRODUCT_NAME; ?> required a powerful CMS (Content Management System) to present the core concepts in a sensible, controlled fashion. The <?php echo PRODUCT_NAME; ?> CMS is packed with features, such as:</p>
<ul>
	<li>User Groups</li>
	<li>User and Group Permissions</li>
	<li>Display Groups</li>
	<li>Display Group Permissions</li>
	<li>Menu and Page Permissions</li>
	<li>Campaigns (ordered groups of Layouts)</li>
	<li>Layout Templates</li>
	<li>Display Statistics</li>
</ul>

<h2>The Manual <small>Final Thoughts</small></h2>
<p>This manual has been organised into sections that broadly reprent the core concepts and the more advanced CMS features. We start off with the Library, Layouts and Scheduling and then move on to Display Management, Users and Developer Options.</p>