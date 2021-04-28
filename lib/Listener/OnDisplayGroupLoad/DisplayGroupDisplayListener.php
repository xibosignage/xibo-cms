<?php


namespace Xibo\Listener\OnDisplayGroupLoad;

use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Factory\DisplayFactory;

class DisplayGroupDisplayListener
{
    /**
     * @var DisplayFactory
     */
    private $displayFactory;

    public function __construct(DisplayFactory $displayFactory)
    {
        $this->displayFactory = $displayFactory;
    }

    public function __invoke(DisplayGroupLoadEvent $event)
    {
        $displayGroup = $event->getDisplayGroup();
        $displayGroup->setDisplayFactory($this->displayFactory);

        $displayGroup->displays = $this->displayFactory->getByDisplayGroupId($displayGroup->displayGroupId);
    }
}
