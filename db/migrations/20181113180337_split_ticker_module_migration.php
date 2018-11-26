<?php
/**
 * Copyright (C) 2018 Xibo Signage Ltd
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
 * Class SplitTickerModuleMigration
 */
class SplitTickerModuleMigration extends AbstractMigration
{
    /** @inheritdoc */
    public function change()
    {
        // Add the new module.
        $this->execute('
            INSERT INTO `module` 
                (`Module`, `Name`, `Enabled`, `RegionSpecific`, `Description`, `ImageUri`, `SchemaVersion`, `ValidExtensions`, `PreviewEnabled`, `assignable`, `render_as`, `settings`, `viewPath`, `class`, `defaultDuration`) 
            VALUES
                (\'datasetticker\', \'DataSet Ticker\', 1, 1, \'Ticker with a DataSet providing the items\', \'forms/ticker.gif\', 1, NULL, 1, 1, \'html\', NULL, \'../modules\', \'Xibo\\\\Widget\\\\DataSetTicker\', 10);        
        ');

        // Find all of the existing tickers which have a dataSet source, and update them to point at the new
        // module `datasetticker`
        $this->execute('
            UPDATE `widget` SET type = \'datasetticker\' WHERE type = \'ticker\' AND widgetId IN (SELECT DISTINCT widgetId FROM `widgetoption` WHERE `option` = \'sourceId\' AND `value` = \'2\')
        ');
    }
}
