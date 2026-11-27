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

namespace Xibo\Tests\Helper;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Xibo\Entity\Display;
use Xibo\Helper\LinkSigner;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Storage\DownloadUrlUnavailableException;
use Xibo\Xmds\PlayerFileLink;

class PlayerFileLinkTest extends TestCase
{
    /** @var array<string, LibraryStorageServiceInterface&MockObject> */
    private array $storages = [];

    private function display(string $clientType = 'android', int $clientCode = 400): Display
    {
        $display = $this->createMock(Display::class);
        $display->method('getSetting')->willReturnCallback(
            fn ($key, $default = null) => $key === 'collectInterval' ? 300 : $default
        );
        $display->method('isPwa')->willReturn($clientType === 'chromeOS');
        $display->displayId = 7;
        $display->clientType = $clientType;
        $display->clientCode = $clientCode;
        return $display;
    }

    private function link(
        bool $remote,
        string $cdnUrl = '',
        bool $hasFilesToMove = false,
        ?string $baseUrl = null
    ): PlayerFileLink {
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getApiKeyDetails')->willReturn(['encryptionKey' => 'secret']);
        $config->method('getSetting')->willReturnCallback(fn ($key) => $key === 'CDN_URL' ? $cdnUrl : null);

        foreach ([
            LibraryAsset::MEDIA,
            LibraryAsset::FONT,
            LibraryAsset::PLAYER_SOFTWARE,
            LibraryAsset::DATA_CONNECTOR,
        ] as $asset) {
            $storage = $this->createMock(LibraryStorageServiceInterface::class);
            $storage->method('hasLocalFiles')->willReturn(!$remote);
            $storage->method('getDirectDownloadUrl')->willReturnCallback(
                fn ($key, $expiresAt) => $remote
                    ? 'https://storage.example.com/' . $asset . '/' . $key . '?e=' . $expiresAt
                    : null
            );
            $this->storages[$asset] = $storage;
        }

        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        // A file waiting to be copied is in the local library, which has no direct URL.
        $local = $this->createMock(LibraryStorageServiceInterface::class);
        $local->method('hasLocalFiles')->willReturn(true);
        $local->method('getDirectDownloadUrl')->willReturn(null);
        $registry->method('get')->willReturnCallback(
            fn ($asset, $storageEngine = null) => $storageEngine === 'local' ? $local : $this->storages[$asset]
        );
        $registry->method('hasFilesToMove')->willReturn($hasFilesToMove);

        return new PlayerFileLink($config, $registry, $baseUrl);
    }

    public function testTheLocalBackendLinksToTheCms()
    {
        [$url, $isDirect] = $this->link(false)->get($this->display(), 'M', 5, '5.mp4');

        $this->assertStringContainsString('/xmds.php?file=5.mp4&displayId=7&type=M&itemId=5', $url);
        $this->assertFalse($isDirect);
    }

    public function testLinksToTheCmsAreOnTheBaseUrl()
    {
        [$url, $isDirect] = $this->link(false, '', false, 'https://acme.displays.cloud')
            ->get($this->display(), 'M', 5, '5.mp4');

        $this->assertStringStartsWith('https://acme.displays.cloud/xmds.php?file=5.mp4&displayId=7', $url);
        $this->assertFalse($isDirect);
    }

    public function testALinuxPlayerBeforeV400KeepsItsOwnAddress()
    {
        [$url] = $this->link(true, '', false, 'https://acme.displays.cloud')
            ->get($this->display('linux', 399), 'M', 5, '5.mp4');

        $this->assertStringContainsString('/xmds.php?file=5.mp4', $url);
        $this->assertStringNotContainsString('acme.displays.cloud', $url);
    }

    public function testALinuxPlayerFromV400GetsLinksOnTheBaseUrl()
    {
        [$url] = $this->link(false, '', false, 'https://acme.displays.cloud')
            ->get($this->display('linux', 400), 'M', 5, '5.mp4');

        $this->assertStringStartsWith('https://acme.displays.cloud/xmds.php?file=5.mp4', $url);
    }

    public function testAPwaKeepsItsOwnAddress()
    {
        $link = $this->link(false, '', false, 'https://acme.displays.cloud');

        [$url] = $link->get($this->display('chromeOS'), 'M', 5, '5.mp4', null, true);
        $this->assertStringNotContainsString('acme.displays.cloud', $url);

        [$url] = $link->get($this->display(), 'P', 3, 'font.ttf', 'font', true);
        $this->assertStringNotContainsString('acme.displays.cloud', $url);
    }

    public function testDirectLinksIgnoreTheBaseUrl()
    {
        [$url, $isDirect] = $this->link(true, '', false, 'https://acme.displays.cloud')
            ->get($this->display(), 'M', 5, '5.mp4', null, false, 's3');

        $this->assertStringStartsWith('https://storage.example.com/', $url);
        $this->assertTrue($isDirect);
    }

    public function testTheCdnFetchesFromTheBaseUrl()
    {
        [$url] = $this->link(false, 'cdn.example.com/?dl=', false, 'https://acme.displays.cloud')
            ->get($this->display(), 'M', 5, '5.mp4');

        $this->assertMatchesRegularExpression('#^https?://cdn\.example\.com/\?dl=#', $url);
        $this->assertStringContainsString(urlencode('https://acme.displays.cloud/xmds.php?file=5.mp4'), $url);
    }

    public function testMediaWaitingForALibraryMoveLinksToTheCms()
    {
        [$url, $isDirect] = $this->link(true)->get($this->display(), 'M', 5, '5.mp4', null, false, 'local');

        $this->assertStringContainsString('/xmds.php?file=5.mp4&displayId=7&type=M&itemId=5', $url);
        $this->assertFalse($isDirect);
    }

    public function testAMovedFileIsLinkedDirectly()
    {
        [, $isDirect] = $this->link(true)->get($this->display(), 'M', 5, '5.mp4', null, false, 's3');

        $this->assertTrue($isDirect);
    }

    public function testTheLocalBackendWrapsLinksInTheCdn()
    {
        [$url, $isDirect] = $this->link(false, 'cdn.example.com/?dl=')->get($this->display(), 'M', 5, '5.mp4');

        $this->assertMatchesRegularExpression('#^https?://cdn\.example\.com/\?dl=#', $url);
        $this->assertFalse($isDirect);
    }

    /**
     * @dataProvider storedFileProvider
     */
    public function testRemoteStorageLinksStoredFilesDirectly(
        string $type,
        string $saveAs,
        ?string $fileType,
        string $asset
    ) {
        [$url, $isDirect] = $this->link(true, 'cdn.example.com/?dl=')
            ->get($this->display(), $type, 1, $saveAs, $fileType);

        $this->assertSame(
            'https://storage.example.com/' . $asset . '/' . $saveAs . '?e=' . LinkSigner::getExpiry($this->display()),
            $url
        );
        $this->assertTrue($isDirect);
    }

    public static function storedFileProvider(): array
    {
        return [
            'media' => ['M', '5.mp4', null, LibraryAsset::MEDIA],
            'layout' => ['L', '12.xlf', null, LibraryAsset::MEDIA],
            'font' => ['P', 'Railway.ttf', 'font', LibraryAsset::FONT],
            'font css' => ['P', 'fonts.css', 'fontCss', LibraryAsset::FONT],
            'player software' => ['P', 'Xibo_v4.apk', 'playersoftware', LibraryAsset::PLAYER_SOFTWARE],
            'data connector' => ['P', '3.js', 'data_connector', LibraryAsset::DATA_CONNECTOR],
        ];
    }

    public function testRemoteStorageLinksFilesItDoesNotHoldToTheCmsWithoutTheCdn()
    {
        foreach (['bundle' => 'bundle.min.js', 'asset' => 'clock.js'] as $fileType => $saveAs) {
            [$url, $isDirect] = $this->link(true, 'cdn.example.com/?dl=')
                ->get($this->display(), 'P', 1, $saveAs, $fileType);

            $this->assertStringContainsString('/xmds.php?file=' . $saveAs, $url);
            $this->assertStringNotContainsString('cdn.example.com', $url);
            $this->assertFalse($isDirect, $fileType);
        }
    }

    /**
     * @dataProvider storedFileProvider
     */
    public function testAPwaGetsEveryStoredFileFromTheCms(string $type, string $saveAs, ?string $fileType)
    {
        [$url, $isDirect] = $this->link(true, 'cdn.example.com/?dl=')
            ->get($this->display('chromeOS'), $type, 1, $saveAs, $fileType, true);

        $this->assertStringContainsString('/xmds.php?file=' . $saveAs . '&displayId=7&type=' . $type, $url);
        $this->assertStringContainsString('X-Amz-Signature=', $url);
        $this->assertStringNotContainsString('cdn.example.com', $url);
        $this->assertFalse($isDirect);
    }

    /**
     * @dataProvider storedFileProvider
     */
    public function testALinuxPlayerBeforeV400GetsEveryStoredFileFromTheCms(
        string $type,
        string $saveAs,
        ?string $fileType
    ) {
        [$url, $isDirect] = $this->link(true)->get($this->display('linux', 399), $type, 1, $saveAs, $fileType);

        $this->assertStringContainsString('/xmds.php?file=' . $saveAs, $url);
        $this->assertFalse($isDirect);

        [, $isDirect] = $this->link(true)->get($this->display('linux', 400), $type, 1, $saveAs, $fileType);
        $this->assertTrue($isDirect, 'v400 and later are linked directly');
    }

    /**
     * @dataProvider storedFileProvider
     */
    public function testATizenOrWebOsPlayerBeforeV420GetsEveryStoredFileFromTheCms(
        string $type,
        string $saveAs,
        ?string $fileType
    ) {
        foreach (['sssp', 'lg'] as $clientType) {
            [$url, $isDirect] = $this->link(true, '', false, 'https://acme.displays.cloud')
                ->get($this->display($clientType, 419), $type, 1, $saveAs, $fileType);

            $this->assertStringContainsString('/xmds.php?file=' . $saveAs, $url, $clientType);
            $this->assertStringNotContainsString('acme.displays.cloud', $url, $clientType);
            $this->assertFalse($isDirect, $clientType);

            [$url, $isDirect] = $this->link(true, '', false, 'https://acme.displays.cloud')
                ->get($this->display($clientType, 420), $type, 1, $saveAs, $fileType);

            $this->assertStringStartsWith('https://storage.example.com/', $url, $clientType);
            $this->assertTrue($isDirect, $clientType . ' v420 and later are linked directly');
        }
    }

    public function testMediaWaitingToBeCopiedIsLinkedThroughTheCdn()
    {
        $link = $this->link(true, 'cdn.example.com/?dl=', true);

        [$url, $isDirect] = $link->get($this->display(), 'M', 5, '5.mp4', null, false, 'local');
        $this->assertMatchesRegularExpression('#^https?://cdn\.example\.com/\?dl=#', $url);
        $this->assertFalse($isDirect);

        // A PWA gets a CMS link for a file already copied, which the CDN can't serve from the CMS's disk.
        [$url] = $link->get($this->display('chromeOS'), 'M', 6, '6.mp4', null, true, 's3');
        $this->assertStringNotContainsString('cdn.example.com', $url);
    }

    public function testASigningFailureIsThrown()
    {
        $link = $this->link(true);
        $this->storages[LibraryAsset::MEDIA] = $this->createMock(LibraryStorageServiceInterface::class);
        $this->storages[LibraryAsset::MEDIA]->method('getDirectDownloadUrl')
            ->willThrowException(new DownloadUrlUnavailableException('no'));

        $this->expectException(DownloadUrlUnavailableException::class);
        $link->get($this->display(), 'M', 5, '5.mp4');
    }

    public function testTheFingerprintChangesWithTheLinkMode()
    {
        $fingerprints = [
            $this->link(false)->getFingerprint(true),
            $this->link(false)->getFingerprint(false),
            $this->link(false, 'cdn.example.com/?dl=')->getFingerprint(true),
            $this->link(true)->getFingerprint(true),
        ];

        $this->assertSame($fingerprints, array_unique($fingerprints));
        $this->assertSame($this->link(false)->getFingerprint(true), $this->link(false)->getFingerprint(true));
        $this->assertSame(
            $this->link(true)->getFingerprint(true),
            $this->link(true, 'cdn.example.com/?dl=')->getFingerprint(true),
            'CDN_URL is ignored without local files'
        );
        $this->assertNotSame(
            $this->link(true)->getFingerprint(true),
            $this->link(true, 'cdn.example.com/?dl=', true)->getFingerprint(true),
            'but not while files wait to be copied'
        );
        $this->assertNotSame(
            $this->link(true)->getFingerprint(true, $this->display('linux', 399)),
            $this->link(true)->getFingerprint(true, $this->display('linux', 400)),
            'a player that upgrades past the CMS-only versions gets its links made again'
        );
        $this->assertSame(
            $this->link(true)->getFingerprint(true, $this->display('chromeOS')),
            $this->link(true)->getFingerprint(true, $this->display('linux', 399)),
        );
    }
}
