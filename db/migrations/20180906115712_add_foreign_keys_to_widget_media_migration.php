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
 * Class AddForeignKeysToWidgetMediaMigration
 */
class AddForeignKeysToWidgetMediaMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        if (!$this->fetchRow('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'lkwidgetmedia\' AND referenced_table_name = \'media\';')) {

            $this->execute('DELETE FROM `lkwidgetmedia` WHERE NOT EXISTS (SELECT mediaId FROM `media` WHERE `media`.mediaId = `lkwidgetmedia`.mediaId) ');

            // Add the constraint
            $this->execute('ALTER TABLE `lkwidgetmedia` ADD CONSTRAINT `lkwidgetmedia_ibfk_1` FOREIGN KEY (`mediaId`) REFERENCES `media` (`mediaId`);');
        }

        if (!$this->fetchRow('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'lkwidgetmedia\' AND referenced_table_name = \'widget\';')) {

            $this->execute('DELETE FROM `lkwidgetmedia` WHERE NOT EXISTS (SELECT widgetId FROM `widget` WHERE `widget`.widgetId = `lkwidgetmedia`.widgetId) ');

            // Add the constraint
            $this->execute('ALTER TABLE `lkwidgetmedia` ADD CONSTRAINT `lkwidgetmedia_ibfk_2` FOREIGN KEY (`widgetId`) REFERENCES `widget` (`widgetId`);');
        }
    }
}
