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
<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm form-horizontal" method="post" action="<?php echo Theme::Get('form_action'); ?>">
	<?php echo Theme::Get('form_meta'); ?>
	<fieldset>
		<div class="control-group">
			<label class="control-label" for="layout" accesskey="n" title="<?php echo Theme::Translate('The Name of the Layout - (1 - 50 characters)'); ?>"><?php echo Theme::Translate('Name'); ?></label>
			<div class="controls">
				<input name="layout" type="text" id="layout" tabindex="1" value="<?php echo Theme::Get('layout'); ?>" />
			</div>
		</div>
		<div class="control-group">
			<label class="control-label" for="description" accesskey="d" title="<?php echo Theme::Translate('An optional description of the Layout. (1 - 250 characters)'); ?>"><?php echo Theme::Translate('Description'); ?></label>
			<div class="controls">
				<input name="description" type="text" id="description" tabindex="2" value="<?php echo Theme::Get('description'); ?>" />
			</div>
		</div>
		<div class="control-group">
			<label class="control-label" for="tags" accesskey="t" title="<?php echo Theme::Translate('Tags for this layout - used when searching for it. Space delimited. (1 - 250 characters)'); ?>"><?php echo Theme::Translate('Tags'); ?></label>
			<div class="controls">
				<input name="tags" type="text" id="tags" tabindex="3" value="<?php echo Theme::Get('tags'); ?>" />
			</div>
		</div>
		<div class="control-group">
			<label class="control-label" for='retired' accesskey="r" title="<?php echo Theme::Translate('Retire this layout or not? It will no longer be visible in lists'); ?>"><?php echo Theme::Translate('Retired'); ?></label>
			<div class="controls">
				<?php echo Theme::SelectList('retired', Theme::Get('retired_field_list'), 'retiredid', 'retired', Theme::Get('retired')); ?>
			</div>
		</div>
	</fieldset>
</form>