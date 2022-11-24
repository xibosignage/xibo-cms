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
 * Add layoutId column to Action table
 * Populate layoutId for existing Actions
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddLayoutIdToActionTableMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        $this->table('action')
            ->addColumn('layoutId', 'integer', ['null' => true, 'default' => null])
            ->save();

        // Set layoutId to sourceId if the source is Layout
        $this->execute('UPDATE `action` SET layoutId = `action`.sourceId WHERE `action`.source = \'layout\' ');

        // Set layoutId to a layout corresponding with the regionId (sourceId) for region source
        foreach ($this->fetchAll('SELECT `region`.layoutId, `region`.regionId FROM `action` INNER JOIN `region` ON `action`.sourceId = `region`.regionId AND `action`.source = \'region\' ') as $regionAction) {
            $this->execute('UPDATE `action` SET `action`.layoutId =' . $regionAction['layoutId'] . ' WHERE `action`.sourceId = ' . $regionAction['regionId'] . ' AND `action`.source = \'region\' ');
        }

        // Set layoutId to Layout corresponding with widgetId (sourceId) for widget source
        foreach ($this->fetchAll('SELECT `region`.layoutId, `widget`.widgetId FROM `action` INNER JOIN `widget` ON `action`.sourceId = `widget`.widgetId AND `action`.source = \'widget\' INNER JOIN `playlist` ON `widget`.playlistId = `playlist`.playlistId INNER JOIN `region` ON `playlist`.regionId = `region`.regionId') as $widgetAction) {
            $this->execute('UPDATE `action` SET `action`.layoutId =' . $widgetAction['layoutId'] . ' WHERE `action`.sourceId = ' . $widgetAction['widgetId'] . ' AND `action`.source = \'widget\' ');
        }
    }
}
