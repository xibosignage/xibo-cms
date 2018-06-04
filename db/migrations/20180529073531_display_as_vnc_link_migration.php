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

/**
 * Class DisplayAsVncLinkMigration
 */
class DisplayAsVncLinkMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        $this->query('UPDATE `setting` SET title = \'Add a link to the Display name using this format mask?\', helpText = \'Turn the display name in display management into a link using the IP address last collected. The %s is replaced with the IP address. Leave blank to disable.\' WHERE setting = \'SHOW_DISPLAY_AS_VNCLINK\';');

        $this->query('UPDATE `setting` SET title = \'The target attribute for the above link\' WHERE setting = \'SHOW_DISPLAY_AS_VNC_TGT\';');
    }
}
