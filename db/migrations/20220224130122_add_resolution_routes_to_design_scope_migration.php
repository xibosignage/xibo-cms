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
