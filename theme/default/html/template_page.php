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
 * 	id = The GridID for rendering AJAX layout table return
 * 	filter_id = The Filter Form ID
 * 	form_meta = Extra form meta that needs to be sent to the CMS to return the list of layouts
 * 	pager = A paging control for this Xibo Grid
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="row">
	<ul class="nav nav-pills span12">
		<?php
			foreach (Theme::GetMenu('Design Menu') as $item) {
				echo $item['li'];
			}
		?>
		<li class="pull-right"><a title="<?php echo Theme::Translate('Open the filter form'); ?>" href="#" onclick="ToggleFilterView('Filter')"><span><?php echo Theme::Translate('Filter'); ?></span></a></li>
	</ul>
</div>
<div class="row">
	<div class="XiboGrid span12" id="<?php echo Theme::Get('id'); ?>">
		<div class="XiboFilter">
			<div class="FilterDiv" id="Filter">
				<form>
					<?php echo Theme::Get('form_meta'); ?>
				
					<table class="filterform">
						<tr>
							<td><?php echo Theme::Translate('Name') ?></td>
							<td><input type="text" name="filter_name" value="<?php echo Theme::Get('filter_name'); ?>"></td>
							<td><?php echo Theme::Translate('System') ?></td>
							<td><?php echo Theme::SelectList('filter_is_system', Theme::Get('is_system_field_list'), 'is_systemid', 'is_system', Theme::Get('filter_is_system')); ?></td>
							<td><label for="<?php echo Theme::Get('filter_id'); ?>"><?php echo Theme::Translate('Keep filter open') ?></label></td>
		                    <td><input type="checkbox" id="<?php echo Theme::Get('filter_id'); ?>" name="XiboFilterPinned" class="XiboFilterPinned" <?php echo Theme::Get('filter_pinned'); ?> /></td>
						</tr>
						<tr>
							<td><?php echo Theme::Translate('Tags') ?></td>
							<td><input type="text" name="filter_tags" value="<?php echo Theme::Get('filter_tags'); ?>" /></td>
						</tr>
					</table>
				</form>
			</div>
		</div>
		<div class="XiboData"></div>
		<?php echo Theme::Get('pager'); ?>
	</div>
</div>