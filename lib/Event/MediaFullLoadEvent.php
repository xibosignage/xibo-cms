<?php


namespace Xibo\Event;


use Xibo\Entity\Media;

class MediaFullLoadEvent extends Event
{
    public static $NAME = 'library.media.full.load.event';

    /** @var Media */
    private $media;

    /**
     * MediaDeleteEvent constructor.
     * @param $media
     */
    public function __construct($media)
    {
        $this->media = $media;
    }

    /**
     * @return Media
     */
    public function getMedia() : Media
    {
        return $this->media;
    }
}