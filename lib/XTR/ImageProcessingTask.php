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
use Xibo\Exception\ConfigurationException;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ReportScheduleFactory;
use Xibo\Factory\SavedReportFactory;
use Xibo\Factory\UserFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\ImageProcessingServiceInterface;
use Xibo\Service\ReportServiceInterface;

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

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->date = $container->get('dateService');
        $this->mediaFactory = $container->get('mediaFactory');
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
        $resize_threshold = $this->config->getSetting('DEFAULT_RESIZE_THRESHOLD');

        // Get list of Images
        foreach ($images as $media) {

            $filePath = $libraryLocation . $media->storedAs;
            list($img_width, $img_height) = @getimagesize($filePath);

            // Orientation of the image
            if ($img_width > $img_height) { // 'landscape';
                $this->imageProcessingService->resizeImage($filePath, $resize_threshold, 1080);
            } else { // 'portrait';
                $this->imageProcessingService->resizeImage($filePath, 1080, $resize_threshold);
            }

            // Release image and save
            // Work out the MD5
            $media->md5 = md5_file($libraryLocation . $media->storedAs);
            $media->released = 1;

            try {
                $media->save();
            } catch (\Exception $error) {
                $this->log->error($error);
            }
        }
    }
}