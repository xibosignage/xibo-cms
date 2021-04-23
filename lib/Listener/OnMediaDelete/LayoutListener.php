<?php


namespace Xibo\Listener\OnMediaDelete;


use Xibo\Entity\Layout;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Factory\LayoutFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Support\Exception\InvalidArgumentException;

class LayoutListener
{
    use ListenerLoggerTrait;

    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    public function __construct(LayoutFactory $layoutFactory)
    {
        $this->layoutFactory = $layoutFactory;
    }

    /**
     * @param MediaDeleteEvent $event
     * @throws InvalidArgumentException
     */
    public function __invoke(MediaDeleteEvent $event)
    {
        $media = $event->getMedia();
        $parentMedia = $event->getParentMedia();

        foreach ($this->layoutFactory->getByBackgroundImageId($media->mediaId) as $layout) {
            if ($media->mediaType == 'image' && $parentMedia != null) {
                $this->getLogger()->debug(sprintf('Updating layouts with the old media %d as the background image.', $media->mediaId));
                $this->getLogger()->debug(sprintf('Found layout that needs updating. ID = %d. Setting background image id to %d', $layout->layoutId, $parentMedia->mediaId));
                $layout->backgroundImageId = $parentMedia->mediaId;
            } else {
                $layout->backgroundImageId = null;
            }

            $layout->save(Layout::$saveOptionsMinimum);
        }
    }
}
