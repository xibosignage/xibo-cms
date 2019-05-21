<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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

class AddForeignKeysToLktagTablesMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        //lktagcampaign
        if (!$this->fetchRow('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'lktagcampaign\' AND referenced_table_name = \'campaign\';')) {

            $this->execute('DELETE FROM `lktagcampaign` WHERE NOT EXISTS (SELECT campaignId FROM `campaign` WHERE `campaign`.campaignId = `lktagcampaign`.campaignId) ');

            // Add the constraint
            $this->execute('ALTER TABLE `lktagcampaign` ADD CONSTRAINT `lktagcampaign_ibfk_2` FOREIGN KEY (`campaignId`) REFERENCES `campaign` (`campaignId`);');
        }

        //lktagdisplaygroup
        if (!$this->fetchRow('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'lktagdisplaygroup\' AND referenced_table_name = \'displaygroup\';')) {

            $this->execute('DELETE FROM `lktagdisplaygroup` WHERE NOT EXISTS (SELECT displayGroupId FROM `displaygroup` WHERE `displaygroup`.displayGroupId = `lktagdisplaygroup`.displayGroupId) ');

            // Add the constraint
            $this->execute('ALTER TABLE `lktagdisplaygroup` ADD CONSTRAINT `lktagdisplaygroup_ibfk_2` FOREIGN KEY (`displayGroupId`) REFERENCES `displaygroup` (`displayGroupId`);');
        }

        //lktaglayout
        if (!$this->fetchRow('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'lktaglayout\' AND referenced_table_name = \'layout\';')) {

            $this->execute('DELETE FROM `lktaglayout` WHERE NOT EXISTS (SELECT layoutId FROM `layout` WHERE `layout`.layoutId = `lktaglayout`.layoutId) ');

            // Add the constraint
            $this->execute('ALTER TABLE `lktaglayout` ADD CONSTRAINT `lktaglayout_ibfk_2` FOREIGN KEY (`layoutId`) REFERENCES `layout` (`layoutId`);');
        }

        //lktagmedia
        if (!$this->fetchRow('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'lktagmedia\' AND referenced_table_name = \'media\';')) {

            $this->execute('DELETE FROM `lktagmedia` WHERE NOT EXISTS (SELECT mediaId FROM `media` WHERE `media`.mediaId = `lktagmedia`.mediaId) ');

            // Add the constraint
            $this->execute('ALTER TABLE `lktagmedia` ADD CONSTRAINT `lktagmedia_ibfk_2` FOREIGN KEY (`mediaId`) REFERENCES `media` (`mediaId`);');
        }

        //lktagplaylist
        if (!$this->fetchRow('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'lktagplaylist\' AND referenced_table_name = \'tag\';')) {

            $this->execute('DELETE FROM `lktagplaylist` WHERE NOT EXISTS (SELECT tagId FROM `tag` WHERE `tag`.tagId = `lktagplaylist`.tagId) ');

            // Add the constraint
            $this->execute('ALTER TABLE `lktagplaylist` ADD CONSTRAINT `lktagplaylist_ibfk_1` FOREIGN KEY (`tagId`) REFERENCES `tag` (`tagId`);');
        }

        if (!$this->fetchRow('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'lktagplaylist\' AND referenced_table_name = \'playlist\';')) {

            $this->execute('DELETE FROM `lktagplaylist` WHERE NOT EXISTS (SELECT playlistId FROM `playlist` WHERE `playlist`.playlistId = `lktagplaylist`.playlistId) ');

            // Add the constraint
            $this->execute('ALTER TABLE `lktagplaylist` ADD CONSTRAINT `lktagplaylist_ibfk_2` FOREIGN KEY (`playlistId`) REFERENCES `playlist` (`playlistId`);');
        }
    }
}
