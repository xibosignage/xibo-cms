<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2018 Spring Signage Ltd
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

use Xibo\Entity\Media;
use Xibo\Entity\Playlist;
use Xibo\Exception\NotFoundException;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Storage\StorageServiceInterface;

/**
 * Class DynamicPlaylistSyncTask
 * @package Xibo\XTR
 *
 * Keep dynamic Playlists in sync with changes to the Media table.
 */
class DynamicPlaylistSyncTask implements TaskInterface
{
    use TaskTrait;

    /** @var StorageServiceInterface */
    private $store;

    /** @var DateServiceInterface */
    private $date;

    /** @var PlaylistFactory */
    private $playlistFactory;

    /** @var MediaFactory */
    private $mediaFactory;

    /** @var ModuleFactory */
    private $moduleFactory;

    /** @var WidgetFactory */
    private $widgetFactory;

    /** @inheritdoc */
    public function setFactories($container)
    {
        $this->store = $container->get('store');
        $this->date = $container->get('date');
        $this->playlistFactory = $container->get('playlistFactory');
        $this->mediaFactory = $container->get('mediaFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        // Run a little query to get the last modified date from the media table
        $lastMediaUpdate = $this->store->select('SELECT MAX(modifiedDt) FROM `media`;', [])[0]['modifiedDt'];

        if (empty($lastMediaUpdate)) {
            $this->appendRunMessage('No library media to assess');
            return;
        }

        $lastMediaUpdate = $this->date->parse($lastMediaUpdate);
        $lastTaskRun = $this->date->parse($this->getTask()->lastRunDt, 'U');

        if ($lastMediaUpdate->lessThan($lastTaskRun)) {
            $this->appendRunMessage('No library media updates since we last ran');
            return;
        }

        $count = 0;

        // Get all Dynamic Playlists
        foreach ($this->playlistFactory->query(null, ['isDynamic' => 1]) as $playlist) {
            // We want to detect any differences in what should be assigned to this Playlist.
            $playlist->load();

            // Query for media which would be assigned to this Playlist and see if there are any differences
            $media = $this->mediaFactory->query(null, ['name' => $playlist->filterMediaName, 'tags' => $playlist->filterMediaTags]);

            // Work out if the set of widgets is different or not.
            $different = (count($playlist->widgets) !== count($media));

            $mediaIds = array_map(function($element){
                /** @var $element Media */
                return $element->mediaId;
            }, $media);

            $compareMediaIds = clone $mediaIds;

            if (!$different) {
                // Try a more complete check, using mediaIds
                // ordering should be the same, so the first time we get one out of order, we can stop
                foreach ($playlist->widgets as $widget) {
                    if ($widget->getPrimaryMediaId() !== $compareMediaIds[0]) {
                        $different = true;
                        break;
                    }

                    array_shift($compareMediaIds);
                }
            }

            if ($different) {
                // The best thing to do here (probably) is to remove all the widgets and add them again?
                // we want to add them in order - remove the ones no-longer present, add the ones we're missing and
                // the reorder the whole lot
                foreach ($playlist->widgets as $widget) {
                    if (!in_array($widget->getPrimaryMediaId(), $mediaIds)) {
                        $widget->delete();
                    } else {
                        // It's present in the array, so pop it off
                        unset($mediaIds[$widget->getPrimaryMediaId()]);
                    }
                }

                // Add the ones we have left
                foreach ($media as $item) {
                    $count++;
                    if (in_array($item->mediaId, $mediaIds)) {
                        $this->createAndAssign($playlist, $item, $count);
                    }
                }
            }
        }

        $this->appendRunMessage('Updated ' . $count . ' Playlists');
    }

    /**
     * @param Playlist $playlist
     * @param Media $media
     * @param int $displayOrder
     * @throws NotFoundException
     */
    private function createAndAssign($playlist, $media, $displayOrder)
    {
        // Create a module
        $module = $this->moduleFactory->create($media->mediaType);

        // Determine the duration
        $mediaDuration = ($media->duration == 0) ? $module->determineDuration() : $media->duration;

        // Create a widget
        $widget = $this->widgetFactory->create($playlist->getOwnerId(), $playlist->playlistId, $media->mediaType, $mediaDuration);
        $widget->assignMedia($media->mediaId);
        $widget->displayOrder = $displayOrder;

        // Assign the widget to the module
        $module->setWidget($widget);

        // Set default options (this sets options on the widget)
        $module->setDefaultWidgetOptions();

        // Calculate the duration
        $widget->calculateDuration($module);

        // Assign the widget to the playlist
        $playlist->assignWidget($widget);
    }
}