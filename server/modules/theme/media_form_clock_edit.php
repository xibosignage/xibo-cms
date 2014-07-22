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
 *
 * Theme variables:
 *  buttons = An array containing the media buttons
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm form-horizontal" method="post" action="<?php echo Theme::Get('form_action'); ?>">
    <?php echo Theme::Get('form_meta'); ?>
    <div class="row-fluid">
        <div class="span12">
            <div class="control-group">
                <label class="control-label" for="clockTypeId" accesskey="t" title="<?php echo Theme::Translate('What clock type is required?'); ?>"><?php echo Theme::Translate('Clock Type'); ?></label>
                <div class="controls">
                    <?php echo Theme::SelectList('clockTypeId', Theme::Get('clockType_field_list'), 'clockTypeId', 'clockType', Theme::Get('clockTypeId')); ?>
                </div>
            </div>
            <div class="control-group">
                <label class="control-label" for="duration" accesskey="d" title="<?php echo Theme::Translate('The duration in seconds this counter should be displayed'); ?>"><?php echo Theme::Translate('Duration'); ?></label>
                <div class="controls">
                    <input class="required number" name="duration" type="text" id="duration" tabindex="1" value="<?php echo Theme::Get('duration'); ?>" />
                </div>
            </div>
            <div class="control-group" id="theme-control-group">
                <label class="control-label" for="themeid" accesskey="t" title="<?php echo Theme::Translate('Should the clock be light or dark?'); ?>"><?php echo Theme::Translate('Theme'); ?></label>
                <div class="controls">
                    <?php echo Theme::SelectList('themeid', Theme::Get('theme_field_list'), 'themeid', 'theme', Theme::Get('theme')); ?>
                </div>
            </div>
        </div>
    </div> 
    <div class="row-fluid">
        <div class="span12">
            <p><?php echo Theme::Translate('Enter a format for the Digital Clock below. e.g. [HH:mm] or [DD/MM/YYYY].'); ?></p>
        </div>
    </div>
    <div class="row-fluid" id="format-control-group">
        <div class="span12">
            <textarea id="ta_text" class="wide_textarea" cols="80" rows="10" name="ta_text"><?php echo Theme::Get('format'); ?></textarea>
        </div>
    </div>
</form>