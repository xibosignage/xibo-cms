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
