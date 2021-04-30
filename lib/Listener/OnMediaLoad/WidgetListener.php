<?php


namespace Xibo\Listener\OnMediaLoad;

use Xibo\Event\MediaFullLoadEvent;
use Xibo\Factory\WidgetFactory;

class WidgetListener
{
    /**
     * @var WidgetFactory
     */
    private $widgetFactory;

    public function __construct(WidgetFactory $widgetFactory)
    {
        $this->widgetFactory = $widgetFactory;
    }

    public function __invoke(MediaFullLoadEvent $event)
    {
        $media = $event->getMedia();

        $media->widgets = $this->widgetFactory->getByMediaId($media->mediaId);
    }
}
