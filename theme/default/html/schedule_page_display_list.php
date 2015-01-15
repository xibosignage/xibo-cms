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
 * 	group_list_items = 
 * 	display_list_items = 
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="well" id="display-list-well">
	<form id="<?php echo Theme::Get('id'); ?>" class="DisplayListForm">
		<ul class="nav nav-list DisplayList">
			<li class="nav-header"><?php echo Theme::Translate('Misc'); ?></li>
			<li id="checkAllForDisplayList">
				<label for="checkAll">
					<input type="checkbox" name="checkAll">
					<?php echo Theme::Translate('Check All'); ?>
				</label>
			</li>

			<li class="nav-header"><?php echo Theme::Translate('Groups'); ?></li>
			<?php foreach(Theme::Get('group_list_items') as $row) { ?>
			<li>
				<label for="displaygroup_<?php echo $row['displaygroupid']; ?>">
					<input type="checkbox" id="displaygroup_<?php echo $row['displaygroupid']; ?>" name="DisplayGroupIDs[]" value="<?php echo $row['displaygroupid']; ?>" <?php echo $row['checked_text']; ?> />
					<?php echo $row['displaygroup']; ?>
				</label>
			</li>
			<?php } ?>

			<li class="nav-header"><?php echo Theme::Translate('Displays'); ?></li>
			<?php foreach(Theme::Get('display_list_items') as $row) { ?>
			<li>
				<label for="displaygroup_<?php echo $row['displaygroupid']; ?>">
					<input type="checkbox" id="displaygroup_<?php echo $row['displaygroupid']; ?>" name="DisplayGroupIDs[]" value="<?php echo $row['displaygroupid']; ?>" <?php echo $row['checked_text']; ?> />
					<?php echo $row['displaygroup']; ?>
				</label>
			</li>	
			<?php } ?>
		</ul>
	</form>
</div>
