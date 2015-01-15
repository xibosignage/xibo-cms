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
<h1>Layouts</h1>
<p>A layout is a complete screen design, including content and a background image, which can be scheduled across multiple displays at multiple times. A layout has one or more region timelines for media to display and are designed in an Aspect Ratio.</p>

<p><?php echo PRODUCT_NAME; ?> has no limit to the number of Layouts in the system or the number of Layouts each individual user can have.</p>

<p><img class="img-thumbnail" alt="Layout Administration" src="content/layout/layout_administration.png"></p>

<p>Layouts are viewed from the Design menu item and the Layouts sub menu, and they presented with a table containing all the Layouts the user has access to.</p>

<dl class="dl-horizontal">
	<dt>Name</dt>
	<dd>The name of the Layout.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Description</dt>
	<dd>A description of the Layout.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Owner</dt>
	<dd>The user who originally designed the Layout.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Permissions</dt>
	<dd>A list of users with permission to access the Layout.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Status</dt>
	<dd>The current status of the Layout. (Hover over for more information)</dd>
</dl>

<h2>Filtering the Results</h2>
<p>The Layout table can be filtered to display the required results - it also has a pager for navigating through large sets of Layouts.</p>
<p><img class="img-thumbnail" alt="Layout Filter" src="content/layout/layout_administration_filter.png"></p>


<h2>Layout Transactions</h2>
<p>Each Layout has a number of transactions that can be performed on it, these are accessible using the Action button at the right hand edge of each row.</p>
<p><img class="img-thumbnail" alt="Layout Filter" src="content/layout/layout_administration_action.png"></p>

<dl class="dl-horizontal">
	<dt>Schedule Now</dt>
	<dd>Immediately push this Layout to a selected number of Display clients.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Design</dt>
	<dd>Open the Layout Designer and make changes to the Layout.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Edit</dt>
	<dd>Adjust the Layout name or Description.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Copy</dt>
	<dd>Copy this entire Layout.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Delete</dt>
	<dd>Delete the Layout.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Permissions</dt>
	<dd>Adjust the Permissions on the Layout.</dd>
</dl>

<p class="alert alert-warning">The actions available are controlled by the permissions the currently logged in user has - they may not all be present.</p>

<h2 id="Add_Layout">Adding a Layout</h2>
<p>New Layouts can be added using the "Add Layout" menu item, next to the Filter Form menu item shown in the above screenshot. Clicking this button will show the add Layout form, as below.</p>


<p><img class="img-thumbnail" alt="Add layout form" src="content/layout/addlayout.png"></p>

<p>The fields on the Add Layout form are described as follows:</p>

<dl class="dl-horizontal">
	<dt>Name</dt>
	<dd>A name for this layout. It provides a reference for which it can be located for later scheduling or editing. This is the information which will appear in the name column of the layout list.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Description</dt>
	<dd>The optional description field is used to add additional information about the layout, for user later recap the purpose of the layout.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Tags</dt>
	<dd>A space-separated list of keywords to apply to the layout. Tags are used to help search for the layout.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Template</dt>
	<dd>Optionally you can choose a template to base the new layout on. Templates typically provide an aspect ratio, a background image and one or more empty region timelines.</dd>
</dl>

<p class="alert alert-info">When a new layout is created (Save is clicked) the user is automatically taken to the Layout Designer.</p>


<h2 id="Edit_Layout">Editing a Layout</h2>
<p>Each Layout in the table can be edited using the Action menu, Edit menu item. Selecting Edit will show the Edit Layout form, see below.</p>

<p><img class="img-thumbnail" alt="Edit Layout Form" src="content/layout/Ss_layout_edit.png"></p>

<p class="alert alert-danger">It is not possible to edit the Template for a Layout once it has been created. Instead use the Layout Designer to alter the Layout manually.</p>

<p class="alert alert-info">Retired Layouts are hidden by default, but can be shown using the filter options. Once shown they can be edited and un-retired.</p>

<h2 id="Delete_Layout">Delete Layout</h2>
<p>Layouts can be deleted using the delete form. It may be the case that the Layout is in use elsewhere and cannot be deleted. In this case a prompt to Retire the layout will be shown instead.</p>

<p><img class="img-thumbnail" alt="Delete Layout Form" src="content/layout/Ss_layout_delete.png"></p>


<h2 id="Copy_Layout">Copy Layout</h2>
<p>A Layout can be Copied so that an exact duplicate is made which contains all of the same media, or so that a duplicate of all the media is created and linked to the new Layout.</p>

<p><img class="img-thumbnail" alt="Copy Layout Form" src="content/layout/Ss_layout_copy.png"></p>

<p class="alert alert-warning">The schedule information for the source Layout is not copied.</p>
