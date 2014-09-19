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
 * 	layout_form_add_url = The URL for calling the Layout Add Form
 * 	id = The GridID for rendering AJAX layout table return
 * 	filter_id = The Filter Form ID
 * 	form_meta = Extra form meta that needs to be sent to the CMS to return the list of layouts
 * 	pager = A paging control for this Xibo Grid
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div id="FileAssociationsAssign" class="well">
	<div>
    	<ul id="FileAssociationsSortable">
		<?php foreach(Theme::Get('existing_associations') as $row) { ?>
			<li id="MediaID_<?php echo $row['MediaID']; ?>" class="li-sortable"><?php echo $row['Name']; ?><span class="glyphicon glyphicon-minus-sign"></span></li>
		<?php } ?>
    	</ul>
    </div>
</div>
<div class="XiboGrid" id="<?php echo Theme::Get('id'); ?>">
	<div class="XiboFilter">
		<div class="FilterDiv" id="Filter">
			<form>
				<?php echo Theme::Get('form_meta'); ?>
			
				<table class="filterform">
					<tr>
						<td><?php echo Theme::Translate('Name') ?></td>
						<td><input class="form-control" type="text" name="filter_name" value="<?php echo Theme::Get('filter_name'); ?>"></td>
						<td><?php echo Theme::Translate('Type') ?></td>
						<td><?php echo Theme::SelectList('filter_type', Theme::Get('module_field_list'), 'moduleid', 'module', Theme::Get('filter_type')); ?></td>
					</tr>
				</table>
			</form>
		</div>
	</div>
	<div class="XiboData"></div>
	<?php echo Theme::Get('pager'); ?>
</div>
