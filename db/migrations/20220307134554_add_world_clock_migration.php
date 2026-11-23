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
 * Add the world clock module if it doesn't already exist
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddWorldClockMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        if (!$this->fetchRow('SELECT * FROM module WHERE module = \'worldclock\'')) {
            $modules = $this->table('module');
            $modules->insert([
                'module' => 'worldclock',
                'name' => 'World Clock',
                'enabled' => 1,
                'regionSpecific' => 1,
                'description' => 'WorldClock Module',
                'schemaVersion' => 1,
                'previewEnabled' => 1,
                'assignable' => 1,
                'render_as' => 'html',
                'viewPath' => '../modules',
                'class' => 'Xibo\Widget\WorldClock',
                'defaultDuration' => 60,
                'installName' => 'worldclock'
            ])->save();
        }
    }
}
