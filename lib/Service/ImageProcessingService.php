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

namespace Xibo\Service;

use Xibo\Service\ImageProcessingServiceInterface;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;

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
            $img = ImageManager::gd()->read($filePath);
            // No upsize() constraint originally, so upsizing is allowed - scale() matches.
            $img->scale($width, $height);

            // Get the updated height and width
            $updatedHeight = $img->height();
            $updatedWidth = $img->width();

            $img->save($filePath);
        } catch (DecoderException $notReadableException) {
            $this->log->error('Image not readable: ' . $notReadableException->getMessage());
            return null;
        }

        return [
            'filePath' => $filePath,
            'height' => $updatedHeight,
            'width' => $updatedWidth
        ];
    }
}
