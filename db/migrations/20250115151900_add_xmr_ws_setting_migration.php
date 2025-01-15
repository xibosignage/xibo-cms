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
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddXmrWsSettingMigration extends AbstractMigration
{
    public function change(): void
    {
        // If the setting does not yet exist, add it.
        if (!$this->fetchRow('SELECT * FROM `setting` WHERE `setting` = \'XMR_WS_ADDRESS\'')) {
            $this->table('setting')->insert([
                [
                    'setting' => 'XMR_WS_ADDRESS',
                    'value' => '',
                    'userSee' => 1,
                    'userChange' => 1
                ]
            ])->save();
        }
    }
}
