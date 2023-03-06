<?php
/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
namespace Xibo\Widget;

use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\Widget;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class Playlist
 * @package Xibo\Widget
 */
class SubPlaylist extends ModuleWidget
{
    /**
     * A private cache of resolved widgets
     * @var Widget[]
     */
    private $_resolvedWidgets = [];

    /** @inheritdoc */
    public function isValid()
    {
        $valid = self::$STATUS_VALID;
        if (count($this->getAssignedPlaylists()) <= 0) {
            $valid = self::$STATUS_INVALID;
        } else {
            foreach ($this->getAssignedPlaylists() as $playlistItem) {
                try {
                    $this->playlistFactory->getById($playlistItem->playlistId);
                } catch (NotFoundException $e) {
                    $this->getLog()->error('Misconfigured sub playlist, playlist ID '
                        . $playlistItem->playlistId . ' Not found');
                    $valid =  self::$STATUS_INVALID;
                }
            }
        }
        if ($valid == 0) {
            throw new InvalidArgumentException(__('Please select a Playlist'), 'playlistId');
        }

        return $valid;
    }

    /** @inheritdoc */
    public function layoutDesignerJavaScript()
    {
        return 'subplaylist-designer-javascript';
    }

    /**
     * @inheritDoc
     */
    public function getExtra()
    {
        return [
            'subPlaylists' => $this->getAssignedPlaylists(),
        ];
    }

    /**
     * @return \Xibo\Widget\SubPlaylistItem[]
     */
    public function getAssignedPlaylists(): array
    {
        $playlists = json_decode($this->getOption('subPlaylists', '[]'), true);
        if (count($playlists) <= 0) {
            // Try and load them the old way.
            $playlistIds = json_decode($this->getOption('subPlaylistIds', '[]'), true);
            $subPlaylistOptions = json_decode($this->getOption('subPlaylistOptions', '[]'), true);
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
        }

        $playlistItems = [];
        foreach ($playlists as $playlist) {
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
     * @param int $playlistId
     * @return \Xibo\Widget\SubPlaylistItem|null
     */
    public function getAssignedPlaylistById(int $playlistId): ?SubPlaylistItem
    {
        foreach ($this->getAssignedPlaylists() as $playlist) {
            if ($playlistId === $playlist->playlistId) {
                return $playlist;
            }
        }
        return null;
    }

    /**
     * Edit Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}?subPlaylist",
     *  operationId="WidgetSubPlaylistEdit",
     *  tags={"widget"},
     *  summary="Edit a Sub-Playlist Widget",
     *  description="Edit a new Sub-Playlist Widget. This call will replace existing Widget object, all not supplied parameters will be set to default.",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The WidgetId to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="subPlaylistId",
     *      type="array",
     *      in="formData",
     *      description="The Playlist Ids to assign",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Parameter(
     *      name="arrangement",
     *      in="formData",
     *      description="Arrangement type - even, roundrobin, none",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="remainder",
     *      in="formData",
     *      description="Reminder - drop, repeat, none",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="subPlaylistIdSpots",
     *      type="array",
     *      in="formData",
     *      description="An array with number of spots for each Playlist",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Parameter(
     *      name="subPlaylistIdSpotLength",
     *      type="array",
     *      in="formData",
     *      description="An array with spot length for each Playlist",
     *      required=true,
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Parameter(
     *      name="subPlaylistIdSpotFill",
     *      type="array",
     *      in="formData",
     *      description="An array of spot fill type for each Playlist - fill, repeat, pad",
     *      required=true,
     *      @SWG\Items(
     *          type="string"
     *      )
     *  ),
     *  @SWG\Parameter(
     *      name="cyclePlaybackEnabled",
     *      in="formData",
     *      description="Enable cycle based playback?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="playCount",
     *      in="formData",
     *      description="In cycle based playback, how many plays should each Widget have before moving on?",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="cycleRandomWidget",
     *      in="formData",
     *      description="In cycle based playback, a random Widget will be selected at the start of each cycle and shown until its play count has been met.",
     *      type="integer",
     *      required=false
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @inheritDoc
     */
    public function edit(Request $request, Response $response): Response
    {
        // Set some dud durations
        $this->setDuration(10);
        $this->setUseDuration(0);
        $sanitizedParams = $this->getSanitizer($request->getParams());
        $this->setOption('name', $sanitizedParams->getString('name'));

        // Options
        $this->setOption('arrangement', $sanitizedParams->getString('arrangement'));
        $this->setOption('remainder', $sanitizedParams->getString('remainder'));

        // Cycle based playback options
        $this->setOption('cyclePlaybackEnabled', $sanitizedParams->getCheckbox('cyclePlaybackEnabled'));
        $this->setOption('playCount', $sanitizedParams->getCheckbox('cyclePlaybackEnabled')
            ? $sanitizedParams->getInt('playCount')
            : null);
        $this->setOption('cycleRandomWidget', $sanitizedParams->getCheckbox('cyclePlaybackEnabled')
            ? $sanitizedParams->getCheckbox('cycleRandomWidget')
            : 0);

        if ($sanitizedParams->getCheckbox('cyclePlaybackEnabled') && empty($sanitizedParams->getInt('playCount'))) {
            throw new InvalidArgumentException(__('Please enter Play Count.'), 'playCount');
        }

        // Playlist setting arrays
        $spots = $sanitizedParams->getArray('subPlaylistIdSpots');
        $spotLength = $sanitizedParams->getArray('subPlaylistIdSpotLength');
        $spotFill = $sanitizedParams->getArray('subPlaylistIdSpotFill');

        // Get our existing IDs
        $existingSubPlaylistIds = [];
        foreach ($this->getAssignedPlaylists() as $assignedPlaylist) {
            if (!in_array($assignedPlaylist->playlistId, $existingSubPlaylistIds)) {
                $existingSubPlaylistIds[] = $assignedPlaylist->playlistId;
            }
        }

        // Make up a companion setting which maps the playlistIds to the options
        $subPlaylists = [];
        $subPlaylistIds = [];
        $i = -1;

        foreach ($sanitizedParams->getIntArray('subPlaylistId', ['default' => []]) as $playlistId) {
            $i++;

            if ($playlistId == '') {
                continue;
            }

            if ($spots[$i] < 0) {
                throw new InvalidArgumentException(
                    __('Number of spots must be empty, 0 or a positive number'),
                    'subPlaylistIdSpots'
                );
            }

            if ($spotLength[$i] < 0) {
                throw new InvalidArgumentException(
                    __('Spot length must be empty, 0 or a positive number'),
                    'subPlaylistIdSpotLength'
                );
            }

            $item = new SubPlaylistItem();
            $item->playlistId = $playlistId;
            $item->rowNo = $i + 1;
            $item->spots = $spots[$i] ?? '';
            $item->spotLength = $spotLength[$i] ?? '';
            $item->spotFill = $spotFill[$i] ?? null;
            $subPlaylists[] = $item;

            if (!in_array($playlistId, $subPlaylistIds)) {
                $subPlaylistIds[] = $playlistId;
            }
        }

        // Validation
        if (count($subPlaylists) < 1) {
            throw new InvalidArgumentException(__('Please select at least 1 Playlist to embed'), 'subPlaylistId');
        }

        // Set this new option
        $this->setOption('subPlaylists', json_encode($subPlaylists));

        // Work out whether we've added/removed
        $addedEntries = array_diff($subPlaylistIds, $existingSubPlaylistIds);
        $removedEntries = array_diff($existingSubPlaylistIds, $subPlaylistIds);

        $this->getLog()->debug('Added ' . var_export($addedEntries, true));
        $this->getLog()->debug('Removed ' . var_export($removedEntries, true));

        // Remove items from closure table if necessary
        foreach ($removedEntries as $entry) {
            $this->getLog()->debug('Removing old link - existing link child is ' . $entry);

            $this->getStore()->update('
                    DELETE link
                      FROM `lkplaylistplaylist` p, `lkplaylistplaylist` link, `lkplaylistplaylist` c
                     WHERE p.parentId = link.parentId AND c.childId = link.childId
                       AND p.childId = :parentId AND c.parentId = :childId
                ', [
                'parentId' => $this->getPlaylistId(),
                'childId' => $entry
            ]);
        }

        foreach ($addedEntries as $addedEntry) {
            $this->getLog()->debug('Manage closure table for parent ' . $this->getPlaylistId()
                . ' and child ' . $addedEntry);

            if ($this->getStore()->exists('
                SELECT parentId, childId, depth 
                  FROM lkplaylistplaylist 
                 WHERE childId = :childId AND parentId = :parentId 
            ', [
                'parentId' => $this->getPlaylistId(),
                'childId' => $addedEntry
            ])) {
                throw new InvalidArgumentException(__('Cannot add the same SubPlaylist twice.'), 'playlistId');
            }

            $this->getStore()->insert('
                INSERT INTO `lkplaylistplaylist` (parentId, childId, depth)
                SELECT p.parentId, c.childId, p.depth + c.depth + 1
                  FROM lkplaylistplaylist p, lkplaylistplaylist c
                 WHERE p.childId = :parentId AND c.parentId = :childId
            ', [
                'parentId' => $this->getPlaylistId(),
                'childId' => $addedEntry
            ]);
        }

        // Make sure we've not created a circular reference
        // this is a lazy last minute check as we can't really tell if there is a circular reference unless
        // we've inserted the records already.
        if ($this->getStore()->exists('
            SELECT depth 
              FROM `lkplaylistplaylist` 
             WHERE parentId = :parentId 
               AND childId = parentId 
               AND depth > 0
        ', ['parentId' => $this->getPlaylistId()])) {
            throw new InvalidArgumentException(
                __('This assignment creates a loop because the Playlist being assigned contains the Playlist being worked on.'),
                'subPlaylistId'
            );
        }

        // Tidy up any old options
        if ($this->getOption('subPlaylistIds') !== null) {
            $this->setOption('subPlaylistIds', null);
            $this->setOption('subPlaylistOptions', null);
        }

        // Save the widget
        $this->saveWidget();

        return $response;
    }

    /** @inheritdoc */
    public function delete(Request $request, Response $response): Response
    {
        $response = parent::delete($request, $response);

        $subPlaylists = $this->getAssignedPlaylists();

        // tidy up the closure table records.
        foreach ($subPlaylists as $subPlaylist) {
            $this->getStore()->update('
            DELETE link
              FROM `lkplaylistplaylist` p, `lkplaylistplaylist` link, `lkplaylistplaylist` c
             WHERE p.parentId = link.parentId AND c.childId = link.childId
               AND p.childId = :parentId AND c.parentId = :childId
        ', [
                'parentId' => $this->getPlaylistId(),
                'childId' => $subPlaylist->playlistId
            ]);
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function preview($width, $height, $scaleOverride = 0)
    {
        // Output a summary
        $resolvedWidgets = $this->getSubPlaylistResolvedWidgets();

        return '
            <div style="text-align:center;">
                <i alt="' . __($this->module->name) . ' thumbnail" class="fa module-preview-icon module-icon-' . __($this->module->type) . '"></i>
                <br/>
                ' . __('%d Widgets / %d seconds', count($resolvedWidgets), $this->getSubPlaylistResolvedDuration()) . '
            </div>';
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        $names = [];

        foreach ($this->getAssignedPlaylists() as $playlist) {
            try {
                $names[] = $this->playlistFactory->getById($playlist->playlistId)->name;
            } catch (NotFoundException $e) {
                $this->getLog()->error('Misconfigured sub playlist, playlist ID '
                    . $playlist->playlistId . ' Not found');
                $names[] = '';
            }
        }

        return __('Sub-Playlist: %s', implode(', ', $names));
    }

    /**
     * @param int $parentWidgetId this tracks the top level widgetId
     * @return Widget[] $widgets
     * @throws NotFoundException
     * @throws GeneralException
     */
    public function getSubPlaylistResolvedWidgets($parentWidgetId = 0): array
    {
        // This is expensive, so cache it if we can.
        if ($this->_resolvedWidgets != null) {
            return $this->_resolvedWidgets;
        }

        $arrangement = $this->getOption('arrangement', 'none');
        $remainder = $this->getOption('remainder', 'none');
        $cyclePlayback = $this->getOption('cyclePlaybackEnabled', 0);
        $playCount = $this->getOption('playCount', 0);
        $isRandom = $this->getOption('cycleRandomWidget', 0);

        $this->getLog()->debug('Resolve widgets for Sub-Playlist ' . $this->getWidgetId()
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
        foreach ($this->getAssignedPlaylists() as $playlistItem) {
            // Get the Playlist and expand its widgets
            try {
                $playlist = $this->playlistFactory->getById($playlistItem->playlistId)
                    ->setModuleFactory($this->moduleFactory);
            } catch (NotFoundException $notFoundException) {
                $this->getLog()->error('getSubPlaylistResolvedWidgets: widget references a playlist which no longer exists. widgetId: '//phpcs:ignore
                    . $this->getWidgetId() . ', playlistId: ' . $playlistItem->playlistId);
                continue;
            }
            $expanded = $playlist->expandWidgets($parentWidgetId);
            $countExpanded = count($expanded);

            // Assert top level options
            // ------------------------
            // options such as stats/cycle playback are asserted from the top down
            // this is not a saved change, we assess this every time
            $playlistEnableStat = empty($playlist->enableStat)
                ? $this->getConfig()->getSetting('PLAYLIST_STATS_ENABLED_DEFAULT')
                : $playlist->enableStat;

            foreach ($expanded as $subPlaylistWidget) {
                // Handle proof of play
                // Go through widgets assigned to this Playlist, if their enableStat is set to Inherit alter that option
                // in memory for this widget.
                $subPlaylistWidgetEnableStat = $subPlaylistWidget->getOptionValue(
                    'enableStat',
                    $this->getConfig()->getSetting('WIDGET_STATS_ENABLED_DEFAULT')
                );

                if ($subPlaylistWidgetEnableStat == 'Inherit') {
                    $this->getLog()->debug('For widget ID ' . $subPlaylistWidget->widgetId
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
            $this->getLog()->debug($playlistItem->spots . ' spots for playlistId ' . $playlistItem->playlistId);

            // Do we need to expand or shrink our list to make our Spot length
            if ($playlistItem->spots !== null && $playlistItem->spots != $countExpanded) {
                // We do need to do something!
                $this->getLog()->debug('There are ' . count($expanded) . ' Widgets in the list and we want '
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

        $this->getLog()->debug('Finished parsing all sub-playlists, smallest list is ' . $smallestListCount
            . ' widgets in size, largest is ' . $largestListCount);

        if ($smallestListCount == 0 && $largestListCount == 0) {
            $this->getLog()->debug('No Widgets to order');
            return [];
        }

        // Enable for debugging only - large log
        //$this->getLog()->debug(json_encode($widgets));
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

        $this->getLog()->debug('Take Indices: ' . json_encode($takeIndices));

        // Round-robin or sequentially
        if ($arrangement === 'roundrobin') {
            // Round Robin
            // Take 1 from each until we have run out, use the smallest list as the "key"
            $loopCount = $largestListCount / $takeIndices[$largestListKey];

            $this->getLog()->debug('Round-Robin: We will loop a maximum of ' . $loopCount . ' times');

            for ($i = 0; $i < $loopCount; $i++) {
                $this->getLog()->debug('Loop number ' . $i);

                foreach (array_keys($widgets) as $listKey) {
                    // How many items should we take from this list each time we go around?
                    $takeEvery = $takeIndices[$listKey];
                    $countInList = count($widgets[$listKey]);

                    $this->getLog()->debug('Assessing playlistId ' . $listKey . ' which has '
                        . $countInList . ' widgets.');

                    for ($count = 1; $count <= $takeEvery; $count++) {
                        // Increment the last index we consumed from this list each time
                        $index = $lastTakeIndices[$listKey] + 1;

                        // Does this key actually have this many items?
                        if ($index >= $countInList) {
                            // it does not :o
                            $this->getLog()->debug('Index ' . $index
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

                        $this->getLog()->debug('Selecting widget at position ' . $index
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
                    $this->getLog()->debug('Dropping list of ' . count($items)
                        . ' widgets down to ' . $smallestListCount);

                    // We trim all arrays down to the smallest of them
                    $items = array_slice($items, 0, $smallestListCount);
                } else if ($remainder === 'repeat') {
                    $this->getLog()->debug('Expanding list of ' . count($items) . ' widgets to ' . $largestListCount);

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
            if ($this->widget->fromDt > $resolvedWidget->fromDt) {
                $resolvedWidget->fromDt = $this->widget->fromDt;
            }

            if ($this->widget->toDt < $resolvedWidget->toDt) {
                $resolvedWidget->toDt = $this->widget->toDt;
            }
        }
        $this->getLog()->debug($log);

        // Cache (maybe we will replace this with Stash cache?)
        $this->_resolvedWidgets = $resolvedWidgets;

        return $this->_resolvedWidgets;
    }

    /**
     * @return int
     * @throws NotFoundException
     * @throws GeneralException
     */
    public function getSubPlaylistResolvedDuration()
    {
        $duration = 0;
        // Add all the sub-playlists widgets too
        foreach ($this->getSubPlaylistResolvedWidgets() as $widget) {
            $duration += $widget->calculatedDuration;
        }

        return $duration;
    }

    /**
     * @inheritdoc
     */
    public function getResource($displayId = 0)
    {
        return '';
    }
}
