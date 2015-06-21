<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (Playlist.php)
 */


namespace Xibo\Controller;


use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\PlaylistFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Helper\Help;
use Xibo\Helper\Sanitize;

class Playlist extends Base
{
    public function libraryAssignForm($playlistId)
    {
        $playlist = PlaylistFactory::getById($playlistId);

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        $this->getState()->template = 'playlist-form-library-assign';
        $this->getState()->setData([
            'playlist' => $playlist,
            'help' => Help::Link('Library', 'Assign')
        ]);
    }

    /**
     * Add Library items to a Playlist
     * @param int $playlistId
     */
    public function libraryAssign($playlistId)
    {
        $playlist = PlaylistFactory::getById($playlistId);

        if (!$this->getUser()->checkEditable($playlist))
            throw new AccessDeniedException();

        // Expect a list of mediaIds
        $media = Sanitize::getIntArray('media');

        if (count($media) <= 0)
            throw new \InvalidArgumentException(__('Please provide Media to Assign'));

        // Loop through all the media
        foreach ($media as $mediaId) {
            /* @var int $mediaId */
            $item = MediaFactory::getById($mediaId);

            if (!$this->getUser()->checkViewable($item))
                throw new AccessDeniedException(__('You do not have permissions to use this media'));

            $widget = WidgetFactory::create($this->getUser()->userId, $playlistId, $item->mediaType, $item->duration);
            $widget->assignMedia($item->mediaId);

            // Assign the widget to the playlist
            $playlist->assignWidget($widget);
        }

        // Save the playlist
        $playlist->save();

        // Success
        $this->getState()->hydrate([
            'message' => __('Media Assigned'),
            'playlist' => $playlist
        ]);
    }
}