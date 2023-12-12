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
            ->addColumn(
                'size',
                'integer',
                ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'default' => null, 'null' => true]
            )
            ->addColumn('md5', 'string', ['limit' => 32, 'default' => null, 'null' => true])
            ->save();

        // create playersoftware sub-folder in the library location
        $libraryLocation = $this->fetchRow('
            SELECT `setting`.`value`
              FROM `setting`
             WHERE `setting`.`setting` = \'LIBRARY_LOCATION\'')[0] ?? null;

        // New installs won't have a library location yet (if they are non-docker).
        if (!empty($libraryLocation)) {
            if (!file_exists($libraryLocation . 'playersoftware')) {
                mkdir($libraryLocation . 'playersoftware', 0777, true);
            }

            // get all existing playersoftware records in media table and convert them
            $sql = '
                SELECT `mediaId`,
                       `name`,
                       `type`,
                       `createdDt`,
                       `modifiedDt`,
                       `storedAs`,
                       `md5`,
                       `fileSize`,
                       `originalFileName`
                  FROM `media`
                 WHERE `media`.`type` = \'playersoftware\'
            ';

            $updateSql = '
                UPDATE `player_software`
                    SET `createdAt` = :createdAt,
                        `modifiedAt` = :modifiedAt,
                        `fileName` = :fileName,
                        `size` = :size,
                        `md5` = :md5
                 WHERE `mediaId` = :mediaId
            ';

            foreach ($this->fetchAll($sql) as $playersoftwareMedia) {
                $this->execute($updateSql, [
                    'mediaId' => $playersoftwareMedia['mediaId'],
                    'createdAt' => $playersoftwareMedia['createdDt'] ?: null,
                    'modifiedAt' => $playersoftwareMedia['modifiedDt'] ?: null,
                    'fileName' => $playersoftwareMedia['originalFileName'],
                    'size' => $playersoftwareMedia['fileSize'],
                    'md5' => $playersoftwareMedia['md5']
                ]);

                // move the stored files with new id to fonts folder
                rename(
                    $libraryLocation . $playersoftwareMedia['storedAs'],
                    $libraryLocation . 'playersoftware/' . $playersoftwareMedia['originalFileName']
                );

                // remove any potential widget links (there shouldn't be any)
                $this->execute('DELETE FROM `lkwidgetmedia` WHERE `lkwidgetmedia`.`mediaId` = '
                    . $playersoftwareMedia['mediaId']);
                
                // remove any potential tagLinks from playersoftware media files
                // unlikely that there will be any, but just in case.
                $this->execute('DELETE FROM `lktagmedia` WHERE `lktagmedia`.mediaId = '
                    . $playersoftwareMedia['mediaId']);

                // player software files assigned directly to the Display.
                $this->execute('DELETE FROM `lkmediadisplaygroup` WHERE `lkmediadisplaygroup`.mediaId = '
                    . $playersoftwareMedia['mediaId']);
            }

            // update versionMediaId in displayProfiles config
            foreach ($this->fetchAll('SELECT displayProfileId, config FROM `displayprofile`') as $displayProfile) {
                // check if there is anything in the config
                if (!empty($displayProfile['config']) && $displayProfile['config'] !== '[]') {
                    $config = json_decode($displayProfile['config'], true);
                    for ($i = 0; $i < count($config); $i++) {
                        $configValue = $config[$i]['value'] ?? 0;

                        if (!empty($configValue) && $config[$i]['name'] === 'versionMediaId') {
                            $row = $this->fetchRow(
                                'SELECT mediaId, versionId
                                        FROM `player_software`
                                     WHERE `player_software`.mediaId =' . $configValue
                            );

                            $config[$i]['value'] = $row['versionId'];
                            $sql = 'UPDATE `displayprofile` SET config = :config
                                     WHERE `displayprofile`.displayProfileId = :displayProfileId';
                            $params = [
                                'config' => json_encode($config),
                                'displayProfileId' => $displayProfile['displayProfileId']
                            ];
                            $this->execute($sql, $params);
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
                        $overrideConfigValue = $overrideConfig[$i]['value'] ?? 0;
                        if (!empty($overrideConfigValue) && $overrideConfig[$i]['name'] === 'versionMediaId') {
                            $row = $this->fetchRow(
                                'SELECT mediaId, versionId
                                        FROM `player_software` 
                                     WHERE `player_software`.mediaId =' . $overrideConfigValue
                            );

                            $overrideConfig[$i]['value'] = $row['versionId'];
                            $sql = 'UPDATE `display` SET overrideConfig = :overrideConfig
                                WHERE `display`.displayId = :displayId';

                            $params = [
                                'overrideConfig' => json_encode($overrideConfig),
                                'displayId' => $display['displayId'],
                            ];

                            $this->execute($sql, $params);
                        }
                    }
                }
            }
        }

        // we are finally done
        if ($this->checkIndexExists('player_software', 'player_software_ibfk_1')) {
            $table->removeIndexByName('player_software_ibfk_1');
        }

        // remove mediaId column and index/key
        if ($table->hasForeignKey('mediaId')) {
            $table->dropForeignKey('mediaId');
        }
        
        $table
            ->removeColumn('mediaId')
            ->save();

        // delete playersoftware records from media table
        $this->execute('DELETE FROM `media` WHERE media.type = \'playersoftware\'');
        // delete module record for playersoftware
        $this->execute('DELETE FROM `module` WHERE `module`.moduleId = \'core-playersoftware\'');
    }

    /**
     * Check if an index exists
     * @param string $table
     * @param $indexName
     * @return bool
     */
    private function checkIndexExists($table, $indexName): bool
    {
        // Use the information schema to see if the index exists or not.
        // all users have permission to the information schema
        $sql = '
          SELECT * 
            FROM INFORMATION_SCHEMA.STATISTICS 
           WHERE `table_schema` = DATABASE() 
            AND `table_name` = \'' . $table . '\' 
            AND `index_name` = \'' . $indexName . '\'';

        return count($this->fetchAll($sql)) > 0;
    }
}
