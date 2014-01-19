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
 * 	form_action = The URL for calling the Layout Add Transaction
 * 	background_image_list	= An array of fields for the background selector
 * 	background_thumbnail_url = The URL to the background thumbnail
 * 	background_id = The MediaID of the current background image
 * 	background_color = The selected background colour
 * 	background_color_list = An array of fields for the background color list
 * 	resolution_field_list = An array of fields for the background resolution
 * 	resolutionid = The selected resoultion
 * 	
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm" method="post" action="<?php echo Theme::Get('form_action'); ?>">
	<?php echo Theme::Get('form_meta'); ?>
	<table>
		<tr>
			<td><label for="bg_color" title="<?php echo Theme::Translate('Use the color picker to select the background color'); ?>"><?php echo Theme::Translate('Background Color'); ?></label></td>
			<td><?php echo Theme::SelectList('bg_color', Theme::Get('background_color_list'), 'colorid', 'color', Theme::Get('background_color'), '', '', 'style'); ?></td>
		</tr>
		<tr>
			<td><label for="bg_image" title="<?php echo Theme::Translate('Select the background image from the library'); ?>"><?php echo Theme::Translate('Background Image'); ?></label></td>
			<td><?php echo Theme::SelectList('bg_image', Theme::Get('background_image_list'), 'mediaid', 'media', Theme::Get('background_id'), 'onchange="background_button_callback()"'); ?></td>
			<td rowspan="3"><img id="bg_image_image" src="<?php echo Theme::Get('background_thumbnail_url'); ?>" alt="<?php echo Theme::Translate('Background thumbnail'); ?>" />
		</tr>
		<tr>
			<td><label for="resolutionid" title="<?php echo Theme::Translate('Pick the resolution'); ?>"><?php echo Theme::Translate('Resolution'); ?></label></td>
			<td><?php echo Theme::SelectList('resolutionid', Theme::Get('resolution_field_list'), 'resolutionid', 'resolution', Theme::Get('resolutionid')); ?></td>
		</tr>
	</table>
</form>



