<?php


namespace Xibo\Listener\OnParsePermissions;


use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Factory\DayPartFactory;

class PermissionsDayPartListener
{
    /**
     * @var DayPartFactory
     */
    private $dayPartFactory;

    public function __construct(DayPartFactory $dayPartFactory)
    {
        $this->dayPartFactory = $dayPartFactory;
    }

    public function __invoke(ParsePermissionEntityEvent $event)
    {
        $event->setObject($this->dayPartFactory->getById($event->getObjectId()));
    }
}
