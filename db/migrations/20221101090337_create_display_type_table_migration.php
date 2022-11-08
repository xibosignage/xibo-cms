<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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
 * Add display types
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class CreateDisplayTypeTableMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $displayType = $this->table('display_types', ['id' => 'displayTypeId']);
        $displayType->addColumn('displayType', 'string', ['limit' => 100])
            ->insert([
                ['displayTypeId' => 1, 'displayType' => 'Billboard'],
                ['displayTypeId' => 2, 'displayType' => 'Kiosk'],
                ['displayTypeId' => 3, 'displayType' => 'LED Matrix / LED Video Wall'],
                ['displayTypeId' => 4, 'displayType' => 'Monitor / Other'],
                ['displayTypeId' => 5, 'displayType' => 'Projector'],
                ['displayTypeId' => 6, 'displayType' => 'Shelf-edge Display'],
                ['displayTypeId' => 7, 'displayType' => 'Smart Mirror'],
                ['displayTypeId' => 8, 'displayType' => 'TV / Panel'],
                ['displayTypeId' => 9, 'displayType' => 'Tablet'],
                ['displayTypeId' => 10, 'displayType' => 'Totem'],
            ])
            ->save();
    }
}
