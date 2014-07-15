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
 *  buttons = An array containing the media buttons
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm form-horizontal" method="post" action="<?php echo Theme::Get('form_action'); ?>">
    <?php echo Theme::Get('form_meta'); ?>
    <div class="row">
    	<div class="span4">
			<div class="control-group">
                <label class="control-label" for="duration" accesskey="n" title="<?php echo Theme::Translate('The duration in seconds this data should be displayed'); ?>"><?php echo Theme::Translate('Duration'); ?></label>
                <div class="controls">
                    <input class="required number" name="duration" type="text" id="duration" tabindex="1" value="<?php echo Theme::Get('duration'); ?>" <?php echo Theme::Get('is_duration_enabled'); ?> <?php echo Theme::Get('durationFieldEnabled'); ?> />
                </div>
            </div>
            <div class="control-group">
                <label class="control-label" for="name" title="<?php echo Theme::Translate('An optional Name for this Embedded Item'); ?>"><?php echo Theme::Translate('Name'); ?></label>
                <div class="controls">
                    <input name="name" type="text" id="name" tabindex="2" />
                </div>
            </div>
            <div class="control-group">
                <div class="controls">
                    <label class="checkbox" for="transparency" accesskey="n" title="<?php echo Theme::Translate('Background transparent?'); ?>"><?php echo Theme::Translate('Background transparent?'); ?>
                        <input class="" name="transparency" type="checkbox" id="transparency" tabindex="2" />
                    </label>
                </div>
            </div>
    	</div>
    </div>
    <div class="row">
        <div class="span4">
            <p class="text-info text-center"><?php echo Theme::Translate('HTML to Embed'); ?></p>
            <textarea class="wide_textarea" cols="80" rows="10" name="embedHtml"></textarea>
        </div>
    </div>
    <div class="row">
        <div class="span4">
            <p class="text-info text-center"><?php echo Theme::Translate('HEAD content to Embed (including script tags)'); ?></p>
            <textarea class="wide_textarea" cols="80" rows="10" name="embedScript"><?php echo Theme::Get('default_head_content'); ?></textarea>
        </div>
    </div>
</form>