<?php


namespace Xibo\Listener\OnParsePermissions;


use Xibo\Event\ParsePermissionEntityEvent;
use Xibo\Factory\MenuBoardFactory;

class PermissionsMenuBoardListener
{
    /**
     * @var MenuBoardFactory
     */
    private $menuBoardFactory;

    public function __construct(MenuBoardFactory $menuBoardFactory)
    {
        $this->menuBoardFactory = $menuBoardFactory;
    }

    public function __invoke(ParsePermissionEntityEvent $event)
    {
        $event->setObject($this->menuBoardFactory->getById($event->getObjectId()));
    }
}
