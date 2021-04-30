<?php


namespace Xibo\Listener\OnParsePermissions;

use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Factory\DisplayGroupFactory;

class PermissionsDisplayGroupListener
{
    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    public function __construct(DisplayGroupFactory $displayGroupFactory)
    {
        $this->displayGroupFactory = $displayGroupFactory;
    }

    /**
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function __invoke(ParsePermissionEntityEvent $event)
    {
        $event->setObject($this->displayGroupFactory->getById($event->getObjectId()));
    }
}
