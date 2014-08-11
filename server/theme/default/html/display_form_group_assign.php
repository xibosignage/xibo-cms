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
 *
 * Theme variables:
 *  displaygroups_assigned_id = 
 *  displaygroups_available_id = 
 *  displaygroups_assigned_url = 
 *  displaygroups_assigned = An array containing all the DisplayGroups currently assigned to this campaign
 *  	DisplayGroup = The name of the DisplayGroup
 *  	list_id = The ID of the List Item
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="row">
	<div class="col-sm-12">
		<p class="text-center text-info"><?php echo Theme::Translate('Drag or double click to move items between lists'); ?></p>
	</div>
</div>
<div class="row">
	<div class="connectedlist col-sm-6">
		<p class="text-info"><?php echo Theme::Translate('Assigned Groups'); ?></p>
		<ul id="<?php echo Theme::Get('displaygroups_assigned_id'); ?>" href="<?php echo Theme::Get('displaygroups_assigned_url'); ?>" class="connectedSortable">
			<?php foreach(Theme::Get('displaygroups_assigned') as $row) { ?>
			<li id="<?php echo $row['list_id']; ?>" class="li-sortable"><?php echo $row['DisplayGroup']; ?></li>
			<?php } ?>
		</ul>
	</div>
	<div class="connectedlist col-sm-6">
		<p class="text-info"><?php echo Theme::Translate('Available Groups'); ?></p>
		<ul id="<?php echo Theme::Get('displaygroups_available_id'); ?>" class="connectedSortable">
			<?php foreach(Theme::Get('displaygroups_available') as $row) { ?>
			<li id="<?php echo $row['list_id']; ?>" class="li-sortable"><?php echo $row['DisplayGroup']; ?></li>
			<?php } ?>
		</ul>
	</div>
</div>
