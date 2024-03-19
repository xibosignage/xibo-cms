<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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
 * Migrations for adding user supplied module templates
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class UserModuleTemplatesMigration extends AbstractMigration
{
    public function change(): void
    {
        $this->table('module_templates')
            ->addColumn('templateId', 'string', [
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('dataType', 'string', [
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('xml', 'text', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_MEDIUM,
                'null' => false,
            ])
            ->addColumn('enabled', 'integer', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'null' => false,
                'default' => 1,
            ])
            ->addColumn('ownerId', 'integer')
            ->addForeignKey('ownerId', 'user', 'userId')
            ->save();

        $this->table('permissionentity')
            ->insert([
                ['entity' => 'Xibo\Entity\ModuleTemplate']
            ])
            ->save();
    }
}
