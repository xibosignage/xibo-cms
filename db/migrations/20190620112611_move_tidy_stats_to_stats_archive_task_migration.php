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

class MoveTidyStatsToStatsArchiveTaskMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // query the database and look for Stats Archive task
        $statsArchiveQuery = $this->query('SELECT taskId, name, options, isActive FROM `task` WHERE `name` = \'Stats Archive\' ;');
        $statsArchiveData = $statsArchiveQuery->fetchAll(PDO::FETCH_ASSOC);

        if (count($statsArchiveData) > 0) {
            foreach ($statsArchiveData as $row) {
                $taskId = $row['taskId'];
                $isActive = $row['isActive'];
                $options = json_decode($row['options']);

                // if the task is current set as Active, we need to ensure that archiveStats option is set to On (default is Off)
                if ($isActive == 1) {
                    $options->archiveStats = 'On';
                } else {
                    $options->archiveStats = 'Off';
                }

                // save updated options to variable
                $newOptions = json_encode($options);

                $this->execute('UPDATE `task` SET isActive = 1, options = \'' . $newOptions . '\' WHERE taskId = '. $taskId );
            }
        }
    }
}
