<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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
 * Class CountdownModuleAddMigration
 */
class CountdownModuleAddMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        if (!$this->fetchRow('SELECT * FROM module WHERE module = \'countdown\'')) {
            $modules = $this->table('module');
            $modules->insert([
                'module' => 'countdown',
                'name' => 'Countdown',
                'enabled' => 1,
                'regionSpecific' => 1,
                'description' => 'Countdown Module',
                'schemaVersion' => 1,
                'previewEnabled' => 1,
                'assignable' => 1,
                'render_as' => 'html',
                'viewPath' => '../modules',
                'class' => 'Xibo\Widget\Countdown',
                'defaultDuration' => 60,
                'installName' => 'countdown'
            ])->save();
        }
    }
}
