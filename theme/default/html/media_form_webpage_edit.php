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
    	<div class="control-group">
    		<label class="control-label" for="uri" accesskey="n" title="<?php echo Theme::Translate('The Location (URL) of the webpage'); ?>"><?php echo Theme::Translate('Link'); ?></label>
    	    <div class="controls">
    	        <input class="required" name="uri" type="text" id="uri" tabindex="1" value="<?php echo Theme::Get('uri'); ?>" />
    	    </div>
    	</div>
		<div class="control-group">
            <label class="control-label" for="duration" accesskey="n" title="<?php echo Theme::Translate('The duration in seconds this media should be displayed'); ?>"><?php echo Theme::Translate('Duration'); ?></label>
            <div class="controls">
                <input class="required number" name="duration" type="text" id="duration" tabindex="2" value="<?php echo Theme::Get('duration'); ?>" <?php echo Theme::Get('is_duration_enabled'); ?> />
            </div>
        </div>
        <div class="control-group">
        	<label class="control-label" for="offsetTop" accesskey="n" title="<?php echo Theme::Translate('The starting point from the top in pixels'); ?>"><?php echo Theme::Translate('Offset Top'); ?></label>
            <div class="controls">
                <input class="number" name="offsetTop" type="text" id="offsetTop" tabindex="3" value="<?php echo Theme::Get('offsetTop'); ?>" />
            </div>
        </div>
        <div class="control-group">
        	<label class="control-label" for="offsetLeft" accesskey="n" title="<?php echo Theme::Translate('The starting point from the left in pixels'); ?>"><?php echo Theme::Translate('Offset Left'); ?></label>
            <div class="controls">
                <input class="number" name="offsetLeft" type="text" id="offsetLeft" tabindex="4" value="<?php echo Theme::Get('offsetLeft'); ?>" />
            </div>
        </div>
        <div class="control-group">
        	<label class="control-label" for="scaling" accesskey="n" title="<?php echo Theme::Translate('The Percentage to Scale this Webpage'); ?>"><?php echo Theme::Translate('Scale Percentage'); ?></label>
            <div class="controls">
                <input class="number" name="scaling" type="text" id="scaling" tabindex="5" value="<?php echo Theme::Get('scaling'); ?>" />
            </div>
        </div>
        <div class="control-group">
            <div class="controls">
                <label class="checkbox" for="transparency" accesskey="n" title="<?php echo Theme::Translate('Background transparent?'); ?>"><?php echo Theme::Translate('Background transparent?'); ?>
                    <input class="" name="transparency" type="checkbox" id="transparency" tabindex="6" <?php echo Theme::Get('transparency_checked'); ?> />
        		</label>
            </div>
        </div>
    </div>
</form>