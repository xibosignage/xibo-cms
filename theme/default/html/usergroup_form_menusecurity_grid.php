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
 * 	table_rows = Array containing the table rows
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="info_table">
	<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm" method="post" action="<?php echo Theme::Get('form_action'); ?>">
		<?php echo Theme::Get('form_meta'); ?>
		<table class="table table-bordered">
			<thead>
				<tr>
					<th class="group-false"></th>
					<th class="group-false"><?php echo Theme::Translate('Menu Item'); ?></th>
					<th class="group-false"><?php echo Theme::Translate('Assigned'); ?></th>	
				</tr>
			</thead>
			<tbody>
				<?php foreach(Theme::Get('table_rows') as $row) { ?>
				<tr>
					<td><input type="checkbox" name="pageids[]" value="<?php echo $row['checkbox_value']; ?>" <?php echo $row['checkbox_ticked']; ?>></td>
					<td><?php echo $row['name']; ?></td>
					<td><span class="<?php echo $row['assigned']; ?>"></span></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
	</form>
</div>