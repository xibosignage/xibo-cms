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

namespace Xibo\Widget\Render;

use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Stream;
use Intervention\Image\ImageManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\Media;
use Xibo\Helper\HttpCacheProvider;
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
     */
    public function __construct(
        private readonly string $libraryLocation,
        private readonly string $sendFileMode,
        private readonly int $resizeLimit
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
     * @return Response|ResponseInterface
     */
    public function download(
        Media $media,
        Request $request,
        Response $response,
        ?string $contentType = null,
        ?string $attachment = null
    ): Response|ResponseInterface {
        $this->logger->debug('widgetDownloader::download: Download for mediaId ' . $media->mediaId);

        // The file path
        $libraryPath = $this->libraryLocation . $media->storedAs;

        $this->logger->debug('widgetDownloader::download: ' . $libraryPath . ', ' . $contentType);

        // Set some headers
        $headers = [];
        $fileSize = filesize($libraryPath);
        $headers['Content-Length'] = $fileSize;

        // Issue some caching headers - these apply whether we're serving a real content type
        // (e.g. streaming playback) or forcing an attachment download.
        $response = HttpCacheProvider::withEtag($response, $media->md5);
        $response = HttpCacheProvider::withExpires($response, '+1 week');

        // If we have been given a content type, then serve that to the browser.
        if ($contentType !== null) {
            $headers['Content-Type'] = $contentType;
        } else {
            // This widget is expected to output a file - usually this is for file based media
            // Get the name with library
            $attachmentName = empty($attachment) ? $media->storedAs : $attachment;

            $headers['Content-Type'] = 'application/octet-stream';
            $headers['Content-Transfer-Encoding'] = 'Binary';
            $headers['Content-disposition'] = 'attachment; filename="' . $attachmentName . '"';
        }

        // Output the file
        if ($this->sendFileMode === 'Apache') {
            // Send via Apache X-Sendfile header?
            $headers['X-Sendfile'] = $libraryPath;
        } else if ($this->sendFileMode === 'Nginx') {
            // Send via Nginx X-Accel-Redirect?
            $headers['X-Accel-Redirect'] = '/download/' . $media->storedAs;
        }

        // Should we output the file via the application stack, or directly by reading the file.
        if ($this->sendFileMode == 'Off') {
            // Return the file with PHP
            $this->logger->debug('download: Returning Stream with response body, sendfile off.');

            $stream = new Stream(fopen($libraryPath, 'r'));
            $start = 0;
            $end = $fileSize - 1;

            $headers['Accept-Ranges'] = 'bytes';

            $rangeHeader = $request->getHeaderLine('Range');
            if ($rangeHeader !== '') {
                $this->logger->debug('download: Handling Range request, header: ' . $rangeHeader);

                if (preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches)) {
                    $start = (int) $matches[1];
                    $end = $matches[2] !== '' ? (int) $matches[2] : $end;
                    if ($start > $end || $end >= $fileSize) {
                        return $response
                            ->withStatus(416)
                            ->withHeader('Content-Range', 'bytes */' . $fileSize);
                    }
                }
                $headers['Content-Range'] = 'bytes ' . $start . '-' . $end . '/' . $fileSize;
                $headers['Content-Length'] = $end - $start + 1;
                $response = $response
                    ->withBody(new LimitStream($stream, $end - $start + 1, $start))
                    ->withStatus(206);
            } else {
                $response = $response->withBody($stream);
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
     * @param string|null $errorThumb
     * @return \Slim\Http\Response
     */
    public function thumbnail(
        Media $media,
        Response $response,
        ?string $errorThumb = null
    ): Response {
        // Our convention is to upload media covers in {mediaId}_{mediaType}cover.png
        // and then thumbnails in tn_{mediaId}_{mediaType}cover.png
        // unless we are an image module, which is its own image, and would then have a thumbnail in
        // tn_{mediaId}_{mediaType}cover.png
        // This single cached thumbnail is shared by the library table view (shown small) and the
        // gallery view (shown larger) - sized for the gallery, downscaled by the browser for the table.
        try {
            $width = 320;
            $height = 320;

            if ($media->mediaType === 'image') {
                $filePath = $this->libraryLocation . $media->storedAs;
                $thumbnailFilePath = $this->libraryLocation . 'tn_' . $media->storedAs;
            } else {
                $filePath = $this->libraryLocation . $media->mediaId . '_'
                    . $media->mediaType . 'cover.png';
                $thumbnailFilePath = $this->libraryLocation . 'tn_' . $media->mediaId . '_'
                    . $media->mediaType . 'cover.png';

                // A video cover might not exist
                if (!file_exists($filePath)) {
                    throw new NotFoundException();
                }
            }

            // Does the thumbnail exist already?
            $manager = ImageManager::gd();
            $img = null;
            $regenerate = true;
            if (file_exists($thumbnailFilePath)) {
                $img = $manager->read($thumbnailFilePath);
                if ($img->width() === $width || $img->height() === $height) {
                    // Correct cache
                    $regenerate = false;
                }
                $response = $response->withHeader('Content-Type', $img->origin()->mediaType());
            }

            if ($regenerate) {
                // Check that our source image is not too large
                $imageInfo = getimagesize($filePath);

                // Make sure none of the sides are greater than allowed
                if ($this->resizeLimit > 0
                    && ($imageInfo[0] > $this->resizeLimit || $imageInfo[1] > $this->resizeLimit)
                ) {
                    throw new InvalidArgumentException(__('Image too large'));
                }

                // Get the full image and make a thumbnail
                $img = $manager->read($filePath);
                // Original resize() call used aspectRatio() only (no upsize() constraint), which in
                // Intervention v2 allowed enlarging beyond the source size — scale() matches that.
                $img->scale($width, $height);
                $img->save($thumbnailFilePath);
                $response = $response->withHeader('Content-Type', $img->origin()->mediaType());
            }

            // Output Etag
            $response = HttpCacheProvider::withEtag($response, md5_file($thumbnailFilePath));

            $response->write($img->encode());
        } catch (\Exception) {
            $this->logger->debug('thumbnail: exception raised.');

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
        try {
            $filePath = $this->libraryLocation . $filePath;

            // Does it exist?
            if (!file_exists($filePath)) {
                throw new NotFoundException(__('File not found'));
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
            if ($errorThumb !== null) {
                $img = ImageManager::gd()->read($errorThumb);
                $response->write($img->encode());
                $response = $response->withHeader('Content-Type', $img->origin()->mediaType());
            } else {
                $this->logger->error('Cannot parse image: ' . $e->getMessage());
                throw new InvalidArgumentException(__('Cannot parse image.'), 'storedAs');
            }
        }

        return $response;
    }
}
