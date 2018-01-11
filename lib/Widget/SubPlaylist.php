<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2018 Spring Signage Ltd
 * (Playlist.php)
 */


namespace Xibo\Widget;
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

    /**
     * @throws InvalidArgumentException
     */
    public function validate()
    {
        // Validation
        if ($this->getOption('subPlaylistId') == 0)
            throw new InvalidArgumentException(__('Please select a Playlist to embed'), 'subPlaylistId');
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
        $this->validate();
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
        $this->validate();
        $this->saveWidget();
    }

    /**
     * Set common options
     */
    private function setCommonOptions()
    {
        $existingSubPlaylistId = $this->getOption('subPlaylistId', 0);
        $subPlaylistId = $this->getSanitizer()->getInt('subPlaylistId');
        $this->setDuration(10);
        $this->setUseDuration(0);
        $this->setOption('subPlaylistId', $subPlaylistId);

        // Manage the closure table that holds these relationships
        if ($existingSubPlaylistId != $subPlaylistId) {
            // Manage closure
            $this->getLog()->debug('Manage closure table for parent ' . $this->widget->playlistId . ' and child ' . $subPlaylistId);

            if ($existingSubPlaylistId != 0) {
                $this->getLog()->debug('Removing old links - existing link child is ' . $existingSubPlaylistId);

                $this->getStore()->update('
                    DELETE link
                      FROM `lkplaylistplaylist` p, `lkplaylistplaylist` link, `lkplaylistplaylist` c
                     WHERE p.parentId = link.parentId AND c.childId = link.childId
                       AND p.childId = :parentId AND c.parentId = :childId
                ', [
                    'parentId' => $this->widget->playlistId,
                    'childId' => $existingSubPlaylistId
                ]);
            }

            $this->getStore()->insert('
                INSERT INTO `lkplaylistplaylist` (parentId, childId, depth)
                SELECT p.parentId, c.childId, p.depth + c.depth + 1
                  FROM lkplaylistplaylist p, lkplaylistplaylist c
                 WHERE p.childId = :parentId AND c.parentId = :childId
            ', [
                'parentId' => $this->widget->playlistId,
                'childId' => $subPlaylistId
            ]);
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
            'parentId' => $this->widget->playlistId,
            'childId' => $subPlaylistId
        ]);
    }

    /**
     * @inheritdoc
     */
    public function preview($width, $height, $scaleOverride = 0)
    {
        //TODO: make a nice little sub-playlist viewer, perhaps showing a list of whats inside?
        return '<h1>Sub Playlist</h1>';
    }
}