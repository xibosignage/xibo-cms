<?php
/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\Helper;

use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Library-file path resolver — boundary check that a relative path stays
 * inside the configured library root before it's passed to a filesystem
 * operation.
 *
 * Every XMDS file-read/write sink (web/xmds.php, lib/Xmds/Soap*.php,
 * lib/Xmds/Listeners/*) concatenates the configured `LIBRARY_LOCATION`
 * setting with a relative path. The relative paths come from a handful
 * of upstream sources — `RequiredFile.path` (DB row populated by event
 * listeners using literal prefixes + sanitized fileNames), `Media.storedAs`
 * (BlueImpUploadHandler basename + extension allowlist), `Font.fileName`
 * (FontFactory regex strip), `Asset.path` (#1491 prefix validation), or
 * integer IDs.
 *
 * Defended today by those upstream sanitizers, but a regression in any
 * upstream defense would propagate through every XMDS sink. Route every
 * `$libraryLocation . $relative` callsite through `LibraryFile::resolve()`
 * so the boundary is enforced explicitly at the sink rather than implicitly
 * by upstream convention.
 */
class LibraryFile
{
    /**
     * Resolve a library-relative path to an absolute filesystem path,
     * verifying the result stays under the library root.
     *
     * @param string $libraryLocation The configured LIBRARY_LOCATION (a trusted
     *                                server-side value with a trailing slash).
     * @param string $relativePath The relative path being concatenated. Must
     *                             not contain `..` segments or be absolute.
     * @return string The verified absolute path. For sinks that read existing
     *                files this is the realpath; for sinks that write new
     *                files it's the concatenated string (the string-level
     *                check is still applied).
     * @throws InvalidArgumentException If the relative path contains traversal
     *         segments, is absolute, or if the resolved path escapes the
     *         library root (e.g. via a misconfigured symlink that points
     *         outside).
     */
    public static function resolve(string $libraryLocation, string $relativePath): string
    {
        // Cheap pre-check on the raw string. Catches the common cases
        // without filesystem I/O. realpath() below catches symlink-escape
        // scenarios that this check would miss for files that exist.
        if (str_contains($relativePath, '..')
            || str_starts_with($relativePath, '/')
            || str_starts_with($relativePath, '\\')
        ) {
            throw new InvalidArgumentException(
                'Library path contains traversal or absolute components: ' . $relativePath,
                'path'
            );
        }

        $absolute = rtrim($libraryLocation, '/') . '/' . ltrim($relativePath, '/');

        // For paths that resolve to existing files, run a realpath() check to
        // catch symlink escape. New-file writes (e.g. screenshot upload) don't
        // exist yet — the pre-check above is the only defence in that case,
        // which is sufficient because new file creation can't follow a symlink
        // that doesn't exist.
        if (file_exists($absolute)) {
            $libraryRoot = realpath($libraryLocation);
            $resolvedFile = realpath($absolute);
            if ($libraryRoot === false || $resolvedFile === false) {
                throw new InvalidArgumentException(
                    'Library path could not be resolved: ' . $relativePath,
                    'path'
                );
            }
            // Allow $resolvedFile === $libraryRoot (resolving the root itself)
            // but require any other match to be strictly under it.
            if ($resolvedFile !== $libraryRoot
                && !str_starts_with($resolvedFile, $libraryRoot . DIRECTORY_SEPARATOR)
            ) {
                throw new InvalidArgumentException(
                    'Library path escapes library root: ' . $relativePath,
                    'path'
                );
            }
        }

        return $absolute;
    }
}
