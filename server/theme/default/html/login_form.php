<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div>
	<p style="text-align:center"><?php echo Theme::Translate('Xibo requires a valid user login to proceed.'); ?></p>
	<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm" method="post" action="<?php echo Theme::Get('form_action'); ?>">
		<div class="login_table">
			<table>
				<tr>
					<td><label for="username"><?php echo Theme::Translate('User'); ?></label></td>
					<td><input class="username" type="text" id="username" name="username" tabindex="1" size="12" /></td>
				</tr>
				<tr>
					<td><label for="password"><?php echo Theme::Translate('Password'); ?></label></td>
					<td><input class="password" id="password" type="password" name="password" tabindex="2" size="12" /></td>
				</tr>
			</table>
		</div>
	</form>
</div>