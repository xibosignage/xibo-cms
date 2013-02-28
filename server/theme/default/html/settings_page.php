<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
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
 * 	id = The GridID for rendering AJAX layout table return
 * 	filter_id = The Filter Form ID
 * 	form_meta = Extra form meta that needs to be sent to the CMS to return the list of layouts
 * 	pager = A paging control for this Xibo Grid
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div id="form_container">
	<div id="form_header">
		<div id="form_header_left"></div>
            <div id="secondaryMenu">
                <ul id="menu" style="padding-left: 26.5em;">
            		<?php
						foreach (Theme::GetMenu('Administration Menu') as $item) {
							echo $item['li'];
						}
					?>
            	</ul>
            </div>
		<div id="form_header_right"></div>
	</div>
	
	<div id="form_body">
		<div class="SecondNav">
			<ul>
				<li><a title="<?php echo Theme::Translate('Save Settings'); ?>" onclick="$('#<?php echo Theme::Get('form_id'); ?>').submit()" ><span><?php echo Theme::Translate('Save'); ?></span></a></li>
				<li><a title="<?php echo Theme::Translate('View Help'); ?>" class="XiboHelpButton" href="<?php echo Theme::Get('settings_help_button_url'); ?>"><span><?php echo Theme::Translate('Help'); ?></span></a></li>
			</ul>
		</div>
		<div id="settings">
			<?php echo Theme::Get('settings_form'); ?>
		</div>
	</div>
		
	<div id="form_footer">
		<div id="form_footer_left"></div>
		<div id="form_footer_right"></div>
	</div>
</div>
