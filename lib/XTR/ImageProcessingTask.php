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

namespace Xibo\XTR;

use Carbon\Carbon;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Service\ImageProcessingServiceInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class ImageProcessingTask
 * @package Xibo\XTR
 */
class ImageProcessingTask implements TaskInterface
{
    use TaskTrait;

    /** @var ImageProcessingServiceInterface */
    private $imageProcessingService;

    /** @var MediaFactory */
    private $mediaFactory;

    /** @var DisplayFactory */
    private $displayFactory;

    /** @var LibraryStorageServiceInterface */
    private $libraryStorageService;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->mediaFactory = $container->get('mediaFactory');
        $this->displayFactory = $container->get('displayFactory');
        $this->imageProcessingService = $container->get('imageProcessingService');
        $this->libraryStorageService = $container->get('libraryStorageService');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Image Processing') . PHP_EOL . PHP_EOL;

        // Long running task
        set_time_limit(0);

        $this->runImageProcessing();
    }

    /**
     * Resizes an image to the threshold.
     * @return array{filePath: string, height: int, width: int, changed: bool}|null null if it can't be decoded
     */
    private function resize(string $path, $resizeThreshold): ?array
    {
        $imageSize = @getimagesize($path);
        if ($imageSize === false) {
            return null;
        }

        [$imgWidth, $imgHeight] = $imageSize;

        // Orientation of the image
        if ($imgWidth > $imgHeight) { // 'landscape';
            $updatedImg = $this->imageProcessingService->resizeImage($path, $resizeThreshold, null);
        } else { // 'portrait';
            $updatedImg = $this->imageProcessingService->resizeImage($path, null, $resizeThreshold);
        }

        if ($updatedImg === null) {
            return null;
        }

        return $updatedImg + ['changed' => true];
    }

    /**
     *
     */
    private function runImageProcessing()
    {
        $images = $this->mediaFactory->query(null, [
            'released' => 0,
            'allModules' => 1,
            'imageProcessing' => 1,
            'excludeWaitingForMove' => 1,
        ]);

        $resizeThreshold = $this->config->getSetting('DEFAULT_RESIZE_THRESHOLD');
        $count = 0;

        // All displayIds
        $displayIds = [];

        // Get list of Images
        foreach ($images as $media) {
            try {
                $tempCopyPath = $this->libraryStorageService->downloadToTemp($media->storedAs);
            } catch (NotFoundException $exception) {
                // The file is gone, so it is released rather than retried every run.
                $this->log->error('Library file missing for image processing, releasing it unchanged: '
                    . $media->storedAs . '. ' . $exception->getMessage());
                $media->release($media->md5, $media->fileSize, $media->height, $media->width);
                $this->store->commitIfNecessary();
                continue;
            } catch (GeneralException $exception) {
                // Storage is unavailable and nothing was read, so it is retried next run.
                $this->log->error('Cannot read library file for image processing: ' . $media->storedAs
                    . '. ' . $exception->getMessage());
                continue;
            }

            try {
                $resized = $this->resize($tempCopyPath, $resizeThreshold);
                if ($resized === null) {
                    // It will never decode, so it is released rather than retried every run.
                    $this->log->warning('Image could not be resized, releasing it unchanged: ' . $media->storedAs);
                    $resized = [
                        'filePath' => $tempCopyPath,
                        'height' => $media->height,
                        'width' => $media->width,
                        'changed' => false,
                    ];
                }

                // Clears file status cache
                clearstatcache(true, $resized['filePath']);

                $newMd5 = md5_file($resized['filePath']);
                $newFileSize = filesize($resized['filePath']);

                if ($newMd5 === false || $newFileSize === false) {
                    $this->log->error('Could not read back resized image, skipping: ' . $media->storedAs);
                    continue;
                }

                // The resize wrote to the temporary copy path, so store it.
                if ($resized['changed']) {
                    try {
                        $this->libraryStorageService->storeFromTemp($resized['filePath'], $media->storedAs);
                    } catch (GeneralException $exception) {
                        $this->log->error('Cannot upload resized image, skipping: ' . $media->storedAs
                            . '. ' . $exception->getMessage());
                        continue;
                    }
                }

                $count++;

                // Release image and save
                $media->release(
                    $newMd5,
                    $newFileSize,
                    $resized['height'],
                    $resized['width']
                );
                $this->store->commitIfNecessary();
            } finally {
                $this->libraryStorageService->releaseTemp($tempCopyPath);
            }

            $mediaDisplays= [];
            $sql = 'SELECT displayId FROM `requiredfile` WHERE itemId = :itemId';
            foreach ($this->store->select($sql, ['itemId' =>  $media->mediaId]) as $row) {
                $displayIds[] = $row['displayId'];
                $mediaDisplays[] = $row['displayId'];
            }

            // Update Required Files
            foreach ($mediaDisplays as $displayId) {
                $this->store->update('UPDATE `requiredfile` SET released = :released, size = :size
                WHERE `requiredfile`.displayId = :displayId AND `requiredfile`.itemId = :itemId ', [
                    'released' => 1,
                    'size' => $newFileSize,
                    'displayId' => $displayId,
                    'itemId' => $media->mediaId
                ]);
            }

            // Mark any effected Layouts to be rebuilt.
            $this->store->update('
                UPDATE `layout` 
                    SET status = :status, `modifiedDT` = :modifiedDt 
                 WHERE layoutId IN (
                     SELECT DISTINCT region.layoutId 
                       FROM lkwidgetmedia
                        INNER JOIN widget
                        ON widget.widgetId = lkwidgetmedia.widgetId
                        INNER JOIN lkplaylistplaylist
                        ON lkplaylistplaylist.childId = widget.playlistId
                        INNER JOIN playlist
                        ON lkplaylistplaylist.parentId = playlist.playlistId
                        INNER JOIN region
                        ON playlist.regionId = region.regionId
                      WHERE lkwidgetmedia.mediaId = :mediaId
                     )
            ', [
                'status' => 3,
                'modifiedDt' => Carbon::now()->format(DateFormatHelper::getSystemFormat()),
                'mediaId' => $media->mediaId
            ]);
        }

        // Notify display
        if ($count > 0) {
            foreach (array_unique($displayIds) as $displayId) {
                // Get display
                $display = $this->displayFactory->getById($displayId);
                $display->notify();
            }
        }

        $this->appendRunMessage('Released and modified image count. ' . $count);
    }
}
