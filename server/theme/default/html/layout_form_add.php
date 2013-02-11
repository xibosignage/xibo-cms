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
 * 	form_action = The URL for calling the Layout Add Transaction
 * 	template_field_list	= An array of fields for the template selector (templateid => template)
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form id="LayoutForm" class="XiboForm" method="post" action="<?php echo Theme::Get('form_action'); ?>">
	<table>
		<tr>
			<td><label for="layout" accesskey="n" title="<?php echo Theme::Translate('The Name of the Layout - (1 - 50 characters)'); ?>"><?php echo Theme::Translate('Name'); ?></label></td>
			<td><input name="layout" type="text" id="layout" tabindex="1" /></td>
		</tr>
		<tr>
			<td><label for="description" accesskey="d" title="<?php echo Theme::Translate('An optional description of the Layout. (1 - 250 characters)'); ?>"><?php echo Theme::Translate('Description'); ?></label></td>
			<td><input name="description" type="text" id="description" tabindex="2" /></td>
		</tr>
		<tr>
			<td><label for="tags" accesskey="t" title="<?php echo Theme::Translate('Tags for this layout - used when searching for it. Space delimited. (1 - 250 characters)'); ?>"><?php echo Theme::Translate('Tags'); ?></label></td>
			<td><input name="tags" type="text" id="tags" tabindex="3" /></td>
		</tr>
		<tr>
        	<td><label for='templateid'><?php echo Theme::Translate('Template'); ?></label></td>
        	<td><?php echo Theme::SelectList('templateid', Theme::Get('template_field_list'), 'templateid', 'template'); ?></td>
        </tr>
	</table>
</form>