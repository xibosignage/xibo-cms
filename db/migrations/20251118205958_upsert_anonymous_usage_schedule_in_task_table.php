<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
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
