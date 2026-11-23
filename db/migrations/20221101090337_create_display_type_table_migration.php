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
