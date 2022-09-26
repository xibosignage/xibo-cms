<?php
/**
 * Copyright (C) 2020 Xibo Signage Ltd
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

use Carbon\Carbon;
use Xibo\Entity\Media;
use Xibo\Entity\Playlist;
use Xibo\Entity\Task;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\DateFormatHelper;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\NotFoundException;

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
        $this->playlistFactory = $container->get('playlistFactory');
        $this->mediaFactory = $container->get('mediaFactory');
        $this->moduleFactory = $container->get('moduleFactory');
        $this->widgetFactory = $container->get('widgetFactory');
        return $this;
    }

    /** @inheritdoc */
    public function run()
    {
        // If we're in the error state, then always run, otherwise check the dates we modified various triggers
        if ($this->getTask()->lastRunStatus !== Task::$STATUS_ERROR) {
            // Run a little query to get the last modified date from the media table
            $lastMediaUpdate = $this->store->select('SELECT MAX(modifiedDt) AS modifiedDt FROM `media` WHERE 
                `type` <> \'module\'
                AND `type` <> \'genericfile\'
                AND `type` <> \'playersoftware\'
                AND `type` <> \'font\';', [])[0]['modifiedDt'];
            $lastPlaylistUpdate = $this->store->select('SELECT MAX(modifiedDt) AS modifiedDt FROM `playlist`;', [])[0]['modifiedDt'];

            if (empty($lastMediaUpdate) || empty($lastPlaylistUpdate)) {
                $this->appendRunMessage('No library media or Playlists to assess');
                return;
            }

            $this->log->debug('Last media updated date is ' . $lastMediaUpdate);
            $this->log->debug('Last playlist updated date is ' . $lastPlaylistUpdate);

            $lastMediaUpdate = Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $lastMediaUpdate);
            $lastPlaylistUpdate = Carbon::createFromFormat(DateFormatHelper::getSystemFormat(), $lastPlaylistUpdate);
            $lastTaskRun = Carbon::createFromTimestamp($this->getTask()->lastRunDt);

            if ($lastMediaUpdate->lessThanOrEqualTo($lastTaskRun) && $lastPlaylistUpdate->lessThanOrEqualTo($lastTaskRun)) {
                $this->appendRunMessage('No library media/playlist updates since we last ran');
                return;
            }
        }

        $count = 0;

        // Get all Dynamic Playlists
        foreach ($this->playlistFactory->query(null, ['isDynamic' => 1]) as $playlist) {
            try {
                // We want to detect any differences in what should be assigned to this Playlist.
                $playlist->load();

                $this->log->debug('Assessing Playlist: ' . $playlist->name);

                if (empty($playlist->filterMediaName) && empty($playlist->filterMediaTags)) {
                    // if this Dynamic Playlist was populated will all Media in the system
                    // before we introduced measures against it, we need to go through and unassign all Widgets from it.
                    // if it is fresh Playlist added recently, it will not have any Widgets on it with empty filters.
                    if (!empty($playlist->widgets)) {
                        foreach ($playlist->widgets as $widget) {
                            $playlist->deleteWidget($widget);
                        }
                    }
                    $this->log->debug(sprintf('Dynamic Playlist ID %d , with no filters set, skipping.', $playlist->playlistId));
                    continue;
                }

                // Query for media which would be assigned to this Playlist and see if there are any differences
                $media = [];
                $mediaIds = [];
                foreach ($this->mediaFactory->query(null, [
                    'name' => $playlist->filterMediaName,
                    'logicalOperatorName' => $playlist->filterMediaNameLogicalOperator,
                    'tags' => $playlist->filterMediaTags,
                    'exactTags' => $playlist->filterExactTags,
                    'logicalOperator' => $playlist->filterMediaTagsLogicalOperator,
                    'userCheckUserId' => $playlist->getOwnerId(),
                    'start' => 0,
                    'length' => $playlist->maxNumberOfItems
                ]) as $item) {
                    $media[$item->mediaId] = $item;
                    $mediaIds[] = $item->mediaId;
                }

                // Work out if the set of widgets is different or not.
                // This is only the first loose check
                $different = (count($playlist->widgets) !== count($media));

                $this->log->debug('There are ' . count($media) . ' that should be assigned and ' . count($playlist->widgets)
                    . ' currently assigned with max number of items set to ' . $playlist->maxNumberOfItems . ' First check difference is ' . var_export($different, true));

                if (!$different) {
                    // Try a more complete check, using mediaIds
                    $compareMediaIds = $mediaIds;

                    // ordering should be the same, so the first time we get one out of order, we can stop
                    foreach ($playlist->widgets as $widget) {
                        try {
                            $widgetMediaId = $widget->getPrimaryMediaId();
                            if ($widgetMediaId !== $compareMediaIds[0] || $widget->duration !== $media[$widgetMediaId]->duration) {
                                $different = true;
                                break;
                            }
                        } catch (NotFoundException $notFoundException) {
                            $this->log->error('Playlist ' . $playlist->getId() . ' has a Widget without any associated media. widgetId = ' . $widget->getId());

                            // We ought to recalculate
                            $different = true;
                            break;
                        }

                        array_shift($compareMediaIds);
                    }
                }

                $this->log->debug('Second check difference is ' . var_export($different, true));

                if ($different) {
                    // We will update this Playlist
                    $assignmentMade = false;
                    $count++;

                    // Remove the ones no-longer present, add the ones we're missing
                    // we don't delete and re-add the lot to avoid regenerating the widgetIds (makes stats harder to
                    // interpret)
                    foreach ($playlist->widgets as $widget) {
                        try {
                            $widgetMediaId = $widget->getPrimaryMediaId();

                            if (!in_array($widgetMediaId, $mediaIds)) {
                                $playlist->deleteWidget($widget);
                            } else {
                                // It's present in the array
                                // Check to see if the duration is different
                                if ($widget->duration !== $media[$widgetMediaId]->duration) {
                                    // The media duration has changed, so update the widget
                                    $widget->useDuration = 1;
                                    $widget->duration = $media[$widgetMediaId]->duration;
                                    $widget->calculatedDuration = $widget->duration;
                                    $widget->save([
                                        'saveWidgetOptions' => false,
                                        'saveWidgetAudio' => false,
                                        'saveWidgetMedia' => false,
                                        'notify' => false,
                                        'notifyPlaylists' => false,
                                        'notifyDisplays' => false,
                                        'audit' => true,
                                        'alwaysUpdate' => true
                                    ]);
                                }

                                // Pop it off the list of ones to assign.
                                $mediaIds = array_diff($mediaIds, [$widgetMediaId]);

                                // We do want to save the Playlist here.
                                $assignmentMade = true;
                            }
                        } catch (NotFoundException $exception) {
                            // Delete it
                            $playlist->deleteWidget($widget);
                        }
                    }

                    // Do we have any mediaId's left which should be assigned and aren't?
                    // Add the ones we have left
                    foreach ($media as $item) {
                        if (in_array($item->mediaId, $mediaIds)) {
                            if (count($playlist->widgets) >= $playlist->maxNumberOfItems) {
                                $this->log->debug(sprintf('Dynamic Playlist ID %d, has reached the maximum number of items %d, finishing assignments', $playlist->playlistId, $playlist->maxNumberOfItems));
                                break;
                            }
                            $assignmentMade = true;
                            $this->createAndAssign($playlist, $item, $count);
                        }
                    }

                    if ($assignmentMade) {
                        // We've made an assignment change, so audit this change
                        // don't audit any downstream save operations
                        $playlist->save([
                            'auditPlaylist' => true,
                            'audit' => false
                        ]);
                    }
                } else {
                    $this->log->debug('No differences detected');
                }
            } catch (GeneralException $exception) {
                $this->log->debug($exception->getTraceAsString());
                $this->log->error('Problem with PlaylistId: ' . $playlist->getId() . ', e = ' . $exception->getMessage());
                $this->appendRunMessage('Error with Playlist: ' . $playlist->name);
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
        $this->log->debug('Media Item needs to be assigned ' . $media->name . ' in sequence ' . $displayOrder);

        // Create a module
        $module = $this->moduleFactory->create($media->mediaType);

        if ($module->getModule()->assignable == 1) {

            // Determine the duration
            $mediaDuration = ($media->duration == 0) ? $module->determineDuration() : $media->duration;

            // Create a widget
            $widget = $this->widgetFactory->create(
                $playlist->getOwnerId(),
                $playlist->playlistId,
                $media->mediaType,
                $mediaDuration
            );

            $widget->assignMedia($media->mediaId);
            $widget->displayOrder = $displayOrder;

            // Assign the widget to the module
            $module->setWidget($widget);

            // Set default options (this sets options on the widget)
            $module->setDefaultWidgetOptions();

            // Set the duration to be equal to the Library duration of this media
            $module->widget->useDuration = 1;
            $module->widget->duration = $mediaDuration;
            $module->widget->calculatedDuration = $mediaDuration;

            // Assign the widget to the playlist
            $playlist->assignWidget($widget);
        }
    }
}
