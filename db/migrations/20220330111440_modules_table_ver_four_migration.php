<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
 * Convert the modules table for v4
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class ModulesTableVerFourMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function change()
    {
        // Rename the old table.
        $this->table('module')->rename('module_old')->save();

        // Add our new table
        $this->table('module', ['id' => false, 'primary_key' => ['moduleId']])
            ->addColumn('moduleId', 'string', [
                'limit' => 35,
                'null' => false
            ])
            ->addColumn('enabled', 'integer', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'default' => 0
            ])
            ->addColumn('previewEnabled', 'integer', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
                'default' => 1
            ])
            ->addColumn('defaultDuration', 'integer', [
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_REGULAR,
                'default' => 60
            ])
            ->addColumn('settings', 'text', [
                'default' => null,
                'null' => true
            ])
            ->save();

        // Pull through old modules, having a guess at their names.
        try {
            //phpcs:disable
            $this->execute('
                INSERT INTO `module` (`moduleId`, `enabled`, `previewEnabled`, `defaultDuration`, `settings`)
                SELECT LOWER(CASE WHEN `class` LIKE \'%Custom%\' THEN IFNULL(installname, module) ELSE CONCAT(\'core-\', `module`) END),
                    MAX(`enabled`),
                    MAX(`previewEnabled`),
                    MAX(`defaultDuration`),
                    MAX(`settings`)
                  FROM `module_old`
                GROUP BY LOWER(CASE WHEN `class` LIKE \'%Custom%\' THEN IFNULL(installname, module) ELSE CONCAT(\'core-\', `module`) END)
            ');
            //phpcs:enable

            // Handle any specific renames
            $this->execute('UPDATE `module` SET moduleId = \'core-rss-ticker\' WHERE moduleId = \'core-ticker\'');
            $this->execute('UPDATE `module` SET moduleId = \'core-dataset\' WHERE moduleId = \'core-datasetticker\'');
            $this->execute('DELETE FROM `module` WHERE moduleId = \'core-datasetview\'');

            // Drop the old table
            $this->table('module_old')->drop()->save();

            // Add more v4 modules

            // Check clock and update/add v4 modules
            $clock = $this->fetchRow('SELECT * FROM `module` WHERE `moduleId` = \'core-clock\'');
            $enabled = $clock['enabled'];
            $previewEnabled = $clock['previewEnabled'];
            $defaultDuration = $clock['defaultDuration'];
            $settings = $clock['settings'];

            $this->execute('UPDATE `module` SET `moduleId` = \'core-clock-analogue\',
                    enabled = ' .$enabled. ', previewEnabled = ' .$previewEnabled. ',
                defaultDuration = ' .$defaultDuration. '  WHERE `moduleId` = \'core-clock\';');

            $this->execute('
            INSERT INTO `module` (`moduleId`, `enabled`, `previewEnabled`, `defaultDuration`, `settings`) VALUES
              (\'core-clock-digital\', '.$enabled.', '.$previewEnabled.', '.$defaultDuration.', \''.$settings.'\'),
              (\'core-clock-flip\', '.$enabled.', '.$previewEnabled.', '.$defaultDuration.', \''.$settings.'\');
            ');

            // Check countdown and update/add v4 modules
            $countdown = $this->fetchRow('SELECT * FROM `module` WHERE `moduleId` = \'core-countdown\'');
            $enabled = $countdown['enabled'];
            $previewEnabled = $countdown['previewEnabled'];
            $defaultDuration = $countdown['defaultDuration'];
            $settings = $countdown['settings'];

            $this->execute('UPDATE `module` SET `moduleId` = \'core-countdown-clock\',
                    enabled = ' .$enabled. ', previewEnabled = ' .$previewEnabled. ',
                defaultDuration = ' .$defaultDuration. '  WHERE `moduleId` = \'core-countdown\';');

            $this->execute('
            INSERT INTO `module` (`moduleId`, `enabled`, `previewEnabled`, `defaultDuration`, `settings`) VALUES
              (\'core-countdown-days\', '.$enabled.', '.$previewEnabled.', '.$defaultDuration.', \''.$settings.'\'),
              (\'core-countdown-table\', '.$enabled.', '.$previewEnabled.', '.$defaultDuration.', \''.$settings.'\'),
              (\'core-countdown-text\', '.$enabled.', '.$previewEnabled.', '.$defaultDuration.', \''.$settings.'\');
            ');

            // Check worldclock and update/add v4 modules
            $worldclock = $this->fetchRow('SELECT * FROM `module` WHERE `moduleId` = \'core-worldclock\'');
            $enabled = $worldclock['enabled'];
            $previewEnabled = $worldclock['previewEnabled'];
            $defaultDuration = $worldclock['defaultDuration'];
            $settings = $worldclock['settings'];

            $this->execute('UPDATE `module` SET `moduleId` = \'core-worldclock-analogue\',
                    enabled = ' .$enabled. ', previewEnabled = ' .$previewEnabled. ',
                defaultDuration = ' .$defaultDuration. '  WHERE `moduleId` = \'core-worldclock\';');

            $this->execute('
            INSERT INTO `module` (`moduleId`, `enabled`, `previewEnabled`, `defaultDuration`, `settings`) VALUES
              (\'core-worldclock-custom\', '.$enabled.', '.$previewEnabled.', '.$defaultDuration.', \''.$settings.'\'),
              (\'core-worldclock-digital-date\', '.$enabled.', '.$previewEnabled.', '.$defaultDuration.', \''.$settings.'\'),
              (\'core-worldclock-digital-text\', '.$enabled.', '.$previewEnabled.', '.$defaultDuration.', \''.$settings.'\');
            ');

            // Add new modules.
            $this->execute('
            INSERT INTO `module` (`moduleId`, `enabled`, `previewEnabled`, `defaultDuration`, `settings`) VALUES
              (\'core-canvas\', \'1\', \'1\', \'60\', \'[]\'),
              (\'core-mastodon\', \'1\', \'1\', \'60\', \'[]\'),
              (\'core-countdown-custom\', \'1\', \'1\', \'60\', \'[]\')
            ');
        } catch (Exception $e) {
            // Keep the old module table around for diagnosis and just continue on.
        }
    }
}
