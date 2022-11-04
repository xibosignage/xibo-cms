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

use Carbon\Carbon;
use Phinx\Migration\AbstractMigration;
use Xibo\Helper\DateFormatHelper;

/**
 * Add new table for fonts
 * Insert Standard fonts into fonts table
 * Convert existing font records in media table
 * Delete font records in media table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddFontsTableMigration extends AbstractMigration
{

    public function change()
    {
        // create new table for fonts
        $table = $this->table('fonts');
        $table
            ->addColumn('createdAt', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modifiedAt', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modifiedBy', 'string', ['null' => true, 'default' => null])
            ->addColumn('name', 'string')
            ->addColumn('fileName', 'string')
            ->addColumn('familyName', 'string')
            ->addColumn('size', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'default' => null, 'null' => true])
            ->addColumn('md5', 'string', ['limit' => 32, 'default' => null, 'null' => true])
            ->create();

        // create fonts sub-folder in the library location
        $libraryLocation = $this->fetchRow('SELECT `setting`.value FROM `setting` WHERE `setting`.setting = \'LIBRARY_LOCATION\'')[0];
        if (!file_exists($libraryLocation . 'fonts')) {
            mkdir($libraryLocation . 'fonts', 0777, true);
        }

        // Fix any potential incorrect dates in modifiedDt
        $this->execute('UPDATE `media` SET `media`.modifiedDt = `media`.createdDt WHERE `media`.modifiedDt < \'2000-01-01\'');

        // get all existing font records in media table and convert them
        foreach ($this->fetchAll('SELECT mediaId, name, type, createdDt, modifiedDt, storedAs, md5, fileSize, originalFileName FROM `media` WHERE media.type = \'font\'') as $fontMedia) {
            $table
                ->insert([
                    'createdAt' => $fontMedia['createdDt'],
                    'modifiedAt' => $fontMedia['modifiedDt'],
                    'name' => $fontMedia['name'],
                    'fileName' => $fontMedia['originalFileName'],
                    'familyName' => strtolower(preg_replace('/\s+/', ' ', preg_replace('/\d+/u', '', $fontMedia['name']))),
                    'size'=> $fontMedia['fileSize'],
                    'md5' => $fontMedia['md5']
                ])
                ->save();

            // move the stored files with new id to fonts folder
            rename($libraryLocation . $fontMedia['storedAs'], $libraryLocation . 'fonts/' . $fontMedia['originalFileName']);

            // remove any potential tagLinks from font media files
            // otherwise we risk failing the migration on the next step when we remove records from media table.
            $this->execute('DELETE FROM `lktagmedia` WHERE `lktagmedia`.mediaId = ' . $fontMedia['mediaId']);
        }

        // delete font records from media table
        $this->execute('DELETE FROM `media` WHERE media.type = \'font\'');
        // delete "module" record for fonts
        $this->execute('DELETE FROM `module` WHERE `module`.moduleId = \'core-font\'');

        // remove fonts.css file records from media table
        $this->execute('DELETE FROM `media` WHERE media.originalFileName = \'fonts.css\' AND media.type = \'module\' AND media.moduleSystemFile = 1 ');
        // remove fonts.css from library folder
        if (file_exists($libraryLocation . 'fonts.css')) {
            @unlink($libraryLocation . 'fonts.css');
        }

        // add a task that will re-generate fonts.css for the player
        $this->table('task')
            ->insert([
            [
                'name' => 'Generate Player font css',
                'class' => '\Xibo\XTR\GeneratePlayerCssTask',
                'options' => '[]',
                'schedule' => '*/5 * * * * *',
                'isActive' => '1',
                'configFile' => '/tasks/player-css.task',
                'pid' => 0,
                'lastRunDt' => 0,
                'lastRunDuration' => 0,
                'lastRunExitCode' => 0
            ],
        ])->save();
    }
}
