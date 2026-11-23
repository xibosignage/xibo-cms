<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

use Phinx\Migration\AbstractMigration;

/**
 * Upsert AnonymousUsageTask Schedule in Task Table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class UpsertAnonymousUsageScheduleInTaskTable extends AbstractMigration
{
    public function change(): void
    {
        // Check if the anonymous usage task exists in the task table
        $row = $this->fetchRow("SELECT * FROM `task` WHERE `name` = 'Anonymous Usage Reporting'");

        try {
            $myHour = random_int(0, 23);
            $myMinute = random_int(0, 59);
        } catch (Exception) {
            $myHour = 0;
            $myMinute = 0;
        }

        $schedule = $myMinute . ' ' . $myHour . ' * * *';

        if (!$row) {
            $this->execute('
                INSERT INTO `task` (`name`, `class`, `options`, `schedule`, `isActive`, `configFile`)
                VALUES (:name, :class, :options, :schedule, :isActive, :configFile)
            ', [
                'name' => 'Anonymous Usage Reporting',
                'class' => '\\Xibo\\XTR\\AnonymousUsageTask',
                'options' => '[]',
                'schedule' => $schedule,
                'isActive' => '1',
                'configFile' => '/tasks/anonymous-usage.task'
            ]);
        } else {
            // Row exists, update existing row
            $this->execute('
                UPDATE `task`
                SET `schedule` = :schedule
                WHERE `name` = :name
            ', [
                'schedule' => $schedule,
                'name' => 'Anonymous Usage Reporting'
            ]);
        }
    }
}
