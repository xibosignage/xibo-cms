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

class DisplayTableDatabaseSchemaAdjustmentsMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // nullable and default values adjusted
        $this->execute('ALTER TABLE display MODIFY `lastaccessed` int(11) NULL DEFAULT NULL');
        $this->execute('ALTER TABLE display MODIFY `license` varchar(40) NULL DEFAULT NULL');
        $this->execute('ALTER TABLE display MODIFY `alert_timeout` int(11) DEFAULT 0');
        $this->execute('ALTER TABLE display MODIFY `clientAddress` varchar(50) NULL DEFAULT NULL');
        $this->execute('ALTER TABLE display MODIFY `macAddress` varchar(254) NULL DEFAULT NULL');
        $this->execute('ALTER TABLE display MODIFY `lastChanged` int(11) NULL DEFAULT NULL');
        $this->execute('ALTER TABLE display MODIFY `numberOfMacAddressChanges` int(11) DEFAULT 0');
        $this->execute('ALTER TABLE display MODIFY `lastWakeOnLanCommandSent` int(11) NULL DEFAULT NULL');
        $this->execute('ALTER TABLE display MODIFY `email_alert` int(11) DEFAULT 0');

        // display profile foreign key
        if (!$this->fetchRow('
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE constraint_schema=DATABASE()
                AND `table_name` = \'display\' AND referenced_table_name = \'displayprofile\';')) {

            $this->execute('UPDATE `display` SET displayProfileId = NULL WHERE NOT EXISTS (SELECT displayProfileId FROM `displayprofile` WHERE `displayprofile`.displayProfileId = `display`.displayProfileId)');

            // Add the constraint
            $this->execute('ALTER TABLE `display` ADD CONSTRAINT `display_ibfk_1` FOREIGN KEY (`displayProfileId`) REFERENCES `displayprofile` (`displayProfileId`);');
        }
    }
}
