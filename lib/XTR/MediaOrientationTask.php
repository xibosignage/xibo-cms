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

use Xibo\Entity\Media;
use Xibo\Factory\MediaFactory;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Support\Exception\GeneralException;

class MediaOrientationTask implements TaskInterface
{
    use TaskTrait;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * @var LibraryStorageServiceInterface
     */
    private $libraryStorageService;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->mediaFactory = $container->get('mediaFactory');
        $this->libraryStorageService = $container->get('libraryStorageService');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $this->runMessage = '# ' . __('Media Orientation') . PHP_EOL . PHP_EOL;

        // Long running task
        set_time_limit(0);

        $this->setMediaOrientation();
    }

    private function setMediaOrientation()
    {
        $this->appendRunMessage('# Setting Media Orientation on Library Media files.');

        // onlyMenuBoardAllowed filter means images and videos
        $filesToCheck = $this->mediaFactory->query(null, [
            'requiresMetaUpdate' => 1,
            'onlyMenuBoardAllowed' => 1,
            'excludeWaitingForMove' => 1,
        ]);
        $count = 0;

        foreach ($filesToCheck as $media) {
            $count++;
            $key = '';

            if ($media->mediaType === 'image') {
                $key = $media->storedAs;
            } elseif ($media->mediaType === 'video' && $media->thumbnailVersion !== null) {
                $key = Media::getVideoCoverKey($media->mediaId);
            }

            if (!empty($key)) {
                try {
                    $tempCopyPath = $this->libraryStorageService->downloadToTemp($key);
                } catch (GeneralException $exception) {
                    $this->log->error('Cannot read library file for orientation update: ' . $key . '. '
                        . $exception->getMessage());
                    continue;
                }

                try {
                    $imageSize = @getimagesize($tempCopyPath);
                    if ($imageSize === false) {
                        $this->log->error('Cannot read image size for orientation update: ' . $key);
                        continue;
                    }

                    [$imgWidth, $imgHeight] = $imageSize;
                    $media->width = $imgWidth;
                    $media->height = $imgHeight;
                    $media->orientation = ($imgWidth >= $imgHeight) ? 'landscape' : 'portrait';
                    $media->save(['saveTags' => false, 'validate' => false]);
                } finally {
                    $this->libraryStorageService->releaseTemp($tempCopyPath);
                }
            }
        }
        $this->appendRunMessage('Updated ' . $count . ' items');
        $this->disableTask();
    }

    private function disableTask()
    {
        $this->appendRunMessage('# Disabling task.');
        $this->log->debug('Disabling task.');

        $this->getTask()->isActive = 0;
        $this->getTask()->save();

        $this->appendRunMessage(__('Done.'. PHP_EOL));
    }
}
