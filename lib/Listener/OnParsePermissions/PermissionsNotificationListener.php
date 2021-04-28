<?php


namespace Xibo\Listener\OnParsePermissions;

use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Factory\NotificationFactory;

class PermissionsNotificationListener
{
    /**
     * @var NotificationFactory
     */
    private $notificationFactory;

    public function __construct(NotificationFactory $notificationFactory)
    {
        $this->notificationFactory = $notificationFactory;
    }
    
    public function __invoke(ParsePermissionEntityEvent $event)
    {
        $event->setObject($this->notificationFactory->getById($event->getObjectId()));
    }
}
