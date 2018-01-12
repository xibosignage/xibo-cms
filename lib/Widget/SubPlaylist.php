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
        $widgets = [];
        // Add all of the sub-playlists widgets too
        // TODO: this will depend very much on the way we select widgets from the playlists in question.
        foreach ($this->getAssignedPlaylistIds() as $playlistId) {
            $playlist = $this->playlistFactory->getById($playlistId)->setModuleFactory($this->moduleFactory);
            $widgets = array_merge($widgets, $playlist->expandWidgets());
        }

        return $widgets;
    }

    /**
     * @return int
     * @throws \Xibo\Exception\NotFoundException
     */
    public function getSubPlaylistResolvedDuration()
    {
        $duration = 0;
        // Add all of the sub-playlists widgets too
        // TODO: this will depend very much on the way we select widgets from the playlists in question.
        foreach ($this->getAssignedPlaylistIds() as $playlistId) {
            $playlist = $this->playlistFactory->getById($playlistId);
            $duration += $playlist->duration;
        }

        return $duration;
    }
}