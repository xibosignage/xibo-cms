<?php
/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

namespace Xibo\Service;

use Xibo\Service\ImageProcessingServiceInterface;
use Intervention\Image\Exception\NotReadableException;
use Intervention\Image\ImageManagerStatic as Img;

/**
 * Class ImageProcessingService
 * @package Xibo\Service
 */
class ImageProcessingService implements ImageProcessingServiceInterface
{

    /** @var LogServiceInterface */
    private $log;

    /**
     * @inheritdoc
     */
    public function __construct()
    {

    }

    /**
     * @inheritdoc
     */
    public function setDependencies($log)
    {
        $this->log = $log;
        return $this;
    }

    /** @inheritdoc */
    public function resizeImage($filePath, $width, $height)
    {
        try {
            Img::configure(array('driver' => 'gd'));
            $img = Img::make($filePath);
            $img->resize($width, $height, function ($constraint)  {
                $constraint->aspectRatio();
            });

            // Get the updated height and width
            $updatedHeight = $img->height();
            $updatedWidth = $img->width();

            $img->save($filePath);
            $img->destroy();
        } catch (NotReadableException $notReadableException) {
            $this->log->error('Image not readable: ' . $notReadableException->getMessage());
        }

        return [
            'filePath' => $filePath,
            'height' => $updatedHeight ?? $height,
            'width' => $updatedWidth ?? $width
        ];
    }
}