<?php


namespace Xibo\Listener\OnMediaDelete;


use Xibo\Event\MediaDeleteEvent;
use Xibo\Factory\WidgetFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

class WidgetListener
{
    use ListenerLoggerTrait;

    /**
     * @var WidgetFactory
     */
    private $widgetFactory;
    /**
     * @var StorageServiceInterface
     */
    private $storageService;

    public function __construct(StorageServiceInterface $storageService, WidgetFactory $widgetFactory)
    {
        $this->storageService = $storageService;
        $this->widgetFactory = $widgetFactory;
    }

    /**
     * @param MediaDeleteEvent $event
     * @throws InvalidArgumentException
     */
    public function __invoke(MediaDeleteEvent $event)
    {
        $media = $event->getMedia();
        $parentMedia = $event->getParentMedia();

        foreach ($this->widgetFactory->getByMediaId($media->mediaId) as $widget) {
            /* @var \Xibo\Entity\Widget $widget */
            $widget->unassignMedia($media->mediaId);

            if ($parentMedia != null) {
                // Assign the parent media to the widget instead
                $widget->assignMedia($parentMedia->mediaId);

                // Swap any audio nodes over to this new widget media assignment.
                $this->storageService->update('
                  UPDATE `lkwidgetaudio` SET mediaId = :mediaId WHERE widgetId = :widgetId AND mediaId = :oldMediaId
                ' , [
                    'mediaId' => $parentMedia->mediaId,
                    'widgetId' => $widget->widgetId,
                    'oldMediaId' => $media->mediaId
                ]);
            } else {
                // Also delete the `lkwidgetaudio`
                $widget->unassignAudioById($media->mediaId);
            }

            // This action might result in us deleting a widget (unless we are a temporary file with an expiry date)
            if ($media->mediaType != 'module' && count($widget->mediaIds) <= 0) {
                $widget->delete();
            } else {
                $widget->save(['saveWidgetOptions' => false]);
            }
        }
    }
}
