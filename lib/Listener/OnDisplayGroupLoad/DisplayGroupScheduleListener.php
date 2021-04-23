<?php


namespace Xibo\Listener\OnDisplayGroupLoad;


use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Factory\ScheduleFactory;

class DisplayGroupScheduleListener
{
    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    public function __construct(ScheduleFactory $scheduleFactory)
    {
        $this->scheduleFactory = $scheduleFactory;
    }

    public function __invoke(DisplayGroupLoadEvent $event)
    {
        $displayGroup = $event->getDisplayGroup();

        $displayGroup->events = $this->scheduleFactory->getByDisplayGroupId($displayGroup->displayGroupId);
    }
}
