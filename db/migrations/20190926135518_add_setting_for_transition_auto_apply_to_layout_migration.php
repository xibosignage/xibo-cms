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

class AddSettingForTransitionAutoApplyToLayoutMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // Add a setting for default value of layout->autoApplyTransitions checkbox
        if (!$this->fetchRow('SELECT * FROM `setting` WHERE setting = \'DEFAULT_TRANSITION_AUTO_APPLY\'')) {
            $this->table('setting')->insert([
                [
                    'setting' => 'DEFAULT_TRANSITION_AUTO_APPLY',
                    'value' => 0,
                    'userSee' => 1,
                    'userChange' => 1
                ]
            ])->save();
        }

        $layoutTable = $this->table('layout');

        // Add a new column to Layout table - autoApplyTransitions
        if (!$layoutTable->hasColumn('autoApplyTransitions')) {
            $this->execute('UPDATE `layout` SET createdDt = \'1970-01-01 00:00:00\' WHERE createdDt < \'2000-01-01\'');
            $this->execute('UPDATE `layout` SET modifiedDt = \'1970-01-01 00:00:00\' WHERE modifiedDt < \'2000-01-01\'');

            $layoutTable
                ->changeColumn('createdDt', 'datetime', ['null' => true, 'default' => null])
                ->changeColumn('modifiedDt', 'datetime', ['null' => true, 'default' => null])
                ->addColumn('autoApplyTransitions', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 0])
                ->save();
        }
    }
}
