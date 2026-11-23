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
 * Class OauthUpgradeMigration
 */
class OauthUpgradeMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // Delete oAuth tables which are no longer in use.
        $this->table('oauth_access_token_scopes')->drop()->save();
        $this->table('oauth_session_scopes')->drop()->save();
        $this->table('oauth_refresh_tokens')->drop()->save();
        $this->table('oauth_auth_code_scopes')->drop()->save();
        $this->table('oauth_auth_codes')->drop()->save();
        $this->table('oauth_access_tokens')->drop()->save();
        $this->table('oauth_sessions')->drop()->save();

        // Add a new column to the Applications table to indicate whether an app is confidential or not
        $clients = $this->table('oauth_clients');
        if (!$clients->hasColumn('isConfidential')) {
            $clients
                ->addColumn('isConfidential', 'integer', [
                    'default' => 1,
                    'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY
                ])
                ->save();
        }
    }
}
