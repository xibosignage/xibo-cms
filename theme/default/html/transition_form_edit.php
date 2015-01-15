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
			<td><label for="EnabledForIn" accesskey="t" title="<?php echo Theme::Translate('Can this transition be used for media start?'); ?>"><?php echo Theme::Translate('Available for In Transitions?'); ?></label></td>
			<td><input name="EnabledForIn" type="checkbox" id="EnabledForIn" tabindex="3" <?php echo Theme::Get('enabledforin_checked'); ?> /></td>
		</tr>
		<tr>
			<td><label for="EnabledForOut" accesskey="t" title="<?php echo Theme::Translate('Can this transition be used for media end'); ?>"><?php echo Theme::Translate('Available for Out Transitions?'); ?></label></td>
			<td><input name="EnabledForOut" type="checkbox" id="EnabledForOut" tabindex="3" <?php echo Theme::Get('enabledforout_checked'); ?> /></td>
		</tr>
	</table>
</form>