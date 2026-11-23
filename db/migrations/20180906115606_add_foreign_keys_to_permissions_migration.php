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
 * Class AddForeignKeysToPermissionsMigration
 */
class AddForeignKeysToPermissionsMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        if (!$this->fetchRow('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'permission\' AND referenced_table_name = \'group\';')) {
            // Delete any records which result in a constraint failure (the records would be orphaned anyway)
            $this->execute('DELETE FROM `permission` WHERE groupId NOT IN (SELECT groupId FROM `group`)');
            // Add the constraint
            $this->execute('ALTER TABLE `permission` ADD CONSTRAINT `permission_ibfk_1` FOREIGN KEY (`groupId`) REFERENCES `group` (`groupId`);');
        }

        if (!$this->fetchRow('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'permission\' AND referenced_table_name = \'permissionentity\';')) {
            // Delete any records which result in a constraint failure (the records would be orphaned anyway)
            $this->execute('DELETE FROM `permission` WHERE entityId NOT IN (SELECT entityId FROM `permissionentity`)');
            // Add the constraint
            $this->execute('ALTER TABLE `permission` ADD CONSTRAINT `permission_ibfk_2` FOREIGN KEY (`entityId`) REFERENCES `permissionentity` (`entityId`);');
        }

        // Index
        if (!$this->checkIndexExists('permission', ['objectId'], 0)) {
            $this->execute('CREATE INDEX permission_objectId_index ON permission (objectId);');
        }
    }

    /**
     * Check if an index exists
     * @param string $table
     * @param string[] $columns
     * @param bool $isUnique
     * @return bool
     * @throws InvalidArgumentException
     */
    private function checkIndexExists($table, $columns, $isUnique)
    {
        if (!is_array($columns) || count($columns) <= 0)
            throw new InvalidArgumentException('Incorrect call to checkIndexExists', 'columns');

        // Use the information schema to see if the index exists or not.
        // all users have permission to the information schema
        $sql = '
          SELECT * 
            FROM INFORMATION_SCHEMA.STATISTICS 
           WHERE table_schema=DATABASE() 
            AND table_name = \'' . $table . '\' 
            AND non_unique = \'' . (($isUnique) ? 0 : 1) . '\'
            AND (
        ';

        $i = 0;
        foreach ($columns as $column) {
            $i++;
            $sql .= (($i == 1) ? '' : ' OR') . ' (seq_in_index = \'' . $i . '\' AND column_name = \'' . $column . '\') ';
        }
        $sql .= ' )';
        $indexes = $this->fetchAll($sql);
        
        return (count($indexes) === count($columns));
    }

}
