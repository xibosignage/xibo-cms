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
 * 	resolution_form_add_url = The Add URL for adding a Resolution
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="row">
	<ul class="nav nav-pills span12">
		<?php
			foreach (Theme::GetMenu('Display Menu') as $item) {
				echo $item['li'];
			}
		?>
	</ul>
</div>
<div class="row">
	<div class="XiboGrid span12" id="<?php echo Theme::Get('id'); ?>">
		<div class="XiboFilter">
			<div class="FilterDiv" id="Filter">
				<form>
					<?php echo Theme::Get('form_meta'); ?>
					<table class="stats_filterform">
						<tr>
							<td><label for="fromdt"><?php echo Theme::Translate('From Date') ?></label></td>
							<td><input type="text" class="date-pick" name="fromdt" value="<?php echo Theme::Get('fromdt'); ?>"/></td>
							<td><label for="todt"><?php echo Theme::Translate('To Date') ?></label></td>
							<td><input type="text" class="date-pick" name="todt" value="<?php echo Theme::Get('todt'); ?>"/></td>
						</tr>
						<tr>
							<td><?php echo Theme::Translate('Display') ?></td>
							<td><?php echo Theme::SelectList('displayid', Theme::Get('display_field_list'), 'displayid', 'displaygroup', 0); ?></td>
							<td><?php echo Theme::Translate('Media') ?></td>
							<td><?php echo Theme::SelectList('mediaid', Theme::Get('media_field_list'), 'mediaid', 'media', 0); ?></td>
						</tr>
					</table>
				</form>
			</div>
		</div>
		<div class="XiboData"></div>
	</div>
</div>
