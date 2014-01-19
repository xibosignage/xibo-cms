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
 * 	new_layout_default = The Name of the Layout
 * 	copy_media_checked = Is the copy media checkbox checked
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm form-horizontal" method="post" action="<?php echo Theme::Get('form_action'); ?>">
    <?php echo Theme::Get('form_meta'); ?>
    <fieldset>
        <div class="control-group">
            <label class="control-label" for="layout" accesskey="n" title="<?php echo Theme::Translate('The name for the new layout'); ?>"><?php echo Theme::Translate('New Name'); ?></label>
            <div class="controls">
                <input name="layout" class="required" type="text" id="layout" value="<?php echo Theme::Get('new_layout_default'); ?>" tabindex="1" />
            </div>
        </div>
        <div class="control-group">
            <div class="controls">
                <input class="checkbox" type="checkbox" id="copyMediaFiles" name="copyMediaFiles" <?php echo Theme::Get('copy_media_checked'); ?> />
                <label for="copyMediaFiles" accesskey="c" title="<?php echo Theme::Translate('Make new copies of all media on this layout?'); ?>"><?php echo Theme::Translate('Make new copies of all media on this layout?'); ?></label>
            </div>
        </div>
    </fieldset>
</form>