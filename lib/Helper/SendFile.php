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

use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Slim\Http\Response;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Storage\DownloadUrlUnavailableException;

/**
 * Class SendFile
 * @package Xibo\Helper
 */
class SendFile
{
    /**
     * @param \Slim\Http\Response $response
     * @param string $sendFile
     * @param string $filePath
     * @param string|null $name
     * @param bool $zlibOff
     * @param string $nginxPath the Nginx internal location the file is under
     * @return \Slim\Http\Response
     */
    public static function decorateResponse(
        $response,
        $sendFile,
        $filePath,
        $name = null,
        $zlibOff = true,
        string $nginxPath = '/download/temp/'
    ):? Response {
        if ($zlibOff) {
            self::disableZlibCompression();
        }

        $baseName = basename($filePath);
        $response = self::withDownloadHeaders($response, $name === null ? $baseName : $name, filesize($filePath));

        // Send via Apache X-Sendfile header?
        if ($sendFile == 'Apache') {
            $response = $response->withHeader('X-Sendfile', $filePath);
        } else if ($sendFile == 'Nginx') {
            // Send via Nginx X-Accel-Redirect?
            $response = $response->withHeader('X-Accel-Redirect', $nginxPath . $baseName);
        } else {
            $response = $response->withBody(new Stream(fopen($filePath, 'r')));
        }

        return $response;
    }

    /**
     * Decorates the response with a stream body, for a file with no local path the web server could send.
     * @param \Slim\Http\Response $response
     * @param StreamInterface $stream
     * @param int $size
     * @param string $name the download file name
     * @return \Slim\Http\Response
     */
    public static function decorateStreamResponse($response, StreamInterface $stream, int $size, string $name)
    {
        self::disableZlibCompression();

        return self::withDownloadHeaders($response, $name, $size)->withBody($stream);
    }

    /**
     * Serves a stored file, streaming it from storage or passing its local path to $sendLocalFile.
     * @param LibraryStorageServiceInterface $storage
     * @param string $key
     * @param callable(StreamInterface, int): ResponseInterface $sendStream
     * @param callable(string): ResponseInterface $sendLocalFile
     * @return ResponseInterface
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public static function fromStorage(
        LibraryStorageServiceInterface $storage,
        string $key,
        callable $sendStream,
        callable $sendLocalFile
    ): ResponseInterface {
        if (!$storage->hasLocalFiles()) {
            // Size first, so a failure there doesn't leave an open stream.
            $size = $storage->size($key);
            return $sendStream($storage->readStream($key), $size);
        }

        $localPath = $storage->downloadToTemp($key);
        try {
            return $sendLocalFile($localPath);
        } finally {
            $storage->releaseTemp($localPath);
        }
    }

    /**
     * Sends a stored file: a redirect to it when storage has no local files, else its local path to $sendLocalFile.
     * @param callable(string): ResponseInterface $sendLocalFile
     * @param int|null $validFrom when set, the redirect is to a URL that stays the same for a window (see redirectToStorage)
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public static function download(
        ResponseInterface $response,
        LibraryStorageServiceInterface $storage,
        string $key,
        string $contentType,
        string $contentDisposition,
        callable $sendLocalFile,
        ?int $validFrom = null,
        ?string $cacheControl = null
    ): ResponseInterface {
        if (!$storage->hasLocalFiles()) {
            return self::redirectToStorage(
                $response,
                $storage,
                $key,
                $contentType,
                $contentDisposition,
                null,
                $validFrom,
                $cacheControl
            );
        }

        $localPath = $storage->downloadToTemp($key);
        try {
            return $sendLocalFile($localPath);
        } finally {
            $storage->releaseTemp($localPath);
        }
    }

    /**
     * A redirect to a time-limited URL for $key in storage that has no local files.
     * @param string $contentType the type the storage answers with
     * @param string $contentDisposition the disposition the storage answers with
     * @param int|null $expiresAt when the URL expires, or null for the default
     * @param int|null $validFrom when set, the URL is signed as of this time, so it is the same for a whole window
     * @param string|null $cacheControl the Cache-Control header storage answers with
     * @throws \Xibo\Storage\DownloadUrlUnavailableException
     */
    public static function redirectToStorage(
        ResponseInterface $response,
        LibraryStorageServiceInterface $storage,
        string $key,
        string $contentType,
        string $contentDisposition,
        ?int $expiresAt = null,
        ?int $validFrom = null,
        ?string $cacheControl = null
    ): ResponseInterface {
        $url = $storage->getUserDownloadUrl(
            $key,
            $contentType,
            $contentDisposition,
            $validFrom,
            $cacheControl,
            $expiresAt
        )
            ?? throw new DownloadUrlUnavailableException(__('The file cannot be downloaded right now.'));

        // Never cached, because the URL expires.
        return $response
            ->withStatus(302)
            ->withHeader('Location', $url)
            ->withHeader('Cache-Control', 'no-store');
    }

    /**
     * An attachment Content-Disposition for $name, with an ASCII fallback and an RFC 5987 UTF-8 name.
     */
    public static function attachmentDisposition(string $name): string
    {
        // Anything outside printable ASCII, plus quotes and backslashes, becomes _ in the fallback.
        $pattern = '/[^\x20\x21\x23-\x5B\x5D-\x7E]/';
        $fallback = preg_replace($pattern . 'u', '_', $name) ?? preg_replace($pattern, '_', $name);

        return 'attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name);
    }

    public static function disableZlibCompression(): void
    {
        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }
    }

    /**
     * @param \Slim\Http\Response $response
     * @param string $name
     * @param int|false $size
     * @return \Slim\Http\Response
     */
    private static function withDownloadHeaders($response, string $name, $size)
    {
        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename=' . $name)
            ->withHeader('Content-Transfer-Encoding', 'Binary')
            ->withHeader('Content-Length', $size);
    }
}
