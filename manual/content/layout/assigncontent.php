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
<h1>Region Timelines</h1>
<p>Each Region on a <?php echo PRODUCT_NAME; ?> Layout has its own Timeline of content, which will be shown in order by the Display Client. If required a timeline can consist of a single item (such as a company logo), or multiple items (such as an image slideshow).</p>

<p>A Regions Timeline is viewed/edited by using the Region Action button and selecting Edit Timeline. The following form is shown.</p>

<p><img class="img-thumbnail" alt="Region Timeline" src="content/layout/Ss_layout_designer_region_timeline.png"></p>		

<p class="alert alert-info">The sequence of media in the Timeline is vertical - top down.</p>

<h2 id="Assigning_Content">Assigning Content to Regions</h2>
<p>Content is Assigned to a Region Timeline using the "Media Toolbox" shown on the left hand side of the Region Timeline form. This toolbox lists all of the enabled media types on the CMS.</p>

<p class="alert alert-info">Media Types can be enabled / disabled in the <a href="index.php?toc=library&p=admin/modules" title="Modules">Modules section</a>.</p>


<h3 id="Add_From_Library">Adding from the Library</h3>
<p>The first link in the Media Toolbox is the "Library" link. This allows stored content to be assigned directly from the CMS library. This content may have been uploaded in a different Layout, or directly into the Library.</p>

<p><img class="img-thumbnail" alt="Assign from Library form" src="content/layout/ss_layout_designer_add_library.png"></p>

<p>Pressing the "Library" link in the media toolbox will open the Assignment form (above). The Assignment form is a standard CMS form with a "staging area" for media which has been queued for assignment.</p>

<p>Media is assigned using the <span class="glyphicon glyphicon-plus-sign"></span> icon, once pressed the media item is automatically moved to the staging area. Items can be removed from the staging area using the <span class="glyphicon glyphicon-minus-sign"></span> icon.</p>

<h3>Editing Assigned Content</h3>
<p>Media content that has already been assigned to the timeline can be edited or removed using the links on the timeline media bars. Each bar has its own "Action" links.</p>
<p><img class="img-thumbnail" alt="Edit from the Media Bar" src="content/layout/Ss_layout_region_contentedit.png"></p>

<h3>Deleting Content</h3>
<p>Each media type has its own unique delete form which taken into account the specific behaviour of that media type during the delete operation. Typically the CMS will offer 3 options, Unassign, Retire or Delete.</p>
<p><img class="img-thumbnail" alt="Delete Form" src="content/layout/layout_media_delete_form.png"></p>

<p class="alert alert-warning">Media that exists only on the Layout (e.g. Text) only offers a single delete option.</p>

<h3>Reordering Items in the Timeline</h3>
<p>The sequence of a media item in the timeline can be changed with drag and drop. Once the item in the desired position press the "Save Order" button at the bottom of the Region Timeline form.</p>

<p><img class="img-thumbnail" alt="Reorder items on the timeline" src="content/layout/Reorder-Items-on-Timeline.png"></p>
