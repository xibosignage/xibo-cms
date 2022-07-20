<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
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

namespace Xibo\Listener\OnMediaDelete;

use Xibo\Entity\Layout;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Factory\LayoutFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Support\Exception\InvalidArgumentException;

class LayoutListener
{
    use ListenerLoggerTrait;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    public function __construct(LayoutFactory $layoutFactory)
    {
        $this->layoutFactory = $layoutFactory;
    }

    /**
     * @param MediaDeleteEvent $event
     * @throws InvalidArgumentException
     */
    public function __invoke(MediaDeleteEvent $event)
    {
        $media = $event->getMedia();
        $parentMedia = $event->getParentMedia();

        foreach ($this->layoutFactory->getByBackgroundImageId($media->mediaId) as $layout) {
            if ($media->mediaType == 'image' && $parentMedia != null) {
                $this->getLogger()->debug(sprintf(
                    'Updating layouts with the old media %d as the background image.',
                    $media->mediaId
                ));
                $this->getLogger()->debug(sprintf(
                    'Found layout that needs updating. ID = %d. Setting background image id to %d',
                    $layout->layoutId,
                    $parentMedia->mediaId
                ));

                $layout->backgroundImageId = $parentMedia->mediaId;
            } else {
                $layout->backgroundImageId = null;
            }

            $layout->save(Layout::$saveOptionsMinimum);
        }
    }
}
