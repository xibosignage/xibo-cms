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
<div class="row">
	<ul class="nav nav-pills span12">
		<?php
			foreach (Theme::GetMenu('Library Menu') as $item) {
				echo $item['li'];
			}
		?>
		<li class="pull-right"><a title="<?php echo Theme::Translate('Open the filter form'); ?>" href="#" onclick="ToggleFilterView('Filter')"><span><?php echo Theme::Translate('Filter'); ?></span></a></li>
		<li class="pull-right"><a title="<?php echo Theme::Translate('Add a new media item to the library'); ?>" class="XiboFormButton" href="<?php echo Theme::Get('library_form_add_url'); ?>" ><span><?php echo Theme::Translate('Add Media'); ?></span></a></li>
	</ul>
</div>
<div class="row">
	<div class="XiboGrid span12" id="<?php echo Theme::Get('id'); ?>">
		<div class="XiboFilter">
			<div class="FilterDiv" id="Filter">
				<form class="form-inline">
					<?php echo Theme::Get('form_meta'); ?>
				
					<input type="text" placeholder="<?php echo Theme::Translate('Name'); ?>" name="filter_name" value="<?php echo Theme::Get('filter_name'); ?>">
					
					<label class="select">
						<?php echo Theme::Translate('Owner') ?>
						<?php echo Theme::SelectList('filter_owner', Theme::Get('owner_field_list'), 'UserID', 'UserName', Theme::Get('filter_owner')); ?>
					</label>

					<label class="select">
						<?php echo Theme::Translate('Type') ?>
						<?php echo Theme::SelectList('filter_type', Theme::Get('module_field_list'), 'moduleid', 'module', Theme::Get('filter_type')); ?>
					</label>

					<label type="select">
						<?php echo Theme::Translate('Retired') ?>
						<?php echo Theme::SelectList('filter_retired', Theme::Get('retired_field_list'), 'retiredid', 'retired', Theme::Get('retired')); ?>
					</label>

					<label class="checkbox">
						<?php echo Theme::Translate('Duration in Seconds') ?>
						<input type="checkbox" name="filter_duration_in_seconds" <?php echo Theme::Get('filter_duration_in_seconds_checked'); ?> />
					</label>

					<label class="checkbox">
						<?php echo Theme::Translate('Keep Open') ?>
						<input type="checkbox" id="<?php echo Theme::Get('filter_id'); ?>" name="XiboFilterPinned" class="XiboFilterPinned" <?php echo Theme::Get('filter_pinned'); ?> />
					</label>
				</form>
			</div>
		</div>
		<div class="XiboData"></div>
		<?php echo Theme::Get('pager'); ?>
	</div>
</div>