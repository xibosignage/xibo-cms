<?php


namespace Xibo\Listener\OnUserDelete;


use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\PlaylistFactory;
use Xibo\Listener\ListenerLoggerTrait;

class PlaylistListener implements OnUserDeleteInterface
{
    use ListenerLoggerTrait;

    /** @var PlaylistFactory */
    private $playlistFactory;

    public function __construct(PlaylistFactory $playlistFactory)
    {
        $this->playlistFactory = $playlistFactory;
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
        // Delete Playlists owned by this user
        foreach ($this->playlistFactory->getByOwnerId($user->userId) as $playlist) {
            $playlist->delete();
        }
    }

    /**
     * @inheritDoc
     */
    public function reassignAllTo($user, $newUser)
    {
        // Reassign playlists and widgets
        foreach ($this->playlistFactory->getByOwnerId($user->userId) as $playlist) {
            $playlist->setOwner($newUser->userId);
        }
    }

    /**
     * @inheritDoc
     */
    public function countChildren($user)
    {
        $playlists = $this->playlistFactory->getByOwnerId($user->userId);

        $count = count($playlists);
        $this->getLogger()->debug(sprintf('Counted Children on %d, there are %d', $user->userId, $count));

        return $count;
    }
}
