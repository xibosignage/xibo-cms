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

namespace Xibo\Listener\OnMediaDelete;

use Xibo\Event\MediaDeleteEvent;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Storage\StorageServiceInterface;

class WidgetListener
{
    use ListenerLoggerTrait;

    /** @var WidgetFactory */
    private $widgetFactory;

    /** @var \Xibo\Factory\ModuleFactory */
    private $moduleFactory;

    /** @var StorageServiceInterface */
    private $storageService;

    public function __construct(
        StorageServiceInterface $storageService,
        WidgetFactory $widgetFactory,
        ModuleFactory $moduleFactory
    ) {
        $this->storageService = $storageService;
        $this->widgetFactory = $widgetFactory;
        $this->moduleFactory = $moduleFactory;
    }

    /**
     * @param MediaDeleteEvent $event
     * @throws \Xibo\Support\Exception\GeneralException
     */
    public function __invoke(MediaDeleteEvent $event)
    {
        $media = $event->getMedia();
        $parentMedia = $event->getParentMedia();

        foreach ($this->widgetFactory->getByMediaId($media->mediaId) as $widget) {
            $widget->unassignMedia($media->mediaId);

            if ($parentMedia != null) {
                // Assign the parent media to the widget instead
                $widget->assignMedia($parentMedia->mediaId);

                // Swap any audio nodes over to this new widget media assignment.
                $this->storageService->update('
                  UPDATE `lkwidgetaudio` SET mediaId = :mediaId WHERE widgetId = :widgetId AND mediaId = :oldMediaId
                ', [
                    'mediaId' => $parentMedia->mediaId,
                    'widgetId' => $widget->widgetId,
                    'oldMediaId' => $media->mediaId
                ]);
            } else {
                // Also delete the `lkwidgetaudio`
                foreach ($widget->audio as $audio) {
                    $widget->unassignAudioById($audio->mediaId);
                    $audio->delete();
                }
            }

            // This action might result in us deleting a widget (unless we are a temporary file with an expiry date)
            if ($media->mediaType != 'module'
                && $this->moduleFactory->getByType($widget->type)->regionSpecific === 0
                && count($widget->mediaIds) <= 0
            ) {
                $widget->delete();
            } else {
                $widget->save(['saveWidgetOptions' => false]);
            }
        }
    }
}
