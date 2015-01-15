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
<table id="LibraryAssignTable" class="table table-bordered">
	<thead>
		<tr>
			<th class="group-false"><?php echo Theme::Translate('Name'); ?></th>
			<th class="group-false"><?php echo Theme::Translate('Type'); ?></th>
			<th class="group-false"><?php echo Theme::Translate('Duration'); ?></th>
			<th class="group-false" data-sorter="false"></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach(Theme::Get('table_rows') as $row) { ?>
		<tr rowid="<?php echo $row['list_id']; ?>" litext="<?php echo $row['media']; ?>">
			<td><?php echo $row['media']; ?></td>
			<td><?php echo $row['mediatype']; ?></td>
			<td><?php echo $row['duration_text']; ?></td>
			<td><span class="library_assign_list_select glyphicon glyphicon-plus-sign"></span>
		</tr>
		<?php } ?>
	</tbody>
</table>