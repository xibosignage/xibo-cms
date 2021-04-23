<?php


namespace Xibo\Listener\OnUserDelete;


use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\User;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\InvalidArgumentException;

class DayPartListener implements OnUserDeleteInterface
{
    use ListenerLoggerTrait;

    /**
     * @var StorageServiceInterface
     */
    private $storageService;
    /**
     * @var DayPartFactory
     */
    private $dayPartFactory;
    /**
     * @var ScheduleFactory
     */
    private $scheduleFactory;

    public function __construct(StorageServiceInterface $storageService, DayPartFactory $dayPartFactory, ScheduleFactory $scheduleFactory)
    {
        $this->storageService = $storageService;
        $this->dayPartFactory = $dayPartFactory;
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
    public function deleteChildren(User $user, EventDispatcherInterface $dispatcher)
    {
        foreach ($this->dayPartFactory->getByOwnerId($user->userId) as $dayPart) {
            if ($dayPart->isAlways === 1 || $dayPart->isCustom === 1) {
                throw new InvalidArgumentException(__('Cannot Delete User, as it is an owner of system specific DayParts, please reassign'));
            }

            $dayPart->setScheduleFactory($this->scheduleFactory)->delete();
        }
    }

    /**
     * @inheritDoc
     */
    public function reassignAllTo(User $user, User $newUser)
    {
        // Reassign Dayparts
        $this->storageService->update('UPDATE `daypart` SET userId = :userId WHERE userId = :oldUserId', [
            'userId' => $newUser->userId,
            'oldUserId' => $user->userId
        ]);
    }

    /**
     * @inheritDoc
     */
    public function countChildren(User $user)
    {
        $dayParts = $this->dayPartFactory->getByOwnerId($user->userId);
        $count = count($dayParts);
        $this->getLogger()->debug(sprintf('Counted DayParts Children on %d, there are %d', $user->userId, $count));

        return $count;
    }
}
