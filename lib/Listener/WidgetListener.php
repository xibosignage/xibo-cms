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

namespace Xibo\Listener;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\Widget;
use Xibo\Event\RegionAddedEvent;
use Xibo\Event\SubPlaylistDurationEvent;
use Xibo\Event\SubPlaylistItemsEvent;
use Xibo\Event\SubPlaylistValidityEvent;
use Xibo\Event\SubPlaylistWidgetsEvent;
use Xibo\Event\WidgetDeleteEvent;
use Xibo\Event\WidgetEditEvent;
use Xibo\Factory\ModuleFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;
use Xibo\Widget\SubPlaylistItem;

/**
 * Widget Listener.
 *
 * Sub Playlist Events
 * -------------------
 * Sub Playlists are a special case in that they resolve to multiple widgets
 * This is handled by the standard widget edit/delete events and a special event to calculate the duration
 * These events are processed by a SubPlaylistListener included with core.
 *
 * Region Events
 * -------------
 * We listen for a region being added and if its a canvas we add a "global" widget to it.
 */
class WidgetListener
{
    use ListenerLoggerTrait;

    /** @var PlaylistFactory */
    private $playlistFactory;

    /** @var \Xibo\Factory\ModuleFactory */
    private $moduleFactory;

    /** @var WidgetFactory */
    private $widgetFactory;

    /** @var StorageServiceInterface */
    private $storageService;

    /** @var \Xibo\Service\ConfigServiceInterface */
    private $configService;

    /**
     * @param PlaylistFactory $playlistFactory
     * @param \Xibo\Factory\ModuleFactory $moduleFactory
     * @param StorageServiceInterface $storageService
     * @param \Xibo\Service\ConfigServiceInterface $configService
     */
    public function __construct(
        PlaylistFactory $playlistFactory,
        ModuleFactory $moduleFactory,
        WidgetFactory $widgetFactory,
        StorageServiceInterface $storageService,
        ConfigServiceInterface $configService
    ) {
        $this->playlistFactory = $playlistFactory;
        $this->moduleFactory = $moduleFactory;
        $this->widgetFactory = $widgetFactory;
        $this->storageService = $storageService;
        $this->configService = $configService;
    }

    /**
     * @param EventDispatcherInterface $dispatcher
     * @return $this
     */
    public function registerWithDispatcher(EventDispatcherInterface $dispatcher) : WidgetListener
    {
        $dispatcher->addListener(WidgetEditEvent::$NAME, [$this, 'onWidgetEdit']);
        $dispatcher->addListener(WidgetDeleteEvent::$NAME, [$this, 'onWidgetDelete']);
        $dispatcher->addListener(SubPlaylistDurationEvent::$NAME, [$this, 'onDuration']);
        $dispatcher->addListener(SubPlaylistWidgetsEvent::$NAME, [$this, 'onWidgets']);
        $dispatcher->addListener(SubPlaylistItemsEvent::$NAME, [$this, 'onSubPlaylistItems']);
        $dispatcher->addListener(SubPlaylistValidityEvent::$NAME, [$this, 'onSubPlaylistValid']);
        $dispatcher->addListener(RegionAddedEvent::$NAME, [$this, 'onRegionAdded']);
        return $this;
    }

    /**
     * Widget Edit
     * @param \Xibo\Event\WidgetEditEvent $event
     * @throws \Xibo\Support\Exception\InvalidArgumentException
     */
    public function onWidgetEdit(WidgetEditEvent $event)
    {
        $widget = $event->getWidget();
        if ($widget->type !== 'subplaylist') {
            return;
        }

        $this->getLogger()->debug('onWidgetEdit: processing subplaylist for widgetId ' . $widget->widgetId);

        // Get the IDs we had before the edit and work out the difference between then and now.
        $existingSubPlaylistIds = [];
        foreach ($this->getAssignedPlaylists($widget, true) as $assignedPlaylist) {
            if (!in_array($assignedPlaylist->playlistId, $existingSubPlaylistIds)) {
                $existingSubPlaylistIds[] = $assignedPlaylist->playlistId;
            }
        }

        $this->getLogger()->debug('onWidgetEdit: there are ' . count($existingSubPlaylistIds) . ' existing playlists');

        // Make up a companion setting which maps the playlistIds to the options
        $subPlaylists = $this->getAssignedPlaylists($widget);
        $subPlaylistIds = [];

        foreach ($subPlaylists as $playlist) {
            if ($playlist->spots < 0) {
                throw new InvalidArgumentException(
                    __('Number of spots must be empty, 0 or a positive number'),
                    'subPlaylistIdSpots'
                );
            }

            if ($playlist->spotLength < 0) {
                throw new InvalidArgumentException(
                    __('Spot length must be empty, 0 or a positive number'),
                    'subPlaylistIdSpotLength'
                );
            }

            if (!in_array($playlist->playlistId, $subPlaylistIds)) {
                $subPlaylistIds[] = $playlist->playlistId;
            }
        }

        // Validation
        if (count($subPlaylists) < 1) {
            throw new InvalidArgumentException(__('Please select at least 1 Playlist to embed'), 'subPlaylistId');
        }

        // Work out whether we've added/removed
        $addedEntries = array_diff($subPlaylistIds, $existingSubPlaylistIds);
        $removedEntries = array_diff($existingSubPlaylistIds, $subPlaylistIds);

        $this->logger->debug('onWidgetEdit Added ' . var_export($addedEntries, true));
        $this->logger->debug('onWidgetEdit Removed ' . var_export($removedEntries, true));

        // Remove items from closure table if necessary
        foreach ($removedEntries as $entry) {
            $this->logger->debug('Removing old link - existing link child is ' . $entry);

            $this->storageService->update('
                DELETE link
                  FROM `lkplaylistplaylist` p, `lkplaylistplaylist` link, `lkplaylistplaylist` c
                 WHERE p.parentId = link.parentId AND c.childId = link.childId
                   AND p.childId = :parentId AND c.parentId = :childId
            ', [
                'parentId' => $widget->playlistId,
                'childId' => $entry
            ]);
        }

        foreach ($addedEntries as $addedEntry) {
            $this->logger->debug('Manage closure table for parent ' . $widget->playlistId
                . ' and child ' . $addedEntry);

            if ($this->storageService->exists('
                SELECT parentId, childId, depth 
                  FROM lkplaylistplaylist 
                 WHERE childId = :childId AND parentId = :parentId 
            ', [
                'parentId' => $widget->playlistId,
                'childId' => $addedEntry
            ])) {
                throw new InvalidArgumentException(__('Cannot add the same SubPlaylist twice.'), 'playlistId');
            }

            $this->storageService->insert('
                INSERT INTO `lkplaylistplaylist` (parentId, childId, depth)
                SELECT p.parentId, c.childId, p.depth + c.depth + 1
                  FROM lkplaylistplaylist p, lkplaylistplaylist c
                 WHERE p.childId = :parentId AND c.parentId = :childId
            ', [
                'parentId' => $widget->playlistId,
                'childId' => $addedEntry
            ]);
        }

        // Make sure we've not created a circular reference
        // this is a lazy last minute check as we can't really tell if there is a circular reference unless
        // we've inserted the records already.
        if ($this->storageService->exists('
            SELECT depth 
              FROM `lkplaylistplaylist` 
             WHERE parentId = :parentId 
               AND childId = parentId 
               AND depth > 0
        ', ['parentId' => $widget->playlistId])) {
            throw new InvalidArgumentException(
                __('This assignment creates a loop because the Playlist being assigned contains the Playlist being worked on.'),//phpcs:ignore
                'subPlaylistId'
            );
        }
    }

    /**
     * @param \Xibo\Event\WidgetDeleteEvent $event
     * @return void
     */
    public function onWidgetDelete(WidgetDeleteEvent $event)
    {
        $widget = $event->getWidget();

        $this->getLogger()->debug('onWidgetDelete: processing widgetId ' . $widget->widgetId);

        // Clear cache
        $renderer = $this->moduleFactory->createWidgetHtmlRenderer();
        $renderer->clearWidgetCache($widget);

        // Everything else relates to sub-playlists
        if ($widget->type !== 'subplaylist') {
            return;
        }

        $subPlaylists = $this->getAssignedPlaylists($widget);

        // tidy up the closure table records.
        foreach ($subPlaylists as $subPlaylist) {
            $this->storageService->update('
                DELETE link
                  FROM `lkplaylistplaylist` p, `lkplaylistplaylist` link, `lkplaylistplaylist` c
                 WHERE p.parentId = link.parentId AND c.childId = link.childId
                   AND p.childId = :parentId AND c.parentId = :childId
            ', [
                'parentId' => $widget->playlistId,
                'childId' => $subPlaylist->playlistId,
            ]);
        }
    }

    /**
     * @param \Xibo\Event\SubPlaylistDurationEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onDuration(SubPlaylistDurationEvent $event)
    {
        $widget = $event->getWidget();
        $this->getLogger()->debug('onDuration: for ' . $widget->type);

        if ($widget->type !== 'subplaylist') {
            return;
        }

        // We give our widgetId to the resolve method so that it resolves us as if we're a child.
        // we only resolve top-level sub-playlists when we build the layout XLF
        $duration = 0;
        $countWidgets = 0;
        foreach ($this->getSubPlaylistResolvedWidgets($widget, $widget->widgetId ?? 0) as $resolvedWidget) {
            $duration += $resolvedWidget->calculatedDuration;
            $countWidgets++;
        }

        if ($widget->getOptionValue('cyclePlaybackEnabled', 0) === 1 && $countWidgets > 0) {
            $this->getLogger()->debug('onDuration: cycle playback is enabled and there are ' . $countWidgets
                . ' widgets with a total of ' . $duration . ' seconds');

            $duration = intval(ceil($duration / $countWidgets));
        }

        $event->appendDuration($duration);
    }

    /**
     * @param \Xibo\Event\SubPlaylistWidgetsEvent $event
     * @return void
     * @throws \Xibo\Support\Exception\GeneralException
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function onWidgets(SubPlaylistWidgetsEvent $event)
    {
        $widget = $event->getWidget();
        if ($widget->type !== 'subplaylist') {
            return;
        }

        $event->setWidgets($this->getSubPlaylistResolvedWidgets($widget, $event->getTempId()));
    }

    /**
     * @param SubPlaylistItemsEvent $event
     * @return void
     */
    public function onSubPlaylistItems(SubPlaylistItemsEvent $event)
    {
        $widget = $event->getWidget();
        if ($widget->type !== 'subplaylist') {
            return;
        }

        $event->setItems($this->getAssignedPlaylists($widget));
    }

    /**
     * @param SubPlaylistValidityEvent $event
     * @return void
     */
    public function onSubPlaylistValid(SubPlaylistValidityEvent $event): void
    {
        $playlists = $this->getAssignedPlaylists($event->getWidget());
        if (count($playlists) <= 0) {
            $event->setIsValid(false);
            return;
        } else {
            foreach ($playlists as $playlistItem) {
                try {
                    $this->playlistFactory->getById($playlistItem->playlistId);
                } catch (NotFoundException $e) {
                    $this->getLogger()->error('Misconfigured sub playlist, playlist ID '
                        . $playlistItem->playlistId . ' Not found');
                    $event->setIsValid(false);
                    return;
                }
            }
        }
        $event->setIsValid(true);
    }

    /**
     * @return \Xibo\Widget\SubPlaylistItem[]
     */
    private function getAssignedPlaylists(Widget $widget, bool $originalValue = false): array
    {
        $this->getLogger()->debug('getAssignedPlaylists: original value: ' . var_export($originalValue, true));

        $playlistItems = [];
        foreach (json_decode($widget->getOptionValue('subPlaylists', '[]', $originalValue), true) as $playlist) {
            $item = new SubPlaylistItem();
            $item->rowNo = intval($playlist['rowNo']);
            $item->playlistId = $playlist['playlistId'];
            $item->spotFill = $playlist['spotFill'] ?? null;
            $item->spotLength =  $playlist['spotLength'] !== '' ? intval($playlist['spotLength']) : null;
            $item->spots = $playlist['spots'] !== '' ? intval($playlist['spots']) : null;

            $playlistItems[] = $item;
        }
        return $playlistItems;
    }

    /**
     * @param int $parentWidgetId this tracks the top level widgetId
     * @return Widget[] $widgets
     * @throws NotFoundException
     * @throws GeneralException
     */
    private function getSubPlaylistResolvedWidgets(Widget $widget, int $parentWidgetId = 0): array
    {
        $this->getLogger()->debug('getSubPlaylistResolvedWidgets: widgetId is ' . $widget->widgetId
            . ', parentWidgetId is ' . $parentWidgetId);

        $arrangement = $widget->getOptionValue('arrangement', 'none');
        $remainder = $widget->getOptionValue('remainder', 'none');
        $cyclePlayback = $widget->getOptionValue('cyclePlaybackEnabled', 0);
        $playCount = $widget->getOptionValue('playCount', 0);
        $isRandom = $widget->getOptionValue('cycleRandomWidget', 0);

        $this->logger->debug('Resolve widgets for Sub-Playlist ' . $widget->widgetId
            . ' with arrangement ' . $arrangement . ' and remainder ' . $remainder);

        // As a first step, get all of our playlists widgets loaded into an array
        /** @var Widget[] $resolvedWidgets */
        $resolvedWidgets = [];
        $widgets = [];
        $firstList = null;
        $firstListCount = 0;
        $largestListKey = null;
        $largestListCount = 0;
        $smallestListCount = 0;

        // Expand or Shrink each of our assigned lists according to the Spot options (if any)
        // Expand all widgets from sub-playlists
        foreach ($this->getAssignedPlaylists($widget) as $playlistItem) {
            try {
                $playlist = $this->playlistFactory->getById($playlistItem->playlistId)
                    ->setModuleFactory($this->moduleFactory);
            } catch (NotFoundException $notFoundException) {
                $this->logger->error('getSubPlaylistResolvedWidgets: widget references a playlist which no longer exists. widgetId: '//phpcs:ignore
                    . $widget->widgetId . ', playlistId: ' . $playlistItem->playlistId);
                continue;
            }
            $expanded = $playlist->expandWidgets($parentWidgetId);
            $countExpanded = count($expanded);

            // Assert top level options
            // ------------------------
            // options such as stats/cycle playback are asserted from the top down
            // this is not a saved change, we assess this every time
            $playlistEnableStat = empty($playlist->enableStat)
                ? $this->configService->getSetting('PLAYLIST_STATS_ENABLED_DEFAULT')
                : $playlist->enableStat;

            foreach ($expanded as $subPlaylistWidget) {
                // Handle proof of play
                // Go through widgets assigned to this Playlist, if their enableStat is set to Inherit alter that option
                // in memory for this widget.
                $subPlaylistWidgetEnableStat = $subPlaylistWidget->getOptionValue(
                    'enableStat',
                    $this->configService->getSetting('WIDGET_STATS_ENABLED_DEFAULT')
                );

                if ($subPlaylistWidgetEnableStat == 'Inherit') {
                    $this->logger->debug('For widget ID ' . $subPlaylistWidget->widgetId
                        . ' enableStat was Inherit, changed to Playlist enableStat value - ' . $playlistEnableStat);
                    $subPlaylistWidget->setOptionValue('enableStat', 'attrib', $playlistEnableStat);
                }

                // Cycle Playback
                // --------------
                // currently we only support cycle playback on the topmost level.
                // https://github.com/xibosignage/xibo/issues/2869
                $subPlaylistWidget->setOptionValue('cyclePlayback', 'attrib', $cyclePlayback);
                $subPlaylistWidget->setOptionValue('playCount', 'attrib', $playCount);
                $subPlaylistWidget->setOptionValue('isRandom', 'attrib', $isRandom);
            }

            // Do we have a number of spots set?
            $this->logger->debug($playlistItem->spots . ' spots for playlistId ' . $playlistItem->playlistId);

            // Do we need to expand or shrink our list to make our Spot length
            if ($playlistItem->spots !== null && $playlistItem->spots != $countExpanded) {
                // We do need to do something!
                $this->logger->debug('There are ' . count($expanded) . ' Widgets in the list and we want '
                    . $playlistItem->spots . ' fill is ' . $playlistItem->spotFill);

                // If our spot size is 0, then we deliberately do not add to the final widgets array
                if ($playlistItem->spots == 0) {
                    if ($firstList === null && count($expanded) > 0) {
                        // If this is the first list, and it contains some values, then set it.
                        $firstList = $expanded;
                    }

                    // Skip over this one (we want to ignore it as it has spots = 0)
                    continue;
                }

                // If there are 0 items in the list, we need to fill
                if (count($expanded) <= 0) {
                    // If this is the first list, then we need to skip it completely
                    if ($firstList === null) {
                        continue;
                    } else {
                        // Not the first list, so we can swap over to fill mode and use the first list instead
                        $playlistItem->spotFill = 'fill';
                    }
                }

                // Expand the list out, using the fill options.
                $spotFillIndex = 0;
                while (count($expanded) < $playlistItem->spots) {
                    $spotsToFill = $playlistItem->spots - count($expanded);

                    if ($playlistItem->spotFill == 'repeat' || $firstList === null) {
                        // Repeat the list to fill the spots
                        $expanded = array_merge($expanded, $expanded);
                    } else if ($playlistItem->spotFill == 'fill') {
                        // Get Playlist 1 and use it to fill
                        // Filling means taking playlist 1 and putting in on the end of the current list
                        // until we're full
                        $expanded = array_merge($expanded, $firstList);
                    } else if ($playlistItem->spotFill == 'pad') {
                        // Get Playlist 1 and use it to pad
                        // padding means taking playlist 1 and interleaving it with the current list, until we're
                        // full
                        $new = [];
                        $loops = $spotsToFill / count($expanded);

                        for ($i = 0; $i < count($expanded); $i++) {
                            // Take one from the playlist we're operating on
                            $new[] = $expanded[$i];

                            // Take $loops from the filler playlist (the first one)
                            for ($j = 0; $j < $loops; $j++) {
                                $new[] = $firstList[$spotFillIndex];
                                $spotFillIndex++;

                                // if we've gone around too far, then start from the beginning.
                                if ($spotFillIndex >= count($firstList)) {
                                    $spotFillIndex = 0;
                                }
                            }
                        }
                        $expanded = $new;
                    }
                }

                if (count($expanded) > $playlistItem->spots) {
                    // Chop the list down to size.
                    $expanded = array_slice($expanded, 0, $playlistItem->spots);
                }

                // Update our count of expanded widgets to be the spots
                $countExpanded = $playlistItem->spots;
            } else if ($countExpanded <= 0) {
                // No spots required and no content in this list.
                continue;
            }

            // first watermark
            if ($firstList === null) {
                $firstList = $expanded;
            }

            if ($firstListCount === 0) {
                $firstListCount = $countExpanded;
            }

            // high watermark
            if ($countExpanded > $largestListCount) {
                $largestListCount = $countExpanded;
                $largestListKey = $playlistItem->playlistId . '_' . $playlistItem->rowNo;
            }

            // low watermark
            if ($countExpanded < $smallestListCount || $smallestListCount === 0) {
                $smallestListCount = $countExpanded;
            }

            // Adjust the widget duration if necessary
            if ($playlistItem->spotLength !== null && $playlistItem->spotLength > 0) {
                foreach ($expanded as $widget) {
                    $widget->useDuration = 1;
                    $widget->duration = $playlistItem->spotLength;
                    $widget->calculatedDuration = $playlistItem->spotLength;
                }
            }

            $widgets[$playlistItem->playlistId . '_' . $playlistItem->rowNo] = $expanded;
        }

        $this->logger->debug('Finished parsing all sub-playlists, smallest list is ' . $smallestListCount
            . ' widgets in size, largest is ' . $largestListCount);

        if ($smallestListCount == 0 && $largestListCount == 0) {
            $this->logger->debug('No Widgets to order');
            return [];
        }

        // Enable for debugging only - large log
        //$thislogger->debug(json_encode($widgets));
        $takeIndices = [];
        $lastTakeIndices = [];

        // Arrangement first
        if ($arrangement === 'even' && $smallestListCount > 0) {
            // Evenly distributed by round-robin
            $arrangement = 'roundrobin';

            // We need to decide how frequently we take from the respective lists.
            // this is different for each list.
            foreach (array_keys($widgets) as $listKey) {
                $takeIndices[$listKey] = intval(floor(count($widgets[$listKey]) / $smallestListCount));
                $lastTakeIndices[$listKey] = -1;
            }
        } else {
            // On a standard round-robin, we take every 1 item (i.e. one from each).
            foreach (array_keys($widgets) as $listKey) {
                $takeIndices[$listKey] = 1;
                $lastTakeIndices[$listKey] = -1;
            }
        }

        $this->logger->debug('Take Indices: ' . json_encode($takeIndices));

        // Round-robin or sequentially
        if ($arrangement === 'roundrobin') {
            // Round Robin
            // Take 1 from each until we have run out, use the smallest list as the "key"
            $loopCount = $largestListCount / $takeIndices[$largestListKey];

            $this->logger->debug('Round-Robin: We will loop a maximum of ' . $loopCount . ' times');

            for ($i = 0; $i < $loopCount; $i++) {
                $this->logger->debug('Loop number ' . $i);

                foreach (array_keys($widgets) as $listKey) {
                    // How many items should we take from this list each time we go around?
                    $takeEvery = $takeIndices[$listKey];
                    $countInList = count($widgets[$listKey]);

                    $this->logger->debug('Assessing playlistId ' . $listKey . ' which has '
                        . $countInList . ' widgets.');

                    for ($count = 1; $count <= $takeEvery; $count++) {
                        // Increment the last index we consumed from this list each time
                        $index = $lastTakeIndices[$listKey] + 1;

                        // Does this key actually have this many items?
                        if ($index >= $countInList) {
                            // it does not :o
                            $this->logger->debug('Index ' . $index
                                . ' is higher than the count of widgets in the list ' . $countInList);
                            // what we do depends on our remainder setting
                            // if we drop, we stop, otherwise we skip
                            if ($remainder === 'drop') {
                                // Stop everything, we've got enough
                                break 3;
                            } else if ($remainder === 'repeat') {
                                // start this list again from the beginning.
                                $index = 0;
                            } else {
                                // Just skip this key
                                continue 2;
                            }
                        }

                        $this->logger->debug('Selecting widget at position ' . $index
                            . ' from playlistId ' . $listKey);

                        // Append the key at the position
                        $resolvedWidgets[] = $widgets[$listKey][$index];

                        // Update our last take index for this list.
                        $lastTakeIndices[$listKey] = $index;
                    }
                }
            }
        } else {
            // None
            // If the arrangement is none we just add all the widgets together
            // Merge the arrays together for returning
            foreach ($widgets as $items) {
                if ($remainder === 'drop') {
                    $this->logger->debug('Dropping list of ' . count($items)
                        . ' widgets down to ' . $smallestListCount);

                    // We trim all arrays down to the smallest of them
                    $items = array_slice($items, 0, $smallestListCount);
                } else if ($remainder === 'repeat') {
                    $this->logger->debug('Expanding list of ' . count($items) . ' widgets to ' . $largestListCount);

                    while (count($items) < $largestListCount) {
                        $items = array_merge($items, $items);
                    }

                    // Finally trim (we might have added too many if the list sizes aren't exactly divisible)
                    $items = array_slice($items, 0, $largestListCount);
                }

                $resolvedWidgets = array_merge($resolvedWidgets, $items);
            }
        }

        // At the end of it, log out what we've calculated
        $log = 'Resolved: ';
        foreach ($resolvedWidgets as $resolvedWidget) {
            $log .= $resolvedWidget->playlistId . '-' . $resolvedWidget->widgetId . ',';

            // Should my from/to dates be applied to the resolved widget?
            // only if they are more restrictive.
            // because this is recursive, we should end up with the top most widget being "ruler" of the from/to dates
            if ($widget->fromDt > $resolvedWidget->fromDt) {
                $resolvedWidget->fromDt = $widget->fromDt;
            }

            if ($widget->toDt < $resolvedWidget->toDt) {
                $resolvedWidget->toDt = $widget->toDt;
            }
        }
        $this->logger->debug($log);

        return $resolvedWidgets;
    }

    /**
     * TODO: we will need a way to upgrade from early v3 to late v3
     *  (this can replace convertOldPlaylistOptions in Layout Factory)
     * @return void
     */
    private function toDoUpgrade(Widget $widget)
    {
        $playlists = json_decode($widget->getOptionValue('subPlaylists', '[]'), true);
        if (count($playlists) <= 0) {
            // Try and load them the old way.
            $this->getLogger()->debug('getAssignedPlaylists: playlists not found in subPlaylists option, loading the old way.');//@phpcs:ignore

            $playlistIds = json_decode($widget->getOptionValue('subPlaylistIds', '[]'), true);
            $subPlaylistOptions = json_decode($widget->getOptionValue('subPlaylistOptions', '[]'), true);
            $i = 0;
            foreach ($playlistIds as $playlistId) {
                $i++;
                $playlists[] = [
                    'rowNo' => $i,
                    'playlistId' => $playlistId,
                    'spotFill' => $subPlaylistOptions[$playlistId]['subPlaylistIdSpotFill'] ?? null,
                    'spotLength' => $subPlaylistOptions[$playlistId]['subPlaylistIdSpotLength'] ?? null,
                    'spots' => $subPlaylistOptions[$playlistId]['subPlaylistIdSpots'] ?? null,
                ];
            }
        } else {
            $this->getLogger()->debug('getAssignedPlaylists: playlists found in subPlaylists option.');
        }



        // Tidy up any old options
        if ($widget->getOptionValue('subPlaylistIds', null) !== null) {
            $widget->setOptionValue('subPlaylistIds', 'attrib', null);
            $widget->setOptionValue('subPlaylistOptions', 'attrib', null);
        }
    }

    /**
     * Handle a region being added
     * @param RegionAddedEvent $event
     * @return void
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws \Xibo\Support\Exception\DuplicateEntityException
     */
    public function onRegionAdded(RegionAddedEvent $event)
    {
        // We are a canvas region
        if ($event->getRegion()->type === 'canvas') {
            $this->getLogger()->debug('onRegionAdded: canvas region found, adding global widget');

            // Add the global widget
            $module = $this->moduleFactory->getById('core-canvas');

            $widget = $this->widgetFactory->create(
                $event->getRegion()->getOwnerId(),
                $event->getRegion()->regionPlaylist->playlistId,
                $module->type,
                $module->defaultDuration,
                $module->schemaVersion
            );

            $widget->calculateDuration($module);

            $event->getRegion()->regionPlaylist->assignWidget($widget, 1);
            $event->getRegion()->regionPlaylist->save(['notify' => false, 'validate' => false]);
        }
    }
}
