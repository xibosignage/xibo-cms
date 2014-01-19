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
 * 	user_form_add_url = The Add URL for adding a user
 * 	myapplications_form_add_url = The URL for viewing the users applications
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="row">
	<ul class="nav nav-pills span12">
		<?php
			foreach (Theme::GetMenu('Administration Menu') as $item) {
				echo $item['li'];
			}
		?>
		<li class="pull-right"><a title="<?php echo Theme::Translate('Open the filter form'); ?>" href="#" onclick="ToggleFilterView('Filter')"><span><?php echo Theme::Translate('Filter'); ?></span></a></li>
		<li class="pull-right"><a title="<?php echo Theme::Translate('View my authenticated applications'); ?>" class="XiboFormButton" href="<?php echo Theme::Get('myapplications_form_add_url'); ?>" ><span><?php echo Theme::Translate('My Applications'); ?></span></a></li>
		<li class="pull-right"><a title="<?php echo Theme::Translate('Add a new User'); ?>" class="XiboFormButton" href="<?php echo Theme::Get('user_form_add_url'); ?>" ><span><?php echo Theme::Translate('Add User'); ?></span></a></li>
	</ul>
</div>
<div class="row">
	<div class="XiboGrid span12" id="<?php echo Theme::Get('id'); ?>">
		<div class="XiboFilter">
			<div class="FilterDiv" id="Filter">
				<form>
					<?php echo Theme::Get('form_meta'); ?>
					<table class="user_filterform">
						<tr>
							<td><label for="filter_username"><?php echo Theme::Translate('Name') ?></label></td>
							<td><input type="text" id="filter_username" name="filter_username" value="<?php echo Theme::Get('filter_username'); ?>"></td>
							<td><label for="filter_usertypeid"><?php echo Theme::Translate('User Type') ?></label></td>
							<td><?php echo Theme::SelectList('filter_usertypeid', Theme::Get('usertype_field_list'), 'usertypeID', 'usertype', Theme::Get('filter_usertypeid')); ?></td>
		                    <td><label for="<?php echo Theme::Get('filter_id'); ?>"><?php echo Theme::Translate('Keep filter open') ?></label></td>
		                    <td><input type="checkbox" id="<?php echo Theme::Get('filter_id'); ?>" name="XiboFilterPinned" class="XiboFilterPinned" <?php echo Theme::Get('filter_pinned'); ?> /></td>
						</tr>
					</table>
				</form>
			</div>
		</div>
		<div class="XiboData"></div>
		<?php echo Theme::Get('pager'); ?>
	</div>
</div>