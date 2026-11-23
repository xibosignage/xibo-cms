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

/**
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;
use Symfony\Component\Filesystem\Filesystem;
use Xibo\Helper\LibraryFile;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * ChromeOS is no longer uploaded as Player Software: the player is bundled into the CMS
 * image at build time and served from web/pwa. Remove the uploaded ChromeOS versions,
 * their files, and the playerVersionId setting that chose which of them was served.
 */
class RemoveChromeOsPlayerSoftwareMigration extends AbstractMigration
{
    /** @inheritDoc */
    public function up(): void
    {
        $libraryLocation = $this->fetchRow('
            SELECT `setting`.`value`
              FROM `setting`
             WHERE `setting`.`setting` = \'LIBRARY_LOCATION\'')['value'] ?? null;

        // New installs won't have a library location yet (if they are non-docker).
        if (!empty($libraryLocation)) {
            $versions = $this->fetchAll('
                SELECT `fileName`
                  FROM `player_software`
                 WHERE `player_type` = \'chromeOS\'');

            $filesystem = new Filesystem();
            foreach ($versions as $version) {
                try {
                    $file = LibraryFile::resolve(
                        $libraryLocation,
                        'playersoftware/' . basename($version['fileName'])
                    );
                } catch (InvalidArgumentException) {
                    // Not a file inside the library, so not one of ours to delete.
                    continue;
                }
                if (is_file($file)) {
                    $filesystem->remove($file);
                }
            }

            // The unpacked versions, and the `latest` symlink Apache used to serve.
            // remove() deletes symlinks without following them.
            $filesystem->remove(LibraryFile::resolve($libraryLocation, 'playersoftware/chromeos'));
        }

        $this->execute('DELETE FROM `player_software` WHERE `player_type` = \'chromeOS\'');

        // playerVersionId only ever chose a ChromeOS version.
        $profiles = $this->fetchAll('
            SELECT `displayProfileId`, `config`
              FROM `displayprofile`
             WHERE `config` LIKE \'%playerVersionId%\'');

        foreach ($profiles as $profile) {
            $config = $this->withoutPlayerVersionId($profile['config']);
            if ($config !== null) {
                $this->execute('UPDATE `displayprofile` SET `config` = :config
                     WHERE `displayProfileId` = :displayProfileId', [
                    'config' => $config,
                    'displayProfileId' => $profile['displayProfileId'],
                ]);
            }
        }

        $displays = $this->fetchAll('
            SELECT `displayId`, `overrideConfig`
              FROM `display`
             WHERE `overrideConfig` LIKE \'%playerVersionId%\'');

        foreach ($displays as $display) {
            $overrideConfig = $this->withoutPlayerVersionId($display['overrideConfig']);
            if ($overrideConfig !== null) {
                $this->execute('UPDATE `display` SET `overrideConfig` = :overrideConfig
                     WHERE `displayId` = :displayId', [
                    'overrideConfig' => $overrideConfig,
                    'displayId' => $display['displayId'],
                ]);
            }
        }
    }

    /**
     * The deleted versions, their files and the playerVersionId settings can't be restored.
     *
     * @throws IrreversibleMigrationException
     */
    public function down(): void
    {
        throw new IrreversibleMigrationException(
            'Removing the uploaded ChromeOS player software cannot be reversed'
        );
    }

    /**
     * @return string|null the config without its playerVersionId entry, or null if it had none
     */
    private function withoutPlayerVersionId(?string $json): ?string
    {
        $config = json_decode($json ?? '', true);
        if (!is_array($config)) {
            return null;
        }

        $kept = array_values(array_filter(
            $config,
            fn ($setting) => ($setting['name'] ?? null) !== 'playerVersionId'
        ));

        return count($kept) === count($config) ? null : json_encode($kept);
    }
}
