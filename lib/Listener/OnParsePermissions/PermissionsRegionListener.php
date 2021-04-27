<?php


namespace Xibo\Listener\OnParsePermissions;


use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Factory\RegionFactory;

class PermissionsRegionListener
{
    /**
     * @var RegionFactory
     */
    private $regionFactory;

    public function __construct(RegionFactory $regionFactory)
    {
        $this->regionFactory = $regionFactory;
    }

    public function __invoke(ParsePermissionEntityEvent $event)
    {
        $event->setObject($this->regionFactory->getById($event->getObjectId()));
    }
}
