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
        return 1;
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
     * Adds a Sub-Playlist Widget
     * @SWG\Post(
     *  path="/playlist/widget/text/{playlistId}",
     *  operationId="WidgetSubPlaylistAdd",
     *  tags={"widget"},
     *  summary="Add a Sub-Playlist Widget",
     *  description="Add a new Sub-Playlist Widget to the specified playlist",
     *  @SWG\Parameter(
     *      name="playlistId",
     *      in="path",
     *      description="The playlist ID to add a Widget to",
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
     *      response=201,
     *      description="successful operation",
     *      @SWG\Schema(ref="#/definitions/Widget"),
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new widget",
     *          type="string"
     *      )
     *  )
     * )
     *
     * @throws InvalidArgumentException
     */
    public function add()
    {
        $this->setCommonOptions();

        // Save the widget
        $this->saveWidget();
    }

    /**
     * Edit Media
     * @throws InvalidArgumentException
     */
    public function edit()
    {
        $this->setCommonOptions();

        // Save the widget
        $this->saveWidget();
    }

    /**
     * Set common options
     * @throws InvalidArgumentException
     */
    private function setCommonOptions()
    {
        // Set some dud durations
        $this->setDuration(10);
        $this->setUseDuration(0);

        // Options
        $this->setOption('arrangement', $this->getSanitizer()->getString('arrangement'));
        $this->setOption('remainder', $this->getSanitizer()->getString('remainder'));

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
            throw new \InvalidArgumentException(__('This assignment creates a circular reference'));
        }
    }

    /** @inheritdoc */
    public function delete()
    {
        parent::delete();

        $subPlaylistId = $this->getOption('subPlaylistId', 0);

        // tidy up the closure table records.
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

    /**
     * @inheritdoc
     */
    public function preview($width, $height, $scaleOverride = 0)
    {
        $this->getLog()->debug('Previewing Sub-Playlist');

        //TODO: make this hook itself into the preview properly so that you can see the actual widgets rather than a list
        $output = '<h1>Sub Playlist</h1><ul>';

        foreach ($this->getAssignedPlaylistIds() as $playlistId) {
            $this->getLog()->debug('Sub-Playlist assigned list is ' . $playlistId);

            foreach ($this->playlistFactory->getById($playlistId)->setModuleFactory($this->moduleFactory)->expandWidgets() as $widget) {
                $output .= '<li>' . $widget->type . $widget->getOptionValue('name', '') . '</li>';
            }
        }

        $this->getLog()->debug('Finished Preview Sub-Playlist');

        return $output . '</ul>';
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

        $this->getLog()->debug('Resolve widgets for Sub-Playlist ' . $this->getWidgetId() . ' with arrangement ' . $arrangement . ' and remainder ' . $remainder);

        // As a first step, get all of our playlists widgets loaded into an array
        $resolvedWidgets = [];
        $widgets = [];
        $firstListCount = 0;
        $largestListCount = 0;
        $smallestListCount = 0;

        // Expand all widgets from sub-playlists
        foreach ($this->getAssignedPlaylistIds() as $playlistId) {
            $playlist = $this->playlistFactory->getById($playlistId)->setModuleFactory($this->moduleFactory);
            $expanded = $playlist->expandWidgets();
            $countExpanded = count($expanded);

            // first watermark
            if ($firstListCount === 0)
                $firstListCount = $countExpanded;

            // high watermark
            if ($countExpanded > $largestListCount)
                $largestListCount = $countExpanded;

            // low watermark
            if ($countExpanded < $smallestListCount || $smallestListCount === 0)
                $smallestListCount = $countExpanded;

            $widgets[$playlistId] = $expanded;
        }

        $this->getLog()->debug('Finished parsing all sub-playlists, smallest list is ' . $smallestListCount . ' widgets in size');

        // Arrangement first
        if ($arrangement === 'even') {
            // Evenly distributed by round robin
            $arrangement = 'roundrobin';

            // We need to decide how frequently we take from the respective lists.
            $takeEvery = ($firstListCount < $largestListCount) ? 1 : ($largestListCount / $smallestListCount);

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
            $keys = array_keys($widgets);
            for ($i = 0; $i < $largestListCount; $i++) {
                $first = true;
                foreach ($keys as $key) {
                    // Start the index as the current loop in the largest list
                    $index = $i;
                    $countInList = count($widgets[$key]);

                    $this->getLog()->debug('Assessing index ' . $i . ' for list ' . $key . ' which has ' . $countInList . ' widgets.' . (($first) ? ' first list' : ''));

                    // We always take from the first list
                    if (!$first) {
                        // We are on the second or later list - should we take?
                        if ($index - $lastTakeIndex !== $takeEvery)
                            continue;

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
                    $resolvedWidgets[] = $widgets[$key][$index];

                    // Not the first list
                    $first = false;
                }
            }
        } else {
            // None
            // If the arrangement is none we just add all of the widgets together
            // Merge the arrays together for returning
            foreach ($widgets as $key => $items) {
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