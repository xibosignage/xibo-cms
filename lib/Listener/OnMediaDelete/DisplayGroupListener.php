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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Listener\ListenerLoggerTrait;

class DisplayGroupListener
{
    use ListenerLoggerTrait;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    public function __construct(DisplayGroupFactory $displayGroupFactory)
    {
        $this->displayGroupFactory = $displayGroupFactory;
    }

    public function __invoke(MediaDeleteEvent $event, string $eventName, EventDispatcherInterface $dispatcher)
    {
        $media = $event->getMedia();
        $parentMedia = $event->getParentMedia();

        foreach ($this->displayGroupFactory->getByMediaId($media->mediaId) as $displayGroup) {
            $dispatcher->dispatch(DisplayGroupLoadEvent::$NAME, new DisplayGroupLoadEvent($displayGroup));
            $displayGroup->load();
            $displayGroup->unassignMedia($media);
            if ($parentMedia != null) {
                $displayGroup->assignMedia($parentMedia);
            }

            $displayGroup->save(['validate' => false]);
        }
    }
}
