<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2018 Spring Signage Ltd
 * (Playlist.php)
 */


namespace Xibo\Widget;
use Xibo\Entity\Widget;
use Xibo\Exception\InvalidArgumentException;

/**
 * Class Playlist
 * @package Xibo\Widget
 */
class SubPlaylist extends ModuleWidget
{
    /** @inheritdoc */
    public function isValid()
    {
        return (count($this->getAssignedPlaylistIds()) > 0) ? self::$STATUS_VALID : self::$STATUS_INVALID;
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
            'subPlaylistId' => $this->getAssignedPlaylistIds()
        ];
    }

    /**
     * @return int[]
     */
    protected function getAssignedPlaylistIds()
    {
        return json_decode($this->getOption('subPlaylistIds', '[]'));
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
     * Edit Widget
     *
     * @SWG\Put(
     *  path="/playlist/widget/{widgetId}",
     *  operationId="WidgetSubPlaylistEdit",
     *  tags={"widget"},
     *  summary="Edit a Sub-Playlist Widget",
     *  description="Edit a new Sub-Playlist Widget",
     *  @SWG\Parameter(
     *      name="widgetId",
     *      in="path",
     *      description="The WidgetId to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="subPlaylistId",
     *      in="formData",
     *      description="The sub-playlist to embed",
     *      type="integer",
     *      required=false
     *   ),
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
        $this->setOption('spotLength', $this->getSanitizer()->getInt('spotLength'));
        $this->setOption('spots', $this->getSanitizer()->getInt('spots'));
        $this->setOption('spotFill', $this->getSanitizer()->getString('spotFill'));

        // Get the list of playlists
        $subPlaylistId = $this->getSanitizer()->getIntArray('subPlaylistId');
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
            $names[] = $this->playlistFactory->getById($playlistId)->name;
        }

        return __('Sub-Playlist: %s', implode(', ', $names));
    }

    /**
     * @return Widget[] $widgets
     * @throws \Xibo\Exception\NotFoundException
     */
    public function getSubPlaylistResolvedWidgets()
    {
        $arrangement = $this->getOption('arrangement', 'none');
        $remainder = $this->getOption('remainder', 'none');
        $spotLength = $this->getOption('spotLength', 0);
        $spots = $this->getOption('spots', 0);
        $spotFill = $this->getOption('spotFill', 'repeat');

        $this->getLog()->debug('Resolve widgets for Sub-Playlist ' . $this->getWidgetId() . ' with arrangement ' . $arrangement . ' and remainder ' . $remainder);

        // As a first step, get all of our playlists widgets loaded into an array
        $resolvedWidgets = [];
        $widgets = [];
        $firstListId = 0;
        $firstListCount = 0;
        $largestListCount = 0;
        $smallestListCount = 0;

        // Expand all widgets from sub-playlists
        foreach ($this->getAssignedPlaylistIds() as $playlistId) {
            $playlist = $this->playlistFactory->getById($playlistId)->setModuleFactory($this->moduleFactory);
            $expanded = $playlist->expandWidgets();
            $countExpanded = count($expanded);

            // first watermark
            if ($firstListCount === 0) {
                $firstListId = $playlistId;
                $firstListCount = $countExpanded;
            }

            // high watermark
            if ($countExpanded > $largestListCount)
                $largestListCount = $countExpanded;

            // low watermark
            if ($countExpanded < $smallestListCount || $smallestListCount === 0)
                $smallestListCount = $countExpanded;

            // Adjust the widget duration if necessary
            if ($spotLength > 0) {
                foreach ($expanded as $widget) {
                    $widget->useDuration = 1;
                    $widget->duration = $spotLength;
                    $widget->calculatedDuration = $spotLength;
                }
            }

            $widgets[$playlistId] = $expanded;
        }

        $this->getLog()->debug('Finished parsing all sub-playlists, smallest list is ' . $smallestListCount . ' widgets in size');

        // Arrangement first
        if ($arrangement === 'even') {
            // Evenly distributed by round robin
            $arrangement = 'roundrobin';

            // We need to decide how frequently we take from the respective lists.
            $takeEvery = intval(floor(($firstListCount < $largestListCount) ? 1 : ($largestListCount / $smallestListCount)));

            $this->getLog()->debug('Even arrangement, take items every ' . $takeEvery);
        } else {
            // On a standard round robin, we take every 1 item (i.e. one from each).
            $takeEvery = 1;
        }

        // Track the index we are taking items for the second or later lists.
        $lastTakeIndex = -1;
        $takeIndex = -1;

        // Round robin or seqentially
        if ($arrangement === 'roundrobin') {
            // Round Robin
            // Take 1 from each until we have run out, use the smallest list as the "key"
            for ($i = 0; $i < $largestListCount; $i++) {
                $first = true;
                foreach (array_keys($widgets) as $playlistId) {
                    // Start the index as the current loop in the largest list
                    $index = $i;
                    $countInList = count($widgets[$playlistId]);

                    $this->getLog()->debug('Assessing index ' . $i . ' for playlistId ' . $playlistId . ' which has ' . $countInList . ' widgets.' . (($first) ? ' first list' : ''));

                    // We might skip the second or later list if we're only taking from that list every N $takeEvery items.
                    if (!$first) {
                        // We are on the second or later list - should we take?
                        if ($index - $lastTakeIndex !== $takeEvery) {
                            $this->getLog()->debug('Skipping over ' . $index . ' because we last took index ' . $lastTakeIndex . ' and we should only take every ' . $takeEvery);
                            continue;
                        }

                        $this->getLog()->debug('Not on the first list, we have assessed that we should take an item');

                        $lastTakeIndex = $index;
                        $takeIndex++;

                        // Reset the item we will take according to the take index
                        $index = $takeIndex;
                    }

                    // Does this key actually have this many items?
                    if ($index >= $countInList) {
                        // it does not :o
                        $this->getLog()->debug('Index is higher than the count of widgets in the list');
                        // what we do depends on our remainder setting
                        // if we drop, we stop, otherwise we skip
                        if ($remainder === 'drop') {
                            // force the whole shebang to stop
                            $i = $largestListCount;
                            break;
                        } else if ($remainder === 'repeat') {
                            // start this list again from the beginning.
                            while ($index >= $countInList) {
                                $index = $index - $countInList;
                            }
                        } else {
                            // Just skip this key
                            continue;
                        }
                    }

                    $this->getLog()->debug('Selecting widget at position '. $index);

                    // Append the key at the position
                    $resolvedWidgets[] = $widgets[$playlistId][$index];

                    // Not the first list
                    $first = false;
                }
            }
        } else {
            // None
            // If the arrangement is none we just add all of the widgets together
            // Merge the arrays together for returning
            foreach ($widgets as $playlist => $items) {
                if ($remainder === 'drop') {
                    $this->getLog()->debug('Dropping list of ' . count($items) . ' widgets down to ' . $smallestListCount);

                    // We trim all arrays down to the smallest of them
                    $items = array_slice($items, 0, $smallestListCount);
                } else if ($remainder === 'repeat') {
                    $this->getLog()->debug('Expanding list of ' . count($items) . ' widgets to ' . $largestListCount);

                    while (count($items) < $largestListCount) {
                        $items = array_merge($items, $items);
                    }

                    // Finally trim (we might have added too many if they list sizes aren't exactly divisable
                    $items = array_slice($items, 0, $largestListCount);
                }

                $resolvedWidgets = array_merge($resolvedWidgets, $items);
            }
        }

        // We have interleaved our Widgets according to the rules presented, now expand out (or trim down) the
        // resulting list if we have been provided a spots size
        if ($spots > 0) {
            while (count($resolvedWidgets) < $spots) {
                if ($spotFill == 'repeat') {
                    $resolvedWidgets = array_merge($resolvedWidgets, $resolvedWidgets);
                } else if ($spotFill == 'fill') {
                    // Get Playlist 1 and use it to fill
                    $resolvedWidgets = array_merge($resolvedWidgets, $widgets[$firstListId]);
                }
            }

            // Trim down to the desired length because we might have overshot
            if (count($resolvedWidgets) > $spots) {
                $resolvedWidgets = array_slice($resolvedWidgets, 0, $spots);
            }
        }

        return $resolvedWidgets;
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