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

namespace Xibo\Helper;

use Stash\Interfaces\PoolInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Caches the size and md5 of small stored files so RequiredFiles reads no storage.
 */
class StoredFileMetadata
{
    /** How long metadata is kept, bounding how long it can disagree with a racing write from another web server. */
    private const TTL = 300;

    /** How long a missing file is remembered, so each request doesn't look for it again. */
    private const MISSING_TTL = 60;

    /**
     * Records the metadata of a file stored with $contents.
     * @return array{size: int, md5: string}
     */
    public static function remember(PoolInterface $pool, string $cacheKey, string $contents): array
    {
        $metadata = ['size' => strlen($contents), 'md5' => md5($contents)];

        $item = $pool->getItem($cacheKey);
        $item->set($metadata);
        $item->expiresAfter(self::TTL);
        $pool->save($item);

        return $metadata;
    }

    /**
     * Stores $contents under $key through a temp file in $tempFolder, and records their metadata.
     * @throws GeneralException
     */
    public static function store(
        PoolInterface $pool,
        string $cacheKey,
        LibraryStorageServiceInterface $storage,
        string $key,
        string $contents,
        string $tempFolder
    ): void {
        self::storeContents($storage, $key, $contents, $tempFolder);
        self::remember($pool, $cacheKey, $contents);
    }

    /**
     * Stores $contents under $key through a temp file in $tempFolder.
     * @throws GeneralException
     */
    public static function storeContents(
        LibraryStorageServiceInterface $storage,
        string $key,
        string $contents,
        string $tempFolder
    ): void {
        $tempPath = $tempFolder . 'stored_' . bin2hex(random_bytes(8)) . '_' . str_replace('/', '_', $key);
        if (file_put_contents($tempPath, $contents) === false) {
            throw new GeneralException('Unable to write ' . $tempPath);
        }

        try {
            $storage->moveFromLocalPath($tempPath, $key);
        } finally {
            // Moved on success; still here after a failure.
            @unlink($tempPath);
        }
    }

    /**
     * The cached metadata, or the file's metadata read from storage on a miss.
     * @param \Closure(): LibraryStorageServiceInterface $storageFactory the storage, fetched only on a miss
     * @return array{size: int, md5: string}
     * @throws NotFoundException if the file isn't in storage
     * @throws GeneralException if storage cannot be reached
     */
    public static function get(
        PoolInterface $pool,
        string $cacheKey,
        \Closure $storageFactory,
        string $key
    ): array {
        $item = $pool->getItem($cacheKey);
        if ($item->isHit() && is_array($item->get())) {
            if ($item->get()['missing'] ?? false) {
                throw new NotFoundException('Not found: ' . $key);
            }
            return $item->get();
        }

        try {
            $stream = $storageFactory()->readStream($key);
            try {
                $contents = $stream->getContents();
            } finally {
                $stream->close();
            }
        } catch (NotFoundException $exception) {
            $item->set(['missing' => true]);
            $item->expiresAfter(self::MISSING_TTL);
            $pool->save($item);
            throw $exception;
        }

        return self::remember($pool, $cacheKey, $contents);
    }

    public static function forget(PoolInterface $pool, string $cacheKey): void
    {
        $pool->deleteItem($cacheKey);
    }
}
