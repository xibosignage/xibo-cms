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

/**
 * Add Orientation column to Layout and Media tables
 * Attempt to determine the resolution for existing Layouts
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;

class AddOrientationToResolutionAndMediaMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('layout')
            ->addColumn('orientation', 'string', ['limit' => 10, 'after' => 'height', 'null' => true, 'default' => null])
            ->save();

        $layouts = $this->query('SELECT layoutId, width, height FROM layout');
        $layouts = $layouts->fetchAll(PDO::FETCH_ASSOC);

        // go through existing Layouts and determine the orientation
        foreach ($layouts as $layout) {
            $orientation = ($layout['width'] >= $layout['height']) ? 'landscape' : 'portrait';
            $this->execute('UPDATE `layout` SET `orientation` = "'. $orientation .'" WHERE layoutId = '. $layout['layoutId']);
        }

        $this->table('media')
            ->addColumn('orientation', 'string', ['limit' => 10, 'null' => true, 'default' => null])
            ->save();

        $this->table('task')
            ->insert([
            [
                'name' => 'Media Orientation',
                'class' => '\Xibo\XTR\MediaOrientationTask',
                'options' => '[]',
                'schedule' => '*/5 * * * * *',
                'isActive' => '1',
                'configFile' => '/tasks/media-orientation.task',
                'pid' => null,
                'lastRunDt' => 0,
                'lastRunDuration' => 0,
                'lastRunExitCode' => 0
            ],
        ])->save();
    }
}
