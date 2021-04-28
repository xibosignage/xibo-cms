<?php


namespace Xibo\Listener\OnParsePermissions;

use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Factory\WidgetFactory;

class PermissionsWidgetListener
{
    /**
     * @var WidgetFactory
     */
    private $widgetFactory;

    public function __construct(WidgetFactory $widgetFactory)
    {
        $this->widgetFactory = $widgetFactory;
    }

    public function __invoke(ParsePermissionEntityEvent $event)
    {
        $event->setObject($this->widgetFactory->getById($event->getObjectId()));
    }
}
