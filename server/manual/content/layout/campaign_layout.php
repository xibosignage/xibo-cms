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
<h1>Campaigns</h1>
<p>Layouts can be grouped together into an ordered list, called a Campaign. Campaigns can then be scheduled as whole units and the Display Client will play them in sequence.</p>

<p>Campaigns are managed in the Campaign Administration page which is accessible from the "Design" navigation item, Campaigns sub menu. Campaigns are simple entities with a Name and a list of assigned Layouts.</p>

<p><img class="img-thumbnail" alt="Campaign Administration" src="content/layout/Ss_layout_campaign.png"></p>

<p>Campaigns have a number of actions available on their Action Menu.</p>

<p><img class="img-thumbnail" alt="Campaign Action Menu" src="content/layout/campaign_action_menu.png"></p>

<dl class="dl-horizontal">
	<dt>Schedule Now</dt>
	<dd>Immediately push this Campaign to a selected number of Display clients.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Layouts</dt>
	<dd>Adjust the list of Layouts assigned to this Campaign.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Edit</dt>
	<dd>Adjust the Campaign name or Description.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Delete</dt>
	<dd>Delete the Campaign.</dd>
</dl>

<dl class="dl-horizontal">
	<dt>Permissions</dt>
	<dd>Adjust the Permissions on the Campaign.</dd>
</dl>

<h2>Add Campaign</h2>
<p>Campaigns are added using the "Add Campaign" menu item in the top right. Only a Name is required.</p>
<p><img class="img-thumbnail" alt="Campaigns_Add" src="content/layout/Ss_layout_campaign_add.png"></p>

<h2>Edit Campaign</h2>
<p>Campaigns can be edited using the Action menu on each Campaign row in the Table. The only item that can be edited is the Name.</p>

<h2>Layouts</h2>
<p>Campaigns contain an ordered list of Layouts - these are adjusted using the Layout action menu item.</p>

<img class="img-thumbnail" alt="Layout_Champaign" src="content/layout/Ss_layout_campaign_layout.png"></p>

<p>Layouts are assigned using the <span class="glyphicon glyphicon-plus-sign"></span> icon, once pressed the Layouts are automatically moved to the staging area. Layouts can be removed from the staging area using the <span class="glyphicon glyphicon-minus-sign"></span> icon.</p>