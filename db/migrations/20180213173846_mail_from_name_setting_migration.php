<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2018 Spring Signage Ltd
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

class MailFromNameSettingMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function up()
    {
        // Check to see if the mail_from_name setting exists
        if (!$this->fetchRow('SELECT * FROM `setting` WHERE setting = \'mail_from_name\'')) {
            $this->execute('INSERT INTO setting (setting, value, fieldType, helptext, options, cat, userChange, title, validation, ordering, `default`, userSee, type) VALUES (\'mail_from_name\', \'\', \'text\', \'Mail will be sent under this name\', NULL, \'maintenance\', 1, \'Sending email name\', \'\', 45, \'\', 1, \'string\');');
        }
    }
}
