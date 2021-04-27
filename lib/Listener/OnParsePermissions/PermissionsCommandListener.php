<?php


namespace Xibo\Listener\OnParsePermissions;


use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Factory\CommandFactory;

class PermissionsCommandListener
{
    /**
     * @var CommandFactory
     */
    private $commandFactory;

    public function __construct(CommandFactory $commandFactory)
    {
        $this->commandFactory = $commandFactory;
    }

    public function __invoke(ParsePermissionEntityEvent $event)
    {
        $event->setObject($this->commandFactory->getById($event->getObjectId()));
    }
}
