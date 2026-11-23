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

class AddMcaasScopeAndRouteMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // Just in case, check if the mcaas scope id exists, if not add it.
        if (!$this->fetchRow('SELECT * FROM `oauth_scopes` WHERE id = \'mcaas\'')) {
            $this->table('oauth_scopes')
                ->insert([
                    'id' => 'mcaas',
                    'description' => 'Media Conversion as a Service'
                ])
                ->save();
        }

        // clear existing scope routes for mcaas, to make the table clean
        $this->execute('DELETE FROM oauth_scope_routes WHERE scopeId = \'mcaas\'');

        // add mcaas scope routes with slim4 pattern
        $this->table('oauth_scope_routes')
            ->insert([
                ['scopeId' => 'mcaas', 'route' => '/', 'method' => 'GET'],
                ['scopeId' => 'mcaas', 'route' => '/library/download/{id}[/{type}]', 'method' => 'GET'],
                ['scopeId' => 'mcaas', 'route' => '/library/mcaas/{id}', 'method' => 'POST'],
            ])
            ->save();
    }
}
