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

use PHPUnit\Framework\TestCase;
use Xibo\Helper\LibraryFile;
use Xibo\Support\Exception\InvalidArgumentException;

class LibraryFileTest extends TestCase
{
    private string $libraryLocation;

    protected function setUp(): void
    {
        $this->libraryLocation = sys_get_temp_dir() . '/xibo-libraryfile-test-' . uniqid() . '/';
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

    public function testResolveReturnsTheConcatenatedAbsolutePathForAnOrdinaryKey()
    {
        $resolved = LibraryFile::resolve($this->libraryLocation, 'tn_5.jpg');
        $this->assertSame(rtrim($this->libraryLocation, '/') . '/tn_5.jpg', $resolved);
    }

    public function testResolveThrowsOnDotDotTraversal()
    {
        $this->expectException(InvalidArgumentException::class);
        LibraryFile::resolve($this->libraryLocation, 'tn_../../../etc/passwd');
    }

    public function testResolveThrowsOnAbsolutePathWithForwardSlash()
    {
        $this->expectException(InvalidArgumentException::class);
        LibraryFile::resolve($this->libraryLocation, '/etc/passwd');
    }

    public function testResolveThrowsOnAbsolutePathWithBackslash()
    {
        $this->expectException(InvalidArgumentException::class);
        LibraryFile::resolve($this->libraryLocation, '\\windows\\system32');
    }

    public function testResolveThrowsWhenAnExistingFileEscapesTheLibraryRootViaSymlink()
    {
        // A symlink pointing outside the library is caught by the realpath check.
        $outside = sys_get_temp_dir() . '/xibo-libraryfile-outside-' . uniqid() . '.txt';
        file_put_contents($outside, 'secret');
        $linkName = 'innocent-looking-name.txt';
        symlink($outside, rtrim($this->libraryLocation, '/') . '/' . $linkName);

        try {
            $this->expectException(InvalidArgumentException::class);
            LibraryFile::resolve($this->libraryLocation, $linkName);
        } finally {
            @unlink($outside);
        }
    }

    public function testResolveAllowsAKeyThatDoesNotYetExist()
    {
        // A file that doesn't exist yet only gets the string-level check.
        $resolved = LibraryFile::resolve($this->libraryLocation, 'tn_not-created-yet.jpg');
        $this->assertSame(rtrim($this->libraryLocation, '/') . '/tn_not-created-yet.jpg', $resolved);
    }
}
