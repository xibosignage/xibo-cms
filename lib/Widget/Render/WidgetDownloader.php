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

namespace Xibo\Widget\Render;

use Intervention\Image\ImageManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Media;
use Xibo\Helper\ByteRange;
use Xibo\Helper\CacheableUrlWindow;
use Xibo\Helper\HttpCacheProvider;
use Xibo\Helper\LibraryFile;
use Xibo\Helper\SendFile;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Storage\DownloadUrlUnavailableException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * A helper class to download widgets from the library (as media files)
 */
class WidgetDownloader
{
    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /**
     * @param string $libraryLocation Library location
     * @param string $sendFileMode Send file mode
     * @param int $resizeLimit CMS resize limit
     * @param LibraryStorageServiceInterface $libraryStorageService
     */
    public function __construct(
        private readonly string $libraryLocation,
        private readonly string $sendFileMode,
        private readonly int $resizeLimit,
        private readonly LibraryStorageServiceInterface $libraryStorageService
    ) {
    }

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @return $this
     */
    public function useLogger(LoggerInterface $logger): WidgetDownloader
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Return File
     * @param \Xibo\Entity\Media $media
     * @param \Slim\Http\ServerRequest $request
     * @param \Slim\Http\Response $response
     * @param string|null $contentType An optional content type, if provided the attachment is ignored
     * @param string|null $attachment An optional attachment, defaults to the stored file name (storedAs)
     * @param int|null $expiresAt when a redirect's URL expires, or null for the default
     * @return Response|ResponseInterface
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function download(
        Media $media,
        Request $request,
        Response $response,
        ?string $contentType = null,
        ?string $attachment = null,
        ?int $expiresAt = null
    ): Response|ResponseInterface {
        $this->logger->debug('widgetDownloader::download: Download for mediaId ' . $media->mediaId);

        $key = $media->storedAs;

        $this->logger->debug('widgetDownloader::download: ' . $key . ', ' . $contentType);

        try {
            $fileSize = $this->libraryStorageService->size($key);
        } catch (NotFoundException) {
            $this->logger->error('widgetDownloader::download: Library file missing for mediaId '
                . $media->mediaId . ' at ' . $key);
            throw new NotFoundException(__('The requested media file could not be found in the library.'));
        }

        // Set some headers
        $headers = [];

        // If we have been given a content type, then serve that to the browser.
        if ($contentType !== null) {
            $headers['Content-Type'] = $contentType;
        } else {
            // This widget is expected to output a file - usually this is for file based media
            // Get the name with library
            $attachmentName = empty($attachment) ? $media->storedAs : $attachment;

            $headers['Content-Type'] = 'application/octet-stream';
            $headers['Content-Transfer-Encoding'] = 'Binary';
            $headers['Content-disposition'] = SendFile::attachmentDisposition($attachmentName);
        }

        $hasLocalFiles = $this->libraryStorageService->hasLocalFiles();
        if (!$hasLocalFiles) {
            // Storage serves the file, including Range requests, with this type and disposition.
            $this->logger->debug('download: redirecting to library storage for mediaId ' . $media->mediaId);
            return SendFile::redirectToStorage(
                $response,
                $this->libraryStorageService,
                $key,
                $headers['Content-Type'],
                $headers['Content-disposition'] ?? 'inline',
                $expiresAt
            );
        }

        $headers['Content-Length'] = $fileSize;

        // Issue some caching headers - these apply whether we're serving a real content type
        // (e.g. streaming playback) or forcing an attachment download.
        $response = HttpCacheProvider::withEtag($response, $media->md5);
        $response = HttpCacheProvider::withExpires($response, '+1 week');

        if ($this->sendFileMode === 'Apache') {
            // Send via Apache X-Sendfile header?
            $headers['X-Sendfile'] = $this->libraryLocation . $key;
        } else if ($this->sendFileMode === 'Nginx') {
            // Send via Nginx X-Accel-Redirect?
            $headers['X-Accel-Redirect'] = '/download/' . $media->storedAs;
        }

        // Should we output the file via the application stack, or directly by reading the file.
        if ($this->sendFileMode == 'Off') {
            $this->logger->debug('download: Returning Stream with response body, sendfile not used.');

            $headers['Accept-Ranges'] = 'bytes';

            $rangeHeader = $request->getHeaderLine('Range');
            $range = ByteRange::parse($rangeHeader, $fileSize);
            if ($range === false) {
                return $response
                    ->withStatus(416)
                    ->withHeader('Content-Range', 'bytes */' . $fileSize);
            }

            if ($range !== null) {
                $this->logger->debug('download: Handling Range request, header: ' . $rangeHeader);

                [$start, $end] = $range;
                $headers['Content-Range'] = 'bytes ' . $start . '-' . $end . '/' . $fileSize;
                $headers['Content-Length'] = $end - $start + 1;

                try {
                    // Only the requested bytes are read, incrementally.
                    $body = $this->libraryStorageService->readRange($key, $start, $end - $start + 1);
                } catch (GeneralException $exception) {
                    $this->logger->error('download: problem reading Range for mediaId '
                        . $media->mediaId . '. ' . $exception->getMessage());
                    throw $exception;
                }

                $response = $response->withBody($body)->withStatus(206);
            } else {
                $response = $response->withBody($this->libraryStorageService->readStream($key));
            }
        } else {
            $this->logger->debug('Using sendfile to return the file, only output headers.');
        }

        // Add the headers we've collected to our response
        foreach ($headers as $header => $value) {
            $response = $response->withHeader($header, $value);
        }

        return $response;
    }

    /**
     * Download a thumbnail for the given media
     * @param \Xibo\Entity\Media $media
     * @param \Slim\Http\Response $response
     * @param PoolInterface $lockPool a missing thumbnail is made under a lock from this pool
     * @param string|null $errorThumb
     * @return \Slim\Http\Response
     */
    public function thumbnail(
        Media $media,
        Response $response,
        PoolInterface $lockPool,
        ?string $errorThumb = null
    ): Response {
        // One stored thumbnail serves the library table view (shown small) and the gallery view (shown larger).
        try {
            // Only an image is made on demand; others would need their whole file downloaded.
            if ($media->thumbnailVersion === null && $media->mediaType === 'image') {
                $this->fillThumbnail($media, $lockPool);
            }

            $key = $media->getThumbnailKey();
            if ($key === null) {
                throw new NotFoundException('No thumbnail stored for media ' . $media->mediaId);
            }

            // Media waiting to be copied keeps its thumbnail in the local library until it's moved.
            $storage = $media->getLocalThumbnailStorage() ?? $this->libraryStorageService;
            if (!$storage->hasLocalFiles()) {
                return SendFile::redirectToStorage(
                    $response,
                    $storage,
                    $key,
                    $media->getThumbnailContentType(),
                    'inline',
                    null,
                    CacheableUrlWindow::start(time()),
                    CacheableUrlWindow::cacheControl()
                );
            }

            try {
                $stream = $storage->readStream($key);
            } catch (NotFoundException) {
                $this->logger->error('thumbnail: ' . $key . ' is missing, making it again.');
                $this->fillThumbnail($media, $lockPool, $media->thumbnailVersion);
                $key = $media->getThumbnailKey()
                    ?? throw new NotFoundException('No thumbnail stored for media ' . $media->mediaId);
                $stream = ($media->getLocalThumbnailStorage() ?? $this->libraryStorageService)->readStream($key);
            }

            $response = HttpCacheProvider::withEtag($response, $media->thumbnailVersion)
                ->withHeader('Content-Type', $media->getThumbnailContentType())
                ->withBody($stream);
        } catch (DownloadUrlUnavailableException $exception) {
            // Answered with a 503, so the browser tries again.
            throw $exception;
        } catch (NotFoundException $exception) {
            // A video with no cover yet is expected, so not an error.
            $this->logger->debug('thumbnail: no source. ' . $exception->getMessage());
            if ($errorThumb !== null) {
                $img = ImageManager::gd()->read($errorThumb);
                $response->write($img->encode());
                $response = $response->withHeader('Content-Type', $img->origin()->mediaType());
            }
        } catch (\Exception $exception) {
            $this->logger->error('thumbnail: exception raised. ' . $exception->getMessage());

            if ($errorThumb !== null) {
                $img = ImageManager::gd()->read($errorThumb);
                $response->write($img->encode());

                // Output the mime type
                $response = $response->withHeader('Content-Type', $img->origin()->mediaType());
            }
        }

        return $response;
    }

    /**
     * @throws GeneralException if it isn't made, including while another request is making it
     */
    private function fillThumbnail(Media $media, PoolInterface $lockPool, ?string $staleVersion = null): void
    {
        if (!$media->fillThumbnail($lockPool, $staleVersion)) {
            throw new GeneralException('Thumbnail for media ' . $media->mediaId . ' is being made elsewhere.');
        }
    }

    /**
     * A local copy of $key for image previews, cached as tn_src_{key}_{modified time}.
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function getCachedPreviewSource(string $key): string
    {
        $prefix = Media::getPreviewSourceCachePrefix($key);
        $cachePath = LibraryFile::resolve(
            $this->libraryLocation,
            $prefix . $this->libraryStorageService->lastModified($key)
        );

        if (file_exists($cachePath)) {
            // Marks the copy as in use, as unused copies are removed after a week.
            @touch($cachePath);
            return $cachePath;
        }

        $tempCopyPath = $this->libraryStorageService->downloadToTemp($key);
        try {
            // Copied under a unique name first, so no request reads a half-written file.
            $partPath = $cachePath . '.' . bin2hex(random_bytes(4)) . '.part';
            if (!@copy($tempCopyPath, $partPath) || !@rename($partPath, $cachePath)) {
                @unlink($partPath);
                throw new GeneralException('Problem caching the preview source for: ' . $key);
            }
        } finally {
            $this->libraryStorageService->releaseTemp($tempCopyPath);
        }

        // Earlier versions of the file.
        Media::deleteCachedVersions($this->libraryLocation, $prefix, $cachePath);

        return $cachePath;
    }

    /**
     * Output an image preview
     * @param \Xibo\Support\Sanitizer\SanitizerInterface $params
     * @param string $filePath
     * @param \Slim\Http\Response $response
     * @param string|null $errorThumb
     * @return \Slim\Http\Response
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function imagePreview(
        SanitizerInterface $params,
        string $filePath,
        Response $response,
        ?string $errorThumb = null
    ): Response {
        // Image previews call for dynamically generated images as various sizes
        // for example a background image will stretch to the entire region
        // an image widget may be aspect, fit or scale
        $tempCopyPath = null;
        try {
            $key = $filePath;

            if ($this->libraryStorageService->hasLocalFiles()) {
                // Does it exist?
                if (!$this->libraryStorageService->exists($key)) {
                    throw new NotFoundException(__('File not found'));
                }

                // The library file itself.
                $filePath = $this->libraryStorageService->downloadToTemp($key);
                $tempCopyPath = $filePath;
            } else {
                // A cached local copy, as previews are requested at many sizes.
                $filePath = $this->getCachedPreviewSource($key);
            }

            // Check that our source image is not too large
            $imageInfo = getimagesize($filePath);

            // Make sure none of the sides are greater than allowed
            if ($this->resizeLimit > 0
                && ($imageInfo[0] > $this->resizeLimit || $imageInfo[1] > $this->resizeLimit)
            ) {
                throw new InvalidArgumentException(__('Image too large'));
            }

            // Continue to output at the desired size
            $width = intval($params->getDouble('width'));
            $height = intval($params->getDouble('height'));
            $proportional = !$params->hasParam('proportional')
                || $params->getCheckbox('proportional') == 1;

            $fit = $proportional && $params->getCheckbox('fit') === 1;

            // only use upsize constraint, if we the requested dimensions are larger than resize limit.
            $useUpsizeConstraint = max($width, $height) > $this->resizeLimit;

            $this->logger->debug('Whole file: ' . $filePath
                . ' requested with Width and Height ' . $width . ' x ' . $height
                . ', proportional: ' . var_export($proportional, true)
                . ', fit: ' . var_export($fit, true)
                . ', upsizeConstraint ' . var_export($useUpsizeConstraint, true));

            // Does the thumbnail exist already?
            $img = ImageManager::gd()->read($filePath);
            if ($tempCopyPath !== null) {
                $this->libraryStorageService->releaseTemp($tempCopyPath);
                $tempCopyPath = null;
            }

            // Output a specific width/height
            if ($width > 0 && $height > 0) {
                if ($fit) {
                    $img->cover($width, $height);
                } elseif ($proportional) {
                    // $useUpsizeConstraint previously added the upsize() constraint, which in
                    // Intervention v2 *prevents* enlarging beyond the source size - scaleDown() matches that.
                    if ($useUpsizeConstraint) {
                        $img->scaleDown($width, $height);
                    } else {
                        $img->scale($width, $height);
                    }
                } else {
                    $img->resize($width, $height);
                }
            }

            $response->write($img->encode());
            $response = HttpCacheProvider::withExpires($response, '+1 week');

            $response = $response->withHeader('Content-Type', $img->origin()->mediaType());
        } catch (\Exception $e) {
            if ($e instanceof NotFoundException) {
                $this->logger->debug('imagePreview: not found. ' . $e->getMessage());
            } else {
                $this->logger->error('imagePreview: exception raised. ' . $e->getMessage());
            }

            if ($errorThumb !== null) {
                $img = ImageManager::gd()->read($errorThumb);
                $response->write($img->encode());
                $response = $response->withHeader('Content-Type', $img->origin()->mediaType());
            } else {
                throw new InvalidArgumentException(__('Cannot parse image.'), 'storedAs');
            }
        } finally {
            if ($tempCopyPath !== null) {
                $this->libraryStorageService->releaseTemp($tempCopyPath);
            }
        }

        return $response;
    }
}
