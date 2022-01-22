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

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Add a new column to connectors table (isVisible)
 * Add a new connector (layoutExchange) to connectors table
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddLayoutExchangeConnectorMigration extends AbstractMigration
{
    public function change()
    {
        $this->table('connectors')
            ->addColumn('isVisible', 'integer', [
                'default' => 1,
                'null' => false,
                'limit' => MysqlAdapter::INT_TINY
            ])
            ->insert([
                'className' => '\\Xibo\\Connector\\XiboExchangeConnector',
                'isEnabled' => 0,
                'isVisible' => 1
            ])
            ->save();
    }
}
