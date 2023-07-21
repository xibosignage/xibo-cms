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
 * Application Tweaks Migration
 * ---------------------
 * Add new aauth link table for storing authorised applications
 * Add more scopes and routes
 * Add more fields to oauth_clients table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class ApplicationsTweaksMigration extends AbstractMigration
{
    public function change()
    {
        // make sure the oauth_client table uses utf8.
        // without this change, for old CMS instances where it was using latin1,
        // it will cause issues creating FK in the new oauth_lkclientuser table.
        $this->execute('
            ALTER TABLE `oauth_clients`  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                CHANGE COLUMN `id` `id` VARCHAR(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE COLUMN `secret` `secret` VARCHAR(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                CHANGE COLUMN `name` `name` VARCHAR(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
        ');

        $this->table('oauth_lkclientuser', ['id' => 'lkClientUserId'])
            ->addColumn('clientId', 'string', ['length' => 254])
            ->addColumn('userId', 'integer')
            ->addColumn('approvedDate', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('approvedIp', 'string', ['null' => true, 'default' => null])
            ->addIndex(['clientId', 'userId'], ['unique' => true])
            ->addForeignKey('clientId', 'oauth_clients', 'id')
            ->addForeignKey('userId', 'user', 'userId')
            ->create();

        $this->table('oauth_scopes')
            ->addColumn('useRegex', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
            ->insert([
                [
                    'id' => 'design',
                    'description' => 'Access to Library, Layouts, Playlists and Widgets',
                    'useRegex' => 1
                ],
                [
                    'id' => 'designDelete',
                    'description' => 'Access to deleting content from Library, Layouts, Playlists and Widgets',
                    'useRegex' => 1
                ],
                [
                    'id' => 'displays',
                    'description' => 'Access to Displays and Display Groups',
                    'useRegex' => 1
                ],
                [
                    'id' => 'displaysDelete',
                    'description' => 'Access to deleting Displays and Display Groups',
                    'useRegex' => 0
                ],
                [
                    'id' => 'schedule',
                    'description' => 'Access to Scheduling',
                    'useRegex' => 1
                ],
                [
                    'id' => 'scheduleDelete',
                    'description' => 'Access to deleting Scheduled Events',
                    'useRegex' => 1
                ],
                [
                    'id' => 'datasets',
                    'description' => 'Access to DataSets',
                    'useRegex' => 1
                ],
                [
                    'id' => 'datasetsDelete',
                    'description' => 'Access to deleting DataSets',
                    'useRegex' => 1
                ]
            ])->save();

        $this->table('oauth_scope_routes')
            ->changeColumn('method', 'string', ['limit' => 50])
            ->save();

        $this->table('oauth_scope_routes')
            ->insert([
                ['scopeId' => 'design', 'route' => '/library', 'method' => 'GET,POST,PUT'],
                ['scopeId' => 'design', 'route' => '/layout', 'method' => 'GET,POST,PUT'],
                ['scopeId' => 'design', 'route' => '/playlist', 'method' => 'GET,POST,PUT'],
                ['scopeId' => 'designDelete', 'route' => '/library', 'method' => 'DELETE'],
                ['scopeId' => 'designDelete', 'route' => '/layout', 'method' => 'DELETE'],
                ['scopeId' => 'designDelete', 'route' => '/playlist', 'method' => 'DELETE'],
                ['scopeId' => 'displays', 'route' => '/display', 'method' => 'GET,POST,PUT'],
                ['scopeId' => 'displays', 'route' => '/displaygroup', 'method' => 'GET,POST,PUT'],
                ['scopeId' => 'displaysDelete', 'route' => '/display/{id}', 'method' => 'DELETE'],
                ['scopeId' => 'displaysDelete', 'route' => '/displaygroup/{id}', 'method' => 'DELETE'],
                ['scopeId' => 'schedule', 'route' => '/schedule', 'method' => 'GET,POST,PUT'],
                ['scopeId' => 'scheduleDelete', 'route' => '/schedule', 'method' => 'DELETE'],
                ['scopeId' => 'datasets', 'route' => '/dataset', 'method' => 'GET,POST,PUT'],
                ['scopeId' => 'datasetsDelete', 'route' => '/dataset', 'method' => 'DELETE']
            ])->save();

        $this->table('oauth_clients')
            ->addColumn('description', 'string', ['limit' => 254, 'null' => true, 'default' => null])
            ->addColumn('logo', 'string', ['limit' => 254, 'null' => true, 'default' => null])
            ->addColumn('coverImage', 'string', ['limit' => 254, 'null' => true, 'default' => null])
            ->addColumn('companyName', 'string', ['limit' => 254, 'null' => true, 'default' => null])
            ->addColumn('termsUrl', 'string', ['limit' => 254, 'null' => true, 'default' => null])
            ->addColumn('privacyUrl', 'string', ['limit' => 254, 'null' => true, 'default' => null])
            ->save();
    }
}
