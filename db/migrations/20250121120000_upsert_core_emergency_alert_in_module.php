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
 * Add some additional fields to menu boards
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class UpsertCoreEmergencyAlertInModule extends AbstractMigration
{
    public function change(): void
    {
        // Check if the core-emergency-alert row exists
        $row = $this->fetchRow("SELECT * FROM `module` WHERE `moduleId` = 'core-emergency-alert'");

        if (!$row) {
            // Row does not exist, insert new row
            $this->execute("
                INSERT INTO `module` (`moduleId`, `enabled`, `previewEnabled`, `defaultDuration`, `settings`)
                VALUES ('core-emergency-alert', '1', '1', '60', NULL)
            ");
        } else {
            // Row exists, update existing row
            $this->execute("
                UPDATE `module`
                SET `enabled` = '1',
                    `previewEnabled` = '1',
                    `defaultDuration` = '60',
                    `settings` = NULL
                WHERE `moduleId` = 'core-emergency-alert'
            ");
        }
    }
}
