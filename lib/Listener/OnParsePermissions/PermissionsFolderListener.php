<?php


namespace Xibo\Listener\OnParsePermissions;

use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Factory\FolderFactory;

class PermissionsFolderListener
{
    /**
     * @var FolderFactory
     */
    private $folderFactory;

    public function __construct(FolderFactory $folderFactory)
    {
        $this->folderFactory = $folderFactory;
    }

    public function __invoke(ParsePermissionEntityEvent $event)
    {
        $event->setObject($this->folderFactory->getById($event->getObjectId()));
    }
}
