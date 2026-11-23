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

use PHPUnit\Framework\TestCase;
use Xibo\Entity\Media;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Tests the decision logic in Media::saveFile()
 */
class MediaSaveFileTest extends TestCase
{
    private string $libraryLocation;

    protected function setUp(): void
    {
        $this->libraryLocation = sys_get_temp_dir() . '/xibo-media-test-' . uniqid() . '/';
        mkdir($this->libraryLocation . 'temp', 0777, true);
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

    /** @var array<array{0: string, 1: array, 2: string}> journal statements: [sql, params, connection] */
    private array $journal = [];

    private function newMedia(LibraryStorageServiceInterface $libraryStorageService, bool $isNew = false): Media
    {
        // A store with no open transaction, so afterCommit() work runs at once.
        $this->journal = [];
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('afterCommit')->willReturnCallback(fn (callable $callback) => $callback());
        $store->method('insert')->willReturnCallback(function ($sql, $params, $connection = 'default') {
            $this->journal[] = [$sql, $params, $connection];
            return count($this->journal);
        });
        $store->method('update')->willReturnCallback(function ($sql, $params, $connection = 'default') {
            if (str_contains($sql, 'library_file_journal')) {
                $this->journal[] = [$sql, $params, $connection];
            }
            return 1;
        });

        $libraryLocation = $this->libraryLocation;
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(function ($setting) use ($libraryLocation) {
            return match ($setting) {
                'LIBRARY_LOCATION' => $libraryLocation,
                'DEFAULT_RESIZE_THRESHOLD', 'DEFAULT_RESIZE_LIMIT' => 0,
                default => null,
            };
        });

        $media = new Media(
            $store,
            $this->createMock(LogServiceInterface::class),
            null,
            $config,
            $this->createMock(MediaFactory::class),
            $this->createMock(PermissionFactory::class),
            $libraryStorageService
        );

        // Set by save() when it adds the media row.
        (new \ReflectionProperty(Media::class, 'isNewMedia'))->setValue($media, $isNew);

        return $media;
    }

    private function newUpload(LibraryStorageServiceInterface $storage): Media
    {
        file_put_contents($this->libraryLocation . 'temp/upload.jpg', 'fresh upload bytes');

        $media = $this->newMedia($storage, true);
        $media->mediaId = 5;
        $media->fileName = 'upload.jpg';
        $media->mediaType = 'image';
        $media->cloned = false;
        $media->alwaysCopy = false;
        $media->isRemote = false;

        return $media;
    }

    /**
     * Re-saving an existing module file (widget sync re-queueing a remote image) decides from library storage
     * whether it must be downloaded again.
     */
    private function saveExistingModuleFile(LibraryStorageServiceInterface $storage): Media
    {
        $media = $this->newMedia($storage);
        $media->mediaId = 9;
        $media->mediaType = 'module';
        $media->name = 'remote_image.png';
        $media->storedAs = 'remote_image.png';
        $media->fileSize = 100;
        $media->valid = 1;
        $media->isSaveRequired = false;

        $media->save(['deferred' => true, 'validate' => false, 'saveTags' => false, 'audit' => false]);

        return $media;
    }

    public function testExistingModuleFileWithTheRecordedSizeIsNotSavedAgain()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->never())->method('exists');
        $storage->method('size')->with('remote_image.png')->willReturn(100);

        $this->assertFalse($this->saveExistingModuleFile($storage)->isSaveRequired);
    }

    public function testMissingOrResizedModuleFileIsSavedAgain()
    {
        $missing = $this->createMock(LibraryStorageServiceInterface::class);
        $missing->method('size')->willThrowException(new NotFoundException());
        $this->assertTrue($this->saveExistingModuleFile($missing)->isSaveRequired);

        $resized = $this->createMock(LibraryStorageServiceInterface::class);
        $resized->method('size')->willReturn(42);
        $this->assertTrue($this->saveExistingModuleFile($resized)->isSaveRequired);
    }

    public function testStorageErrorKeepsTheStoredModuleFile()
    {
        // Re-downloading during an outage would fail, and a failed download deletes the media.
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('size')->willThrowException(new GeneralException('outage'));

        $this->assertFalse($this->saveExistingModuleFile($storage)->isSaveRequired);
    }

    public function testFreshUploadFinalizesFromTempAndComputesMetadataFromLocalSource()
    {
        $contents = 'fresh upload bytes';
        file_put_contents($this->libraryLocation . 'temp/upload.jpg', $contents);

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->once())
            ->method('moveFromLocalPath')
            ->with($this->libraryLocation . 'temp' . DIRECTORY_SEPARATOR . 'upload.jpg', '5.jpg');
        $storage->expects($this->never())->method('copy');

        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->fileName = 'upload.jpg';
        $media->mediaType = 'image';
        $media->cloned = false;
        $media->alwaysCopy = false;
        $media->isRemote = false;

        $media->saveFile();

        $this->assertSame('5.jpg', $media->storedAs);
        $this->assertSame(md5($contents), $media->md5);
        $this->assertSame(strlen($contents), $media->fileSize);
        $this->assertSame(1, $media->valid);
    }

    public function testClonedMediaCopiesWithinBackendAndDoesNotRecomputeMetadata()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->once())
            ->method('copy')
            ->with('10.jpg', '99.jpg');
        $storage->expects($this->never())->method('moveFromLocalPath');

        $media = $this->newMedia($storage);
        $media->mediaId = 99;
        $media->fileName = '10.jpg'; // set by __clone() to the source's storedAs
        $media->mediaType = 'image';
        $media->cloned = true;
        $media->alwaysCopy = false;
        $media->isRemote = false;
        // Simulate what PHP's native clone would already have copied from the source object.
        $media->md5 = 'source-md5-unchanged';
        $media->fileSize = 123456;
        $media->width = 800;
        $media->height = 600;
        $media->orientation = 'landscape';

        $media->saveFile();

        $this->assertSame('99.jpg', $media->storedAs);
        $this->assertSame('source-md5-unchanged', $media->md5, 'md5 must not be recomputed for a clone');
        $this->assertSame(123456, $media->fileSize, 'fileSize must not be recomputed for a clone');
        $this->assertSame(800, $media->width);
        $this->assertSame(600, $media->height);
    }

    public function testPreDefinedStoredAsRemoteCaseMovesTheDownloadedTempFile()
    {
        $contents = 'downloaded bytes';
        file_put_contents($this->libraryLocation . 'temp/remote-name.png', $contents);

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->once())
            ->method('moveFromLocalPath')
            ->with($this->libraryLocation . 'temp' . DIRECTORY_SEPARATOR . 'remote-name.png', 'chosen-name.png');

        $media = $this->newMedia($storage);
        $media->mediaId = 7;
        $media->fileName = 'irrelevant.png';
        $media->name = 'remote-name.png';
        $media->storedAs = 'chosen-name.png';
        $media->mediaType = 'image';
        $media->isRemote = true;

        $media->saveFile();

        $this->assertSame('chosen-name.png', $media->storedAs);
        $this->assertSame(md5($contents), $media->md5);
    }

    public function testPreDefinedStoredAsNonRemoteCaseFinalizesACopyAndKeepsTheSource()
    {
        $contents = 'arbitrary local file bytes';
        $arbitraryPath = $this->libraryLocation . 'temp/not-under-temp-conceptually.dat';
        file_put_contents($arbitraryPath, $contents);

        $finalizedPath = null;
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->once())
            ->method('moveFromLocalPath')
            ->with($this->anything(), 'replacement.dat')
            ->willReturnCallback(function ($path) use (&$finalizedPath, $contents) {
                $finalizedPath = $path;
                $this->assertSame($contents, file_get_contents($path));
            });

        $media = $this->newMedia($storage);
        $media->mediaId = 8;
        $media->fileName = $arbitraryPath;
        $media->storedAs = 'replacement.dat';
        $media->mediaType = 'image';
        $media->isRemote = false;

        $media->saveFile();

        $this->assertSame(md5($contents), $media->md5);
        $this->assertNotSame($arbitraryPath, $finalizedPath);
        $this->assertFileExists($arbitraryPath, 'the caller\'s file is left in place');
        $this->assertFileDoesNotExist($finalizedPath, 'the temporary copy is removed');
    }

    public function testThrowsConfigurationExceptionWhenLocalSourceIsMissing()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->never())->method('moveFromLocalPath');

        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->fileName = 'never-uploaded.jpg';
        $media->mediaType = 'image';
        $media->cloned = false;
        $media->isRemote = false;

        $this->expectException(ConfigurationException::class);
        $media->saveFile();
    }

    public function testThrowsConfigurationExceptionWhenFinalizeFails()
    {
        file_put_contents($this->libraryLocation . 'temp/upload.jpg', 'x');

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('moveFromLocalPath')->willThrowException(
            new GeneralException('storage upload failed')
        );

        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->fileName = 'upload.jpg';
        $media->mediaType = 'image';
        $media->cloned = false;
        $media->isRemote = false;

        $this->expectException(ConfigurationException::class);
        $media->saveFile();
    }

    public function testUploadIsJournalledOutsideTheTransactionAndClearedOnCommit()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('exists')->willReturn(false);
        $storage->expects($this->once())->method('moveFromLocalPath');

        $this->newUpload($storage)->saveFile();

        [$sql, $params, $connection] = $this->journal[0];
        $this->assertStringStartsWith('INSERT INTO `library_file_journal`', $sql);
        $this->assertSame(['5.jpg', 'pending_upload'], [$params['key'], $params['state']]);
        $this->assertSame('isolated', $connection, 'the record must survive the caller rolling back');

        $this->assertSame(
            ['DELETE FROM `library_file_journal` WHERE `id` = :id', ['id' => 1], 'isolated'],
            $this->journal[1]
        );
    }

    public function testAFailedUploadLeavesItsJournalRecordForLibraryTidy()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('exists')->willReturn(false);
        $storage->method('moveFromLocalPath')->willThrowException(new GeneralException('storage upload failed'));

        try {
            $this->newUpload($storage)->saveFile();
            $this->fail('Expected a ConfigurationException');
        } catch (ConfigurationException) {
            $this->assertCount(1, $this->journal, 'only the upload record, never cleared');
        }
    }

    public function testNewMediaNeverOverwritesAFileAlreadyStoredUnderItsKey()
    {
        // Another CMS sharing the storage prefix, or media uploaded before a database restore, already has 5.jpg.
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(false);
        $storage->method('exists')->with('5.jpg')->willReturn(true);
        $storage->expects($this->never())->method('moveFromLocalPath');

        $this->expectException(ConfigurationException::class);
        $this->newUpload($storage)->saveFile();
    }

    public function testNewMediaOverwritesALeftoverFileInALocalLibrary()
    {
        // A local library is this CMS's own, so a file left behind (e.g. after a database restore) is overwritten.
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn(true);
        $storage->expects($this->never())->method('exists');
        $storage->expects($this->once())->method('moveFromLocalPath');

        $this->newUpload($storage)->saveFile();
    }

    public function testReplacingAnExistingMediaFileIsNotTreatedAsAClash()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->never())->method('exists');
        $storage->expects($this->once())->method('moveFromLocalPath');

        $media = $this->newUpload($storage);
        (new \ReflectionProperty(Media::class, 'isNewMedia'))->setValue($media, false);

        $media->saveFile();
    }

    public function testARefusedUrlUploadNeverDeletesTheFileAlreadyStoredUnderItsKey()
    {
        // A refused URL upload's rollback must not delete the file already stored under its key.
        file_put_contents($this->libraryLocation . 'temp/remote.jpg', 'downloaded bytes');

        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('exists')->with('5.jpg')->willReturn(true);
        $storage->expects($this->never())->method('moveFromLocalPath');
        $storage->expects($this->never())->method('delete');

        $media = $this->newMedia($storage, true);
        $media->mediaId = 5;
        $media->name = 'remote.jpg';
        $media->fileName = 'https://example.com/remote.jpg';
        $media->mediaType = 'image';
        $media->isRemote = true;
        $media->setUnmatchedProperty('urlDownload', true);

        try {
            $media->saveFile();
            $this->fail('Expected a ConfigurationException');
        } catch (ConfigurationException) {
            // As MediaFactory::processDownloads() does on a failed save.
            $media->delete(['rollback' => true]);
        }

        $this->assertSame('5.jpg', $media->storedAs);
        $deletes = array_filter($this->journal, fn ($j) => ($j[1]['state'] ?? null) === 'pending_delete');
        $this->assertSame([], $deletes, 'no delete may be journalled for a file this media never stored');
    }

    public function testARolledBackNewUploadStillDeletesTheFileItStored()
    {
        $deleted = [];
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('exists')->willReturn(false);
        $storage->method('delete')->willReturnCallback(function ($key) use (&$deleted) {
            $deleted[] = $key;
        });

        $media = $this->newUpload($storage);
        $media->saveFile();
        $media->delete(['rollback' => true]);

        $this->assertContains('5.jpg', $deleted);
    }
}
