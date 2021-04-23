<?php


namespace Xibo\Listener\OnUserDelete;


use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\User;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\MenuBoardFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Storage\StorageServiceInterface;

class MenuBoardListener implements OnUserDeleteInterface
{
    use ListenerLoggerTrait;

    /**
     * @var StorageServiceInterface
     */
    private $storageService;
    /**
     * @var MenuBoardFactory
     */
    private $menuBoardFactory;

    public function __construct(StorageServiceInterface $storageService, MenuBoardFactory $menuBoardFactory)
    {
        $this->storageService = $storageService;
        $this->menuBoardFactory = $menuBoardFactory;
    }

    /**
     * @inheritDoc
     */
    public function __invoke(UserDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $user = $event->getUser();
        $function = $event->getFunction();
        $newUser = $event->getNewUser();

        if ($function === 'delete') {
            $this->deleteChildren($user, $dispatcher);
        } else if ($function === 'reassignAll') {
            $this->reassignAllTo($user, $newUser);
        } else if ($function === 'countChildren') {
            $event->setReturnValue($event->getReturnValue() + $this->countChildren($user));
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteChildren(User $user, EventDispatcherInterface $dispatcher)
    {
        foreach ($this->menuBoardFactory->getByOwnerId($user->userId) as $menuBoard) {
            $menuBoard->delete();
        }
    }

    /**
     * @inheritDoc
     */
    public function reassignAllTo(User $user, User $newUser)
    {
        // Reassign Menu Boards
        $this->storageService->update('UPDATE `menu_board` SET userId = :userId WHERE userId = :oldUserId', [
            'userId' => $newUser->userId,
            'oldUserId' => $user->userId
        ]);
    }

    /**
     * @inheritDoc
     */
    public function countChildren(User $user)
    {
        $menuBoards = $this->menuBoardFactory->getByOwnerId($user->userId);
        $count = count($menuBoards);
        $this->getLogger()->debug(sprintf('Counted Menu Board Children on %d, there are %d', $user->userId, $count));

        return $count;
    }
}
