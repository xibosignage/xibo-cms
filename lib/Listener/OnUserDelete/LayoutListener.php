<?php


namespace Xibo\Listener\OnUserDelete;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\LayoutFactory;
use Xibo\Listener\ListenerLoggerTrait;

class LayoutListener implements OnUserDeleteInterface
{
    use ListenerLoggerTrait;

    /** @var LayoutFactory */
    private $layoutFactory;

    public function __construct(LayoutFactory $layoutFactory)
    {
        $this->layoutFactory = $layoutFactory;
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
    public function deleteChildren($user, EventDispatcherInterface $dispatcher)
    {
        // Delete any layouts
        foreach ($this->layoutFactory->getByOwnerId($user->userId) as $layout) {
            $layout->delete();
        }
    }

    /**
     * @inheritDoc
     */
    public function reassignAllTo($user, $newUser)
    {
        $this->getLogger()->debug(sprintf('Reassign all to %s', $newUser->userName));

        $this->getLogger()->debug(sprintf('There are %d children', $this->countChildren($user)));

        // Reassign layouts, regions, region Playlists and Widgets.
        foreach ($this->layoutFactory->getByOwnerId($user->userId) as $layout) {
            $layout->setOwner($newUser->userId, true);
            $layout->save(['notify' => false]);
        }
    }

    /**
     * @inheritDoc
     */
    public function countChildren($user)
    {
        $layouts = $this->layoutFactory->getByOwnerId($user->userId);

        $count = count($layouts);
        $this->getLogger()->debug(sprintf('Counted Children Layouts on %d, there are %d', $user->userId, $count));

        return $count;
    }
}
