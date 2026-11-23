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

namespace Xibo\Tests\Storage;

use PHPUnit\Framework\TestCase;
use Xibo\Service\LibraryAsset;
use Xibo\Storage\LibraryFileReferences;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Each asset type's file is referenced by the table that owns it.
 */
class LibraryFileReferencesTest extends TestCase
{
    /** @var array<array{0: string, 1: array}> queries run: [sql, params] */
    private array $queries = [];

    /**
     * @param array<string, array[]> $rows the rows each table holds, as the parameters a lookup binds to find them
     */
    private function references(array $rows): LibraryFileReferences
    {
        $this->queries = [];
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('exists')->willReturnCallback(function (string $sql, array $params) use ($rows) {
            $this->queries[] = [$sql, $params];
            preg_match('/FROM `(\w+)`/', $sql, $matches);
            return in_array($params, $rows[$matches[1]] ?? [], true);
        });

        return new LibraryFileReferences($store);
    }

    /**
     * @dataProvider ownedKeyProvider
     */
    public function testAKeyIsReferencedOnlyWhileItsOwningRowExists(
        string $assetType,
        string $key,
        string $table,
        array $row
    ) {
        $this->assertTrue($this->references([$table => [$row]])->isReferenced($assetType, $key));
        $this->assertFalse($this->references([])->isReferenced($assetType, $key));

        $otherRow = array_map(fn ($value) => is_int($value) ? $value + 1 : 'other-' . $value, $row);
        $this->assertFalse(
            $this->references([$table => [$otherRow]])->isReferenced($assetType, $key),
            'another row does not refer to it'
        );
    }

    public static function ownedKeyProvider(): array
    {
        return [
            'media' => [LibraryAsset::MEDIA, '5.jpg', 'media', ['storedAs' => '5.jpg']],
            'video cover' => [LibraryAsset::MEDIA, '5_videocover.png', 'media', ['mediaId' => 5]],
            'image thumbnail' => [LibraryAsset::MEDIA, 'tn_5.jpg_abc', 'media', [
                'storedAs' => '5.jpg',
                'thumbnailVersion' => 'abc',
            ]],
            'cover thumbnail' => [LibraryAsset::MEDIA, 'tn_5_videocover.png_abc', 'media', [
                'mediaId' => 5,
                'thumbnailVersion' => 'abc',
            ]],
            'saved report' => [LibraryAsset::SAVED_REPORT, 'rs_1_2.json.zip', 'saved_report', [
                'key' => 'rs_1_2.json.zip',
            ]],
            'attachment' => [LibraryAsset::ATTACHMENT, '4_report.pdf', 'notification', ['key' => '4_report.pdf']],
            'player software' => [LibraryAsset::PLAYER_SOFTWARE, 'xibo-v4.apk', 'player_software', [
                'key' => 'xibo-v4.apk',
            ]],
            'font' => [LibraryAsset::FONT, 'Lato.ttf', 'fonts', ['key' => 'Lato.ttf']],
            'screenshot' => [LibraryAsset::SCREENSHOT, '12_screenshot.jpg', 'display', ['id' => 12]],
            'data connector' => [LibraryAsset::DATA_CONNECTOR, 'dataSet_3.js', 'dataset', ['id' => 3]],
            'data connector md5' => [LibraryAsset::DATA_CONNECTOR, 'dataSet_3.js.md5', 'dataset', ['id' => 3]],
            'draft thumbnail' => [LibraryAsset::LAYOUT_THUMBNAIL, '9_layout_thumb.png', 'layout', ['id' => 9]],
            'campaign thumbnail' => [LibraryAsset::LAYOUT_THUMBNAIL, '9_campaign_thumb.png', 'campaign', [
                'id' => 9,
            ]],
        ];
    }

    public function testADraftThumbnailNeedsADraftInItsCampaignAndAPublishedOneItsCampaign()
    {
        $campaignOnly = $this->references(['campaign' => [['id' => 9]]]);
        $this->assertFalse($campaignOnly->isReferenced(LibraryAsset::LAYOUT_THUMBNAIL, '9_layout_thumb.png'));
        $this->assertTrue($campaignOnly->isReferenced(LibraryAsset::LAYOUT_THUMBNAIL, '9_campaign_thumb.png'));

        $draftOnly = $this->references(['layout' => [['id' => 9]]]);
        $this->assertTrue($draftOnly->isReferenced(LibraryAsset::LAYOUT_THUMBNAIL, '9_layout_thumb.png'));
        $this->assertFalse($draftOnly->isReferenced(LibraryAsset::LAYOUT_THUMBNAIL, '9_campaign_thumb.png'));
    }

    public function testFontsCssIsAlwaysReferenced()
    {
        $this->assertTrue($this->references([])->isReferenced(LibraryAsset::FONT, 'fonts.css'));
        $this->assertSame([], $this->queries);
    }

    /**
     * @dataProvider unrecognisedKeyProvider
     */
    public function testAKeyAnAssetNeverUsesIsKept(string $assetType, string $key)
    {
        $this->assertTrue($this->references([])->isReferenced($assetType, $key));
        $this->assertSame([], $this->queries);
    }

    public static function unrecognisedKeyProvider(): array
    {
        return [
            'screenshot' => [LibraryAsset::SCREENSHOT, 'notes.txt'],
            'data connector' => [LibraryAsset::DATA_CONNECTOR, 'notes.txt'],
            'thumbnail' => [LibraryAsset::LAYOUT_THUMBNAIL, 'logo.png'],
            'unversioned widget render' => [LibraryAsset::WIDGET_HTML, '7_1.html'],
        ];
    }

    public function testAWidgetRenderIsNeverKeptForItsReferences()
    {
        // A render is made again when it is next needed.
        $this->assertFalse($this->references([])->isReferenced(LibraryAsset::WIDGET_HTML, '7/1_1700000000.html'));
        $this->assertSame([], $this->queries);
    }

    public function testUnknownAssetTypeIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->references([])->isReferenced('certs', 'private.key');
    }
}
