<?php


namespace Xibo\Listener\OnDisplayGroupLoad;

use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Factory\MediaFactory;

class DisplayGroupMediaListener
{
    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    public function __construct(MediaFactory $mediaFactory)
    {
        $this->mediaFactory = $mediaFactory;
    }

    public function __invoke(DisplayGroupLoadEvent $event)
    {
        $displayGroup = $event->getDisplayGroup();

        $displayGroup->media = ($displayGroup->displayGroupId != null)
            ? $this->mediaFactory->getByDisplayGroupId($displayGroup->displayGroupId)
            : [];
    }
}
