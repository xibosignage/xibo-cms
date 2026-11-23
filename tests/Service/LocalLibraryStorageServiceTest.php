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
use Xibo\Service\LocalLibraryStorageService;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

class LocalLibraryStorageServiceTest extends TestCase
{
    private string $libraryLocation;
    private LocalLibraryStorageService $service;

    protected function setUp(): void
    {
        $this->libraryLocation = sys_get_temp_dir() . '/xibo-storage-test-' . uniqid() . '/';
        mkdir($this->libraryLocation, 0777, true);
        mkdir($this->libraryLocation . 'temp', 0777, true);
        $this->service = new LocalLibraryStorageService($this->libraryLocation);
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

    public function testHasLocalFiles()
    {
        $this->assertTrue($this->service->hasLocalFiles());
    }

    public function testFinalizeFromLocalPathMovesFileAndRemovesSource()
    {
        $source = $this->libraryLocation . 'temp/upload.txt';
        file_put_contents($source, 'hello world');

        $this->service->moveFromLocalPath($source, '5.txt');

        $this->assertFileDoesNotExist($source, 'source should no longer exist after finalize');
        $this->assertTrue($this->service->exists('5.txt'));
        $this->assertSame('hello world', file_get_contents($this->libraryLocation . '5.txt'));
    }

    public function testFinalizeFromLocalPathThrowsWhenSourceMissing()
    {
        $this->expectException(GeneralException::class);
        $this->service->moveFromLocalPath($this->libraryLocation . 'temp/does-not-exist.txt', '5.txt');
    }

    public function testFinalizeFromLocalPathKeepsSourceWhenItCannotBeStored()
    {
        $source = $this->libraryLocation . 'temp/upload.txt';
        file_put_contents($source, 'hello world');

        // A file where the destination folder should be, so the folder, the move and the copy all fail.
        file_put_contents($this->libraryLocation . 'blocked', '');

        try {
            $this->service->moveFromLocalPath($source, 'blocked/5.txt');
            $this->fail('Expected a GeneralException');
        } catch (GeneralException) {
            $this->assertFileExists($source);
        }
    }

    public function testCopyWithinBackendCopiesWithoutRemovingSource()
    {
        file_put_contents($this->libraryLocation . '5.txt', 'cloned content');

        $this->service->copy('5.txt', '6.txt');

        $this->assertTrue($this->service->exists('5.txt'), 'source should still exist after a copy');
        $this->assertTrue($this->service->exists('6.txt'));
        $this->assertSame('cloned content', file_get_contents($this->libraryLocation . '6.txt'));
    }

    public function testCopyWithinBackendThrowsWhenSourceMissing()
    {
        $this->expectException(GeneralException::class);
        $this->service->copy('missing.txt', '6.txt');
    }

    public function testExistsReflectsFilePresence()
    {
        $this->assertFalse($this->service->exists('nope.txt'));
        file_put_contents($this->libraryLocation . 'present.txt', 'x');
        $this->assertTrue($this->service->exists('present.txt'));
    }

    public function testSizeReturnsTheFileSize()
    {
        file_put_contents($this->libraryLocation . '5.txt', '0123456789');
        $this->assertSame(10, $this->service->size('5.txt'));
    }

    public function testSizeThrowsWhenKeyMissing()
    {
        $this->expectException(NotFoundException::class);
        $this->service->size('missing.txt');
    }

    public function testDeleteRemovesExistingFile()
    {
        file_put_contents($this->libraryLocation . 'todelete.txt', 'x');
        $this->service->delete('todelete.txt');
        $this->assertFalse($this->service->exists('todelete.txt'));
    }

    public function testDeleteOnMissingFileIsANoOp()
    {
        // Should not throw.
        $this->service->delete('never-existed.txt');
        $this->assertFalse($this->service->exists('never-existed.txt'));
    }

    public function testReadIntoTempReturnsRealPathWithNoCopy()
    {
        file_put_contents($this->libraryLocation . '5.txt', 'abc');

        $tempCopyPath = $this->service->downloadToTemp('5.txt');

        $this->assertSame(realpath($this->libraryLocation . '5.txt'), realpath($tempCopyPath));
        $this->assertSame('abc', file_get_contents($tempCopyPath));
    }

    public function testReadIntoTempThrowsWhenKeyMissing()
    {
        $this->expectException(NotFoundException::class);
        $this->service->downloadToTemp('missing.txt');
    }

    public function testReadStreamReturnsAStreamWithCorrectContent()
    {
        file_put_contents($this->libraryLocation . '5.txt', 'stream me');

        $stream = $this->service->readStream('5.txt');

        $this->assertSame('stream me', $stream->getContents());
        $stream->close();
    }

    public function testReadStreamThrowsWhenKeyMissing()
    {
        $this->expectException(NotFoundException::class);
        $this->service->readStream('missing.txt');
    }

    public function testReadRangeReturnsExactlyTheRequestedBytes()
    {
        file_put_contents($this->libraryLocation . '5.txt', '0123456789');

        $this->assertSame('34567', $this->service->readRange('5.txt', 3, 5)->getContents());
        $this->assertSame('0', $this->service->readRange('5.txt', 0, 1)->getContents());
    }

    public function testReadRangeStopsAtTheEndOfTheFile()
    {
        file_put_contents($this->libraryLocation . '5.txt', '0123456789');

        $this->assertSame('89', $this->service->readRange('5.txt', 8, 100)->getContents());
        $this->assertSame('', $this->service->readRange('5.txt', 50, 10)->getContents());
    }

    public function testCommitTempIsANoOpBecauseTheTempCopyPathIsTheFile()
    {
        file_put_contents($this->libraryLocation . '5.txt', 'abc');
        $tempCopyPath = $this->service->downloadToTemp('5.txt');
        file_put_contents($tempCopyPath, 'resized');

        $this->service->storeFromTemp($tempCopyPath, '5.txt');

        $this->assertSame('resized', file_get_contents($this->libraryLocation . '5.txt'));
    }

    public function testLastModifiedIsTheFileModifiedTime()
    {
        file_put_contents($this->libraryLocation . '5.txt', 'abc');
        touch($this->libraryLocation . '5.txt', 1700000000);

        $this->assertSame(1700000000, $this->service->lastModified('5.txt'));
    }

    public function testLastModifiedThrowsWhenKeyMissing()
    {
        $this->expectException(NotFoundException::class);
        $this->service->lastModified('missing.txt');
    }

    public function testReadRangeRejectsAnInvalidRange()
    {
        file_put_contents($this->libraryLocation . '5.txt', '0123456789');

        $this->expectException(InvalidArgumentException::class);
        $this->service->readRange('5.txt', 0, 0);
    }

    public function testReadRangeThrowsWhenKeyMissing()
    {
        $this->expectException(NotFoundException::class);
        $this->service->readRange('missing.txt', 0, 1);
    }

    public function testExistsReturnsFalseRatherThanThrowingOnPathTraversal()
    {
        // An invalid key does not exist rather than being an error.
        $this->assertFalse($this->service->exists('../../../etc/passwd'));
    }

    public function testReadIntoTempRejectsPathTraversal()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->downloadToTemp('../../../etc/passwd');
    }

    public function testKeyPathTraversalIsRejectedOnFinalize()
    {
        $source = $this->libraryLocation . 'temp/upload.txt';
        file_put_contents($source, 'x');

        $this->expectException(InvalidArgumentException::class);
        $this->service->moveFromLocalPath($source, '../escape.txt');
    }

    public function testReleaseTempIsANoOpAndNeverDeletesTheRealFile()
    {
        // The temporary copy path is the library file itself, so releasing it must never delete it.
        file_put_contents($this->libraryLocation . '5.txt', 'abc');
        $tempCopyPath = $this->service->downloadToTemp('5.txt');

        $this->service->releaseTemp($tempCopyPath);

        $this->assertFileExists($tempCopyPath);
        $this->assertTrue($this->service->exists('5.txt'));
    }

    public function testListKeysReturnsOnlyRootLevelFiles()
    {
        file_put_contents($this->libraryLocation . '5.jpg', 'abc');
        // setUp() also created temp/, which must not be listed.

        $keys = iterator_to_array($this->service->listKeys(), false);

        $this->assertCount(1, $keys);
        $this->assertSame('5.jpg', $keys[0]['key']);
        $this->assertSame(3, $keys[0]['size']);
        $this->assertIsInt($keys[0]['lastModified']);
    }

    public function testListKeysOfAFolderPrefixesItsKeys()
    {
        mkdir($this->libraryLocation . '7');
        file_put_contents($this->libraryLocation . '7/1_100.html', 'a');

        $keys = array_column(iterator_to_array($this->service->listKeys('7'), false), 'key');

        $this->assertSame(['7/1_100.html'], $keys);
    }

    public function testListKeysOfAMissingFolderIsEmpty()
    {
        $this->assertSame([], iterator_to_array($this->service->listKeys('7'), false));
    }

    public function testListKeysOfAMissingRootThrows()
    {
        $this->expectException(GeneralException::class);
        iterator_to_array((new LocalLibraryStorageService($this->libraryLocation . 'missing/'))->listKeys(), false);
    }

    public function testListKeysRecursiveIncludesSubFolders()
    {
        file_put_contents($this->libraryLocation . '5.jpg', 'abc');
        mkdir($this->libraryLocation . '7');
        file_put_contents($this->libraryLocation . '7/1_100.html', 'a');

        $keys = array_column(iterator_to_array($this->service->listKeys('', true), false), 'key');
        sort($keys);

        $this->assertSame(['5.jpg', '7/1_100.html'], $keys);
    }

    public function testMoveFromLocalPathCreatesMissingFolders()
    {
        $source = $this->libraryLocation . 'temp/source.html';
        file_put_contents($source, 'html');

        $this->service->moveFromLocalPath($source, '7/1_100.html');

        $this->assertSame('html', file_get_contents($this->libraryLocation . '7/1_100.html'));
    }

    public function testLocalFilesHaveNoDirectDownloadUrl()
    {
        // The web server sends them itself.
        $this->assertNull($this->service->getDirectDownloadUrl('5.jpg'));
    }
}
