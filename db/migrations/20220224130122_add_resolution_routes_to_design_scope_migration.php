<?php
/**
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

use Phinx\Migration\AbstractMigration;

/**
 * Add Resolution Routes to the Design scope
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddResolutionRoutesToDesignScopeMigration extends AbstractMigration
{
    public function change()
    {
        $this->execute('UPDATE `oauth_scopes` SET `description` = \'Full account access\' WHERE `id` = \'all\';');
        $this->execute('UPDATE `oauth_scopes` SET `description` = \'Access to Library, Layouts, Playlists, Widgets and Resolutions\' WHERE `id` = \'design\';');
        $this->execute('UPDATE `oauth_scopes` SET `description` = \'Access to deleting content from Library, Layouts, Playlists, Widgets and Resolutions\' WHERE `id` = \'designDelete\';');

        $this->table('oauth_scope_routes')
            ->insert([
                ['scopeId' => 'design', 'route' => '/resolution', 'method' => 'GET,POST,PUT'],
                ['scopeId' => 'designDelete', 'route' => '/resolution', 'method' => 'DELETE'],
            ])
            ->save();
    }
}
