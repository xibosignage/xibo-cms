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

use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Service\LibraryStorageRegistryInterface;
use Xibo\Service\LibraryStorageServiceInterface;
use Xibo\Support\Exception\GeneralException;

/**
 * Class UpdateEmptyVideoDurations
 * @package Xibo\XTR
 *
 *  update video durations
 */
class UpdateEmptyVideoDurations implements TaskInterface
{
    use TaskTrait;

    /** @var MediaFactory */
    private $mediaFactory;

    /** @var ModuleFactory */
    private $moduleFactory;

    /** @var LibraryStorageServiceInterface */
    private $libraryStorageService;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->mediaFactory = $container->get('mediaFactory');
        $this->moduleFactory = $container->get('moduleFactory');
        $this->libraryStorageService = $container->get('libraryStorageService');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        $module = $this->moduleFactory->getByType('video');
        $videos = $this->mediaFactory->getByMediaType($module->type);

        foreach ($videos as $video) {
            // A video still in the local library waits until it has been moved.
            if ($video->duration == 0 && $video->storageEngine !== LibraryStorageRegistryInterface::LOCAL_ENGINE) {
                try {
                    $tempCopyPath = $this->libraryStorageService->downloadToTemp($video->storedAs);
                } catch (GeneralException $exception) {
                    $this->log->error('Cannot read library file for duration update: ' . $video->storedAs
                        . '. ' . $exception->getMessage());
                    continue;
                }

                // Update
                try {
                    $video->duration = $module->fetchDurationOrDefaultFromFile($tempCopyPath);
                    $video->save(['validate' => false]);
                } finally {
                    $this->libraryStorageService->releaseTemp($tempCopyPath);
                }
            }
        }
    }
}
