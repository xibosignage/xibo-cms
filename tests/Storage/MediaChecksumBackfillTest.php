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
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Xmds\Soap;

/**
 * Required Files fills in a media row's missing MD5 or size from storage, reading no more than it needs.
 */
class MediaChecksumBackfillTest extends TestCase
{
    private string $tempFile = '';

    protected function tearDown(): void
    {
        if ($this->tempFile !== '' && file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    private function backfiller(LibraryStorageServiceInterface $storage): Soap
    {
        $registry = $this->createConfiguredMock(LibraryStorageRegistryInterface::class, ['get' => $storage]);
        return new class ($storage, $registry, $this->createMock(LogServiceInterface::class)) extends Soap {
            public function __construct($storage, $registry, private $log)
            {
                $this->libraryStorageService = $storage;
                $this->libraryStorageRegistry = $registry;
            }

            protected function getLog()
            {
                return $this->log;
            }

            public function backfill(int $id, ?string $path, ?string $md5, ?int $fileSize, \PDOStatement $mediaSth)
            {
                return $this->backfillMediaChecksum($id, $path, $md5, $fileSize, $mediaSth);
            }
        };
    }

    public function testAMissingSizeIsReadWithoutDownloading()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->never())->method('downloadToTemp');
        $storage->expects($this->once())->method('size')->with('1.jpg')->willReturn(42);
        $mediaSth = $this->createMock(\PDOStatement::class);
        $mediaSth->expects($this->once())->method('execute')
            ->with(['md5' => 'abc', 'size' => 42, 'mediaid' => 1]);

        $this->assertSame(['abc', 42], $this->backfiller($storage)->backfill(1, '1.jpg', 'abc', 0, $mediaSth));
    }

    public function testAnEmptyFileIsComplete()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->never())->method('downloadToTemp');
        $storage->expects($this->never())->method('size');
        $mediaSth = $this->createMock(\PDOStatement::class);
        $mediaSth->expects($this->never())->method('execute');

        $this->assertSame(
            [md5(''), 0],
            $this->backfiller($storage)->backfill(1, '1.jpg', md5(''), 0, $mediaSth)
        );
    }

    public function testAMissingMd5IsComputedFromADownload()
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'md5');
        file_put_contents($this->tempFile, 'hello');
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->once())->method('downloadToTemp')->with('1.jpg')->willReturn($this->tempFile);
        $storage->expects($this->once())->method('releaseTemp')->with($this->tempFile);
        $mediaSth = $this->createMock(\PDOStatement::class);
        $mediaSth->expects($this->once())->method('execute')
            ->with(['md5' => md5('hello'), 'size' => 5, 'mediaid' => 1]);

        $this->assertSame(
            [md5('hello'), 5],
            $this->backfiller($storage)->backfill(1, '1.jpg', '', 5, $mediaSth)
        );
    }

    public function testAFailedReadKeepsTheCurrentValues()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('downloadToTemp')->willThrowException(new GeneralException('outage'));
        $mediaSth = $this->createMock(\PDOStatement::class);
        $mediaSth->expects($this->never())->method('execute');

        $this->assertSame(['', 0], $this->backfiller($storage)->backfill(1, '1.jpg', '', 0, $mediaSth));
    }

    public function testAFailedSizeReadKeepsTheCurrentValues()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('size')->willThrowException(new GeneralException('outage'));
        $mediaSth = $this->createMock(\PDOStatement::class);
        $mediaSth->expects($this->never())->method('execute');

        $this->assertSame(['abc', 0], $this->backfiller($storage)->backfill(1, '1.jpg', 'abc', 0, $mediaSth));
    }

    public function testAMissingPathIsSkipped()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->never())->method('downloadToTemp');
        $storage->expects($this->never())->method('size');
        $mediaSth = $this->createMock(\PDOStatement::class);
        $mediaSth->expects($this->never())->method('execute');

        $this->assertSame([null, 0], $this->backfiller($storage)->backfill(1, null, null, null, $mediaSth));
        $this->assertSame(['abc', 0], $this->backfiller($storage)->backfill(1, '', 'abc', null, $mediaSth));
    }

    public function testANullSizeIsReadFromStorage()
    {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->expects($this->once())->method('size')->with('1.jpg')->willReturn(42);
        $mediaSth = $this->createMock(\PDOStatement::class);
        $mediaSth->expects($this->once())->method('execute')
            ->with(['md5' => 'abc', 'size' => 42, 'mediaid' => 1]);

        $this->assertSame(['abc', 42], $this->backfiller($storage)->backfill(1, '1.jpg', 'abc', null, $mediaSth));
    }
}
