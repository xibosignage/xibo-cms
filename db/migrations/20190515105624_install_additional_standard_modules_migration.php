<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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

class InstallAdditionalStandardModulesMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        $modules = $this->table('module');

        if (!$this->fetchRow('SELECT * FROM module WHERE module = \'htmlpackage\'')) {
            $modules->insert([
                'module' => 'htmlpackage',
                'name' => 'HTML Package',
                'enabled' => 1,
                'regionSpecific' => 0,
                'description' => 'Upload a complete package to distribute to Players',
                'schemaVersion' => 1,
                'validExtensions' => 'htz',
                'previewEnabled' => 0,
                'assignable' => 1,
                'render_as' => 'native',
                'viewPath' => '../modules',
                'class' => 'Xibo\Widget\HtmlPackage',
                'defaultDuration' => 60
            ])->save();
        }

        if (!$this->fetchRow('SELECT * FROM module WHERE module = \'videoin\'')) {
            $modules->insert([
                'module' => 'videoin',
                'name' => 'Video In',
                'enabled' => 1,
                'regionSpecific' => 1,
                'description' => 'Display input from an external source',
                'schemaVersion' => 1,
                'validExtensions' => null,
                'previewEnabled' => 0,
                'assignable' => 1,
                'render_as' => 'native',
                'viewPath' => '../modules',
                'class' => 'Xibo\Widget\VideoIn',
                'defaultDuration' => 60
            ])->save();
        }

        if (!$this->fetchRow('SELECT * FROM module WHERE module = \'hls\'')) {
            $modules->insert([
                'module' => 'hls',
                'name' => 'HLS',
                'enabled' => 1,
                'regionSpecific' => 1,
                'description' => 'Display live streamed content',
                'schemaVersion' => 1,
                'validExtensions' => null,
                'previewEnabled' => 1,
                'assignable' => 1,
                'render_as' => 'html',
                'viewPath' => '../modules',
                'class' => 'Xibo\Widget\Hls',
                'defaultDuration' => 60
            ])->save();
        }

        if (!$this->fetchRow('SELECT * FROM module WHERE module = \'calendar\'')) {
            $modules->insert([
                'module' => 'calendar',
                'name' => 'Calendar',
                'enabled' => 1,
                'regionSpecific' => 1,
                'description' => 'Display events from an iCAL feed',
                'schemaVersion' => 1,
                'validExtensions' => null,
                'previewEnabled' => 1,
                'assignable' => 1,
                'render_as' => 'html',
                'viewPath' => '../modules',
                'class' => 'Xibo\Widget\Calendar',
                'defaultDuration' => 60
            ])->save();
        }

        if (!$this->fetchRow('SELECT * FROM module WHERE module = \'chart\'')) {
            $modules->insert([
                'module' => 'chart',
                'name' => 'Chart',
                'enabled' => 1,
                'regionSpecific' => 1,
                'description' => 'Display information held in a DataSet as a type of Chart',
                'schemaVersion' => 1,
                'validExtensions' => null,
                'previewEnabled' => 1,
                'assignable' => 1,
                'render_as' => 'html',
                'viewPath' => '../modules',
                'class' => 'Xibo\Widget\Chart',
                'defaultDuration' => 240
            ])->save();
        }
    }
}
