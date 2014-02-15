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
<div class="container-fluid">
    <form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm form-horizontal" method="post" action="<?php echo Theme::Get('form_action'); ?>">
        <?php echo Theme::Get('form_meta'); ?>
        <div class="row-fluid">
        	<div class="span6">
    			<div class="control-group">
    				<label class="control-label" for="direction" accesskey="n" title="<?php echo Theme::Translate('Direction to Scroll'); ?>"><?php echo Theme::Translate('Direction'); ?></label>
    			    <div class="controls">
    			        <?php echo Theme::SelectList('direction', Theme::Get('direction_field_list'), 'directionid', 'direction', Theme::Get('direction')); ?>
    			    </div>
    			</div>
        		<div class="control-group">
        			<label class="control-label" for="scrollSpeed" accesskey="n" title="<?php echo Theme::Translate('The scroll speed to apply if a direction is specified. Higher is faster.'); ?>"><?php echo Theme::Translate('Scroll Speed'); ?></label>
        		    <div class="controls">
        		        <input class="" name="scrollSpeed" type="text" id="scrollSpeed" tabindex="1" value="<?php echo Theme::Get('scrollSpeed'); ?>" />
        		    </div>
        		</div>
        	</div>
        	<div class="span6">
    			<div class="control-group">
                    <label class="control-label" for="duration" accesskey="n" title="<?php echo Theme::Translate('The duration in seconds this media should be displayed'); ?>"><?php echo Theme::Translate('Duration'); ?></label>
                    <div class="controls">
                        <input class="required number" name="duration" type="text" id="duration" tabindex="3" value="<?php echo Theme::Get('duration'); ?>" <?php echo Theme::Get('is_duration_enabled'); ?> />
                    </div>
                </div>
                <div class="control-group">
                    <div class="controls">
                        <label class="checkbox" for="fitText" accesskey="n" title="<?php echo Theme::Translate('Fit text to region'); ?>"><?php echo Theme::Translate('Fit text to region'); ?>
                            <input class="checkbox" name="fitText" type="checkbox" id="fitText" tabindex="2" <?php echo Theme::Get('fitTextChecked'); ?> />
                		</label>
                    </div>
                </div>
        	</div>
        </div>
        <div class="row-fluid">
            <div class="span12">
                <div class="well">
                    <div class="text-center text-info"><?php echo Theme::Translate('Available Substitutions'); ?></div>
                        <ul id="TickerDataSetColumns">
                            <?php foreach(Theme::Get('substitutions') as $column) { ?>
                            <li class="ckeditor_snippits" linkedto="ta_text"><?php echo $column['Substitute']; ?></li>
                            <?php } ?>
                        </ul>
                </div>
            </div>
        </div>
        <div class="row-fluid">
            <div class="span12">
                <textarea id="ta_text" class="wide_textarea" cols="80" rows="10" name="ta_text"><?php echo Theme::Get('text'); ?></textarea>
            </div>
        </div>
    </form>
</div>