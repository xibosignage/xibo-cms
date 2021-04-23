<?php


namespace Xibo\Listener\OnMediaLoad;


use Xibo\Event\MediaFullLoadEvent;
use Xibo\Factory\DisplayGroupFactory;

class DisplayGroupListener
{
    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    public function __construct(DisplayGroupFactory $displayGroupFactory)
    {
        $this->displayGroupFactory = $displayGroupFactory;
    }

    public function __invoke(MediaFullLoadEvent $event)
    {
        $media = $event->getMedia();

        $media->displayGroups = $this->displayGroupFactory->getByMediaId($media->mediaId);
    }
}