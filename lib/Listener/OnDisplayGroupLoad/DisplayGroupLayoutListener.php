<?php


namespace Xibo\Listener\OnDisplayGroupLoad;

use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Factory\LayoutFactory;

class DisplayGroupLayoutListener
{
    /**
     * @var LayoutFactory
     */
    private $layoutFactory;

    public function __construct(LayoutFactory $layoutFactory)
    {
        $this->layoutFactory = $layoutFactory;
    }

    public function __invoke(DisplayGroupLoadEvent $event)
    {
        $displayGroup = $event->getDisplayGroup();

        $displayGroup->layouts = $this->layoutFactory->getByDisplayGroupId($displayGroup->displayGroupId);
    }
}
