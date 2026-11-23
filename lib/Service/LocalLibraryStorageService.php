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

namespace Xibo\Service;

use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use Xibo\Helper\LibraryFile;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Stores media library files in the local library folder, resolving every key through LibraryFile::resolve().
 */
class LocalLibraryStorageService implements LibraryStorageServiceInterface
{
    public function __construct(private readonly string $libraryLocation)
    {
    }

    private function resolve(string $key): string
    {
        return LibraryFile::resolve($this->libraryLocation, $key);
    }

    public function moveFromLocalPath(string $localSourcePath, string $key): void
    {
        $destination = $this->resolve($key);

        $folder = dirname($destination);
        if (!is_dir($folder)) {
            @mkdir($folder, 0777, true);
        }

        // Move, falling back to copy and delete across filesystems.
        $moved = @rename($localSourcePath, $destination);

        if (!$moved) {
            $moved = @copy($localSourcePath, $destination);
            if ($moved) {
                @unlink($localSourcePath);
            }
        }

        if (!$moved) {
            throw new GeneralException(
                'Problem moving file into the Library Folder: ' . $localSourcePath . ' -> ' . $key
            );
        }
    }

    public function storeFromTemp(string $tempCopyPath, string $key): void
    {
        // The temporary copy path is the library file itself, so edits are already stored.
    }

    public function copy(string $sourceKey, string $destKey): void
    {
        if (!@copy($this->resolve($sourceKey), $this->resolve($destKey))) {
            throw new GeneralException('Problem copying file in the Library Folder: ' . $sourceKey);
        }
    }

    public function delete(string $key): void
    {
        $path = $this->resolve($key);
        if (file_exists($path) && !@unlink($path)) {
            throw new GeneralException('Problem deleting file from the Library Folder: ' . $key);
        }
    }

    public function exists(string $key): bool
    {
        try {
            return file_exists($this->resolve($key));
        } catch (InvalidArgumentException) {
            // A key outside the library does not exist.
            return false;
        }
    }

    public function size(string $key): int
    {
        $path = $this->resolve($key);
        if (!file_exists($path)) {
            throw new NotFoundException('Library file not found: ' . $key);
        }

        $size = @filesize($path);
        if ($size === false) {
            throw new GeneralException('Problem reading size of library file: ' . $key);
        }

        return $size;
    }

    public function lastModified(string $key): int
    {
        $path = $this->resolve($key);
        if (!file_exists($path)) {
            throw new NotFoundException('Library file not found: ' . $key);
        }

        $modified = @filemtime($path);
        if ($modified === false) {
            throw new GeneralException('Problem reading the modified date of library file: ' . $key);
        }

        return $modified;
    }

    public function downloadToTemp(string $key): string
    {
        $path = $this->resolve($key);
        if (!file_exists($path)) {
            throw new NotFoundException('Library file not found: ' . $key);
        }

        // The library file itself serves as the temporary copy.
        return $path;
    }

    public function readStream(string $key): StreamInterface
    {
        return new Stream($this->open($key));
    }

    public function readRange(string $key, int $offset, int $length): StreamInterface
    {
        LibraryFile::assertValidRange($key, $offset, $length);

        return new LimitStream(new Stream($this->open($key)), $length, $offset);
    }

    /**
     * @return resource
     * @throws NotFoundException|GeneralException
     */
    private function open(string $key)
    {
        $path = $this->resolve($key);
        if (!file_exists($path)) {
            throw new NotFoundException('Library file not found: ' . $key);
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new GeneralException('Problem opening library file: ' . $key);
        }

        return $handle;
    }

    public function listKeys(string $prefix = '', bool $recursive = false): iterable
    {
        $directory = $prefix === '' ? rtrim($this->libraryLocation, '/') : $this->resolve($prefix);

        // A sub-folder nothing has been written to yet holds no files.
        if ($prefix !== '' && !is_dir($directory)) {
            return;
        }

        $entries = @scandir($directory);
        if ($entries === false) {
            throw new GeneralException('Problem listing the Library Folder: ' . $prefix);
        }

        $keyPrefix = $prefix === '' ? '' : rtrim($prefix, '/') . '/';
        foreach ($entries as $entry) {
            $path = $directory . '/' . $entry;
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (is_dir($path)) {
                if ($recursive) {
                    yield from $this->listKeys($keyPrefix . $entry, true);
                }
                continue;
            }

            if (!is_file($path)) {
                continue;
            }

            $modified = @filemtime($path);
            $size = @filesize($path);
            yield [
                'key' => $keyPrefix . $entry,
                'lastModified' => $modified === false ? null : $modified,
                'size' => $size === false ? null : $size,
            ];
        }
    }

    public function releaseTemp(string $path): void
    {
        // The temporary copy path is the library file itself, so it is never deleted.
    }

    public function getDirectDownloadUrl(string $key, ?int $expiresAt = null): ?string
    {
        // Local files are sent by the web server (X-Sendfile/X-Accel-Redirect).
        return null;
    }

    public function getUserDownloadUrl(
        string $key,
        string $contentType,
        string $contentDisposition,
        ?int $validFrom = null,
        ?string $cacheControl = null,
        ?int $expiresAt = null
    ): ?string {
        return null;
    }

    public function getLocation(): string
    {
        return 'local:' . rtrim($this->libraryLocation, '/') . '/';
    }

    public function hasLocalFiles(): bool
    {
        return true;
    }
}
