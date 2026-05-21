<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
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
 * Add search indexes to Menu Board tables
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddIndexesForInteractiveActionsMigration extends AbstractMigration
{
    public function change(): void
    {
        // These indexes were found to be beneficial in XMDS when calculating actions for a layout
        // ALTER TABLE `action`
        //    ADD INDEX idx_action_type_source (actionType, sourceId),
        //    ADD INDEX idx_action_layoutcode (layoutCode);
        //
        // ALTER TABLE `layout`
        //    ADD INDEX idx_layout_code (code);
        if (!$this->checkIndexExists('action', ['actionType', 'sourceId'], false)) {
            $this->execute('ALTER TABLE `action` ADD INDEX idx_action_type_source (`actionType`, `sourceId`)');
        }
        if (!$this->checkIndexExists('action', ['layoutCode'], false)) {
            $this->execute('ALTER TABLE `action` ADD INDEX idx_action_layoutcode (`layoutCode`)');
        }
        if (!$this->checkIndexExists('layout', ['code'], false)) {
            $this->execute('ALTER TABLE `layout` ADD INDEX idx_layout_code (`code`)');
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
    private function checkIndexExists($table, $columns, $isUnique): bool
    {
        if (!is_array($columns) || count($columns) <= 0) {
            throw new InvalidArgumentException('Incorrect call to checkIndexExists', 'columns');
        }

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

            $sql .= (($i == 1) ? '' : ' OR')
                . ' (seq_in_index = \'' . $i . '\' AND column_name = \'' . $column . '\') ';
        }

        $sql .= ' )';

        $indexes = $this->fetchAll($sql);

        return (count($indexes) === count($columns));
    }
}
