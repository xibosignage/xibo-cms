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
 *  id = The GridID for rendering AJAX layout table return
 *  filter_id = The Filter Form ID
 *  form_meta = Extra form meta that needs to be sent to the CMS to return the list of layouts
 *  pager = A paging control for this Xibo Grid
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
?>
<div class="row">
    <div class="col-sm-12">
        <div class="btn-group pull-right xibo-calendar-controls">
            <button type="button" class="btn btn-warning" data-calendar-view="year"><?php echo Theme::Translate('Year'); ?></button>
            <button type="button" class="btn btn-warning active" data-calendar-view="month"><?php echo Theme::Translate('Month'); ?></button>
            <button type="button" class="btn btn-warning" data-calendar-view="week"><?php echo Theme::Translate('Week'); ?></button>
            <button type="button" class="btn btn-warning" data-calendar-view="day"><?php echo Theme::Translate('Day'); ?></button>
        </div>
        <div class="btn-group pull-right xibo-calendar-controls">
            <button type="button" class="btn btn-primary" data-calendar-nav="prev"><span class="glyphicon glyphicon-backward"></span> <?php echo Theme::Translate('Prev'); ?></button>
            <button type="button" class="btn btn-default" data-calendar-nav="today"><?php echo Theme::Translate('Today'); ?></button>
            <button type="button" class="btn btn-primary" data-calendar-nav="next"><?php echo Theme::Translate('Next'); ?> <span class="glyphicon glyphicon-forward"></span></button>
        </div>
        <div class="btn-group pull-right xibo-calendar-controls">
            <button class="btn btn-success XiboFormButton" href="<?php echo Theme::Get('event_add_url'); ?>"><?php echo Theme::Translate('Add Event'); ?></button>
        </div>
        <div class="xibo-calendar-controls dropdown pull-right">
            <select id="<?php echo Theme::Get('id'); ?>" type="form-control" name="DisplayGroupIDs[]" data-live-search="true" data-selected-text-format="count > 4" title="<?php echo Theme::Translate('Nothing Selected'); ?>" multiple>
                <option value="-1"<?php echo (Theme::Get('allSelected') == 1) ? 'selected' : ''; ?>><?php echo Theme::Translate('All'); ?></option>
                <optgroup label="<?php echo Theme::Translate('Groups'); ?>">
                    <?php foreach(Theme::Get('groups') as $row) { ?>
                    <option value="<?php echo $row['displaygroupid']; ?>"<?php echo $row['checked_text']; ?>><?php echo $row['displaygroup']; ?></option>
                    <?php } ?>
                </optgroup>
                <optgroup label="<?php echo Theme::Translate('Displays'); ?>">
                    <?php foreach(Theme::Get('displays') as $row) { ?>
                    <option value="<?php echo $row['displaygroupid']; ?>"<?php echo $row['checked_text']; ?>><?php echo $row['displaygroup']; ?></option>
                    <?php } ?>  
                </optgroup>
            </select>
        </div>
        <h1 class="page-header"></h1>
    </div>
</div>
<div class="row">
    <div id="CalendarContainer" data-calendar-type="<?php echo Theme::Get('calendarType'); ?>" class="col-sm-12">
        <div id="Calendar"></div>
    </div>
</div>
<div class="row">
    <div class="col-sm-12">
        <div class="cal-legend">
            <ul>
                <li class="event-info"><span class="fa fa-desktop"></span> <?php echo Theme::Translate('Single Display'); ?></li>
                <li class="event-success"><span class="fa fa-desktop"></span> <?php echo Theme::Translate('Multi Display'); ?></li>
                <li class="event-important"><span class="fa fa-bullseye"></span> <?php echo Theme::Translate('Priority'); ?></li>
                <li class="event-special"><span class="fa fa-repeat"></span> <?php echo Theme::Translate('Recurring'); ?></li>
                <li class="event-inverse"><span class="fa fa-lock"></span> <?php echo Theme::Translate('View Permission Only'); ?></li>
            </ul>
        </div>
    </div>
</div>