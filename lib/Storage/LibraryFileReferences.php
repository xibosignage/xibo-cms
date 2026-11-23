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

namespace Xibo\Storage;

use Xibo\Entity\Media;
use Xibo\Factory\FontFactory;
use Xibo\Service\LibraryAsset;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Widget\Render\WidgetHtmlRenderer;

/**
 * Whether a database row still refers to a library file, treating keys of an unknown shape as referenced.
 */
class LibraryFileReferences
{
    public function __construct(private readonly StorageServiceInterface $store)
    {
    }

    /**
     * @throws InvalidArgumentException for an unknown asset type
     */
    public function isReferenced(string $assetType, string $key): bool
    {
        LibraryAsset::assertKnown($assetType);

        return match ($assetType) {
            LibraryAsset::MEDIA => $this->isMediaReferenced($key),
            LibraryAsset::SAVED_REPORT => $this->rowExists(
                'SELECT `savedReportId` FROM `saved_report` WHERE `fileName` = :key',
                $key
            ),
            LibraryAsset::ATTACHMENT => $this->rowExists(
                'SELECT `notificationId` FROM `notification` WHERE `filename` = :key',
                $key
            ),
            LibraryAsset::PLAYER_SOFTWARE => $this->rowExists(
                'SELECT `versionId` FROM `player_software` WHERE `fileName` = :key',
                $key
            ),
            // fonts.css is generated from the fonts table, so it is always in use.
            LibraryAsset::FONT => $key === FontFactory::FONTS_CSS_KEY
                || $this->rowExists('SELECT `id` FROM `fonts` WHERE `fileName` = :key', $key),
            LibraryAsset::SCREENSHOT => $this->idExists(
                '/^(\d+)_screenshot\.\w+$/',
                'SELECT `displayId` FROM `display` WHERE `displayId` = :id',
                $key
            ),
            LibraryAsset::DATA_CONNECTOR => $this->idExists(
                '/^dataSet_(\d+)\.js(\.md5)?$/',
                'SELECT `dataSetId` FROM `dataset` WHERE `dataSetId` = :id AND `isRealTime` = 1',
                $key
            ),
            LibraryAsset::LAYOUT_THUMBNAIL => $this->isThumbnailReferenced($key),
            // A render is made again when next needed, so only a key of an unknown shape is kept.
            LibraryAsset::WIDGET_HTML => WidgetHtmlRenderer::parseRenderKey($key) === null,
        };
    }

    /**
     * A media file is referenced by its media row, a video cover by its video while it has one, and a thumbnail by
     * the media whose version it is.
     */
    private function isMediaReferenced(string $key): bool
    {
        // tn_{source key}_{version}, referenced while its media records that version.
        if (preg_match('/^tn_(.+)_([^_]+)$/', $key, $matches)) {
            $thumbnailOf = Media::getMediaIdFromVideoCoverKey($matches[1]);
            return $thumbnailOf !== null
                ? (bool)$this->store->exists(
                    'SELECT `mediaId` FROM `media`'
                        . ' WHERE `mediaId` = :mediaId AND `thumbnailVersion` = :thumbnailVersion',
                    ['mediaId' => $thumbnailOf, 'thumbnailVersion' => $matches[2]]
                )
                : (bool)$this->store->exists(
                    'SELECT `mediaId` FROM `media`'
                        . ' WHERE `storedAs` = :storedAs AND `thumbnailVersion` = :thumbnailVersion',
                    ['storedAs' => $matches[1], 'thumbnailVersion' => $matches[2]]
                );
        }

        $coverOf = Media::getMediaIdFromVideoCoverKey($key);
        if ($coverOf !== null) {
            return (bool)$this->store->exists(
                'SELECT `mediaId` FROM `media` WHERE `mediaId` = :mediaId AND `thumbnailVersion` IS NOT NULL',
                ['mediaId' => $coverOf]
            );
        }

        return (bool)$this->store->exists('SELECT `mediaId` FROM `media` WHERE `storedAs` = :storedAs', [
            'storedAs' => $key,
        ]);
    }

    /**
     * A draft thumbnail is referenced while its campaign has a draft, and a published one while its campaign exists.
     */
    private function isThumbnailReferenced(string $key): bool
    {
        if (preg_match('/^(\d+)_layout_thumb\.png$/', $key, $matches)) {
            return (bool)$this->store->exists(
                'SELECT `layout`.`layoutId` FROM `layout`
                    INNER JOIN `lkcampaignlayout` ON `lkcampaignlayout`.`layoutId` = `layout`.`layoutId`
                  WHERE `lkcampaignlayout`.`campaignId` = :id AND `layout`.`parentId` IS NOT NULL',
                ['id' => (int)$matches[1]]
            );
        }

        if (preg_match('/^(\d+)_campaign_thumb\.png$/', $key, $matches)) {
            return (bool)$this->store->exists(
                'SELECT `campaignId` FROM `campaign` WHERE `campaignId` = :id',
                ['id' => (int)$matches[1]]
            );
        }

        // A key of an unknown shape counts as referenced, so it is never deleted.
        return true;
    }

    private function rowExists(string $sql, string $key): bool
    {
        return (bool)$this->store->exists($sql, ['key' => $key]);
    }

    /**
     * Whether the row whose id is in $key exists, treating a key without one as referenced.
     */
    private function idExists(string $pattern, string $sql, string $key): bool
    {
        if (!preg_match($pattern, $key, $matches)) {
            return true;
        }

        return (bool)$this->store->exists($sql, ['id' => (int)$matches[1]]);
    }
}
