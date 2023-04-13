<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
 * Add widget compatibility task
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddWidgetCompatibilityTaskMigration extends AbstractMigration
{
    public function change()
    {
        // Check to see if the task exists
        if (!$this->fetchRow('SELECT * FROM `task` WHERE name = \'Widget Compatibity\'')) {
            $this->execute('INSERT INTO `task` SET `name`=\'Widget Compatibity\', `class`=\'\\\\Xibo\\\\XTR\\\\WidgetCompatibilityTask\', `status`=2, `isActive`=1, `configFile`=\'/tasks/widget-compatibility.task\', `options`=\'{}\', `runNow`=1, `schedule`=\'0 0 1 1 *\';');
        }
    }
}
