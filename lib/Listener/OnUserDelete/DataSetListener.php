<?php


namespace Xibo\Listener\OnUserDelete;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\User;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\DataSetFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Storage\StorageServiceInterface;

class DataSetListener implements OnUserDeleteInterface
{
    use ListenerLoggerTrait;

    /**
     * @var StorageServiceInterface
     */
    private $storageService;
    /**
     * @var DataSetFactory
     */
    private $dataSetFactory;

    public function __construct(StorageServiceInterface $storageService, DataSetFactory $dataSetFactory)
    {
        $this->storageService = $storageService;
        $this->dataSetFactory = $dataSetFactory;
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
        foreach ($this->dataSetFactory->getByOwnerId($user->userId) as $dataSet) {
            $dataSet->delete();
        }
    }

    /**
     * @inheritDoc
     */
    public function reassignAllTo(User $user, User $newUser)
    {
        // Reassign datasets
        $this->storageService->update('UPDATE `dataset` SET userId = :userId WHERE userId = :oldUserId', [
            'userId' => $newUser->userId,
            'oldUserId' => $user->userId
        ]);
    }

    /**
     * @inheritDoc
     */
    public function countChildren(User $user)
    {
        $dataSets = $this->dataSetFactory->getByOwnerId($user->userId);
        $count = count($dataSets);
        $this->getLogger()->debug(sprintf('Counted DataSet Children on %d, there are %d', $user->userId, $count));

        return $count;
    }
}
