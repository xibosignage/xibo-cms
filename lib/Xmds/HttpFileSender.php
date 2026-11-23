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

namespace Xibo\Xmds;

use GuzzleHttp\Psr7\MimeType;
use Psr\Log\LoggerInterface;
use Xibo\Entity\Bandwidth;
use Xibo\Entity\Display;
use Xibo\Entity\Layout;
use Xibo\Entity\RequiredFile;
use Xibo\Factory\BandwidthFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\RequiredFileFactory;
use Xibo\Helper\ByteRange;
use Xibo\Helper\LibraryFile;
use Xibo\Helper\SendFile;
use Xibo\Service\LibraryAsset;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Sends a file requested with a signed xmds.php link: a local file, or a stored one streamed by the CMS.
 */
class HttpFileSender
{
    public function __construct(
        private readonly HttpOutputInterface $output,
        private readonly LibraryStorageServiceInterface $mediaStorage,
        private readonly LayoutFactory $layoutFactory,
        private readonly RequiredFileFactory $requiredFileFactory,
        private readonly PlayerFileLink $playerFileLink,
        private readonly BandwidthFactory $bandwidthFactory,
        private readonly StorageServiceInterface $store,
        private readonly LoggerInterface $logger,
        private readonly string $sendFileMode,
        private readonly string $libraryLocation,
        private readonly LibraryStorageRegistryInterface $storageRegistry
    ) {
    }

    /**
     * @param array<string, string> $server the request's $_SERVER values, for HEAD and Range requests
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function send(RequiredFile $file, Display $display, array $server = []): void
    {
        [$storage, $key] = $this->getStorageFor($file);
        $hasLocalFiles = $storage === null || $storage->hasLocalFiles();
        $isRewrittenCss = $display->isPwa() && $this->isCss($file);

        if ($file->type === RequiredFile::$TYPE_LAYOUT) {
            $this->rebuildMissingXlf((int)$file->itemId, $hasLocalFiles ? null : $storage, $key);
        }

        $this->sendContentType($file, $hasLocalFiles);

        if ($isRewrittenCss) {
            $this->sendRewrittenCss($file, $display, $hasLocalFiles ? null : $storage, $key);
        } else if ($hasLocalFiles) {
            $this->sendLocalFile($file, $key);
        } else {
            $this->sendStoredFile($file, $storage, $key, $server);
        }
    }

    /**
     * The storage and key of the requested file, with no storage for a local file outside the library.
     * @return array{0: ?LibraryStorageServiceInterface, 1: string}
     */
    private function getStorageFor(RequiredFile $file): array
    {
        if ($file->type === RequiredFile::$TYPE_LAYOUT) {
            return [$this->mediaStorage, Layout::getXlfKey((int)$file->itemId)];
        }

        // A dependency is sent from its own storage when its listener gives one, otherwise from the library.
        $storage = $file->type === RequiredFile::$TYPE_DEPENDENCY ? $file->getUnmatchedProperty('storage') : null;
        if ($storage !== null) {
            return [$storage, (string)$file->getUnmatchedProperty('storageKey')];
        }

        return [
            $file->type === RequiredFile::$TYPE_MEDIA ? $this->getMediaStorage($file) : null,
            (string)$file->path,
        ];
    }

    /**
     * Where a media file is, which is the local library while it waits to be copied to the configured store.
     */
    private function getMediaStorage(RequiredFile $file): LibraryStorageServiceInterface
    {
        // Nothing waits to be copied into the local library the CMS already uses, or once copying has finished.
        if ($this->mediaStorage->hasLocalFiles() || !$this->storageRegistry->hasFilesToMove()) {
            return $this->mediaStorage;
        }

        $storageEngine = $this->store->select(
            'SELECT `storageEngine` FROM `media` WHERE `mediaId` = :mediaId',
            ['mediaId' => (int)$file->itemId]
        )[0]['storageEngine'] ?? null;

        return $this->storageRegistry->get(LibraryAsset::MEDIA, $storageEngine);
    }

    /**
     * Builds the XLF again only when the file is missing.
     * @param LibraryStorageServiceInterface|null $storage the service holding it, or null for a local file
     */
    private function rebuildMissingXlf(int $layoutId, ?LibraryStorageServiceInterface $storage, string $key): void
    {
        $exists = $storage === null
            ? is_file(LibraryFile::resolve($this->libraryLocation, $key))
            : $storage->exists($key);

        if (!$exists) {
            $this->layoutFactory->rebuildMissingXlf($this->layoutFactory->getById($layoutId));
            $this->store->commitIfNecessary();
        }
    }

    private function isCss(RequiredFile $file): bool
    {
        return $file->fileType === 'fontCss' || str_ends_with((string)$file->path, '.css');
    }

    private function sendContentType(RequiredFile $file, bool $hasLocalFiles): void
    {
        if ($file->type === RequiredFile::$TYPE_LAYOUT) {
            $contentType = 'text/xml';
        } else if ($file->fileType === 'bundle' || str_ends_with((string)$file->path, '.js')) {
            $contentType = 'application/javascript';
        } else if ($this->isCss($file)) {
            $contentType = 'text/css';
        } else if ($hasLocalFiles) {
            $contentType = @mime_content_type(LibraryFile::resolve($this->libraryLocation, (string)$file->path));
        } else {
            $contentType = MimeType::fromFilename((string)$file->path);
        }

        if (!empty($contentType)) {
            $this->output->header('Content-Type: ' . $contentType);
        }
    }

    /**
     * Sends a PWA's CSS with each file it uses linked for the PWA.
     * @param LibraryStorageServiceInterface|null $storage the storage to read it from, or null for a local file
     */
    private function sendRewrittenCss(
        RequiredFile $file,
        Display $display,
        ?LibraryStorageServiceInterface $storage,
        string $key
    ): void {
        $this->logger->debug('Rewriting CSS for PWA: ' . $file->path);

        $css = $storage === null
            ? file_get_contents(LibraryFile::resolve($this->libraryLocation, (string)$file->path))
            : $storage->readStream($key)->getContents();

        // Each match is replaced on its own, so a file used twice is only substituted once.
        $this->output->write($this->linkCssFiles($css, $file, $display));
    }

    private function linkCssFiles(string $css, RequiredFile $file, Display $display): string
    {
        return preg_replace_callback('/url\(\'?(.*?)\'?\)/', function ($matches) use ($file, $display) {
            try {
                $linked = $this->requiredFileFactory->getByDisplayAndDependencyPath($display->displayId, $matches[1]);
                [$url] = $this->playerFileLink->get(
                    $display,
                    RequiredFile::$TYPE_DEPENDENCY,
                    $linked->realId,
                    $linked->path,
                    $file->fileType === 'fontCss' ? 'font' : 'asset',
                    true,
                );

                return 'url(\'' . $url . '\')';
            } catch (\Exception) {
                $this->logger->error('CSS has dependency which does not exist in Required Files: ' . $matches[1]);
                return $matches[0];
            }
        }, $css);
    }

    /**
     * Streams a file the CMS doesn't hold locally, in chunks so a large video isn't read into memory, with HEAD
     * and a single byte Range supported and only the bytes sent counted.
     * @param array<string, string> $server the request's $_SERVER values
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function sendStoredFile(
        RequiredFile $file,
        LibraryStorageServiceInterface $storage,
        string $key,
        array $server
    ): void {
        $this->logger->info('HTTP GetFile: streaming ' . $file->path . ' from storage');

        $sent = 0;
        try {
            // A stream may not know its size, so ask for it, which also lets the player spot a cut-off body.
            $size = $storage->size($key);
            SendFile::disableZlibCompression();
            $this->output->header('Accept-Ranges: bytes');

            // There's no validator to compare If-Range with, so a conditional range gets the whole file.
            $range = isset($server['HTTP_IF_RANGE']) ? null : ByteRange::parse($server['HTTP_RANGE'] ?? '', $size);
            if ($range === false) {
                $this->output->status(416);
                $this->output->header('Content-Range: bytes */' . $size);
                return;
            }

            [$start, $end] = $range ?? [0, $size - 1];
            $length = $end - $start + 1;
            $isHead = ($server['REQUEST_METHOD'] ?? 'GET') === 'HEAD';

            // Opened before the headers, so a failure to read it isn't sent with this file's length.
            $stream = $length <= 0 || $isHead
                ? null
                : ($range === null ? $storage->readStream($key) : $storage->readRange($key, $start, $length));

            if ($range !== null) {
                $this->output->status(206);
                $this->output->header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            }
            $this->output->header('Content-Length: ' . $length);

            if ($stream === null) {
                return;
            }

            // Kept running when the player goes, so the bytes it did receive are counted.
            ignore_user_abort(true);
            set_time_limit(0);
            try {
                while (!$stream->eof()) {
                    $chunk = $stream->read(1024 * 1024);
                    $this->output->write($chunk);
                    $sent += strlen($chunk);
                    if (!$stream->eof() && $this->output->isAborted()) {
                        break;
                    }
                }
            } finally {
                $stream->close();
            }
        } catch (\Throwable $exception) {
            // The caller's handling can't say which file failed, e.g. a missing one is logged as an unknown nonce.
            $this->logger->error('HTTP GetFile: streaming ' . $file->path . ' failed after ' . $sent
                . ' bytes. ' . $exception->getMessage());
            throw $exception;
        } finally {
            // Bytes sent before a failure were still used.
            if ($sent > 0) {
                $this->bandwidthFactory->createAndSave(Bandwidth::$GETFILE, $file->displayId, $sent);
            }
        }
    }

    private function sendLocalFile(RequiredFile $file, string $key): void
    {
        $relativePath = $file->type === RequiredFile::$TYPE_LAYOUT ? $key : (string)$file->path;
        $resolvedPath = LibraryFile::resolve($this->libraryLocation, $relativePath);
        $this->logger->info('HTTP GetFile request redirecting to ' . $resolvedPath);

        if ($this->sendFileMode === 'Apache') {
            $this->output->header('X-Sendfile: ' . $resolvedPath);
        } else if ($this->sendFileMode === 'Nginx') {
            // The path was checked by LibraryFile::resolve(), so it is safe as the internal redirect suffix.
            $this->output->header('X-Accel-Redirect: /download/' . $relativePath);
        } else {
            $this->output->status(404);
        }

        $this->bandwidthFactory->createAndSave(Bandwidth::$GETFILE, $file->displayId, $file->size);
    }
}
