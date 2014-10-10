<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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
 * 	table_cols = Array containing the table columns
 * 	table_rows = Array containing the table rows
 * 	  buttons = The buttons enabled for the layout
 * 	    id = The ID of the button
 * 	    text = The Text for the button
 * 	    url = The URL of the button
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

// Row class defined?
$rowClass = (Theme::Get('rowClass') != '') ? Theme::Get('rowClass') : '';
?>
<table class="table">
	<thead>
		<tr>
			<?php foreach(Theme::Get('table_cols') as $col) { ?>
			<th<?php if (isset($col['helpText']) && $col['helpText'] != '') { echo ' title="' . $col['helpText'] . '"'; } ?><?php if (isset($col['icons']) && $col['icons']) { ?> data-sorter="tickcross"<?php } else if (isset($col['sorter']) && $col['sorter'] != '') { ?> data-sorter="<?php echo $col['sorter'] ?>"<?php } ?>><?php echo $col['title']; ?></th>
			<?php } ?>
			<th data-sorter="false"></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach(Theme::Get('table_rows') as $row) { ?>
		<tr<?php if ($rowClass != '') { echo ' class="' . $row[$rowClass] . '"';} ?>>
			<?php foreach(Theme::Get('table_cols') as $col) { ?>
			<?php if (isset($col['icons']) && $col['icons']) { ?>
			<td><span class="<?php echo ($row[$col['name']] == 1) ? 'glyphicon glyphicon-ok' : (($row[$col['name']] == 0) ? 'glyphicon glyphicon-remove' : 'glyphicon glyphicon-exclamation-sign'); ?>"></span></td>
			<?php } else { ?>
			<td><?php echo $row[$col['name']]; ?></td>
			<?php } ?>
			<?php } ?>
			<?php if (isset($row['buttons']) && is_array($row['buttons']) && count($row['buttons'] > 0)) { ?>
			<td>
				<div class="btn-group pull-right">
    				<button class="btn dropdown-toggle" data-toggle="dropdown">
      					<?php echo Theme::Translate('Action'); ?>
      					<span class="glyphicon glyphicon-tasks"></span>
    				</button>
    				<ul class="dropdown-menu">
						<?php foreach($row['buttons'] as $button) {
							if (isset($button['linkType']) && $button['linkType'] == 'divider') { ?>
								<li class="divider"></li>
							<?php } else if (isset($button['linkType']) && $button['linkType'] != '') { ?>
								<li><a tabindex="-1" target="<?php echo $button['linkType']; ?>" href="<?php echo $button['url']; ?>"><?php echo $button['text']; ?></a></li>
							<?php } else { ?>
								<li class="<?php echo (isset($button['class']) ? $button['class'] : 'XiboFormButton'); ?>" href="<?php echo $button['url']; ?>"><a tabindex="-1" href="#"><?php echo $button['text']; ?></a></li>
							<?php } ?>
						<?php } ?>
    				</ul>
  				</div>
			</td>
			<?php } ?>
		</tr>
		<?php } ?>
	</tbody>
</table>