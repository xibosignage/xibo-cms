<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
 * Add some additional fields to menu boards
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class MenuboardAdditionalFieldsMigration extends AbstractMigration
{
    public function change(): void
    {
        // Before I do this I need to make sure that all products in this table have a numeric field in price
        foreach ($this->fetchAll('SELECT `menuProductId`, `price` FROM `menu_product`') as $row) {
            if (!empty($row['price']) && !is_numeric($row['price'])) {
                $this->execute('UPDATE `menu_product` SET `price` = :price WHERE menuProductId = :id', [
                    'id' => $row['menuProductId'],
                    'price' => preg_replace('/[^0-9.]/', '', $row['price']),
                ]);
            }
        }

        $this->table('menu_product')
            ->addColumn('calories', 'integer', [
                'length' => \Phinx\Db\Adapter\MysqlAdapter::INT_SMALL,
                'null' => true,
                'default' => null,
            ])
            ->addColumn('displayOrder', 'integer', [
                'length' => \Phinx\Db\Adapter\MysqlAdapter::INT_MEDIUM,
                'null' => false,
                'default' => 0,
            ])
            ->changeColumn('price', 'decimal', [
                'precision' => 10,
                'scale' => 4,
                'default' => null,
                'null' => true,
            ])
            ->save();

        $this->table('menu_category')
            ->addColumn('description', 'string', [
                'length' => 254,
                'null' => true,
                'default' => null,
            ])
            ->save();

        // Drop the old menu-board module entirely, and insert the new ones.
        $this->execute('DELETE FROM `module` WHERE moduleId = \'core-menuboard\'');
        $this->execute('
            INSERT INTO `module` (`moduleId`, `enabled`, `previewEnabled`, `defaultDuration`, `settings`) VALUES
              (\'core-menuboard-category\', \'1\', \'1\', \'60\', \'[]\'),
              (\'core-menuboard-product\', \'1\', \'1\', \'60\', \'[]\');
        ');
    }
}
