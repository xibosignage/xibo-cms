<?php


namespace Xibo\Listener\OnUserDelete;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\User;
use Xibo\Event\DisplayGroupLoadEvent;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

class DisplayGroupListener implements OnUserDeleteInterface
{
    use ListenerLoggerTrait;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;
    /**
     * @var StorageServiceInterface
     */
    private $storageService;

    public function __construct(StorageServiceInterface $storageService, DisplayGroupFactory $displayGroupFactory)
    {
        $this->storageService = $storageService;
        $this->displayGroupFactory = $displayGroupFactory;
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
        } elseif ($function === 'reassignAll') {
            $this->reassignAllTo($user, $newUser);
        } elseif ($function === 'countChildren') {
            $event->setReturnValue($event->getReturnValue() + $this->countChildren($user));
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteChildren(User $user, EventDispatcherInterface $dispatcher)
    {
        foreach ($this->displayGroupFactory->getByOwnerId($user->userId, -1) as $displayGroup) {
            if ($displayGroup->isDisplaySpecific === 1) {
                throw new InvalidArgumentException(__(
                    'Cannot Delete User, as it is an owner of one or more Displays, please reassign'
                ));
            }

            $displayGroup->load();
            $dispatcher->dispatch(DisplayGroupLoadEvent::$NAME, new DisplayGroupLoadEvent($displayGroup));
            $displayGroup->delete();
        }
    }

    /**
     * @inheritDoc
     */
    public function reassignAllTo(User $user, User $newUser)
    {
        // Reassign display groups
        $this->storageService->update('UPDATE `displaygroup` SET userId = :userId WHERE userId = :oldUserId', [
            'userId' => $newUser->userId,
            'oldUserId' => $user->userId
        ]);
    }

    /**
     * @inheritDoc
     */
    public function countChildren(User $user)
    {
        $displayGroups = $this->displayGroupFactory->getByOwnerId($user->userId, -1);

        $count = count($displayGroups);
        $this->getLogger()->debug(sprintf('Counted Display Group Children on %d, there are %d', $user->userId, $count));

        return $count;
    }
}
