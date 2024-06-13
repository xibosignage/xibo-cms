<?php
/*
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
 * Add display venue metadata
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

class AddAuthUrlForOauth2ToDatasetTable extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // Add new column
        $this->table('dataset')
            ->addColumn('oauth2Url', 'string', ['after' => 'authentication', 'limit' => 1024, 'default' => null, 'null' => true])
            ->addColumn('oauth2Client', 'string', ['after' => 'oauth2Url', 'limit' => 1024, 'default' => null, 'null' => true])
            ->addColumn('oauth2ClientSecret', 'string', ['after' => 'oauth2Client', 'limit' => 1024, 'default' => null, 'null' => true])
            ->addColumn('oauth2GrantType', 'enum', ['values' => ['client_credentials', 'authorization_code'], 'default' => null, 'null' => true])
            ->save();

        // Modify the existing 'authentication' column to add the 'oauth2' value
        $tableName = 'dataset';
        $columnName = 'authentication';
        $newValues = "'none', 'plain', 'basic', 'digest', 'bearer', 'ntlm', 'oauth2'";
        
        $this->execute("ALTER TABLE `$tableName` CHANGE `$columnName` `$columnName` ENUM($newValues) DEFAULT NULL");
    }
}
