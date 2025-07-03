<?php
/*
 * Copyright (C) 2025 Xibo Signage Ltd
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
 * Add some additional fields to menu boards
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class FixOauthRoutesTableMigration extends AbstractMigration
{
    public function change(): void
    {
        // Remove the Regex column
        $this->table('oauth_scopes')
            ->removeColumn('useRegex')
            ->save();

        // Update the table
        $oauthRouteScopes = $this->table('oauth_scope_routes');
        $oauthRouteScopes->truncate();

        $oauthRouteScopes->insert([
            ['scopeId' => 'datasets', 'route' => '#^/dataset#', 'method' => 'GET,POST,PUT'],
            ['scopeId' => 'datasetsDelete', 'route' => '#^/dataset#', 'method' => 'DELETE'],
            ['scopeId' => 'design', 'route' => '#^/library#', 'method' => 'GET,POST,PUT'],
            ['scopeId' => 'design', 'route' => '#^/layout#', 'method' => 'GET,POST,PUT'],
            ['scopeId' => 'design', 'route' => '#^/playlist#', 'method' => 'GET,POST,PUT'],
            ['scopeId' => 'design', 'route' => '#^/resolution#', 'method' => 'GET,POST,PUT'],
            ['scopeId' => 'designDelete', 'route' => '#^/library#', 'method' => 'DELETE'],
            ['scopeId' => 'designDelete', 'route' => '#^/layout#', 'method' => 'DELETE'],
            ['scopeId' => 'designDelete', 'route' => '#^/playlist#', 'method' => 'DELETE'],
            ['scopeId' => 'designDelete', 'route' => '#^/resolution#', 'method' => 'DELETE'],
            ['scopeId' => 'displays', 'route' => '#^/display#', 'method' => 'GET,POST,PUT'],
            ['scopeId' => 'displays', 'route' => '#^/displaygroup#', 'method' => 'GET,POST,PUT'],
            ['scopeId' => 'displaysDelete', 'route' => '#^/display/{id}#', 'method' => 'DELETE'],
            ['scopeId' => 'displaysDelete', 'route' => '#^/displaygroup/{id}#', 'method' => 'DELETE'],
            ['scopeId' => 'mcaas', 'route' => '#^/$#', 'method' => 'GET'],
            ['scopeId' => 'mcaas', 'route' => '#^/library/download/{id}#', 'method' => 'GET'],
            ['scopeId' => 'mcaas', 'route' => '#^/library/mcaas/{id}#', 'method' => 'POST'],
            ['scopeId' => 'schedule', 'route' => '#^/schedule#', 'method' => 'GET,POST,PUT'],
            ['scopeId' => 'scheduleDelete', 'route' => '#^/schedule#', 'method' => 'DELETE'],
        ])->saveData();
    }
}
