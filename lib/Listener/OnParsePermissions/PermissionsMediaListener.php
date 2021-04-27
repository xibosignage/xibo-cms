<?php


namespace Xibo\Listener\OnParsePermissions;


use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Factory\MediaFactory;

class PermissionsMediaListener
{
    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    public function __construct(MediaFactory $mediaFactory)
    {
        $this->mediaFactory = $mediaFactory;
    }
    
    public function __invoke(ParsePermissionEntityEvent $event)
    {
        $event->setObject($this->mediaFactory->getById($event->getObjectId()));
    }
}
