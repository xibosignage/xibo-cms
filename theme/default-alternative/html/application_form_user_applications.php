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
 * 	  layout = The layout name
 * 	  description = The layout description
 * 	  owner = The Layout Owner
 * 	  permissions = The Permissions for the layout
 * 	  buttons = The buttons enabled for the layout
 * 	    id = The ID of the button
 * 	    text = The Text for the button
 * 	    url = The URL of the button
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="info_table">
	<table style="width:100%">
		<thead>
			<tr>
				<th><?php echo Theme::Translate('Application'); ?></th>
				<th><?php echo Theme::Translate('Enabled'); ?></th>
				<th><?php echo Theme::Translate('Status'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach(Theme::Get('table_rows') as $row) { ?>
			<tr>
				<td><?php echo $row['application_title']; ?></td>
				<td><?php echo $row['enabled']; ?></td>
				<td><?php echo $row['status']; ?></td>
			</tr>
			<?php } ?>
		</tbody>
	</table>
</div>