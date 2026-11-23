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
 * Adds media.thumbnailVersion and records each video cover's and image's md5, keeping any old thumbnail under that
 * version.
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */
class AddMediaThumbnailVersionMigration extends AbstractMigration
{
    public function up(): void
    {
        $libraryLocation = $this->fetchRow(
            'SELECT `value` FROM `setting` WHERE `setting` = \'LIBRARY_LOCATION\''
        )['value'] ?? '';

        // Before the column is added, so a failed run can simply be run again.
        $videos = $this->fetchAll('SELECT `mediaId`, `storedAs` FROM `media` WHERE `type` = \'video\'');
        $images = $this->fetchAll(
            'SELECT `mediaId`, `storedAs`, `md5` FROM `media` WHERE `type` = \'image\' AND IFNULL(`md5`, \'\') <> \'\''
        );
        $this->checkLibraryHolds(
            $libraryLocation,
            array_merge($videos, $images),
            !empty(getenv('XIBO_MIGRATION_SKIP_LIBRARY_CHECK'))
        );

        $this->table('media')
            ->addColumn('thumbnailVersion', 'string', ['limit' => 32, 'null' => true, 'default' => null])
            ->save();

        if (count($videos) > 0) {
            $this->recordVideoCovers($libraryLocation);
        }
        $this->recordImageThumbnails($libraryLocation, $images);
    }

    /**
     * Throws when none of $media's files are in $libraryLocation, which means it isn't the library, unless skipped.
     */
    private function checkLibraryHolds(string $libraryLocation, array $media, bool $skip = false): void
    {
        foreach ($media as $row) {
            if (is_file($libraryLocation . $row['storedAs'])) {
                return;
            }
        }

        if (count($media) <= 0) {
            return;
        }

        $message = 'None of the library\'s videos or images are in \'' . $libraryLocation . '\'.';
        if ($skip) {
            $this->output->writeln($message . ' Continuing without the old video and image thumbnails.');
            return;
        }

        throw new \RuntimeException($message . ' Run the migrations where the library is mounted, or set '
            . 'XIBO_MIGRATION_SKIP_LIBRARY_CHECK=1 to continue without the old video and image thumbnails.');
    }

    /**
     * Keeps each image's old thumbnail under the image's md5, the version a thumbnail is made under now.
     */
    private function recordImageThumbnails(string $libraryLocation, array $images): void
    {
        foreach ($images as $row) {
            $thumbnail = $libraryLocation . 'tn_' . $row['storedAs'];
            $md5 = $row['md5'];
            if (!preg_match('/^[a-f0-9]{32}$/', $md5) || !$this->isThumbnailSized($thumbnail)) {
                continue;
            }

            if (!@copy($thumbnail, $thumbnail . '_' . $md5)) {
                $this->output->writeln('Cannot copy the thumbnail of image ' . (int)$row['mediaId']);
                continue;
            }

            $this->execute(
                'UPDATE `media` SET `thumbnailVersion` = \'' . $md5 . '\' WHERE `mediaId` = ' . (int)$row['mediaId']
            );
        }
    }

    /**
     * Whether $path is a thumbnail of the size made now; an old one of another size was remade when viewed.
     */
    private function isThumbnailSized(string $path): bool
    {
        $size = is_file($path) ? @getimagesize($path) : false;
        return $size !== false && ($size[0] === 320 || $size[1] === 320);
    }

    private function recordVideoCovers(string $libraryLocation): void
    {
        foreach ($this->fetchAll('SELECT `mediaId` FROM `media` WHERE `type` = \'video\'') as $row) {
            $mediaId = (int)$row['mediaId'];
            $cover = $libraryLocation . $mediaId . '_videocover.png';
            $md5 = @md5_file($cover);
            if ($md5 === false) {
                continue;
            }

            // An old thumbnail is kept under the new name, but none is made, to keep the upgrade fast.
            $thumbnail = $libraryLocation . 'tn_' . $mediaId . '_videocover.png';
            if (is_file($thumbnail) && !@copy($thumbnail, $thumbnail . '_' . $md5)) {
                $this->output->writeln('Cannot copy the thumbnail of video ' . $mediaId);
            }

            $this->execute(
                'UPDATE `media` SET `thumbnailVersion` = \'' . $md5 . '\' WHERE `mediaId` = ' . $mediaId
            );
        }
    }

    public function down(): void
    {
        $this->table('media')->removeColumn('thumbnailVersion')->save();
    }
}
