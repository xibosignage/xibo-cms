<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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
 * Class DisplayRemoteLinksMigration
 */
class DisplayRemoteLinksMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $table = $this->table('display');

        if (!$table->hasColumn('teamViewerSerial')) {
            $table
                ->addColumn('teamViewerSerial', 'string', ['limit' => 255, 'default' => null, 'null' => true])
                ->addColumn('webkeySerial', 'string', ['limit' => 255, 'default' => null, 'null' => true])
                ->save();
        }

        // Go through all existing displays, and see if the teamviewerSerial or webkeySerial have been set in Display settings.
        foreach ($this->fetchAll('SELECT displayId, overrideConfig FROM `display`') as $row) {
            $displayId = (int)$row['displayId'];
            $overrideConfig = $row['overrideConfig'];

            if (!empty($overrideConfig)) {
                $teamViewerSerial = null;
                $webkeySerial = null;
                $overrideConfig = json_decode($overrideConfig, true);

                if (is_array($overrideConfig)) {
                    foreach ($overrideConfig as $value) {
                        if ($value['name'] === 'teamViewerSerial') {
                            $teamViewerSerial = $value['value'];
                        } else if ($value['name'] === 'webkeySerial') {
                            $webkeySerial = $value['value'];
                        }
                    }
                }

                if ($teamViewerSerial !== null || $webkeySerial !== null) {
                    $this->execute(sprintf('UPDATE `display` SET teamViewerSerial = %s, webkeySerial = %s WHERE displayId = %d',
                        $teamViewerSerial === null ? 'NULL' : '\'' . $teamViewerSerial . '\'',
                        $webkeySerial === null ? 'NULL' : '\'' . $webkeySerial . '\'',
                        $displayId
                    ));
                }
            }
        }
    }
}
