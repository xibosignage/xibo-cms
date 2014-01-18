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
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="XiboGrid" id="<?php echo Theme::Get('id'); ?>">
	<div class="XiboFilter">
		<div class="FilterDiv" id="Filter">
			<form>
				<?php echo Theme::Get('form_meta'); ?>
				<table class="user_filterform">
					<tr>
						<td><label for="filter_name"><?php echo Theme::Translate('Name') ?></label></td>
						<td><input type="text" id="filter_name" name="filter_name" value="<?php echo Theme::Get('filter_name'); ?>"></td>
					</tr>
				</table>
			</form>
		</div>
	</div>
	<div class="XiboData"></div>
</div>