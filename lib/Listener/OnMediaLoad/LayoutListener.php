<?php


namespace Xibo\Listener\OnMediaLoad;

use Xibo\Event\MediaFullLoadEvent;
use Xibo\Factory\LayoutFactory;

class LayoutListener
{
    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    public function __construct(LayoutFactory $layoutFactory)
    {
        $this->layoutFactory = $layoutFactory;
    }

    public function __invoke(MediaFullLoadEvent $event)
    {
        $media = $event->getMedia();

        $media->layoutBackgroundImages = $this->layoutFactory->getByBackgroundImageId($media->mediaId);
    }
}
