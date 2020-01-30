<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

namespace Xibo\XTR;
use Xibo\Entity\DisplayGroup;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\ImageProcessingServiceInterface;

/**
 * Class ImageProcessingTask
 * @package Xibo\XTR
 */
class ImageProcessingTask implements TaskInterface
{
    use TaskTrait;

    /** @var DateServiceInterface */
    private $date;

    /** @var ImageProcessingServiceInterface */
    private $imageProcessingService;

    /** @var MediaFactory */
    private $mediaFactory;

    /** @var DisplayFactory */
    private $displayFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->date = $container->get('dateService');
        $this->mediaFactory = $container->get('mediaFactory');
        $this->displayFactory = $container->get('displayFactory');
        $this->imageProcessingService = $container->get('imageProcessingService');
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
     *
     */
    private function runImageProcessing()
    {
        $images = $this->mediaFactory->query(null, ['released' => 0, 'allModules' => 1, 'imageProcessing' => 1]);

        $libraryLocation = $this->config->getSetting('LIBRARY_LOCATION');
        $resizeThreshold = $this->config->getSetting('DEFAULT_RESIZE_THRESHOLD');
        $count = 0;

        // All displayIds
        $displayIds = [];

        // Get list of Images
        foreach ($images as $media) {

            $filePath = $libraryLocation . $media->storedAs;
            list($imgWidth, $imgHeight) = @getimagesize($filePath);

            // Orientation of the image
            if ($imgWidth > $imgHeight) { // 'landscape';
                $this->imageProcessingService->resizeImage($filePath, $resizeThreshold, null);
            } else { // 'portrait';
                $this->imageProcessingService->resizeImage($filePath, null, $resizeThreshold);
            }

            // Clears file status cache
            clearstatcache(true, $filePath);

            $count++;

            // Release image and save
            $media->release(md5_file($filePath), filesize($filePath));
            $this->store->commitIfNecessary();

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
                    'size' => $media->fileSize,
                    'displayId' => $displayId,
                    'itemId' => $media->mediaId
                ]);
            }

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