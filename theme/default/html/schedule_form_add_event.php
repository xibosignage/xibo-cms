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
        <div class="span6">
            <?php echo Theme::Get('layout_filter'); ?>
        </div>
        <div class="span6">
            <?php echo Theme::Get('display_filter'); ?>
        </div>
    </div>
    <div class="row-fluid">
        <div class="span12">
            <form id="<?php echo Theme::Get('form_id'); ?>" class="XiboScheduleForm" action="<?php echo Theme::Get('form_action'); ?>" method="post">
                <?php echo Theme::Get('form_meta'); ?>
        
            <table style="width:100%;">
                <tr>
                    <td colspan="4"><center><h3><?php echo Theme::Translate('Event Schedule'); ?></h3></center></td>
                </tr>
                <tr>
                    <td>
                        <label for="starttime" title="<?php echo Theme::Translate('Select the start time for this event'); ?>"><?php echo Theme::Translate('Start Time'); ?></label>
                    </td>
                    <td>
                        <div class="date-pick input-append date">
                            <input data-format="dd/MM/yyyy hh:mm" type="text" class="input-medium" name="starttime" id="starttime"></input>
                            <span class="add-on">
                                <i data-time-icon="icon-time" data-date-icon="icon-calendar"></i>
                            </span>
                        </div>
                    </td>
                    <td>
                        <label for="endtime" title="<?php echo Theme::Translate('Select the end time for this event'); ?>"><?php echo Theme::Translate('End Time'); ?></label>
                    </td>
                    <td>
                        <div class="date-pick input-append date">
                            <input data-format="dd/MM/yyyy hh:mm" type="text" class="input-medium" name="endtime" id="endtime"></input>
                            <span class="add-on">
                                <i data-time-icon="icon-time" data-date-icon="icon-calendar"></i>
                            </span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td><label for="DisplayOrder" title="<?php echo Theme::Translate('Select the Order for this Event'); ?>"><?php echo Theme::Translate('Display Order'); ?></label></td>
                    <td><input type="text" name="DisplayOrder" />
                    <td><label title="<?php echo Theme::Translate('Sets whether or not this event has priority. If set the event will be show in preference to other events.'); ?>" for="cb_is_priority"><?php echo Theme::Translate('Priority'); ?></label></td>
                    <td><input type="checkbox" id="cb_is_priority" name="is_priority" title="<?php echo Theme::Translate('Sets whether or not this event has priority. If set the event will be show in preference to other events.'); ?>"></td>
                </tr>
                <tr>
                    <td colspan="4"><center><h3><?php echo Theme::Translate('Recurring Event'); ?></h3></center></td>
                </tr>
                <tr>
                    <td><label for="rec_type" title="<?php echo Theme::Translate('What type of repeat is required?'); ?>"><?php echo Theme::Translate('Repeats'); ?></label></td>
                    <td><?php echo Theme::SelectList('rec_type', Theme::Get('recurrence_field_list'), 'id', 'name'); ?></td>
                    <td><label for="rec_detail" title="<?php echo Theme::Translate('How often does this event repeat?'); ?>"><?php echo Theme::Translate('Repeat every'); ?></label></td>
                    <td><input class="number" type="text" name="rec_detail" /></td>
                </tr>
                <tr>
                    <td><label for="rec_range" title="<?php echo Theme::Translate('When should this event stop repeating?'); ?>"><?php echo Theme::Translate('Until'); ?></label></td>
                    <td><div class="date-pick input-append date">
                        <input data-format="dd/MM/yyyy hh:mm" type="text" class="input-medium" name="rec_range" id="rec_range"></input>
                        <span class="add-on">
                            <i data-time-icon="icon-time" data-date-icon="icon-calendar"></i>
                        </span>
                    </div></td>
                </tr>
            </table>
            </form>
        </div>
    </div>
</div>