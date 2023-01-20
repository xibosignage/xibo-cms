<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
 * Add additional columns to required file so that we can handle dependencies separately to media
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class RequiredfileDependencyMigration extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('requiredfile');
        $table
            ->addColumn('fileType', 'string', [
                'limit' => 50,
                'null' => true,
                'default' => null
            ])
            ->addColumn('realId', 'string', [
                'limit' => 254,
                'null' => true,
                'default' => null
            ])
            ->save();

        $this->table('bandwidthtype')
            ->insert([
                [
                    'bandwidthTypeId' => 12,
                    'name' => 'Get Data'
                ],
                [
                    'bandwidthTypeId' => 13,
                    'name' => 'Get Dependency'
                ],
            ])
            ->save();
    }
}
