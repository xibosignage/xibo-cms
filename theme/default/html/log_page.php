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
 * 	form_meta = Extra form meta that needs to be sent to the CMS to return the report
 * 	pager = A paging control for this Xibo Grid
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="row">
	<ul class="nav nav-pills span12">
		<?php
			foreach (Theme::GetMenu('Advanced Menu') as $item) {
				echo $item['li'];
			}
		?>
		<li class="pull-right"><a title="<?php echo Theme::Translate('Truncate the Log'); ?>" class="XiboFormButton" href="<?php echo Theme::Get('truncate_url'); ?>"><span><?php echo Theme::Translate('Truncate'); ?></span></a></li>
		<li class="pull-right"><a title="<?php echo Theme::Translate('Refresh the Report'); ?>" href="#" onclick="XiboGridRender('<?php echo Theme::Get('id'); ?>')"><span><?php echo Theme::Translate('Refresh'); ?></span></a></li>
		<li class="pull-right"><a title="<?php echo Theme::Translate('Open the filter form'); ?>" href="#" onclick="ToggleFilterView('Filter')"><span><?php echo Theme::Translate('Filter'); ?></span></a></li>
	</ul>
</div>
<div class="row">
	<div class="XiboGrid span12" id="<?php echo Theme::Get('id'); ?>">
		<div class="XiboFilter">
			<div class="FilterDiv" id="Filter">
				<form>
					<?php echo Theme::Get('form_meta'); ?>
					<table class="filterform" id="report_filterform">
						<tr>
							<td><label for="filter_type"><?php echo Theme::Translate('Type') ?></label></td>
							<td><?php echo Theme::SelectList('filter_type', Theme::Get('type_field_list'), 'typeid', 'type', Theme::Get('filter_typeid')); ?></td>
							<td><label for="filter_fromdt"><?php echo Theme::Translate('From DT') ?></label></td>
							<td><input class="date-pick" type="text" id="filter_fromdt" name="filter_fromdt" value="<?php echo Theme::Get('filter_fromdt'); ?>"></td>
							<td><label for="<?php echo Theme::Get('filter_id'); ?>"><?php echo Theme::Translate('Keep filter open') ?></label></td>
		                    <td><input type="checkbox" id="<?php echo Theme::Get('filter_id'); ?>" name="XiboFilterPinned" class="XiboFilterPinned" <?php echo Theme::Get('filter_pinned'); ?> /></td>
						</tr>
						<tr>
							<td><label for="filter_page"><?php echo Theme::Translate('Page') ?></label></td>
							<td><?php echo Theme::SelectList('filter_page', Theme::Get('page_field_list'), 'pageid', 'page', Theme::Get('filter_page')); ?></td>
							<td><label for="filter_seconds"><?php echo Theme::Translate('Seconds back') ?></label></td>
							<td><input type="text" id="filter_seconds" name="filter_seconds" value="<?php echo Theme::Get('filter_seconds'); ?>"></td>
						</tr>
						<tr>
							<td><label for="filter_function"><?php echo Theme::Translate('Function') ?></label></td>
							<td><?php echo Theme::SelectList('filter_function', Theme::Get('function_field_list'), 'functionid', 'function', Theme::Get('filter_function')); ?></td>
						</tr>
						<tr>
							<td><label for="filter_display"><?php echo Theme::Translate('Display') ?></label></td>
							<td><?php echo Theme::SelectList('filter_display', Theme::Get('display_field_list'), 'displayid', 'display', Theme::Get('filter_display')); ?></td>
						</tr>
					</table>
				</form>
			</div>
		</div>
		<div class="XiboData"></div>
		<?php echo Theme::Get('pager'); ?>
	</div>
</div>
