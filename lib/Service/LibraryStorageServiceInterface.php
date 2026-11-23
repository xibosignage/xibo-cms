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

use Psr\Http\Message\StreamInterface;

/**
 * Reads and writes media library files by key, wherever the library is stored.
 */
interface LibraryStorageServiceInterface
{
    /** Seconds a direct download URL can be used to start a download. */
    public const DIRECT_DOWNLOAD_URL_TTL = 900;

    /** Seconds a user download URL given a start time can be used, counted from that start. */
    public const CACHEABLE_URL_TTL = 604800;

    /**
     * Moves a local file into storage under $key and removes the local file.
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function moveFromLocalPath(string $localSourcePath, string $key): void;

    /**
     * Saves in-place edits made to a downloadToTemp() copy of $key back to storage.
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function storeFromTemp(string $tempCopyPath, string $key): void;

    /**
     * Copies $sourceKey to $destKey inside storage.
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function copy(string $sourceKey, string $destKey): void;

    /**
     * Deletes $key, doing nothing if it does not exist.
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function delete(string $key): void;

    /**
     * Whether $key exists.
     * @throws \Xibo\Support\Exception\GeneralException if storage cannot be reached
     */
    public function exists(string $key): bool;

    /**
     * The size of $key in bytes.
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function size(string $key): int;

    /**
     * When $key was last written, as a Unix timestamp.
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function lastModified(string $key): int;

    /**
     * A local path holding the bytes of $key, to be released with releaseTemp().
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function downloadToTemp(string $key): string;

    /**
     * A stream of the whole of $key, read incrementally.
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function readStream(string $key): StreamInterface;

    /**
     * A stream of $length bytes of $key from $offset, clipped to the end of the file.
     * @throws \Xibo\Support\Exception\NotFoundException
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function readRange(string $key, int $offset, int $length): StreamInterface;

    /**
     * Releases a downloadToTemp() path; never unlink it directly, as on local storage it is the library file.
     */
    public function releaseTemp(string $path): void;

    /**
     * The files under the folder $prefix, with keys in the same form the other methods take.
     * @param bool $recursive whether to include the files in its sub-folders
     * @return iterable<array{key: string, lastModified: ?int, size: ?int, etag?: ?string}> etag only when the store
     *     provides one
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function listKeys(string $prefix = '', bool $recursive = false): iterable;

    /**
     * A time-limited URL players can download $key from directly, or null when keys are local files the CMS sends.
     * @param int|null $expiresAt when the URL expires, or null for DIRECT_DOWNLOAD_URL_TTL from now
     * @throws \Xibo\Storage\DownloadUrlUnavailableException if no URL can be made
     */
    public function getDirectDownloadUrl(string $key, ?int $expiresAt = null): ?string;

    /**
     * A time-limited URL a user can download $key from directly, answered with the given headers, or null when keys
     * are local files the CMS sends.
     * @param int|null $validFrom when set, the URL is made as of this time and lasts CACHEABLE_URL_TTL from it, so
     *                           every URL made with the same start is identical
     * @param string|null $cacheControl the Cache-Control header to answer with
     * @param int|null $expiresAt when the URL expires, or null for DIRECT_DOWNLOAD_URL_TTL from now
     * @throws \Xibo\Storage\DownloadUrlUnavailableException if no URL can be made
     */
    public function getUserDownloadUrl(
        string $key,
        string $contentType,
        string $contentDisposition,
        ?int $validFrom = null,
        ?string $cacheControl = null,
        ?int $expiresAt = null
    ): ?string;

    /**
     * Identifies this store, so journalled work is only carried out against the store it was recorded for.
     * @throws \Xibo\Support\Exception\GeneralException if storage is unusable
     */
    public function getLocation(): string;

    /**
     * Whether keys are local files the web server can send itself.
     */
    public function hasLocalFiles(): bool;
}
