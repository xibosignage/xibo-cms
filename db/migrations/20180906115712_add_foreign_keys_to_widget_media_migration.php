<?php
/**
 * Copyright (C) 2018 Xibo Signage Ltd
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
