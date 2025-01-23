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
 * Migrations for schedule criteria
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class UpdateMetricColumnLimitInScheduleCriteria extends AbstractMigration
{
    public function change(): void
    {
        $this->table('schedule_criteria')
            ->changeColumn('type', 'string', [
                'limit' => 100,
                'null' => false,
            ])
            ->changeColumn('metric', 'string', [
                'limit' => 100,
                'null' => false,
            ])
            ->save();
    }
}
