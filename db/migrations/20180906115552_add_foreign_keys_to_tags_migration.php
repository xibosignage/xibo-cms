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
 * Class AddForeignKeysToTagsMigration
 */
class AddForeignKeysToTagsMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        if (!$this->fetchRow('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'lktagcampaign\' AND referenced_table_name = \'campaign\';')) {
            // Delete any records which result in a constraint failure (the records would be orphaned anyway)
            $this->execute('DELETE FROM `lktagcampaign` WHERE campaignId NOT IN (SELECT campaignId FROM `campaign`)');
            // Add the constraint
            $this->execute('ALTER TABLE `lktagcampaign` ADD CONSTRAINT `lktagcampaign_ibfk_1` FOREIGN KEY (`campaignId`) REFERENCES `campaign` (`campaignId`);');
        }

        if (!$this->fetchRow('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'lktaglayout\' AND referenced_table_name = \'layout\';')) {
            // Delete any records which result in a constraint failure (the records would be orphaned anyway)
            $this->execute('DELETE FROM `lktaglayout` WHERE layoutId NOT IN (SELECT layoutId FROM `layout`)');
            // Add the constraint
            $this->execute('ALTER TABLE `lktaglayout` ADD CONSTRAINT `lktaglayout_ibfk_1` FOREIGN KEY (`layoutId`) REFERENCES `layout` (`layoutId`);');
        }

        if (!$this->fetchRow('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'lktagmedia\' AND referenced_table_name = \'media\';')) {
            // Delete any records which result in a constraint failure (the records would be orphaned anyway)
            $this->execute('DELETE FROM `lktagmedia` WHERE mediaId NOT IN (SELECT mediaId FROM `media`)');
            // Add the constraint
            $this->execute('ALTER TABLE `lktagmedia` ADD CONSTRAINT `lktagmedia_ibfk_1` FOREIGN KEY (`mediaId`) REFERENCES `media` (`mediaId`);');
        }

        if (!$this->fetchRow('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'lktagdisplaygroup\' AND referenced_table_name = \'displaygroup\';')) {
            // Delete any records which result in a constraint failure (the records would be orphaned anyway)
            $this->execute('DELETE FROM `lktagdisplaygroup` WHERE displayGroupId NOT IN (SELECT displayGroupId FROM `displaygroup`)');
            // Add the constraint
            $this->execute('ALTER TABLE `lktagdisplaygroup` ADD CONSTRAINT `lktagdisplaygroup_ibfk_1` FOREIGN KEY (`displayGroupId`) REFERENCES `displaygroup` (`displayGroupId`);');
        }
    }
}
