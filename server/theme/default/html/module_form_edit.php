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
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm" method="post" action="<?php echo Theme::Get('form_action'); ?>">
	<?php echo Theme::Get('form_meta'); ?>
	<table>
		<tr>
			<td><label for="ValidExtensions" accesskey="e" title="<?php echo Theme::Translate('The Extensions allowed on files uploaded using this module. Comma Seperated.'); ?>"><?php echo Theme::Translate('Valid Extensions'); ?></label></td>
			<td><input name="ValidExtensions" type="text" id="ValidExtensions" tabindex="1" value="<?php echo Theme::Get('validextensions'); ?>" /></td>
		</tr>
		<tr>
			<td><label for="ImageUri" accesskey="d" title="<?php echo Theme::Translate('The Image to display for this module'); ?>"><?php echo Theme::Translate('Image Uri'); ?></label></td>
			<td><input name="ImageUri" type="text" id="ImageUri" tabindex="2" value="<?php echo Theme::Get('imageuri'); ?>" /></td>
		</tr>
		<tr>
			<td><label for="PreviewEnabled" accesskey="t" title="<?php echo Theme::Translate('When PreviewEnabled users will be able to see a preview in the layout designer'); ?>"><?php echo Theme::Translate('Preview Enabled'); ?></label></td>
			<td><input name="PreviewEnabled" type="checkbox" id="PreviewEnabled" tabindex="3" <?php echo Theme::Get('preview_enabled_checked'); ?> /></td>
		</tr><tr>
			<td><label for="Enabled" accesskey="t" title="<?php echo Theme::Translate('When Enabled users will be able to add media using this module'); ?>"><?php echo Theme::Translate('Enabled'); ?></label></td>
			<td><input name="Enabled" type="checkbox" id="Enabled" tabindex="3" <?php echo Theme::Get('enabled_checked'); ?> /></td>
		</tr>
	</table>
</form>