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
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\LibraryKeyLookup;
use Xibo\Support\Exception\GeneralException;

/**
 * A grid checks each row's file through one lookup: a single listing when the files aren't local, a check per
 * row when they are.
 */
class LibraryKeyLookupTest extends TestCase
{
    private int $listings = 0;

    /** @var string[] */
    private array $existsChecks = [];

    /** @var string[] */
    private array $errors = [];

    /**
     * @param string[] $stored
     */
    private function lookup(
        bool $hasLocalFiles,
        int $keyCount,
        array $stored,
        bool $fails = false
    ): LibraryKeyLookup {
        $storage = $this->createMock(LibraryStorageServiceInterface::class);
        $storage->method('hasLocalFiles')->willReturn($hasLocalFiles);
        $storage->method('listKeys')->willReturnCallback(function () use ($stored, $fails) {
            $this->listings++;
            if ($fails) {
                throw new GeneralException('outage');
            }
            return array_map(fn ($key) => ['key' => $key, 'lastModified' => null, 'size' => null], $stored);
        });
        $storage->method('exists')->willReturnCallback(function ($key) use ($stored, $fails) {
            $this->existsChecks[] = $key;
            if ($fails) {
                throw new GeneralException('outage');
            }
            return in_array($key, $stored, true);
        });

        $log = $this->createMock(LogServiceInterface::class);
        $log->method('error')->willReturnCallback(function ($message) {
            $this->errors[] = $message;
        });

        return new LibraryKeyLookup($storage, $log, 'thumbnails', $keyCount);
    }

    /**
     * @return bool[] whether each of keys a, b and c was found
     */
    private function checkRows(LibraryKeyLookup $lookup): array
    {
        return array_map(fn ($key) => $lookup->has($key), ['a', 'b', 'c']);
    }

    public function testManyRowsWithoutLocalFilesListTheFolderOnce()
    {
        $found = $this->checkRows($this->lookup(false, LibraryKeyLookup::LIST_ABOVE_KEYS + 1, ['a', 'c', 'other']));

        $this->assertSame([true, false, true], $found);
        $this->assertSame(1, $this->listings);
        $this->assertSame([], $this->existsChecks);
    }

    public function testManyRowsOnLocalStorageCheckEachFile()
    {
        $found = $this->checkRows($this->lookup(true, LibraryKeyLookup::LIST_ABOVE_KEYS + 1, ['a', 'c']));

        $this->assertSame([true, false, true], $found);
        $this->assertSame(0, $this->listings);
        $this->assertSame(['a', 'b', 'c'], $this->existsChecks);
    }

    public function testAPageOfRowsWithoutLocalFilesChecksEachFile()
    {
        $found = $this->checkRows($this->lookup(false, LibraryKeyLookup::LIST_ABOVE_KEYS, ['a', 'c', 'other']));

        $this->assertSame([true, false, true], $found);
        $this->assertSame(0, $this->listings);
        $this->assertSame(['a', 'b', 'c'], $this->existsChecks);
    }

    public function testOneRowWithoutLocalFilesChecksOnlyItsFile()
    {
        $this->assertTrue($this->lookup(false, 1, ['a'])->has('a'));

        $this->assertSame(0, $this->listings);
        $this->assertSame(['a'], $this->existsChecks);
    }

    public function testAStorageErrorWithoutLocalFilesHidesEveryRowAndIsLoggedOnce()
    {
        $found = $this->checkRows($this->lookup(false, LibraryKeyLookup::LIST_ABOVE_KEYS + 1, ['a'], true));

        $this->assertSame([false, false, false], $found);
        $this->assertSame(1, $this->listings);
        $this->assertCount(1, $this->errors);
    }

    public function testALocalStorageErrorStopsFurtherChecksAndIsLoggedOnce()
    {
        $found = $this->checkRows($this->lookup(true, LibraryKeyLookup::LIST_ABOVE_KEYS + 1, ['a'], true));

        $this->assertSame([false, false, false], $found);
        $this->assertSame(['a'], $this->existsChecks);
        $this->assertCount(1, $this->errors);
    }
}
