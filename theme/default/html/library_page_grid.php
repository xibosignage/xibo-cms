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
 * 	  media = 
 * 	  mediatype = 
 * 	  duration_text =
 * 	  size_text =
 * 	  owner = 
 * 	  permissions = 
 * 	  revised = 
 * 	  filename =  
 * 	  buttons = The buttons enabled for the layout
 * 	    id = The ID of the button
 * 	    text = The Text for the button
 * 	    url = The URL of the button
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<table class="table">
	<thead>
		<tr>
			<th><?php echo Theme::Translate('ID'); ?></th>
			<th><?php echo Theme::Translate('Name'); ?></th>
			<th><?php echo Theme::Translate('Type'); ?></th>
			<th><?php echo Theme::Translate('Duration'); ?></th>	
			<th><?php echo Theme::Translate('Size'); ?></th>	
			<th><?php echo Theme::Translate('Owner'); ?></th>	
			<th><?php echo Theme::Translate('Permissions'); ?></th>	
			<th><?php echo Theme::Translate('Revised'); ?></th>	
			<th><?php echo Theme::Translate('File Name'); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach(Theme::Get('table_rows') as $row) { ?>
		<tr>
			<td><?php echo $row['mediaid']; ?></td>
			<td><?php echo $row['media']; ?></td>
			<td><?php echo $row['mediatype']; ?></td>
			<td><?php echo $row['duration_text']; ?></td>
			<td><?php echo $row['size_text']; ?></td>
			<td><?php echo $row['owner']; ?></td>
			<td><?php echo $row['permissions']; ?></td>
			<td><?php echo $row['revised']; ?></td>
			<td><?php echo $row['filename']; ?></td>
			<td>
				<div class="btn-group pull-right">
    				<button class="btn dropdown-toggle" data-toggle="dropdown">
      					<?php echo Theme::Translate('Action'); ?>
      					<span class="icon-tasks"></span>
    				</button>
    				<ul class="dropdown-menu">
						<?php foreach($row['buttons'] as $button) {
						    if ($button['id'] == 'content_button_download') {
						    ?>
						    <li><a tabindex="-1" href="<?php echo $button['url']; ?>"><?php echo $button['text']; ?></a></li>
						    <?php
						    }
						    else {
						    ?>
						    <li class="XiboFormButton" href="<?php echo $button['url']; ?>"><a tabindex="-1" href="#"><?php echo $button['text']; ?></a></li>
						    <?php
						    } ?>
						
						<?php } ?>
    				</ul>
    			</div>
			</td>
		</tr>
		<?php } ?>
	</tbody>
</table>
