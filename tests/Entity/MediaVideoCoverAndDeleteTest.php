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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Xibo\Entity\Media;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Video cover flag and file delete behaviour against mocked storage.

 */
class MediaVideoCoverAndDeleteTest extends TestCase
{
    private string $libraryLocation;

    protected function setUp(): void
    {
        $this->libraryLocation = sys_get_temp_dir() . '/xibo-media-videocover-test-' . uniqid() . '/';
        mkdir($this->libraryLocation, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemove($this->libraryLocation);
    }

    private function recursiveRemove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->recursiveRemove($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function newMedia(
        StorageServiceInterface $store,
        LibraryStorageServiceInterface $libraryStorageService,
        ?MediaFactory $mediaFactory = null
    ): Media {
        $libraryLocation = $this->libraryLocation;
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(function ($setting) use ($libraryLocation) {
            return match ($setting) {
                'LIBRARY_LOCATION' => $libraryLocation,
                default => null,
            };
        });

        return new Media(
            $store,
            $this->createMock(LogServiceInterface::class),
            null,
            $config,
            $mediaFactory ?? $this->createMock(MediaFactory::class),
            $this->createMock(PermissionFactory::class),
            $libraryStorageService
        );
    }

    /**
     * Journal records get distinct ids, as the real table does.
     */
    private function withJournalIds(StorageServiceInterface&MockObject $store): void
    {
        $id = 0;
        $store->method('insert')->willReturnCallback(function () use (&$id) {
            return ++$id;
        });
    }

    /**
     * Only storing a file or cover sets the thumbnail version, so a clone never claims a cover it lacks.
     */
    public function testAddAndEditNeverWriteTheThumbnailVersion()
    {
        $sql = [];
        $store = $this->createMock(StorageServiceInterface::class);
        // No open transaction here, so afterCommit() work runs straight away, as the real store does.
        $store->method('afterCommit')->willReturnCallback(fn (callable $callback) => $callback());
        $store->method('insert')->willReturnCallback(function ($query) use (&$sql) {
            $sql[] = $query;
            return 43;
        });
        $store->method('update')->willReturnCallback(function ($query) use (&$sql) {
            $sql[] = $query;
        });

        $media = $this->newMedia($store, $this->createMock(LibraryStorageServiceInterface::class));
        $media->mediaId = 42;
        $media->mediaType = 'video';
        $media->fileName = '42.mp4';
        $media->storedAs = '42.mp4';
        $media->thumbnailVersion = 'v1';

        $clone = clone $media;
        (new \ReflectionMethod($clone, 'add'))->invoke($clone);
        (new \ReflectionMethod($media, 'edit'))->invoke($media);

        $this->assertNotEmpty($sql);
        foreach ($sql as $query) {
            $this->assertStringNotContainsStringIgnoringCase('thumbnailVersion', $query);
        }
    }

    public function testDeleteFileRemovesCachedThumbnailsLocallyNotThroughStorageInterface()
    {
        // Pre-create cached derivative thumbnails directly on disk (versioned, and the unversioned earlier name)
        file_put_contents($this->libraryLocation . 'tn_20.jpg', 'cached thumbnail bytes');
        file_put_contents($this->libraryLocation . 'tn_20.jpg_abc', 'cached thumbnail bytes');
        file_put_contents($this->libraryLocation . 'tn_20_videocover.png_1000', 'cached cover thumbnail bytes');

        $libraryStorageService = $this->createMock(LibraryStorageServiceInterface::class);
        $deletedKeys = [];
        $libraryStorageService->method('delete')->willReturnCallback(function ($key) use (&$deletedKeys) {
            $deletedKeys[] = $key;
        });

        $store = $this->createMock(StorageServiceInterface::class);

        // No open transaction here, so afterCommit() work runs straight away, as the real store does.

        $store->method('afterCommit')->willReturnCallback(fn (callable $callback) => $callback());

        $this->withJournalIds($store);
        $media = $this->newMedia($store, $libraryStorageService);
        $media->mediaId = 20;
        $media->storedAs = '20.jpg';
        $media->mediaType = 'video';
        $media->name = 'test video';

        $media->delete(['rollback' => true]);

        // Only the two source keys go through storage, never the thumbnails.
        sort($deletedKeys);
        $this->assertSame(['20.jpg', '20_videocover.png'], $deletedKeys);

        // The cached derivative thumbnails are gone from local disk directly.
        $this->assertFileDoesNotExist($this->libraryLocation . 'tn_20.jpg');
        $this->assertFileDoesNotExist($this->libraryLocation . 'tn_20.jpg_abc');
        $this->assertFileDoesNotExist($this->libraryLocation . 'tn_20_videocover.png_1000');
    }

    /**
     * The row is already gone by the time the files are deleted, so there's no thumbnail version to clear.
     */
    public function testDeleteFileNeverWritesTheThumbnailVersion()
    {
        $store = $this->createMock(StorageServiceInterface::class);
        // No open transaction here, so afterCommit() work runs straight away, as the real store does.
        $store->method('afterCommit')->willReturnCallback(fn (callable $callback) => $callback());
        $updateCalls = [];
        $store->method('update')->willReturnCallback(function ($sql, $params) use (&$updateCalls) {
            $updateCalls[] = $sql;
            return 1;
        });

        $this->withJournalIds($store);
        $media = $this->newMedia($store, $this->createMock(LibraryStorageServiceInterface::class));
        $media->mediaId = 22;
        $media->storedAs = '22.mp4';
        $media->mediaType = 'video';
        $media->name = 'test video';

        $media->delete(['rollback' => true]);

        $thumbnailCalls = array_filter($updateCalls, fn ($sql) => str_contains($sql, 'thumbnailVersion'));
        $this->assertCount(0, $thumbnailCalls);
    }

    /**
     * A file waiting to be copied may already be in storage, uploaded but not yet recorded, so it goes there too.
     */
    public function testDeletingAFileWaitingForTheMoveDeletesItFromStorageToo()
    {
        $deleted = ['local' => [], 'cloud' => []];
        $local = $this->createMock(LibraryStorageServiceInterface::class);
        $local->method('delete')->willReturnCallback(function ($key) use (&$deleted) {
            $deleted['local'][] = $key;
        });
        $cloud = $this->createMock(LibraryStorageServiceInterface::class);
        $cloud->method('delete')->willReturnCallback(function ($key) use (&$deleted) {
            $deleted['cloud'][] = $key;
        });
        $mediaFactory = $this->createMock(MediaFactory::class);
        $mediaFactory->method('getStorageFor')->willReturn($local);

        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('afterCommit')->willReturnCallback(fn (callable $callback) => $callback());
        $this->withJournalIds($store);
        $media = $this->newMedia($store, $cloud, $mediaFactory);
        $media->mediaId = 25;
        $media->storedAs = '25.jpg';
        $media->mediaType = 'image';
        $media->name = 'test image';
        $media->storageEngine = 'local';

        $media->delete(['rollback' => true]);

        $this->assertSame(['25.jpg'], $deleted['local']);
        $this->assertSame(['25.jpg'], $deleted['cloud']);
    }

    public function testDeleteFileKeepsAFileAnotherRowNowUses()
    {
        $libraryStorageService = $this->createMock(LibraryStorageServiceInterface::class);
        $deletedKeys = [];
        $libraryStorageService->method('delete')->willReturnCallback(function ($key) use (&$deletedKeys) {
            $deletedKeys[] = $key;
        });

        $store = $this->createMock(StorageServiceInterface::class);
        // No open transaction here, so afterCommit() work runs straight away, as the real store does.
        $store->method('afterCommit')->willReturnCallback(fn (callable $callback) => $callback());
        $store->method('exists')->willReturnCallback(
            fn ($sql, $params) => str_contains($sql, 'storedAs') && $params['storedAs'] === 'feed-image.png'
        );

        $this->withJournalIds($store);
        $media = $this->newMedia($store, $libraryStorageService);
        $media->mediaId = 23;
        $media->storedAs = 'feed-image.png';
        $media->mediaType = 'module';
        $media->name = 'feed-image.png';

        $media->delete(['rollback' => true]);

        // The module file is used by another row, and a module file has no cover.
        $this->assertSame([], $deletedKeys);
    }

    /**
     * The deletes are journalled in the deleting transaction, so one that fails is retried by Library Tidy rather
     * than leaving the file behind.
     */
    public function testDeletesAreJournalledAndAFailureIsKeptForRetry()
    {
        $libraryStorageService = $this->createMock(LibraryStorageServiceInterface::class);
        $libraryStorageService->method('delete')->willReturnCallback(function ($key) {
            if ($key === '24.jpg') {
                throw new \Xibo\Support\Exception\GeneralException('Problem deleting object: 24.jpg');
            }
        });

        $inserts = [];
        $updates = [];
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('afterCommit')->willReturnCallback(fn (callable $callback) => $callback());
        $store->method('insert')->willReturnCallback(function ($sql, $params) use (&$inserts) {
            $inserts[] = $params;
            return count($inserts);
        });
        $store->method('update')->willReturnCallback(function ($sql, $params) use (&$updates) {
            $updates[] = [$sql, $params];
            return 1;
        });

        $media = $this->newMedia($store, $libraryStorageService);
        $media->mediaId = 24;
        $media->storedAs = '24.jpg';
        $media->mediaType = 'image';
        $media->name = 'test image';

        $media->delete(['rollback' => true]);

        // Only a video has a cover to delete.
        $this->assertSame(['24.jpg'], array_column($inserts, 'key'));
        $this->assertSame(['pending_delete'], array_column($inserts, 'state'));

        // 24.jpg failed: its record is kept with the failure noted.
        $journal = array_values(array_filter($updates, fn ($u) => str_contains($u[0], 'library_file_journal')));
        $this->assertCount(1, $journal);
        $this->assertStringContainsString('`attempts` = `attempts` + 1', $journal[0][0]);
        $this->assertSame(1, $journal[0][1]['id']);
    }
}
