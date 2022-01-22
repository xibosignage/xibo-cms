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
 * Add a new column (code) to menu_board, menu_category and menu_product tables
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddCodeColumnToMenuBoardsMigration extends AbstractMigration
{
    public function change()
    {
        $this->table('menu_board')
            ->addColumn('code', 'string', ['limit' => 50, 'after' => 'description', 'null' => true, 'default' => null])
            ->save();

        $this->table('menu_category')
            ->addColumn('code', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->save();

        $this->table('menu_product')
            ->addColumn('code', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->save();
    }
}
