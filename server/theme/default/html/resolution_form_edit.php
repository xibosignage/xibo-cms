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
 *  form_id = The ID of the Form
 * 	form_action = The URL for calling the Layout Edit Transaction
 * 	form_meta = Additional META information required by Xibo in the form submit call
 * 	layout = The Name of the Layout
 * 	description = The Description of the Layout
 * 	tags = The tags associated with the Layout
 * 	retired = A flag (0|1) indicating whether the Layout is retired or not
 * 	retired_field_list = An array of retired options for a select list (retiredid => retired text)
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm" method="post" action="<?php echo Theme::Get('form_action'); ?>">
	<?php echo Theme::Get('form_meta'); ?>
	<table>
		<tr>
			<td><label for="resolution" accesskey="r" title="<?php echo Theme::Translate('A name for this Resolution'); ?>"><?php echo Theme::Translate('Resolution'); ?></label></td>
			<td><input name="resolution" type="text" id="resolution" tabindex="1" value="<?php echo Theme::Get('resolution'); ?>" /></td>
		</tr>
		<tr>
			<td><label for="width" accesskey="w" title="<?php echo Theme::Translate('The Width for this Resolution'); ?>"><?php echo Theme::Translate('Width'); ?></label></td>
			<td><input name="width" type="text" id="width" tabindex="2" value="<?php echo Theme::Get('width'); ?>" /></td>
		</tr>
		<tr>
			<td><label for="height" accesskey="t" title="<?php echo Theme::Translate('Height for this Resolution'); ?>"><?php echo Theme::Translate('Height'); ?></label></td>
			<td><input name="height" type="text" id="height" tabindex="3" value="<?php echo Theme::Get('height'); ?>" /></td>
		</tr>
	</table>
</form>