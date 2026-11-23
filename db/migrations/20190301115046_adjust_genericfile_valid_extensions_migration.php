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
 * Class AdjustGenericfileValidExtensionsMigration
 */
class AdjustGenericfileValidExtensionsMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // get the current validExtensions for genericfile module
        $extensionsData = $this->query('SELECT `validExtensions` FROM `module` WHERE `Module` = \'genericfile\';');
        $extensions = $extensionsData->fetchAll(PDO::FETCH_ASSOC);
        $newExtensions = [];

        //iterate through the array
        foreach ($extensions as $extension) {
            foreach ($extension as $validExt) {

                // make an array out of comma separated string
                $explode = explode(',', $validExt);

                // iterate through our array, remove apk and ipk extensions from it and put them in a new array
                foreach ($explode as $item) {
                    if ($item != 'apk' && $item != 'ipk') {
                        $newExtensions[] = $item;
                    }
                }
            }
        }

        // make a comma separated string from our new array
        $newValidExtensions = implode(',', $newExtensions);

        // update validExtensions for genericfile module with our adjusted extensions
        $this->execute('UPDATE `module` SET `validExtensions` = \'' . $newValidExtensions . '\' WHERE module = \'genericfile\' LIMIT 1;');
    }
}
