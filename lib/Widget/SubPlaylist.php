<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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
use Xibo\Entity\Widget;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;

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
        if (count($this->getAssignedPlaylistIds()) <= 0) {
           $valid = self::$STATUS_INVALID;
        } else {
            foreach ($this->getAssignedPlaylistIds() as $playlistId) {
                try {
                    $this->playlistFactory->getById($playlistId);
                } catch (NotFoundException $e) {
                    $this->getLog()->error('Misconfigured subplaylist, playlist ID ' . $playlistId . ' Not found');
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
     * Extra data for the Form rendering
     * @return array
     */
    public function getExtra()
    {
        return [
            'playlists' => $this->getAssignablePlaylists(),
            'subPlaylistId' => $this->getAssignedPlaylistIds(),
            'subPlaylistOptions'=> $this->getSubPlaylistOptions()
        ];
    }

    /**
     * @return int[]
     */
    public function getAssignedPlaylistIds()
    {
        return json_decode($this->getOption('subPlaylistIds', '[]'), true);
    }

    /**
     * @param int[] $playlistIds
     * @return $this
     */
    protected function setAssignedPlaylistIds($playlistIds)
    {
        $this->setOption('subPlaylistIds', json_encode($playlistIds));
        return $this;
    }

    /**
     * Get Sub-Playlist Options
     * @param null $playlistId
     * @return array|mixed
     */
    public function getSubPlaylistOptions($playlistId = null)
    {
        $subPlaylistOptions = json_decode($this->getOption('subPlaylistOptions', '[]'), true);

        if ($playlistId == null) {
            return $subPlaylistOptions;
        } else {
            return isset($subPlaylistOptions[$playlistId]) ? $subPlaylistOptions[$playlistId] : [];
        }
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
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     *
     * @throws InvalidArgumentException
     */
    public function edit()
    {
        // Set some dud durations
        $this->setDuration(10);
        $this->setUseDuration(0);

        // Options
        $this->setOption('arrangement', $this->getSanitizer()->getString('arrangement'));
        $this->setOption('remainder', $this->getSanitizer()->getString('remainder'));

        // Get the list of playlists
        $subPlaylistId = $this->getSanitizer()->getIntArray('subPlaylistId');
        $spots = $this->getSanitizer()->getStringArray('subPlaylistIdSpots');
        $spotLength = $this->getSanitizer()->getStringArray('subPlaylistIdSpotLength');
        $spotFill = $this->getSanitizer()->getStringArray('subPlaylistIdSpotFill');

        // Check to make sure we do not have any duplicates in the playlistId array at this level
        if (count($subPlaylistId) !== count(array_unique($subPlaylistId, SORT_NUMERIC))) {
            throw new InvalidArgumentException(__('Please do not use the same Playlist twice'), 'playlistId');
        }

        // Make up a companion setting which maps the playlistIds to the options
        $subPlaylistOptions = [];
        $i = -1;
        foreach ($subPlaylistId as $playlistId) {
            $i++;

            if ($playlistId == '') {
                continue;
            }

            if ($spots[$i] < 0) {
                throw new InvalidArgumentException(__('Number of spots must be empty, 0 or a positive number'), 'subPlaylistIdSpots');
            }

            if ($spotLength[$i] < 0) {
                throw new InvalidArgumentException(__('Spot length must be empty, 0 or a positive number'), 'subPlaylistIdSpotLength');
            }

            // Map the stop code received to the stop ref (if there is one)
            $subPlaylistOptions[$playlistId] = [
                'subPlaylistIdSpots' => isset($spots[$i]) ? $spots[$i] : '',
                'subPlaylistIdSpotLength' => isset($spotLength[$i]) ? $spotLength[$i] : '',
                'subPlaylistIdSpotFill' => isset($spotFill[$i]) ? $spotFill[$i] : '',
            ];
        }

        $this->setOption('subPlaylistOptions', json_encode($subPlaylistOptions));

        // Existing Playlists (if any)
        $existingSubPlaylistId = $this->getAssignedPlaylistIds();

        // Validation
        if (count($subPlaylistId) < 1)
            throw new InvalidArgumentException(__('Please select at least 1 Playlist to embed'), 'subPlaylistId');

        // Set the new list
        $this->setAssignedPlaylistIds($subPlaylistId);

        // Work out whether we've added/removed
        $addedEntries = array_diff($subPlaylistId, $existingSubPlaylistId);
        $removedEntries = array_diff($existingSubPlaylistId, $subPlaylistId);

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
            $this->getLog()->debug('Manage closure table for parent ' . $this->getPlaylistId() . ' and child ' . $addedEntry);

            if ($this->getStore()->exists('SELECT parentId, childId, depth FROM lkplaylistplaylist WHERE childId = :childId AND parentId = :parentId ', [
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
            throw new InvalidArgumentException(__('This assignment creates a loop because the Playlist being assigned contains the Playlist being worked on.'), 'subPlaylistId');
        }

        // Save the widget
        $this->saveWidget();
    }

    /** @inheritdoc */
    public function delete()
    {
        parent::delete();

        $subPlaylistIds = $this->getAssignedPlaylistIds();

        // tidy up the closure table records.
        foreach ($subPlaylistIds as $subPlaylistId) {
            $this->getStore()->update('
            DELETE link
              FROM `lkplaylistplaylist` p, `lkplaylistplaylist` link, `lkplaylistplaylist` c
             WHERE p.parentId = link.parentId AND c.childId = link.childId
               AND p.childId = :parentId AND c.parentId = :childId
        ', [
                'parentId' => $this->getPlaylistId(),
                'childId' => $subPlaylistId
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function preview($width, $height, $scaleOverride = 0)
    {
        // Output a summary
        $resolvedWidgets = $this->getSubPlaylistResolvedWidgets();

        $output = '
            <div style="text-align:center;">
                <i alt="' . __($this->module->name) . ' thumbnail" class="fa module-preview-icon module-icon-' . __($this->module->type) . '"></i>
                <br/>
                ' . __('%d Widgets / %d seconds', count($resolvedWidgets), $this->getSubPlaylistResolvedDuration()) . '
            </div>';
        return $output;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        $names = [];

        foreach ($this->getAssignedPlaylistIds() as $playlistId) {
            try {
                $names[] = $this->playlistFactory->getById($playlistId)->name;
            } catch (NotFoundException $e) {
                $this->getLog()->error('Misconfigured subplaylist, playlist ID ' . $playlistId . ' Not found');
                $names[] = '';
            }
        }

        return __('Sub-Playlist: %s', implode(', ', $names));
    }

    /**
     * @param int $parentWidgetId this tracks the top level widgetId
     * @return Widget[] $widgets
     * @throws \Xibo\Exception\NotFoundException
     * @throws \Xibo\Exception\InvalidArgumentException
     */
    public function getSubPlaylistResolvedWidgets($parentWidgetId = 0)
    {
        // This is expensive, so cache it if we can.
        if ($this->_resolvedWidgets != null) {
            return $this->_resolvedWidgets;
        }

        $arrangement = $this->getOption('arrangement', 'none');
        $remainder = $this->getOption('remainder', 'none');

        $this->getLog()->debug('Resolve widgets for Sub-Playlist ' . $this->getWidgetId() . ' with arrangement ' . $arrangement . ' and remainder ' . $remainder);

        // As a first step, get all of our playlists widgets loaded into an array
        /** @var Widget[] $resolvedWidgets */
        $resolvedWidgets = [];
        $widgets = [];
        $firstList = null;
        $firstListCount = 0;
        $largestListId = 0;
        $largestListCount = 0;
        $smallestListCount = 0;

        // Expand or Shrink each of our assigned lists according to the Spot options (if any)
        // Expand all widgets from sub-playlists
        foreach ($this->getAssignedPlaylistIds() as $playlistId) {
            // Get the Playlist and expand its widgets
            $playlist = $this->playlistFactory->getById($playlistId)->setModuleFactory($this->moduleFactory);
            $expanded = $playlist->expandWidgets($parentWidgetId);
            $countExpanded = count($expanded);

            // Handle proof of play settings
            // -----------------------------
            // Go through widgets assigned to this Playlist, if their enableStat is set to Inherit alter that option
            // in memory for this widget.
            // this is not a saved change, we assess this every time
            $playlistEnableStat = empty($playlist->enableStat)
                ? $this->getConfig()->getSetting('PLAYLIST_STATS_ENABLED_DEFAULT')
                : $playlist->enableStat;

            foreach ($expanded as $subPlaylistWidget) {

                $subPlaylistWidgetEnableStat = $subPlaylistWidget->getOptionValue('enableStat',
                    $this->getConfig()->getSetting('WIDGET_STATS_ENABLED_DEFAULT')
                );

                if ($subPlaylistWidgetEnableStat == 'Inherit') {
                    $this->getLog()->debug('For widget ID ' . $subPlaylistWidget->widgetId . ' enableStat was Inherit, changed to Playlist enableStat value - ' . $playlistEnableStat);
                    $subPlaylistWidget->setOptionValue('enableStat', 'attrib', $playlistEnableStat);
                }
            }

            // Do we have a number of spots set?
            $options = $this->getSubPlaylistOptions($playlistId);
            $spots = isset($options['subPlaylistIdSpots']) ? $options['subPlaylistIdSpots'] : null;
            $spotLength = isset($options['subPlaylistIdSpotLength']) ? intval($options['subPlaylistIdSpotLength']) : null;
            $spotFill = isset($options['subPlaylistIdSpotFill']) ? $options['subPlaylistIdSpotFill'] : null;

            $this->getLog()->debug($spots . ' spots for playlistId ' . $playlistId);

            // Do we need to expand or shrink our list to make our Spot length
            if ($spots !== null && $spots !== '' && intval($spots) != $countExpanded) {
                // We do need to do something!
                $spots = intval($spots);

                $this->getLog()->debug('There are ' . count($expanded) . ' Widgets in the list and we want ' . $spots . ' fill is ' . $spotFill);

                // If our spot size is 0, then we deliberately do not add to the final widgets array
                if ($spots == 0) {
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
                        $spotFill = 'fill';
                    }
                }

                // Expand the list out, using the fill options.
                $spotFillIndex = 0;
                while (count($expanded) < $spots) {
                    $spotsToFill = $spots - count($expanded);

                    if ($spotFill == 'repeat' || $firstList === null) {
                        // Repeat the list to fill the spots
                        $expanded = array_merge($expanded, $expanded);
                    } else if ($spotFill == 'fill') {
                        // Get Playlist 1 and use it to fill
                        // Filling means taking playlist 1 and putting in on the end of the current list
                        // until we're full
                        $expanded = array_merge($expanded, $firstList);
                    } else if ($spotFill == 'pad') {
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

                if (count($expanded) > $spots) {
                    // Chop the list down to size.
                    $expanded = array_slice($expanded, 0, $spots);
                }

                // Update our count of expanded widgets to be the spots
                $countExpanded = $spots;

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
                $largestListId = $playlistId;
            }

            // low watermark
            if ($countExpanded < $smallestListCount || $smallestListCount === 0) {
                $smallestListCount = $countExpanded;
            }

            // Adjust the widget duration if necessary
            if ($spotLength !== null && $spotLength > 0) {
                foreach ($expanded as $widget) {
                    $widget->useDuration = 1;
                    $widget->duration = $spotLength;
                    $widget->calculatedDuration = $spotLength;
                }
            }

            $widgets[$playlistId] = $expanded;
        }

        $this->getLog()->debug('Finished parsing all sub-playlists, smallest list is ' . $smallestListCount . ' widgets in size, largest is ' . $largestListCount);

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
            // Evenly distributed by round robin
            $arrangement = 'roundrobin';

            // We need to decide how frequently we take from the respective lists.
            // this is different for each list.
            foreach (array_keys($widgets) as $playlistId) {
                $takeIndices[$playlistId] = intval(floor(count($widgets[$playlistId]) / $smallestListCount));
                $lastTakeIndices[$playlistId] = -1;
            }
        } else {
            // On a standard round robin, we take every 1 item (i.e. one from each).
            foreach (array_keys($widgets) as $playlistId) {
                $takeIndices[$playlistId] = 1;
                $lastTakeIndices[$playlistId] = -1;
            }
        }

        $this->getLog()->debug('Take Indices: ' . json_encode($takeIndices));

        // Round robin or sequentially
        if ($arrangement === 'roundrobin') {
            // Round Robin
            // Take 1 from each until we have run out, use the smallest list as the "key"
            $loopCount = $largestListCount / $takeIndices[$largestListId];

            $this->getLog()->debug('Round-Robin: We will loop a maximum of ' . $loopCount . ' times');

            for ($i = 0; $i < $loopCount; $i++) {
                $this->getLog()->debug('Loop number ' . $i);

                foreach (array_keys($widgets) as $playlistId) {
                    // How many items should we take from this list each time we go around?
                    $takeEvery = $takeIndices[$playlistId];
                    $countInList = count($widgets[$playlistId]);

                    $this->getLog()->debug('Assessing playlistId ' . $playlistId . ' which has ' . $countInList . ' widgets.');

                    for ($count = 1; $count <= $takeEvery; $count++) {
                        // Increment the last index we consumed from this list each time
                        $index = $lastTakeIndices[$playlistId] + 1;

                        // Does this key actually have this many items?
                        if ($index >= $countInList) {
                            // it does not :o
                            $this->getLog()->debug('Index ' . $index . ' is higher than the count of widgets in the list ' . $countInList);
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

                        $this->getLog()->debug('Selecting widget at position ' . $index . ' from playlistId ' . $playlistId);

                        // Append the key at the position
                        $resolvedWidgets[] = $widgets[$playlistId][$index];

                        // Update our last take index for this list.
                        $lastTakeIndices[$playlistId] = $index;

                        //$this->getLog()->debug('There are ' . count($resolvedWidgets) . ' resolved Widgets');
                    }
                }
            }
        } else {
            // None
            // If the arrangement is none we just add all of the widgets together
            // Merge the arrays together for returning
            foreach ($widgets as $playlistId => $items) {
                if ($remainder === 'drop') {
                    $this->getLog()->debug('Dropping list of ' . count($items) . ' widgets down to ' . $smallestListCount);

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
     * @throws \Xibo\Exception\NotFoundException
     */
    public function getSubPlaylistResolvedDuration()
    {
        $duration = 0;
        // Add all of the sub-playlists widgets too
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