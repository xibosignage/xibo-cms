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
