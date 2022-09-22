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
 * Convert the modules table for v4
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class ModulesTableVerFourMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // Rename the old table.
        $this->table('module')->rename('module_old');

        // Add our new table
        $this->table('module', ['id' => false, 'primary_key' => ['moduleId']])
            ->addColumn('moduleId', 'string', [
                'limit' => 35,
                'null' => false
            ])
            ->addColumn('enabled', 'integer', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'default' => 0
            ])
            ->addColumn('previewEnabled', 'integer', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'default' => 1
            ])
            ->addColumn('defaultDuration', 'integer', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
                'default' => 60
            ])
            ->addColumn('settings', 'text', [
                'default' => null,
                'null' => true
            ])
            ->save();

        // Pull through old modules, having a guess at their names.
        try {
            $this->execute('
            INSERT INTO `module` (`moduleId`, `enabled`, `previewEnabled`, `defaultDuration`, `settings`)
            SELECT DISTINCT LOWER(CASE WHEN `class` LIKE \'%Custom%\' 
                       THEN IFNULL(installname, module)
                       ELSE CONCAT(\'core-\', `module`)
                   END),
                   `enabled`,
                   `previewEnabled`,
                   `defaultDuration`, 
                   `settings`
              FROM `module_old`');

            // Handle any specific renames
            $this->execute('UPDATE `module` SET moduleId = \'core-rss-ticker\' WHERE moduleId = \'core-ticker\'');

            // Drop the old table
            $this->dropTable('module_old');
        } catch (Exception $e) {
            // Keep the old module table around for diagnosis and just continue on.
        }
    }
}
