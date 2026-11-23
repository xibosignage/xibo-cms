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

namespace Xibo\Tests\Entity;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Xibo\Entity\Font;
use Xibo\Factory\FontFactory;
use Xibo\Service\BaseDependenciesService;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Font files are journalled when stored, and deleted from font storage after their row.
 */
class FontStorageTest extends TestCase
{
    /** @var string[] */
    private array $events = [];

    private function recordingStore(): StorageServiceInterface
    {
        $store = $this->createMock(StorageServiceInterface::class);
        // No open transaction here, so afterCommit() work runs straight away, as the real store does.
        $store->method('afterCommit')->willReturnCallback(fn (callable $callback) => $callback());
        $store->method('update')->willReturnCallback(function ($sql) {
            $this->events[] = str_contains($sql, 'library_file_journal') ? 'journal:cleared' : 'sql';
        });
        $store->method('insert')->willReturnCallback(function ($sql, $params) {
            $this->events[] = 'journal:' . $params['state'] . ':' . $params['assetType'] . ':' . $params['key'];
            return 1;
        });
        return $store;
    }

    private function recordingStorage(bool $fails = false): LibraryStorageServiceInterface
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('moveFromLocalPath')->willReturnCallback(function (string $path, string $key) use ($fails) {
            $this->events[] = 'move:' . $key;
            if ($fails) {
                throw new GeneralException('outage');
            }
        });
        $storage->method('delete')->willReturnCallback(function (string $key) use ($fails) {
            $this->events[] = 'delete:' . $key;
            if ($fails) {
                throw new GeneralException('outage');
            }
        });
        return $storage;
    }

    private function fontFactory(StorageServiceInterface $store, LibraryStorageServiceInterface $storage): FontFactory
    {
        $registry = $this->createMock(LibraryStorageRegistryInterface::class);
        $registry->method('get')->with(LibraryAsset::FONT)->willReturn($storage);

        $dependencies = $this->createMock(BaseDependenciesService::class);
        $dependencies->method('getStore')->willReturn($store);
        $dependencies->method('getLogger')->willReturn($this->createMock(LogServiceInterface::class));

        $factory = new FontFactory($registry);
        $factory->useBaseDependenciesService($dependencies);
        return $factory;
    }

    public function testStoringAFontIsJournalledUntilCommit()
    {
        $this->fontFactory($this->recordingStore(), $this->recordingStorage())->storeFontFile('/tmp/x', 'Lato.ttf');

        $this->assertSame(
            ['journal:pending_upload:fonts:Lato.ttf', 'move:Lato.ttf', 'journal:cleared'],
            $this->events
        );
    }

    public function testAFailedStoreLeavesTheJournalRecordForTheTidy()
    {
        $factory = $this->fontFactory($this->recordingStore(), $this->recordingStorage(true));

        try {
            $factory->storeFontFile('/tmp/x', 'Lato.ttf');
            $this->fail('the storage error must reach the caller');
        } catch (GeneralException) {
            // Expected.
        }

        $this->assertSame(['journal:pending_upload:fonts:Lato.ttf', 'move:Lato.ttf'], $this->events);
    }

    public function testAFileNameContainingTwoDotsIsRefusedBeforeTheFontIsRead()
    {
        $factory = $this->fontFactory($this->recordingStore(), $this->recordingStorage());

        // The path doesn't exist, so reading the font first would fail with a different error.
        try {
            $factory->createFontFromUpload('/nonexistent/Roboto..Bold.ttf', '', 'Roboto..Bold.ttf', 'admin');
            $this->fail('a file name containing \'..\' must be refused');
        } catch (InvalidArgumentException $exception) {
            $body = json_decode((string)$exception->generateHttpResponse(new Response())->getBody(), true);
            $this->assertSame('fileName', $body['property']);
        }
    }

    /**
     * @dataProvider storageOutageProvider
     */
    public function testDeleteRemovesTheFileAfterTheRow(bool $storageFails)
    {
        $store = $this->recordingStore();
        $font = new Font(
            $store,
            $this->createMock(LogServiceInterface::class),
            null,
            $this->fontFactory($store, $this->recordingStorage($storageFails))
        );
        $font->id = 4;
        $font->fileName = 'Lato.ttf';

        $font->delete();

        $this->assertSame(
            ['sql', 'journal:pending_delete:fonts:Lato.ttf', 'delete:Lato.ttf'],
            array_slice($this->events, 0, 3)
        );
    }

    public static function storageOutageProvider(): array
    {
        return ['storage available' => [false], 'storage outage' => [true]];
    }
}
