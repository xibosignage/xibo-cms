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
 * 	  name = 
 * 	  description = 
 * 	  isregionspecific_image = 
 * 	  validextensions = 
 * 	  imageuri = 
 *    preview_enabled_image = 
 *    enabled_image = 
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
			<th><?php echo Theme::Translate('Name'); ?></th>
			<th><?php echo Theme::Translate('Description'); ?></th>
			<th><?php echo Theme::Translate('Library Media'); ?></th>	
			<th><?php echo Theme::Translate('Valid Extensions'); ?></th>	
			<th><?php echo Theme::Translate('Image Uri'); ?></th>	
			<th><?php echo Theme::Translate('Preview Enabled'); ?></th>	
			<th title="<?php echo Theme::Translate('Can this module be assigned to a Layout?'); ?>"><?php echo Theme::Translate('Assignable'); ?></th>	
			<th><?php echo Theme::Translate('Enabled'); ?></th>	
		</tr>
	</thead>
	<tbody>
		<?php foreach(Theme::Get('table_rows') as $row) { ?>
		<tr>
			<td><?php echo $row['name']; ?></td>
			<td><?php echo $row['description']; ?></td>
			<td><span class="<?php echo $row['isregionspecific_image']; ?>"></span></td>
			<td><?php echo $row['validextensions']; ?></td>
			<td><?php echo $row['imageuri']; ?></td>
			<td><span class="<?php echo $row['preview_enabled_image']; ?>"></span></td>
			<td><span class="<?php echo $row['assignable_image']; ?>"></span></td>
			<td><span class="<?php echo $row['enabled_image']; ?>"></span></td>
			<td>
				<div class="btn-group pull-right">
    				<button class="btn dropdown-toggle" data-toggle="dropdown">
      					<?php echo Theme::Translate('Action'); ?>
      					<span class="icon-tasks"></span>
    				</button>
    				<ul class="dropdown-menu">
						<?php foreach($row['buttons'] as $button) { ?>
						<li class="XiboFormButton" href="<?php echo $button['url']; ?>"><a tabindex="-1" href="#"><?php echo $button['text']; ?></a></li>
						<?php } ?>
    				</ul>
  				</div>
			</td>
		</tr>
		<?php } ?>
	</tbody>
</table>