<?php


namespace Xibo\Event;


use Xibo\Entity\Media;

class MediaDeleteEvent extends Event
{
    public static $NAME = 'library.media.delete.event';

    /** @var Media */
    private $media;
    /**
     * @var Media|null
     */
    private $parentMedia;

    /**
     * MediaDeleteEvent constructor.
     * @param $media
     */
    public function __construct($media, $parentMedia = null)
    {
        $this->media = $media;
        $this->parentMedia = $parentMedia;
    }

    /**
     * @return Media
     */
    public function getMedia() : Media
    {
        return $this->media;
    }

    public function getParentMedia()
    {
        return $this->parentMedia;
    }
}