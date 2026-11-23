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

namespace Xibo\Service;

use Xibo\Support\Exception\InvalidArgumentException;

/**
 * The kinds of file kept in the library, each stored by its own LibraryStorageServiceInterface.
 */
final class LibraryAsset
{
    public const MEDIA = 'media';
    public const SAVED_REPORT = 'savedreport';
    public const ATTACHMENT = 'attachment';
    public const SCREENSHOT = 'screenshots';
    public const PLAYER_SOFTWARE = 'playersoftware';
    public const FONT = 'fonts';
    public const DATA_CONNECTOR = 'data_connectors';
    public const LAYOUT_THUMBNAIL = 'thumbs';
    public const WIDGET_HTML = 'widget';

    /** Each asset's folder under LIBRARY_LOCATION, with media at the root for X-Sendfile. */
    private const LOCAL_FOLDERS = [
        self::MEDIA => '',
        self::SAVED_REPORT => 'savedreport/',
        self::ATTACHMENT => 'attachment/',
        self::SCREENSHOT => 'screenshots/',
        self::PLAYER_SOFTWARE => 'playersoftware/',
        self::FONT => 'fonts/',
        self::DATA_CONNECTOR => 'data_connectors/',
        self::LAYOUT_THUMBNAIL => 'thumbs/',
        self::WIDGET_HTML => 'widget/',
    ];

    /**
     * @return string[] every asset type
     */
    public static function all(): array
    {
        return array_keys(self::LOCAL_FOLDERS);
    }

    /**
     * @throws InvalidArgumentException for an unknown asset type
     */
    public static function assertKnown(string $assetType): void
    {
        if (!array_key_exists($assetType, self::LOCAL_FOLDERS)) {
            throw new InvalidArgumentException('Unknown library asset type: ' . $assetType, 'assetType');
        }
    }

    /**
     * The asset's folder under LIBRARY_LOCATION, '' or with a trailing slash.
     * @throws InvalidArgumentException for an unknown asset type
     */
    public static function getLocalFolder(string $assetType): string
    {
        self::assertKnown($assetType);

        return self::LOCAL_FOLDERS[$assetType];
    }
}
