<?php


namespace Xibo\Listener\OnMediaDelete;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Listener\ListenerLoggerTrait;

class DisplayGroupListener
{
    use ListenerLoggerTrait;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    public function __construct(DisplayGroupFactory $displayGroupFactory)
    {
        $this->displayGroupFactory = $displayGroupFactory;
    }

    public function __invoke(MediaDeleteEvent $event, string $eventName, EventDispatcherInterface $dispatcher)
    {
        $media = $event->getMedia();
        $parentMedia = $event->getParentMedia();

        foreach ($this->displayGroupFactory->getByMediaId($media->mediaId) as $displayGroup) {
            $dispatcher->dispatch(DisplayGroupLoadEvent::$NAME, new DisplayGroupLoadEvent($displayGroup));
            $displayGroup->load();
            $displayGroup->unassignMedia($media);
            if ($parentMedia != null) {
                $displayGroup->assignMedia($parentMedia);
            }

            $displayGroup->save(['validate' => false]);
        }
    }
}
