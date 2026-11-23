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

namespace Xibo\Tests\Service;

use PHPUnit\Framework\TestCase;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistry;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * LibraryStorageRegistry's test
 */
class LibraryStorageRegistryTest extends TestCase
{
    public function testMediaStaysAtTheLibraryRoot()
    {
        $registry = new LibraryStorageRegistry('/var/www/cms/library/');

        $this->assertSame('local:/var/www/cms/library/', $registry->get(LibraryAsset::MEDIA)->getLocation());
    }

    public function testOtherAssetsAreRootedAtTheirExistingFolders()
    {
        $registry = new LibraryStorageRegistry('/var/www/cms/library');

        $expected = [
            LibraryAsset::SAVED_REPORT => 'savedreport/',
            LibraryAsset::ATTACHMENT => 'attachment/',
            LibraryAsset::SCREENSHOT => 'screenshots/',
            LibraryAsset::PLAYER_SOFTWARE => 'playersoftware/',
            LibraryAsset::FONT => 'fonts/',
            LibraryAsset::DATA_CONNECTOR => 'data_connectors/',
            LibraryAsset::LAYOUT_THUMBNAIL => 'thumbs/',
            LibraryAsset::WIDGET_HTML => 'widget/',
        ];

        foreach ($expected as $assetType => $folder) {
            $this->assertSame(
                'local:/var/www/cms/library/' . $folder,
                $registry->get($assetType)->getLocation(),
                $assetType
            );
        }

        $this->assertEqualsCanonicalizing(
            array_merge([LibraryAsset::MEDIA], array_keys($expected)),
            LibraryAsset::all()
        );
    }

    public function testEachAssetIsBuiltOnce()
    {
        $registry = new LibraryStorageRegistry('/var/www/cms/library/');

        $this->assertSame($registry->get(LibraryAsset::FONT), $registry->get(LibraryAsset::FONT));
    }

    public function testUnknownAssetTypeIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        (new LibraryStorageRegistry('/var/www/cms/library/'))->get('certs');
    }
}
