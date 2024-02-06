<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 * @phpcs:disable Generic.Files.LineLength.TooLong
 */
class OneRegionPerPlaylistMigration extends AbstractMigration
{
    /**
     * Up
     */
    public function up()
    {
        $playlist = $this->table('playlist');
        $playlist
            ->addColumn('regionId', 'integer', ['null' => true])
            ->addColumn('createdDt', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modifiedDt', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('duration', 'integer', ['default' => 0])
            ->addColumn(
                'requiresDurationUpdate',
                'integer',
                ['default' => 0, 'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY]
            )
            ->addIndex('regionId')
            ->save();

        $this->execute('UPDATE `playlist` SET regionId = (SELECT MAX(regionId) FROM lkregionplaylist WHERE playlist.playlistId = lkregionplaylist.playlistId);');

        $this->table('lkregionplaylist')->drop()->save();

        $this->execute('UPDATE `pages` SET asHome = 1 WHERE name = \'playlist\';');

        $this->execute('
          INSERT INTO module (Module, Name, Enabled, RegionSpecific, Description, ImageUri, SchemaVersion, ValidExtensions, PreviewEnabled, assignable, render_as, settings, viewPath, class, defaultDuration) 
          VALUES (\'subplaylist\', \'Sub-Playlist\', 1, 1, \'Embed a Sub-Playlist\', \'forms/library.gif\', 1, null, 1, 1, \'native\', null, \'../modules\', \'Xibo\\\\Widget\\\\SubPlaylist\', 10);
        ');

        $playlistClosure = $this->table('lkplaylistplaylist', ['id' => false, 'primary_key' => ['parentId', 'childId', 'depth']]);
        $playlistClosure
            ->addColumn('parentId', 'integer')
            ->addColumn('childId', 'integer')
            ->addColumn('depth', 'integer')
            ->addIndex(['childId', 'parentId', 'depth'], ['unique' => true])
            ->save();

        $this->execute('INSERT INTO lkplaylistplaylist (parentId, childId, depth) SELECT playlistId, playlistId, 0 FROM playlist;');
    }
}
