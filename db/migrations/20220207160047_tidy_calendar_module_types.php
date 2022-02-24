<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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
 */

use Phinx\Migration\AbstractMigration;

/**
 * Update calendar/agenda widgets to respect the new type naming
 * Insert of update the calendar-advanced table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class TidyCalendarModuleTypes extends AbstractMigration
{
    public function change()
    {
        $this->execute('UPDATE `widget` SET widget.type = \'calendaradvanced\' WHERE widget.type = \'calendar\' ');
        $this->execute('UPDATE `widget` SET widget.type = \'calendar\' WHERE widget.type = \'agenda\' ');
        
        // Is the new calendar module installed (we didn't auto install it)
        if (count($this->fetchAll('SELECT * FROM `module` WHERE module = \'calendar\' ')) > 0) {
            // Rename calendar type to advanced
            $this->execute('UPDATE `module` SET module = \'calendaradvanced\', installName = \'calendaradvanced\' WHERE module = \'calendar\' ');
        } else {
            // Add the new calendar advanced type
            $this->table('module')->insert(
                [
                    'module' => 'calendaradvanced',
                    'name' => 'Calendar',
                    'enabled' => 1,
                    'regionSpecific' => 1,
                    'description' => 'A module for displaying a calendar based on an iCal feed',
                    'schemaVersion' => 1,
                    'validExtensions' => '',
                    'previewEnabled' => 1,
                    'assignable' => 1,
                    'render_as' => 'html',
                    'viewPath' => '../modules',
                    'class' => 'Xibo\Widget\Calendar',
                    'defaultDuration' => 60,
                    'installName' => 'calendaradvanced'
                ]
            )->save();
        }

        // Rename agenda type to calendar
        $this->execute('UPDATE `module` SET module = \'calendar\', installName = \'calendar\' WHERE module = \'agenda\' ');
    }
}
