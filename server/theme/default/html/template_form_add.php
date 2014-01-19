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
 * 	form_action = The URL for calling the Layout Add Transaction
 * 	form_meta = Additional META information required by Xibo in the form submit call
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm" method="post" action="<?php echo Theme::Get('form_action'); ?>">
	<?php echo Theme::Get('form_meta'); ?>
	<table>
		<tr>
			<td><label for="template" accesskey="n" title="<?php echo Theme::Translate('The Name of the Template - (1 - 50 characters)'); ?>"><?php echo Theme::Translate('Name'); ?></label></td>
			<td><input name="template" type="text" id="template" tabindex="1" /></td>
			<td><label for="tags" accesskey="t" title="<?php echo Theme::Translate('Tags for this Template - used when searching for it. Space delimited. (1 - 250 characters)'); ?>"><?php echo Theme::Translate('Tags'); ?></label></td>
			<td><input name="tags" type="text" id="tags" tabindex="3" /></td>
		</tr>
		<tr>
			<td colspan="4"><label for="description" accesskey="d" title="<?php echo Theme::Translate('An optional description of the Template. (1 - 250 characters)'); ?>"><?php echo Theme::Translate('Description'); ?>: </label><br/>
			<textarea name="description" type="text" id="description" tabindex="2" rows="4" cols="80"></textarea></td>
		</tr>
	</table>
</form>
