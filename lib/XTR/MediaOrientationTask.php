<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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

use Xibo\Factory\MediaFactory;

class MediaOrientationTask implements TaskInterface
{
    use TaskTrait;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->mediaFactory = $container->get('mediaFactory');
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
        $filesToCheck = $this->mediaFactory->query(null, ['requiresMetaUpdate' => 1, 'onlyMenuBoardAllowed' => 1]);
        $count = 0;

        foreach ($filesToCheck as $media) {
            $count++;
            $filePath = '';
            $libraryFolder = $this->config->getSetting('LIBRARY_LOCATION');

            if ($media->mediaType === 'image') {
                $filePath = $libraryFolder . $media->storedAs;
            } elseif ($media->mediaType === 'video' && file_exists($libraryFolder . $media->mediaId . '_videocover.png')) {
                $filePath = $libraryFolder . $media->mediaId . '_videocover.png';
            }

            if (!empty($filePath)) {
                list($imgWidth, $imgHeight) = @getimagesize($filePath);
                $media->width = $imgWidth;
                $media->height = $imgHeight;
                $media->orientation = ($imgWidth >= $imgHeight) ? 'landscape' : 'portrait';
                $media->save(['saveTags' => false, 'validate' => false]);
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
