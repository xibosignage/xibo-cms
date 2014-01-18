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
                    <label class="control-label" for="duration" accesskey="n" title="<?php echo Theme::Translate('The duration in seconds this data should be displayed'); ?>"><?php echo Theme::Translate('Duration'); ?></label>
                    <div class="controls">
                        <input class="required number" name="duration" type="text" id="duration" tabindex="1" value="<?php echo Theme::Get('duration'); ?>" <?php echo Theme::Get('is_duration_enabled'); ?> <?php echo Theme::Get('durationFieldEnabled'); ?> />
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="lowerLimit"><?php echo Theme::Translate('Lower Row Limit'); ?></label>
                    <div class="controls">
                        <input class="numeric required" id="lowerLimit" name="lowerLimit" type="text" value="<?php echo Theme::Get('lowerLimit'); ?>">
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="ordering"><?php echo Theme::Translate('Order'); ?></label>
                    <div class="controls">
                        <input id="ordering" name="ordering" type="text" value="<?php echo Theme::Get('ordering'); ?>">
                    </div>
                </div>
                <div class="control-group">
                    <div class="controls">
                        <label for="showHeadings">
                            <input id="showHeadings" name="showHeadings" type="checkbox" <?php echo Theme::Get('showHeadingsChecked'); ?>>
                            <?php echo Theme::Translate('Show the table headings?'); ?>
                        </label>
                    </div>
                </div>
            </div>
            <div class="span6">
                <div class="control-group">
                    <label class="control-label" for="updateInterval"><?php echo Theme::Translate('Update Interval (mins)'); ?></label>
                    <div class="controls">
                        <input id="updateInterval" name="updateInterval" type="text" value="<?php echo Theme::Get('updateInterval'); ?>">
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="upperLimit"><?php echo Theme::Translate('Upper Row Limit'); ?></label>
                    <div class="controls">
                        <input class="numeric required" id="upperLimit" name="upperLimit" type="text" value="<?php echo Theme::Get('upperLimit'); ?>">
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="filter"><?php echo Theme::Translate('Filter'); ?></label>
                    <div class="controls">
                        <input id="filter" name="filter" type="text" value="<?php echo Theme::Get('filter'); ?>">
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label" for="rowsPerPage" accesskey="n" title="<?php echo Theme::Translate('The number of rows per page'); ?>"><?php echo Theme::Translate('Rows per page'); ?></label>
                    <div class="controls">
                        <input class="number" name="rowsPerPage" type="text" id="rowsPerPage" tabindex="1" value="<?php echo Theme::Get('rowsPerPage'); ?>" />
                    </div>
                </div>
            </div>
        </div>
        <div class="row-fluid">
            <div class="connectedlist span6">
                <p class="text-info"><?php echo Theme::Translate('Columns Selected'); ?></p>
                <?php echo Theme::Get('columns_selected_list'); ?>
            </div>
            <div class="connectedlist span6">
                <p class="text-info"><?php echo Theme::Translate('Columns Available'); ?></p>
                <?php echo Theme::Get('columns_available_list'); ?>
            </div>
        </div>
        <div class="row-fluid">
            <div class="span12">
                <p class="text-info text-center"><?php echo Theme::Translate('Stylesheet for the Table'); ?></p>
                <textarea class="wide_textarea" cols="80" rows="10" id="styleSheet" name="styleSheet"><?php echo Theme::Get('styleSheet'); ?></textarea>
            </div>
        </div>
    </form>
</div>