<?php
/**
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
 * Class OauthUpgradeMigration
 */
class OauthUpgradeMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // Delete oAuth tables which are no longer in use.
        $this->dropTable('oauth_access_token_scopes');
        $this->dropTable('oauth_session_scopes');
        $this->dropTable('oauth_refresh_tokens');
        $this->dropTable('oauth_auth_code_scopes');
        $this->dropTable('oauth_auth_codes');
        $this->dropTable('oauth_access_tokens');
        $this->dropTable('oauth_sessions');

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
