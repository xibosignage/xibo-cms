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
 * Minor DB changes for 3.2.0 - add logicalOperator columns to playlist and displayGroup tables
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddLogicalOperatorNameMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('playlist')
            ->renameColumn('filterLogicalOperator', 'filterMediaTagsLogicalOperator')
            ->addColumn('filterMediaNameLogicalOperator', 'string', ['after' => 'filterMediaName', 'default' => 'OR', 'limit' => 3])
            ->save();

        $this->table('displaygroup')
            ->renameColumn('dynamicCriteriaLogicalOperator', 'dynamicCriteriaTagsLogicalOperator')
            ->addColumn('dynamicCriteriaLogicalOperator', 'string', ['after' => 'dynamicCriteria', 'default' => 'OR', 'limit' => 3])
            ->save();
    }
}
