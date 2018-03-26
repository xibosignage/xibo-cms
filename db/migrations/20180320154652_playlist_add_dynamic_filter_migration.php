<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2018 Spring Signage Ltd
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
 * Class PlaylistAddDynamicFilterMigration
 * add dynamic playlist filtering
 */
class PlaylistAddDynamicFilterMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        $table = $this->table('playlist');

        $table
            ->addColumn('isDynamic', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->addColumn('filterMediaName', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('filterMediaTags', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->update();

        $task = $this->table('task');
        $task->insert([
                'name' => 'Sync Dynamic Playlists',
                'class' => '\Xibo\XTR\DynamicPlaylistSyncTask',
                'options' => '[]',
                'schedule' => '* * * * * *',
                'isActive' => '1',
                'configFile' => '/tasks/dynamic-playlist-sync.task'
            ])
            ->save();
    }
}
