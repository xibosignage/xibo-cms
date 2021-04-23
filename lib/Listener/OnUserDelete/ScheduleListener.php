<?php


namespace Xibo\Listener\OnUserDelete;


use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\Schedule;
use Xibo\Entity\User;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\ScheduleFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Storage\StorageServiceInterface;

class ScheduleListener implements OnUserDeleteInterface
{
    use ListenerLoggerTrait;

    /** @var StorageServiceInterface */
    private $storageService;

    /** @var ScheduleFactory */
    private $scheduleFactory;

    public function __construct(StorageServiceInterface $storageService, ScheduleFactory $scheduleFactory)
    {
        $this->storageService = $storageService;
        $this->scheduleFactory = $scheduleFactory;
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
    public function deleteChildren($user, EventDispatcherInterface $dispatcher)
    {
        // Delete any scheduled events
        foreach ($this->scheduleFactory->getByOwnerId($user->userId) as $event) {
            /* @var Schedule $event */
            $event->delete();
        }
    }

    /**
     * @inheritDoc
     */
    public function reassignAllTo(User $user, User $newUser)
    {
        // Reassign events
        $this->storageService->update('UPDATE `schedule` SET userId = :userId WHERE userId = :oldUserId', [
            'userId' => $newUser->userId,
            'oldUserId' => $user->userId
        ]);
    }

    /**
     * @inheritDoc
     */
    public function countChildren(User $user)
    {
        $events = $this->scheduleFactory->getByOwnerId($user->userId);
        $count = count($events);
        $this->getLogger()->debug(sprintf('Counted Event Children on %d, there are %d', $user->userId, $count));

        return $count;
    }
}
