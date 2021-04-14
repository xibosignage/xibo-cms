<?php


namespace Xibo\Listener;

use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\MenuBoardFactory;

class OnUserDelete
{
    use ListenerLoggerTrait;

    /** @var MenuBoardFactory */
    private $menuBoardFactory;

    public function __construct($menuBoardFactory)
    {
        $this->menuBoardFactory = $menuBoardFactory;
    }

    /**
     * @param object $event
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function __invoke(object $event)
    {
        /** @var  UserDeleteEvent $event */
        $user = $event->getUser();

        foreach ($this->menuBoardFactory->query(null, ['userId' => $user->userId]) as $menuBoard) {
            $menuBoard->delete();
        }
    }
}