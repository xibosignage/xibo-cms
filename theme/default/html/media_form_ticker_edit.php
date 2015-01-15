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
                    <label class="control-label" for="uri" title="The Link for the RSS feed"><?php echo Theme::Translate('Feed URL'); ?></label>
                    <div class="controls">
                        <input class="required" id="uri" name="uri" value="<?php echo Theme::Get('uri'); ?>" type="text">
                    </div>
                </div>
    			<div class="control-group">
    				<label class="control-label" for="name" title="A friendly name for this Ticker"><?php echo Theme::Translate('Name'); ?></label>
    				<div class="controls">
    					<input id="name" name="name" value="<?php echo Theme::Get('name'); ?>" type="text">
    				</div>
    			</div>
    			<div class="control-group">
    	    		<label class="control-label" for="direction" title="The Direction this text should move, if any"><?php echo Theme::Translate('Direction'); ?></label>
    	    		<div class="controls">
    	    			<?php echo Theme::SelectList('direction', Theme::Get('direction_field_list'), 'directionid', 'direction', Theme::Get('direction')); ?>
    				</div>
    			</div>
    			<div class="control-group">
    	    		<label class="control-label" for="scrollSpeed" title="The scroll speed of the ticker."><?php echo Theme::Translate('Scroll Speed (higher is faster)'); ?></label>
    	    		<div class="controls">
    	    			<input class="required number" id="scrollSpeed" name="scrollSpeed" type="text" value="<?php echo Theme::Get('scrollSpeed'); ?>">
        			</div>
        		</div>
        		<div class="control-group">
                    <label class="control-label" for="numItems" title="<?php echo Theme::Translate('The Number of RSS items you want to display'); ?>"><?php echo Theme::Translate('Number of Items'); ?></label>
                    <div class="controls">
                    	<input class="number" id="numItems" name="numItems" type="text" value="<?php echo Theme::Get('numItems'); ?>" />
                	</div>
            	</div>
            	<div class="control-group">
            		<label class="control-label" for="itemsPerPage" accesskey="n" title="<?php echo Theme::Translate('When in single mode how many items per page should be shown.'); ?>"><?php echo Theme::Translate('Items per Page'); ?></label>
            	    <div class="controls">
            	        <input class="number" name="itemsPerPage" type="text" id="itemsPerPage" tabindex="1" value="<?php echo Theme::Get('itemsPerPage'); ?>" />
            	    </div>
            	</div>
            	<div class="control-group">
                    <div class="controls">
                        <label class="checkbox" for="fitText" title="<?php echo Theme::Translate('Fit text to region'); ?>"><?php echo Theme::Translate('Fit text to region'); ?>
                            <input id="fitText" name="fitText" type="checkbox" <?php echo Theme::Get('fitTextChecked'); ?>>
                    	</label>
                	</div>
            	</div>
        	</div>
        	<div class="span6">
    			<div class="control-group">
    				<label class="control-label" for="copyright" title="Copyright information to display as the last item in this feed."><?php echo Theme::Translate('Copyright'); ?></label>
    				<div class="controls">
    					<input id="copyright" name="copyright" type="text" value="<?php echo Theme::Get('copyright'); ?>" />
    				</div>
    			</div>
    			<div class="control-group">
                    <label class="control-label" for="duration" accesskey="n" title="<?php echo Theme::Translate('The duration in seconds this media should be displayed'); ?>"><?php echo Theme::Translate('Duration'); ?></label>
                    <div class="controls">
                        <input class="required number" name="duration" type="text" id="duration" tabindex="1" value="<?php echo Theme::Get('duration'); ?>" <?php echo Theme::Get('is_duration_enabled'); ?> />
                    </div>
                </div>
    			<div class="control-group">
    	    		<label class="control-label" for="updateInterval" title="The Interval at which the client should cache the feed."><?php echo Theme::Translate('Update Interval (mins)'); ?></label>
    	    		<div class="controls">
    	    			<input class="required number" id="updateInterval" name="updateInterval" type="text" value="<?php echo Theme::Get('updateInterval'); ?>">
        			</div>
    			</div>
    			<div class="control-group">
                    <label class="control-label" for="takeItemsFrom" title="<?php echo Theme::Translate('Take the items from the beginning or the end of the list'); ?>"><?php echo Theme::Translate('from the '); ?></label>
    	    		<div class="controls">
                    	<?php echo Theme::SelectList('takeItemsFrom', Theme::Get('takeitemsfrom_field_list'), 'takeitemsfromid', 'takeitemsfrom', Theme::Get('takeItemsFrom')); ?>
                	</div>
                </div>
                <div class="control-group">
                    <div class="controls">
                        <label class="checkbox" for="durationIsPerItem" title="<?php echo Theme::Translate('The duration speficied is per item otherwise it is per feed.'); ?>"><?php echo Theme::Translate('Duration is per item'); ?>
                		  <input id="durationIsPerItem" name="durationIsPerItem" type="checkbox" <?php echo Theme::Get('durationIsPerItemChecked'); ?> />
                    	</label>
                	</div>
                </div>
                <div class="control-group">
                    <div class="controls">
                        <label class="checkbox" for="itemsSideBySide" title="<?php echo Theme::Translate('Show items side by side?'); ?>"><?php echo Theme::Translate('Show items side by side?'); ?>
                            <input id="itemsSideBySide" name="itemsSideBySide" type="checkbox" <?php echo Theme::Get('itemsSideBySideChecked'); ?> />
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
    		<div class="control-group">
    			<div class="span12">
    				<textarea id="ta_text" name="ta_text"><?php echo Theme::Get('text'); ?></textarea>
    			</div>
    		</div>
        </div>
        <div class="row-fluid">
            <div class="text-center text-info"><?php echo Theme::Translate('Optional Stylesheet'); ?></div>
        </div>
        <div class="row-fluid">
            <div class="control-group">
                <div class="span12">
                    <textarea class="span12" id="ta_css" name="ta_css"><?php echo Theme::Get('css'); ?></textarea>
                </div>
            </div>
        </div>
    </form>
</div>