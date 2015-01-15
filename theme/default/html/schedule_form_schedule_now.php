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
<div class="container-fluid">
    <div class="row-fluid">
        <div class="span12">
            <form id="<?php echo Theme::Get('form_id'); ?>" class="XiboForm" action="<?php echo Theme::Get('form_action'); ?>" method="post">
                <?php echo Theme::Get('form_meta'); ?>

            <table style="width:100%;">
                <tr>
                    <td><label for="duration" title="<?php echo Theme::Translate('How long should this event be scheduled for?'); ?>"><?php echo Theme::Translate('Duration'); ?><span class="required">*</span></label></td>
                    <td>H: <input type="text" name="hours" id="hours" size="3" class="number span2">
                    M: <input type="text" name="minutes" id="minutes" size="3" class="number span2">
                    S: <input type="text" name="seconds" id="seconds" size="3" class="number span2"></td>
                </tr>
                <tr>
                    <td><label for="CampaignID" title="<?php echo Theme::Translate('Select which layout this event will show.'); ?>"><?php echo Theme::Translate('Campaign / Layout'); ?><span class="required">*</span></label></td>
                    <td><?php echo Theme::Get('layout_list'); ?></td>
                </tr>
                <tr>
                    <td><label for="DisplayOrder" title="<?php echo Theme::Translate('Select the Order for this Event'); ?>"><?php echo Theme::Translate('Display Order'); ?></label></td>
                    <td><input type="text" name="DisplayOrder" /></td>
                </tr>
                <tr>
                    <td><label title="<?php echo Theme::Translate('Sets whether or not this event has priority. If set the event will be show in preference to other events.'); ?>" for="cb_is_priority"><?php echo Theme::Translate('Priority'); ?></label></td>
                    <td><input type="checkbox" id="cb_is_priority" name="is_priority" title="<?php echo Theme::Translate('Sets whether or not this event has priority. If set the event will be show in preference to other events.'); ?>"></td>
                </tr>
                <tr>
                    <td colspan="2">
                        <div class="FormDisplayList">
                        <?php echo Theme::Get('display_list'); ?>
                        </div>
                    </td>
                </tr>
            </table>
            </form>
        </div>
    </div>
</div>