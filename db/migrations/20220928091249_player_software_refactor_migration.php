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
 * Remove playersoftware from media table
 * Add more columns to player_software table
 * Adjust versionMediaId
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class PlayerSoftwareRefactorMigration extends AbstractMigration
{
    public function change()
    {
        // add some new columns
        $table = $this->table('player_software');
        $table
            ->addColumn('createdAt', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modifiedAt', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modifiedBy', 'string', ['null' => true, 'default' => null])
            ->addColumn('fileName', 'string')
            ->addColumn('size', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'default' => null, 'null' => true])
            ->addColumn('md5', 'string', ['limit' => 32, 'default' => null, 'null' => true])
            ->save();

        // create playersoftware sub-folder in the library location
        $libraryLocation = $this->fetchRow('
            SELECT `setting`.value
              FROM `setting`
             WHERE `setting`.setting = \'LIBRARY_LOCATION\'')[0] ?? null;

        // New installs won't have a library location yet (if they are non-docker).
        if (!empty($libraryLocation)) {
            if (!file_exists($libraryLocation . 'playersoftware')) {
                mkdir($libraryLocation . 'playersoftware', 0777, true);
            }

            // get all existing playersoftware records in media table and convert them
            foreach ($this->fetchAll('SELECT mediaId, name, type, createdDt, modifiedDt, storedAs, md5, fileSize, originalFileName FROM `media` WHERE media.type = \'playersoftware\'') as $playersoftwareMedia) {
                $this->execute('UPDATE `player_software` SET createdAt = \'' . $playersoftwareMedia['createdDt'] . '\',
                                    modifiedAt = \'' . $playersoftwareMedia['modifiedDt'] . '\',
                                    fileName = \'' . $playersoftwareMedia['originalFileName'] . '\',
                                    size = ' . $playersoftwareMedia['fileSize'] . ',
                                    md5 = \'' . $playersoftwareMedia['md5'] . '\'
                                WHERE `player_software`.mediaId = ' . $playersoftwareMedia['mediaId']);

                // move the stored files with new id to fonts folder
                rename($libraryLocation . $playersoftwareMedia['storedAs'], $libraryLocation . 'playersoftware/' . $playersoftwareMedia['originalFileName']);

                // remove any potential tagLinks from playersoftware media files
                // unlikely that there will be any, but just in case.
                $this->execute('DELETE FROM `lktagmedia` WHERE `lktagmedia`.mediaId = ' . $playersoftwareMedia['mediaId']);
            }

            // update versionMediaId in displayProfiles config
            foreach ($this->fetchAll('SELECT displayProfileId, config FROM `displayprofile`') as $displayProfile) {
                // check if there is anything in the config
                if (!empty($displayProfile['config']) && $displayProfile['config'] !== '[]') {
                    $config = json_decode($displayProfile['config'], true);
                    for ($i = 0; $i < count($config); $i++) {
                        if ($config[$i]['name'] === 'versionMediaId') {
                            $row = $this->fetchRow('SELECT mediaId, versionId FROM `player_software` WHERE `player_software`.mediaId =' . $config[$i]['value']);
                            $config[$i]['value'] = $row['versionId'];
                            $this->execute('UPDATE `displayprofile` SET config = \'' . json_encode($config) . '\' WHERE `displayprofile`.displayProfileId =' . $displayProfile['displayProfileId']);
                        }
                    }
                }
            }

            // update versionMediaId in display overrideConfig
            foreach ($this->fetchAll('SELECT displayId, overrideConfig FROM `display`') as $display) {
                // check if there is anything in the config
                if (!empty($display['overrideConfig']) && $display['overrideConfig'] !== '[]') {
                    $overrideConfig = json_decode($display['overrideConfig'], true);
                    for ($i = 0; $i < count($overrideConfig); $i++) {
                        if ($overrideConfig[$i]['name'] === 'versionMediaId') {
                            $row = $this->fetchRow('SELECT mediaId, versionId FROM `player_software` WHERE `player_software`.mediaId =' . $overrideConfig[$i]['value']);
                            $overrideConfig[$i]['value'] = $row['versionId'];
                            $this->execute('UPDATE `display` SET overrideConfig = \'' . json_encode($overrideConfig) . '\' WHERE `display`.displayId =' . $display['displayId']);
                        }
                    }
                }
            }
        }

        // we are finally done
        // remove mediaId column and index/key
        $table
            ->removeIndexByName('player_software_ibfk_1')
            ->dropForeignKey('mediaId')
            ->removeColumn('mediaId')
            ->save();

        // delete playersoftware records from media table
        $this->execute('DELETE FROM `media` WHERE media.type = \'playersoftware\'');
        // delete module record for playersoftware
        $this->execute('DELETE FROM `module` WHERE `module`.moduleId = \'core-playersoftware\'');
    }
}
