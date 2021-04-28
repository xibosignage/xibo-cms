<?php


namespace Xibo\Listener\OnUserDelete;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\User;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\MediaFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Support\Exception\NotFoundException;

class MediaListener implements OnUserDeleteInterface
{
    use ListenerLoggerTrait;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;
    /**
     * @var EventDispatcher
     */
    private $dispatcher;
    /**
     * @var StorageServiceInterface
     */
    private $storageService;

    public function __construct(StorageServiceInterface $storageService, MediaFactory $mediaFactory)
    {
        $this->storageService = $storageService;
        $this->mediaFactory = $mediaFactory;
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
        // Delete any media
        foreach ($this->mediaFactory->getByOwnerId($user->userId) as $media) {
            // If there is a parent, bring it back
            try {
                $parentMedia = $this->mediaFactory->getParentById($media->mediaId);
                $parentMedia->isEdited = 0;
                $parentMedia->parentId = null;
                $parentMedia->save(['validate' => false]);
            } catch (NotFoundException $e) {
                // This is fine, no parent
                $parentMedia = null;
            }
            $dispatcher->dispatch(MediaDeleteEvent::$NAME, new MediaDeleteEvent($media, $parentMedia));
            $media->delete();
        }
    }

    /**
     * @inheritDoc
     */
    public function reassignAllTo(User $user, User $newUser)
    {
        // Reassign media
        $this->storageService->update('UPDATE `media` SET userId = :userId WHERE userId = :oldUserId', [
            'userId' => $newUser->userId,
            'oldUserId' => $user->userId
        ]);
    }

    /**
     * @inheritDoc
     */
    public function countChildren(User $user)
    {
        $media = $this->mediaFactory->getByOwnerId($user->userId);
        $count = count($media);
        $this->getLogger()->debug(sprintf('Counted Children Media on %d, there are %d', $user->userId, $count));

        return $count;
    }
}
