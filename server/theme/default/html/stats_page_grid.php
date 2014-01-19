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
<p><?php echo Theme::Translate('Export raw data to CSV'); ?></p>
<form action="<?php echo Theme::Get('form_action'); ?>">
	<?php echo Theme::Get('form_meta'); ?>
	<button type="submit"><?php echo Theme::Translate('Export'); ?></button>
</form>

<p><?php echo Theme::Translate('Layouts Shown'); ?></p>
<table class="table">
	<thead>
		<tr>
			<th><?php echo Theme::Translate('Display'); ?></th>
			<th><?php echo Theme::Translate('Layout'); ?></th>
			<th><?php echo Theme::Translate('Number of Plays'); ?></th>
			<th><?php echo Theme::Translate('Total Duration (s)'); ?></th>
			<th><?php echo Theme::Translate('Total Duration'); ?></th>	
			<th><?php echo Theme::Translate('First Shown'); ?></th>	
			<th><?php echo Theme::Translate('Last Shown'); ?></th>	
		</tr>
	</thead>
	<tbody>
		<?php foreach(Theme::Get('table_layouts_shown') as $row) { ?>
		<tr>
			<td><?php echo $row['Display']; ?></td>
			<td><?php echo $row['Layout']; ?></td>
			<td><?php echo $row['NumberPlays']; ?></td>
			<td><?php echo $row['DurationSec']; ?></td>
			<td><?php echo $row['Duration']; ?></td>
			<td><?php echo $row['MinStart']; ?></td>
			<td><?php echo $row['MaxEnd']; ?></td>
		</tr>
		<?php } ?>
	</tbody>
</table>

<p><?php echo Theme::Translate('Library Media Shown'); ?></p>
<table class="table">
	<thead>
		<tr>
			<th><?php echo Theme::Translate('Display'); ?></th>
			<th><?php echo Theme::Translate('Media'); ?></th>
			<th><?php echo Theme::Translate('Number of Plays'); ?></th>
			<th><?php echo Theme::Translate('Total Duration (s)'); ?></th>
			<th><?php echo Theme::Translate('Total Duration'); ?></th>	
			<th><?php echo Theme::Translate('First Shown'); ?></th>	
			<th><?php echo Theme::Translate('Last Shown'); ?></th>	
		</tr>
	</thead>
	<tbody>
		<?php foreach(Theme::Get('table_media_shown') as $row) { ?>
		<tr>
			<td><?php echo $row['Display']; ?></td>
			<td><?php echo $row['Media']; ?></td>
			<td><?php echo $row['NumberPlays']; ?></td>
			<td><?php echo $row['DurationSec']; ?></td>
			<td><?php echo $row['Duration']; ?></td>
			<td><?php echo $row['MinStart']; ?></td>
			<td><?php echo $row['MaxEnd']; ?></td>
		</tr>
		<?php } ?>
	</tbody>
</table>

<p><?php echo Theme::Translate('Media on Layouts Shown'); ?></p>
<table class="table">
	<thead>
		<tr>
			<th><?php echo Theme::Translate('Display'); ?></th>
			<th><?php echo Theme::Translate('Layout'); ?></th>
			<th><?php echo Theme::Translate('Media'); ?></th>
			<th><?php echo Theme::Translate('Number of Plays'); ?></th>
			<th><?php echo Theme::Translate('Total Duration (s)'); ?></th>
			<th><?php echo Theme::Translate('Total Duration'); ?></th>	
			<th><?php echo Theme::Translate('First Shown'); ?></th>	
			<th><?php echo Theme::Translate('Last Shown'); ?></th>	
		</tr>
	</thead>
	<tbody>
		<?php foreach(Theme::Get('table_media_on_layouts_shown') as $row) { ?>
		<tr>
			<td><?php echo $row['Display']; ?></td>
			<td><?php echo $row['Layout']; ?></td>
			<td><?php echo $row['Media']; ?></td>
			<td><?php echo $row['NumberPlays']; ?></td>
			<td><?php echo $row['DurationSec']; ?></td>
			<td><?php echo $row['Duration']; ?></td>
			<td><?php echo $row['MinStart']; ?></td>
			<td><?php echo $row['MaxEnd']; ?></td>
		</tr>
		<?php } ?>
	</tbody>
</table>