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
class UpsertInteractiveLinkAndZoneInModuleTableMigration extends AbstractMigration
{
    public function change(): void
    {
        $ids = ['core-interactive-link', 'core-interactive-zone'];
        $pdo = $this->getAdapter()->getConnection();

        foreach ($ids as $id) {
            // Safely quoted literal
            $qid = $pdo->quote($id);

            // Check if the core-interactive-link and core-interactive-zone row exists
            $row = $this->fetchRow('SELECT 1 FROM `module` WHERE `moduleId` = "'. $qid . '"');

            if (!$row) {
                // Row does not exist, insert new row
                $this->execute('
                    INSERT INTO `module` (`moduleId`, `enabled`, `previewEnabled`, `defaultDuration`, `settings`)
                    VALUES (' . $qid . ', "1", "1", "60", NULL)
                ');
            } else {
                // Row exists, update existing row
                $this->execute('
                    UPDATE `module`
                    SET `enabled` = "1",
                        `previewEnabled` = "1",
                        `defaultDuration` = "60",
                        `settings` = NULL
                    WHERE `moduleId` = "' . $qid . '"');
            }
        }
    }
}
