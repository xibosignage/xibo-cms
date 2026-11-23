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

class InstallSavedReportsAndSpacerModulesMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        $modules = $this->table('module');

        if (!$this->fetchRow('SELECT * FROM module WHERE module = \'savedreport\'')) {
            $modules->insert([
                'module' => 'savedreport',
                'name' => 'Saved Reports',
                'enabled' => 1,
                'regionSpecific' => 0,
                'description' => 'A saved report to be stored in the library',
                'schemaVersion' => 1,
                'previewEnabled' => 0,
                'assignable' => 0,
                'render_as' => null,
                'class' => 'Xibo\Widget\SavedReport',
                'defaultDuration' => 10,
                'validExtensions' => 'json',
                'installName' => 'savedreport'
            ])->save();
        }

        if (!$this->fetchRow('SELECT * FROM module WHERE module = \'spacer\'')) {
            $modules->insert([
                'module' => 'spacer',
                'name' => 'Spacer',
                'enabled' => 1,
                'regionSpecific' => 1,
                'description' => 'Make a Region empty for a specified duration',
                'schemaVersion' => 1,
                'previewEnabled' => 0,
                'assignable' => 1,
                'render_as' => 'html',
                'class' => 'Xibo\Widget\Spacer',
                'defaultDuration' => 60,
                'validExtensions' => null,
                'installName' => 'spacer'
            ])->save();
        }
    }
}
