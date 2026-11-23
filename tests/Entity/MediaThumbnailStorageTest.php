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
use Stash\Driver\Ephemeral;
use Stash\Pool;
use Xibo\Entity\Media;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PermissionFactory;
use Xibo\Helper\CacheableUrlWindow;
use Xibo\Helper\StashLock;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LocalLibraryStorageService;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Media thumbnails made when a file or cover is stored, and filled on first request for older media.
 */
class MediaThumbnailStorageTest extends TestCase
{
    private string $libraryLocation;

    /** @var string[] storage writes and column updates, in order */
    private array $events = [];

    /** @var array<string, string> what each moved key held, read before the local file went */
    private array $stored = [];

    /** @var array<array{0: string, 1: array}> journal inserts */
    private array $journal = [];

    /** @var array<int, string[]> the events so far when each journal record was cleared, by record id */
    private array $clearedAfter = [];

    /** @var callable[] work waiting for the commit, when the store holds it back */
    private array $afterCommit = [];

    private bool $holdAfterCommit = false;

    private ?string $columnInDatabase = null;

    private int $resizeLimit = 0;

    protected function setUp(): void
    {
        $this->libraryLocation = sys_get_temp_dir() . '/xibo-media-thumbnail-test-' . uniqid() . '/';
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

    private function newStore(): StorageServiceInterface&MockObject
    {
        $store = $this->createMock(StorageServiceInterface::class);
        $store->method('afterCommit')->willReturnCallback(function (callable $callback) {
            if ($this->holdAfterCommit) {
                $this->afterCommit[] = $callback;
            } else {
                $callback();
            }
        });
        $store->method('insert')->willReturnCallback(function ($sql, $params) {
            $this->journal[] = [$sql, $params];
            return count($this->journal);
        });
        $store->method('update')->willReturnCallback(function ($sql, $params) {
            if (str_contains($sql, 'thumbnailVersion')) {
                $this->events[] = 'column:' . var_export($params['thumbnailVersion'], true);
            }
            if (str_starts_with($sql, 'DELETE FROM `library_file_journal`')) {
                $this->clearedAfter[$params['id']] = $this->events;
            }
            return 1;
        });
        $store->method('select')->willReturnCallback(fn () => [['thumbnailVersion' => $this->columnInDatabase]]);

        return $store;
    }

    /**
     * Storage that records each write and keeps what was written.
     */
    private function newStorage(): LibraryStorageServiceInterface&MockObject
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('moveFromLocalPath')->willReturnCallback(function ($path, $key) {
            $this->events[] = 'move:' . $key;
            $this->stored[$key] = file_get_contents($path);
            unlink($path);
        });
        $storage->method('copy')->willReturnCallback(function ($source, $dest) {
            $this->events[] = 'copy:' . $source . '>' . $dest;
        });
        $storage->method('delete')->willReturnCallback(function ($key) {
            $this->events[] = 'delete:' . $key;
        });

        return $storage;
    }

    private function newMedia(
        LibraryStorageServiceInterface $storage,
        ?StorageServiceInterface $store = null,
        ?MediaFactory $mediaFactory = null
    ): Media {
        $config = $this->createMock(ConfigServiceInterface::class);
        $config->method('getSetting')->willReturnCallback(fn ($setting) => match ($setting) {
            'LIBRARY_LOCATION' => $this->libraryLocation,
            'DEFAULT_RESIZE_THRESHOLD' => 0,
            'DEFAULT_RESIZE_LIMIT' => $this->resizeLimit,
            default => null,
        });

        return new Media(
            $store ?? $this->newStore(),
            $this->createMock(LogServiceInterface::class),
            null,
            $config,
            $mediaFactory ?? $this->createMock(MediaFactory::class),
            $this->createMock(PermissionFactory::class),
            $storage
        );
    }

    private function writeImage(string $path, string $type, int $width = 640, int $height = 480): string
    {
        $img = imagecreatetruecolor($width, $height);
        match ($type) {
            'png' => imagepng($img, $path),
            'jpg' => imagejpeg($img, $path),
        };
        imagedestroy($img);
        return $path;
    }

    private function newUpload(LibraryStorageServiceInterface $storage, string $type = 'png'): Media
    {
        $this->writeImage($this->libraryLocation . 'temp/upload.' . $type, $type);

        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->fileName = 'upload.' . $type;
        $media->mediaType = 'image';
        $media->cloned = false;
        $media->alwaysCopy = false;
        $media->isRemote = false;

        return $media;
    }

    private function journalled(string $state): array
    {
        return array_values(array_map(
            fn ($insert) => $insert[1]['key'],
            array_filter($this->journal, fn ($insert) => ($insert[1]['state'] ?? null) === $state)
        ));
    }

    public static function formatProvider(): array
    {
        return [
            'png' => ['png', 'image/png'],
            'jpg' => ['jpg', 'image/jpeg'],
        ];
    }

    /**
     * @dataProvider formatProvider
     */
    public function testAnUploadedImageStoresItsThumbnailUnderTheSourceMd5(string $type, string $mimeType)
    {
        $media = $this->newUpload($this->newStorage(), $type);
        $md5 = md5_file($this->libraryLocation . 'temp/upload.' . $type);

        $media->saveFile();

        $key = 'tn_5.' . $type . '_' . $md5;
        $this->assertSame(['move:5.' . $type, 'move:' . $key, 'column:\'' . $md5 . '\''], $this->events);
        $this->assertSame($md5, $media->thumbnailVersion);
        $this->assertSame($key, $media->getThumbnailKey());
        $this->assertSame(['5.' . $type, $key], $this->journalled('pending_upload'));

        $size = getimagesizefromstring($this->stored[$key]);
        $this->assertSame([320, 240], [$size[0], $size[1]]);
        $this->assertSame($mimeType, $size['mime'], 'the thumbnail keeps the source format');
        $this->assertSame($mimeType, $media->getThumbnailContentType());
        $this->assertSame(['.', '..'], scandir($this->libraryLocation . 'temp'), 'nothing is left in temp');
    }

    public function testAnUndecodableImageIsStoredWithoutAThumbnail()
    {
        file_put_contents($this->libraryLocation . 'temp/upload.jpg', 'not an image');
        $media = $this->newMedia($this->newStorage());
        $media->mediaId = 5;
        $media->fileName = 'upload.jpg';
        $media->mediaType = 'image';

        $media->saveFile();

        $this->assertSame(['move:5.jpg'], $this->events);
        $this->assertNull($media->thumbnailVersion);
        $this->assertSame(['.', '..'], scandir($this->libraryLocation . 'temp'));
    }

    public function testAnImageOverTheResizeLimitIsStoredWithoutAThumbnail()
    {
        $this->resizeLimit = 600;
        $media = $this->newUpload($this->newStorage());

        $media->saveFile();

        $this->assertSame(['move:5.png'], $this->events);
        $this->assertNull($media->thumbnailVersion);
    }

    public function testAnImageTooLargeToDecodeInTheMemoryLeftIsStoredWithoutAThumbnail()
    {
        $media = $this->newUpload($this->newStorage());

        // 640x480 needs about 1.5MB to decode, more than is left.
        $limit = ini_get('memory_limit');
        ini_set('memory_limit', (string)(memory_get_usage(true) + 1024 * 1024));
        try {
            $media->saveFile();
        } finally {
            ini_set('memory_limit', $limit);
        }

        $this->assertSame(['move:5.png'], $this->events);
        $this->assertNull($media->thumbnailVersion);
    }

    public function testReplacingTheFileJournalsTheOldThumbnailForDelete()
    {
        $source = $this->writeImage($this->libraryLocation . 'replacement.png', 'png');
        $md5 = md5_file($source);
        $media = $this->newMedia($this->newStorage());
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->fileName = $source;
        $media->storedAs = '5.png';
        $media->thumbnailVersion = 'old';

        $media->saveFile();

        $this->assertSame([
            'move:5.png',
            'move:tn_5.png_' . $md5,
            'delete:tn_5.png_old',
            'column:\'' . $md5 . '\'',
        ], $this->events);
        $this->assertSame(['tn_5.png_old'], $this->journalled('pending_delete'));
    }

    public function testAThumbnailThatCannotBeMadeOnReplaceClearsTheColumnAndDeletesTheOldOne()
    {
        $source = $this->libraryLocation . 'replacement.png';
        file_put_contents($source, 'not an image');
        $media = $this->newMedia($this->newStorage());
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->fileName = $source;
        $media->storedAs = '5.png';
        $media->thumbnailVersion = 'old';

        $media->saveFile();

        $this->assertSame(['move:5.png', 'delete:tn_5.png_old', 'column:NULL'], $this->events);
        $this->assertNull($media->thumbnailVersion);
    }

    public function testAThumbnailThatCannotBeStoredLeavesTheUploadStored()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('moveFromLocalPath')->willReturnCallback(function ($path, $key) {
            if (str_starts_with($key, 'tn_')) {
                throw new GeneralException('Problem writing object');
            }
            $this->events[] = 'move:' . $key;
            unlink($path);
        });
        $media = $this->newUpload($storage);

        $media->saveFile();

        $this->assertSame(['move:5.png'], $this->events);
        $this->assertNull($media->thumbnailVersion);
        $this->assertSame(1, $media->valid);
        $this->assertSame(['.', '..'], scandir($this->libraryLocation . 'temp'));
    }

    public function testAClonedImageCopiesItsThumbnail()
    {
        $media = $this->newMedia($this->newStorage());
        $media->mediaId = 6;
        $media->mediaType = 'image';
        $media->storedAs = '5.png';
        $media->thumbnailVersion = 'v1';

        $clone = clone $media;
        $clone->mediaId = 6;
        $clone->saveFile();

        $this->assertSame(['copy:5.png>6.png', 'copy:tn_5.png_v1>tn_6.png_v1', 'column:\'v1\''], $this->events);
        $this->assertSame('v1', $clone->thumbnailVersion);
        $this->assertSame(['6.png', 'tn_6.png_v1'], $this->journalled('pending_upload'));
    }

    public function testAClonedImageWaitingForTheMoveCopiesItsThumbnailFromTheLocalLibrary()
    {
        $oldLibrary = $this->libraryLocation . 'old/';
        mkdir($oldLibrary);
        file_put_contents($oldLibrary . '5.png', 'image');
        file_put_contents($oldLibrary . 'tn_5.png_v1', 'thumbnail');
        $mediaFactory = $this->createMock(MediaFactory::class);
        $mediaFactory->method('getStorageFor')->willReturn(new LocalLibraryStorageService($oldLibrary));
        $media = $this->newMedia($this->newStorage(), null, $mediaFactory);
        $media->mediaId = 6;
        $media->mediaType = 'image';
        $media->storedAs = '5.png';
        $media->thumbnailVersion = 'v1';
        $media->storageEngine = 'local';

        $clone = clone $media;
        $clone->mediaId = 6;
        $clone->saveFile();

        $this->assertSame(['move:6.png', 'move:tn_6.png_v1', 'column:\'v1\''], $this->events);
        $this->assertSame('thumbnail', $this->stored['tn_6.png_v1']);
        $this->assertSame('v1', $clone->thumbnailVersion);
    }

    public function testAClonedVideoHasNoThumbnail()
    {
        $media = $this->newMedia($this->newStorage());
        $media->mediaType = 'video';
        $media->storedAs = '5.mp4';
        $media->thumbnailVersion = 'v1';

        $clone = clone $media;

        $this->assertNull($clone->thumbnailVersion, 'the cover is not copied, so neither is its thumbnail');
    }

    public function testACoverStoresItsThumbnailUnderTheCoverMd5()
    {
        $cover = $this->writeImage($this->libraryLocation . 'temp/cover.png', 'png');
        $md5 = md5_file($cover);
        $media = $this->newMedia($this->newStorage());
        $media->mediaId = 42;
        $media->mediaType = 'video';
        $media->thumbnailVersion = 'old';

        $media->storeVideoCover($cover);

        $key = 'tn_42_videocover.png_' . $md5;
        $this->assertSame([
            'move:42_videocover.png',
            'move:' . $key,
            'delete:tn_42_videocover.png_old',
            'column:\'' . $md5 . '\'',
        ], $this->events);
        $this->assertSame($key, $media->getThumbnailKey());
        $this->assertSame('image/png', $media->getThumbnailContentType());
        $this->assertSame(320, getimagesizefromstring($this->stored[$key])[0]);
        $this->assertSame(['42_videocover.png', $key], $this->journalled('pending_upload'));
    }

    public function testACoverInALocalLibraryKeepsItsStoredThumbnailAndDropsThePreviewSources()
    {
        file_put_contents($this->libraryLocation . 'tn_src_42_videocover.png_1000', 'old preview source');
        $cover = $this->writeImage($this->libraryLocation . 'temp/cover.png', 'png');
        $md5 = md5_file($cover);
        $media = $this->newMedia(new LocalLibraryStorageService($this->libraryLocation));
        $media->mediaId = 42;
        $media->mediaType = 'video';

        $media->storeVideoCover($cover);

        $this->assertFileExists($this->libraryLocation . '42_videocover.png');
        $this->assertFileExists($this->libraryLocation . 'tn_42_videocover.png_' . $md5);
        $this->assertFileDoesNotExist($this->libraryLocation . 'tn_src_42_videocover.png_1000');
    }

    public function testDeletingMediaJournalsItsThumbnail()
    {
        $media = $this->newMedia($this->newStorage());
        $media->mediaId = 5;
        $media->name = 'test image';
        $media->mediaType = 'image';
        $media->storedAs = '5.png';
        $media->thumbnailVersion = 'v1';

        $media->delete(['rollback' => true]);

        $this->assertSame(['5.png', 'tn_5.png_v1'], $this->journalled('pending_delete'));
    }

    public function testFillAdoptsAThumbnailAlreadyStoredUnderTheCurrentVersion()
    {
        // An upgraded local library already holds tn_{storedAs}_{md5} from the earlier cache.
        $storage = $this->newStorage();
        $storage->method('exists')->with('tn_5.jpg_abc')->willReturn(true);
        $storage->expects($this->never())->method('downloadToTemp');
        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.jpg';
        $media->md5 = 'abc';

        $media->fillThumbnail(new Pool(new Ephemeral()));

        $this->assertSame(['column:\'abc\''], $this->events);
        $this->assertSame('abc', $media->thumbnailVersion);
    }

    public function testFillMakesAndStoresAMissingThumbnail()
    {
        $source = $this->writeImage($this->libraryLocation . 'source-copy.png', 'png');
        $storage = $this->newStorage();
        $storage->method('exists')->willReturn(false);
        $storage->method('downloadToTemp')->with('5.png')->willReturn($source);
        $storage->expects($this->once())->method('releaseTemp')->with($source);
        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.png';
        $media->md5 = 'abc';

        $media->fillThumbnail(new Pool(new Ephemeral()));

        $this->assertSame(['move:tn_5.png_abc', 'column:\'abc\''], $this->events);
        $this->assertSame(['tn_5.png_abc'], $this->journalled('pending_upload'));

        // Outside a transaction, the upload record is only cleared once the column names the thumbnail.
        $this->assertSame([1 => ['move:tn_5.png_abc', 'column:\'abc\'']], $this->clearedAfter);
    }

    public function testFillVersionsAVideoByItsCoverMd5()
    {
        $cover = $this->writeImage($this->libraryLocation . 'cover-copy.png', 'png');
        $md5 = md5_file($cover);
        $storage = $this->newStorage();
        $storage->method('exists')->willReturn(false);
        $storage->method('downloadToTemp')->with('42_videocover.png')->willReturn($cover);
        $media = $this->newMedia($storage);
        $media->mediaId = 42;
        $media->mediaType = 'video';
        $media->storedAs = '42.mp4';

        $media->fillThumbnail(new Pool(new Ephemeral()));

        $this->assertSame(['move:tn_42_videocover.png_' . $md5, 'column:\'' . $md5 . '\''], $this->events);
    }

    public function testFillUsesTheThumbnailAnotherRequestStored()
    {
        $this->columnInDatabase = 'xyz';
        $storage = $this->newStorage();
        $storage->expects($this->never())->method('exists');
        $storage->expects($this->never())->method('downloadToTemp');
        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.png';
        $media->md5 = 'abc';

        $media->fillThumbnail(new Pool(new Ephemeral()));

        $this->assertSame([], $this->events);
        $this->assertSame('xyz', $media->thumbnailVersion);
    }

    public function testFillRemakesAThumbnailMissingFromStorage()
    {
        $this->columnInDatabase = 'abc';
        $source = $this->writeImage($this->libraryLocation . 'source-copy.png', 'png');
        $storage = $this->newStorage();
        $storage->method('exists')->willReturn(false);
        $storage->method('downloadToTemp')->willReturn($source);
        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.png';
        $media->md5 = 'abc';
        $media->thumbnailVersion = 'abc';

        $media->fillThumbnail(new Pool(new Ephemeral()), 'abc');

        $this->assertSame(['move:tn_5.png_abc'], $this->events, 'the version is unchanged, so only the file is new');
    }

    public function testFillGivesUpWhileAnotherRequestHoldsTheLock()
    {
        $pool = new Pool(new Ephemeral());
        $this->assertTrue($this->isFree($pool, 5), 'another request takes the lock');

        $storage = $this->newStorage();
        $storage->expects($this->never())->method('downloadToTemp');
        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.png';
        $media->md5 = 'abc';

        $this->assertFalse($media->fillThumbnail($pool, null, 1), 'the other request makes it');
        $this->assertSame([], $this->events);
    }

    public function testFillReportsASourceThatIsGone()
    {
        $storage = $this->newStorage();
        $storage->method('exists')->willReturn(false);
        $storage->method('downloadToTemp')->willThrowException(new NotFoundException('gone'));
        $media = $this->newMedia($storage);
        $media->mediaId = 42;
        $media->mediaType = 'video';
        $media->storedAs = '42.mp4';
        $pool = new Pool(new Ephemeral());

        try {
            $media->fillThumbnail($pool);
            $this->fail('A missing cover is reported');
        } catch (NotFoundException) {
            $this->assertTrue($this->isFree($pool, 42), 'the lock is released');
        }
    }

    public function testFillReportsASourceItCannotMakeAThumbnailFrom()
    {
        $source = $this->libraryLocation . 'cover-copy.png';
        file_put_contents($source, 'not an image');
        $storage = $this->newStorage();
        $storage->method('downloadToTemp')->willReturn($source);
        $media = $this->newMedia($storage);
        $media->mediaId = 42;
        $media->mediaType = 'video';
        $media->storedAs = '42.mp4';

        $this->expectException(InvalidArgumentException::class);
        $media->fillThumbnail(new Pool(new Ephemeral()));
    }

    public function testAThumbnailThatCannotBeMadeIsNotTriedAgainForADay()
    {
        $source = $this->libraryLocation . 'cover-copy.png';
        file_put_contents($source, 'not an image');
        $storage = $this->newStorage();
        $storage->expects($this->once())->method('downloadToTemp')->willReturn($source);
        $media = $this->newMedia($storage);
        $media->mediaId = 42;
        $media->mediaType = 'video';
        $media->storedAs = '42.mp4';
        $media->md5 = 'abc';
        $pool = new Pool(new Ephemeral());

        $this->assertSame([false, false], [$this->tryFill($media, $pool), $this->tryFill($media, $pool)]);
        $this->assertTrue($this->isFree($pool, 42), 'the lock is released');
    }

    public function testFillHoldsTheLockUntilTheColumnIsCommitted()
    {
        $this->holdAfterCommit = true;
        $storage = $this->newStorage();
        $storage->method('exists')->willReturn(true);
        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.png';
        $media->md5 = 'abc';
        $pool = new Pool(new Ephemeral());

        $media->fillThumbnail($pool);

        $this->assertFalse((new StashLock($pool, 'locks/media_thumbnail/5'))->acquire(60, 1, 1), 'held until commit');
        foreach ($this->afterCommit as $callback) {
            $callback();
        }
        $this->assertTrue($this->isFree($pool, 5), 'released on commit');
    }

    public function testFillNeverReleasesALockAnotherRequestTookOver()
    {
        $this->holdAfterCommit = true;
        $storage = $this->newStorage();
        $storage->method('exists')->willReturn(true);
        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.png';
        $media->md5 = 'abc';
        $pool = new Pool(new Ephemeral());

        $media->fillThumbnail($pool);

        // The lock expires before the commit and another request takes it.
        $pool->deleteItem('locks/media_thumbnail/5');
        $other = new StashLock($pool, 'locks/media_thumbnail/5');
        $this->assertTrue($other->acquire(60, 1, 1));

        foreach ($this->afterCommit as $callback) {
            $callback();
        }
        $this->assertTrue($other->isOwner(), 'the other request still holds it');
    }

    /**
     * Takes the media's thumbnail lock if nobody holds it.
     */
    private function isFree(Pool $pool, int $mediaId): bool
    {
        return (new StashLock($pool, 'locks/media_thumbnail/' . $mediaId))->acquire(60, 1, 1);
    }

    public function testFillNeverDownloadsAnImageOverTheResizeLimit()
    {
        $this->resizeLimit = 6000;
        $storage = $this->newStorage();
        $storage->method('exists')->willReturn(false);
        $storage->expects($this->never())->method('downloadToTemp');
        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.png';
        $media->md5 = 'abc';
        $media->width = 7000;
        $media->height = 3000;
        $pool = new Pool(new Ephemeral());

        try {
            $media->fillThumbnail($pool);
            $this->fail('An image over the limit has no thumbnail');
        } catch (GeneralException) {
            $this->assertTrue($this->isFree($pool, 5), 'the lock is released');
        }
    }

    public function testFillNeverDownloadsAnImageTooLargeToDecodeInTheMemoryLeft()
    {
        $storage = $this->newStorage();
        $storage->method('exists')->willReturn(false);
        $storage->expects($this->never())->method('downloadToTemp');
        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.png';
        $media->md5 = 'abc';
        $media->width = 40000;
        $media->height = 40000;

        $pool = new Pool(new Ephemeral());

        $limit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');
        try {
            $messages = [$this->fillError($media, $pool), $this->fillError($media, $pool)];
        } finally {
            ini_set('memory_limit', $limit);
        }
        $this->assertSame(['Image too large', 'Image too large'], $messages, 'a repeat keeps the reason');
    }

    public function testAThumbnailTooLargeForTheMemoryLeftIsTriedAgainWithMore()
    {
        $source = $this->writeImage($this->libraryLocation . 'source-copy.png', 'png');
        $storage = $this->newStorage();
        $storage->method('exists')->willReturn(false);
        $storage->expects($this->once())->method('downloadToTemp')->willReturn($source);
        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.png';
        $media->md5 = 'abc';
        $media->width = 40000;
        $media->height = 40000;
        $pool = new Pool(new Ephemeral());

        $limit = ini_get('memory_limit');
        try {
            ini_set('memory_limit', '512M');
            $first = $this->tryFill($media, $pool);
            ini_set('memory_limit', '-1');
            $second = $this->tryFill($media, $pool);
        } finally {
            ini_set('memory_limit', $limit);
        }
        $this->assertSame([false, true], [$first, $second]);
    }

    /**
     * Whether fillThumbnail() made a thumbnail, false when it threw InvalidArgumentException.
     */
    private function tryFill(Media $media, Pool $pool): bool
    {
        return $this->fillError($media, $pool) === null;
    }

    /**
     * The message of the InvalidArgumentException fillThumbnail() threw, or null if it didn't throw.
     */
    private function fillError(Media $media, Pool $pool): ?string
    {
        try {
            $media->fillThumbnail($pool);
            return null;
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }
    }

    public function testTheThumbnailUrlIsTheSameThroughoutAWindow()
    {
        $start = CacheableUrlWindow::start(1_800_000_000);
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->exactly(2))
            ->method('getUserDownloadUrl')
            ->with('tn_5.png_v1', 'image/png', 'inline', $start, 'private, max-age=' . CacheableUrlWindow::SECONDS)
            ->willReturn('https://storage.example.com/tn_5.png_v1?signed');
        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.png';
        $media->thumbnailVersion = 'v1';

        $this->assertSame('https://storage.example.com/tn_5.png_v1?signed', $media->getThumbnailUrl($start));
        $media->getThumbnailUrl($start + CacheableUrlWindow::SECONDS - 1);
    }

    public function testMediaWithoutAStoredThumbnailHasNoThumbnailUrl()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->never())->method('getUserDownloadUrl');
        $media = $this->newMedia($storage);
        $media->mediaType = 'image';
        $media->storedAs = '5.png';

        $this->assertNull($media->getThumbnailUrl(1_800_000_000));
        $this->assertNull($media->getThumbnailKey());
    }

    public function testAnImageWithoutAKnownExtensionHasAPngThumbnail()
    {
        $this->writeImage($this->libraryLocation . 'temp/upload', 'jpg');
        $media = $this->newMedia($this->newStorage());
        $media->mediaId = 5;
        $media->fileName = 'upload';
        $media->mediaType = 'image';

        $media->saveFile();

        $this->assertSame('5', (string)$media->storedAs);
        $this->assertSame('image/png', $media->getThumbnailContentType());
        $this->assertSame('image/png', getimagesizefromstring($this->stored[$media->getThumbnailKey()])['mime']);
    }

    public function testACoverOverTheResizeLimitStillHasAThumbnail()
    {
        $this->resizeLimit = 600;
        $cover = $this->writeImage($this->libraryLocation . 'temp/cover.png', 'png');
        $md5 = md5_file($cover);
        $media = $this->newMedia($this->newStorage());
        $media->mediaId = 42;
        $media->mediaType = 'video';

        $media->storeVideoCover($cover);

        $this->assertSame($md5, $media->thumbnailVersion, 'a video has a cover exactly when it has a thumbnail');
    }

    public function testACoverWithoutAThumbnailIsRefused()
    {
        $cover = $this->libraryLocation . 'temp/cover.png';
        file_put_contents($cover, 'not an image');
        $media = $this->newMedia($this->newStorage());
        $media->mediaId = 42;
        $media->mediaType = 'video';

        try {
            $media->storeVideoCover($cover);
            $this->fail('A cover a thumbnail cannot be made from is refused');
        } catch (GeneralException) {
            $this->assertSame([], $this->events, 'nothing is stored');
        }
    }

    public function testACoverWhoseThumbnailCannotBeStoredFails()
    {
        $cover = $this->writeImage($this->libraryLocation . 'temp/cover.png', 'png');
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('moveFromLocalPath')->willReturnCallback(function ($path, $key) {
            if (str_starts_with($key, 'tn_')) {
                throw new GeneralException('Problem writing object');
            }
            unlink($path);
        });
        $media = $this->newMedia($storage);
        $media->mediaId = 42;
        $media->mediaType = 'video';

        $this->expectException(GeneralException::class);
        $media->storeVideoCover($cover);
    }

    public function testFillLeavesAThumbnailAnotherRequestRemadeAlone()
    {
        $this->columnInDatabase = 'abc';
        $storage = $this->newStorage();
        $storage->method('exists')->with('tn_5.png_abc')->willReturn(true);
        $storage->expects($this->never())->method('downloadToTemp');
        $media = $this->newMedia($storage);
        $media->mediaId = 5;
        $media->mediaType = 'image';
        $media->storedAs = '5.png';
        $media->md5 = 'abc';
        $media->thumbnailVersion = 'abc';

        $media->fillThumbnail(new Pool(new Ephemeral()), 'abc');

        $this->assertSame([], $this->events);
    }

    public function testAThumbnailWrittenShortCountsAsNone()
    {
        // A full disk can leave part of the file written, which the image library reports as a failed write.
        $libraryLocation = $this->libraryLocation;
        $cover = $this->writeImage($libraryLocation . 'cover.png', 'png');
        stream_wrapper_register('shortwrite', ShortWriteStream::class);
        try {
            $this->libraryLocation = 'shortwrite://library/';
            $media = $this->newMedia($this->newStorage());
            $media->mediaId = 42;
            $media->mediaType = 'video';

            try {
                $media->storeVideoCover($cover);
                $this->fail('A cover whose thumbnail was written short is refused');
            } catch (GeneralException) {
                $this->assertSame([], $this->events, 'nothing is stored');
                $this->assertSame([], ShortWriteStream::$files, 'the partial file is removed');
            }
        } finally {
            stream_wrapper_unregister('shortwrite');
            $this->libraryLocation = $libraryLocation;
        }
    }
}
