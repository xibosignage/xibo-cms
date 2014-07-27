<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2014 Daniel Garner
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
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form class="XiboForm form-horizontal" id="<?php echo Theme::Get('form_id'); ?>" method="post" action="<?php echo Theme::Get('form_action'); ?>">
    <?php echo Theme::Get('form_meta'); ?>
    <fieldset>
        <div class="control-group">
            <label class="control-label" for="name" accesskey="n" title="<?php echo Theme::Translate('The Name of the Profile - (1 - 50 characters)'); ?>"><?php echo Theme::Translate('Name'); ?></label>
            <div class="controls">
                <input name="name" class="required" type="text" id="name" tabindex="1" minlength="1" maxlength="50" required />
            </div>
        </div>
        <div class="control-group">
            <label class="control-label" for='type'><?php echo Theme::Translate('Client Type'); ?></label>
            <div class="controls">
                <?php echo Theme::SelectList('type', Theme::Get('type_field_list'), 'typeid', 'type'); ?>
            </div>
        </div>
        <div class="control-group">
            <div class="controls">
                <label class="checkbox" for="isdefault" accesskey="n" title="<?php echo Theme::Translate('Is this the default profile for all Displays of this type? Only 1 profile can be the default.'); ?>"><?php echo Theme::Translate('Default Profile'); ?>
                    <input class="checkbox" type="checkbox" id="isdefault" name="isdefault">
                </label>
            </div>
        </div>
    </fieldset>
</form>