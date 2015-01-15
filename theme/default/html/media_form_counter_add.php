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
 * 	buttons = An array containing the media buttons
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm form-horizontal" method="post" action="<?php echo Theme::Get('form_action'); ?>">
	<?php echo Theme::Get('form_meta'); ?>
	<p class="text-warning text-center"><?php echo Theme::Translate('Python Client Only'); ?></p>
	<div class="control-group">
		<label class="control-label" for="duration" accesskey="n" title="<?php echo Theme::Translate('The duration in seconds this counter should be displayed'); ?>"><?php echo Theme::Translate('Duration'); ?></label>
		<div class="controls">
			<input class="required number" name="duration" type="text" id="duration" tabindex="1" />
		</div>
	</div>
	<div class="control-group">
		<div class="controls">
			<label class="checkbox" for="popupNotification" accesskey="n" title="<?php echo Theme::Translate('Popup a notification when the counter changes'); ?>">
				<input name="popupNotification" type="checkbox" id="popupNotification" tabindex="2" />
				<?php echo Theme::Translate('Popup Notification?'); ?>
			</label>
		</div>
	</div>
	<div class="control-group">
		<textarea id="ta_text" name="ta_text" rows="5"></textarea>
	</div>
</form>