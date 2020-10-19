<?php
/*
 * Copyright (C) 2020 Xibo Signage Ltd
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

/**
 * Class FeaturesMigration
 */
class FeaturesMigration extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function change()
    {
        $this->table('group')
            ->addColumn('features', 'string', [
                'null' => true,
                'default' => null,
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_MEDIUM
            ])
            ->save();

        $this->table('user')
            ->changeColumn('homePageId', 'string', [
                'null' => true,
                'default' => 'null',
                'limit' => '255'
            ])
            ->save();

        $this->execute('UPDATE `user` SET homePageId = (SELECT CONCAT(pages.name, \'.view\') FROM pages WHERE user.homePageId = pages.pageId)');

        // Migrate Page Permissions
        $entityId = $this->fetchRow('SELECT entityId FROM permissionentity WHERE entity LIKE \'%Page%\'')[0];

        // TODO: We need to match permissions

        // Delete Page Permissions
        $this->execute('DELETE FROM permission WHERE entityId = ' . $entityId);

        // Delete Page Permission Entity
        $this->execute('DELETE FROM permissionentity WHERE entityId = ' . $entityId);

        // Delete Page Table
        $this->dropTable('pages');
    }
}
