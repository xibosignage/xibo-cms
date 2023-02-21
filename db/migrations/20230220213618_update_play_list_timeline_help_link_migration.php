<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
 * Update playlist timeline help link
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class UpdatePlayListTimelineHelpLinkMigration extends AbstractMigration
{
    public function change()
    {
        $this->execute('UPDATE `help` SET `topic` = \'Media\', `category` = \'Playlists\', `link` = \'media_playlists.html\'
              WHERE `topic` = \'Layout\' AND `category` = \'RegionOptions\' ');
    }
}
