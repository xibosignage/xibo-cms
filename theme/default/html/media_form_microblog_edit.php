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
    	<div class="col-md-6">
			<div class="form-group">
                <div class="col-sm-10">
                    <input type="checkbox" name="twitter" <?php echo Theme::Get('twitter_checked'); ?> />
					<label class="checkbox" for="twitter" accesskey="t" title="<?php echo Theme::Translate('Enable Twitter Feed'); ?>"><?php echo Theme::Translate('Twitter'); ?></label>
                </div>
			</div>
			<div class="form-group">
				<label for="searchTerm" accesskey="n" title="<?php echo Theme::Translate('Enter a search term'); ?>"><?php echo Theme::Translate('Search Term'); ?></label>
			    <div class="col-sm-10">
			        <input class="" name="searchTerm" type="text" id="searchTerm" tabindex="1" value="<?php echo Theme::Get('searchTerm'); ?>" />
			    </div>
			</div>
			<div class="form-group">
				<label for="fadeInterval" accesskey="n" title="<?php echo Theme::Translate('Fade Interval'); ?>"><?php echo Theme::Translate('Fade Interval'); ?></label>
			    <div class="col-sm-10">
			        <input class="" name="fadeInterval" type="text" id="fadeInterval" tabindex="1" value="<?php echo Theme::Get('fadeInterval'); ?>" />
			    </div>
			</div>
			<div class="form-group">
				<label for="updateInterval" accesskey="n" title="<?php echo Theme::Translate('How often to update this feed'); ?>"><?php echo Theme::Translate('Update Interval'); ?></label>
			    <div class="col-sm-10">
			        <input class="" name="updateInterval" type="text" id="updateInterval" tabindex="1" value="<?php echo Theme::Get('updateInterval'); ?>" />
			    </div>
			</div>
    	</div>
    	<div class="col-md-6">
    		<div class="form-group">
			    <div class="col-sm-10">
			        <input name="identica" type="checkbox" id="identica" <?php echo Theme::Get('identica_checked'); ?> />
					<label class="checkbox" for="identica" accesskey="n" title="<?php echo Theme::Translate('Enable Identica Feed'); ?>"><?php echo Theme::Translate('Identica'); ?></label>
			    </div>
			</div>
    		<div class="form-group">
                <label for="duration" accesskey="n" title="<?php echo Theme::Translate('The duration in seconds this media should be displayed'); ?>"><?php echo Theme::Translate('Duration'); ?></label>
                <div class="col-sm-10">
                    <input class="required number" name="duration" type="text" id="duration" tabindex="1" value="<?php echo Theme::Get('duration'); ?>" <?php echo Theme::Get('is_duration_enabled'); ?> />
                </div>
            </div>
            <div class="form-group">
            	<label for="speedInterval" accesskey="n" title="<?php echo Theme::Translate('The speed in seconds between each item'); ?>"><?php echo Theme::Translate('Speed'); ?></label>
                <div class="col-sm-10">
                    <input class="" name="speedInterval" type="text" id="speedInterval" tabindex="1" value="<?php echo Theme::Get('speedInterval'); ?>" />
                </div>
            </div>
            <div class="form-group">
            	<label for="historySize" accesskey="n" title="<?php echo Theme::Translate('The History Size in Number of Items'); ?>"><?php echo Theme::Translate('History Size'); ?></label>
                <div class="col-sm-10">
                    <input class="" name="historySize" type="text" id="historySize" tabindex="1" value="<?php echo Theme::Get('historySize'); ?>" />
                </div>
            </div>
    	</div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <p class="text-info text-center"><?php echo Theme::Translate('Message Template'); ?></p>
            <textarea id="ta_template" class="wide_textarea" cols="80" rows="10" name="template"><?php echo Theme::Get('template'); ?></textarea>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <p class="text-info text-center"><?php echo Theme::Translate('Message to display when there are no messages'); ?></p>
            <textarea id="ta_nocontent" class="wide_textarea" cols="80" rows="10" name="nocontent"><?php echo Theme::Get('nocontent'); ?></textarea>
        </div>
    </div>
</form>