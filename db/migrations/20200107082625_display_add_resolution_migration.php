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
 * Class DisplayAddResolutionMigration
 */
class DisplayAddResolutionMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // Add orientation and resolution to the display table
        // these are informational fields intended to be updated by the Player during a NotifyStatus call
        // the Player will send the resolution as two integers of width and height, which we will combine to
        // WxH in the resolution column and use to work out the orientation.
        $display = $this->table('display');
        $display
            ->addColumn('orientation', 'string', ['limit' => 10, 'null' => true, 'default' => null])
            ->addColumn('resolution', 'string', ['limit' => 10, 'null' => true, 'default' => null])
            ->save();
    }
}
