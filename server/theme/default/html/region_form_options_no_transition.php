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
 * 	form_meta = Extra META information required by the Transation
 * 	form_action = The URL for calling the Transaction
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm" method="post" action="<?php echo Theme::Get('form_action'); ?>">
	<?php echo Theme::Get('form_meta'); ?>
	<table>
        <tr>
            <td><label for="name" title="<?php echo Theme::Translate('Name of the Region'); ?>"><?php echo Theme::Translate('Name'); ?></label></td>
            <td><input name="name" type="text" id="name" value="<?php echo Theme::Get('regionName'); ?>" tabindex="1" /></td>
        </tr>
        <tr>
            <td><label for="top" title="<?php echo Theme::Translate('Offset from the Top Corner'); ?>"><?php echo Theme::Translate('Top Offset'); ?></label></td>
            <td><input name="top" type="text" id="top" value="<?php echo Theme::Get('top'); ?>" tabindex="2" /></td>
        </tr>
        <tr>
            <td><label for="left" title="<?php echo Theme::Translate('Offset from the Left Corner'); ?>"><?php echo Theme::Translate('Left Offset'); ?></label></td>
            <td><input name="left" type="text" id="left" value="<?php echo Theme::Get('left'); ?>" tabindex="3" /></td>
        </tr>
        <tr>
            <td><label for="width" title="<?php echo Theme::Translate('Width of the Region'); ?>"><?php echo Theme::Translate('Width'); ?></label></td>
            <td><input name="width" type="text" id="width" value="<?php echo Theme::Get('width'); ?>" tabindex="4" /></td>
        </tr>
        <tr>
            <td><label for="height" title="<?php echo Theme::Translate('Height of the Region'); ?>"><?php echo Theme::Translate('Height'); ?></label></td>
            <td><input name="height" type="text" id="height" value="<?php echo Theme::Get('height'); ?>" tabindex="5" /></td>
        </tr>
    </table>
</form>