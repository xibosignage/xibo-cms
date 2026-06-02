<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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
 * Add a Task to migrate override.css to new theme.css
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddThemeCssMigrateTaskMigration extends AbstractMigration
{
    public function change()
    {
        if (!$this->fetchRow('SELECT * FROM `task` WHERE `name` = \'Theme CSS Migration\'')) {
            $this->table('task')
                ->insert([
                    [
                        'name' => 'Theme CSS Migration',
                        'class' => '\Xibo\XTR\ThemeCssMigrateTask',
                        'options' => '[]',
                        'schedule' => '*/10 * * * * *',
                        'isActive' => '1',
                        'configFile' => '/tasks/theme-css-migrate.task',
                        'pid' => null,
                        'lastRunDt' => 0,
                        'lastRunDuration' => 0,
                        'lastRunExitCode' => 0
                    ],
                ])->save();
        }
    }
}
