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

class OldUpgradeStep129Migration extends AbstractMigration
{
    public function up()
    {
        $STEP = 129;

        // Are we an upgrade from an older version?
        if ($this->hasTable('version')) {
            // We do have a version table, so we're an upgrade from anything 1.7.0 onward.
            $row = $this->fetchRow('SELECT * FROM `version`');
            $dbVersion = $row['DBVersion'];

            // Are we on the relevent step for this upgrade?
            if ($dbVersion < $STEP) {
                // Perform the upgrade
                $requiredFile = $this->table('requiredfile', ['id' => 'rfId']);
                $requiredFile
                    ->addColumn('displayId', 'integer')
                    ->addColumn('type', 'string', ['limit' => 1])
                    ->addColumn('class', 'string', ['limit' => 1])
                    ->addColumn('itemId', 'integer', ['null' => true])
                    ->addColumn('bytesRequested', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG])
                    ->addColumn('complete', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
                    ->addColumn('path', 'string', ['null' => true, 'limit' => 255])
                    ->addColumn('size', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_BIG, 'default' => 0])
                    ->addIndex(['displayId', 'type'])
                    ->save();

                $resolution = $this->table('resolution');
                $resolution
                    ->addColumn('userId', 'integer')
                    ->save();

                $this->execute('UPDATE `resolution` SET userId = 0;');

                $this->execute('UPDATE `setting` SET `options` = \'private|group|group write|public|public write\' WHERE setting IN (\'MEDIA_DEFAULT\', \'LAYOUT_DEFAULT\');');

                $linkCampaignTag = $this->table('lktagcampaign', ['id' => 'lkTagCampaignId']);
                $linkCampaignTag
                    ->addColumn('tagId', 'integer')
                    ->addColumn('campaignId', 'integer')
                    ->addIndex(['tagId', 'campaignId'], ['unique' => true])
                    ->save();

                $display = $this->table('display');
                $display
                    ->addColumn('timeZone', 'string', ['limit' => 254, 'null' => true])
                    ->save();

                // Bump our version
                $this->execute('UPDATE `version` SET DBVersion = ' . $STEP);
            }
        }
    }
}
