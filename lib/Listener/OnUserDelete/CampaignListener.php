<?php


namespace Xibo\Listener\OnUserDelete;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\Campaign;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Storage\StorageServiceInterface;

class CampaignListener implements OnUserDeleteInterface
{
    use ListenerLoggerTrait;

    /** @var CampaignFactory */
    private $campaignFactory;

    /** @var LayoutFactory */
    private $layoutFactory;
    /**
     * @var StorageServiceInterface
     */
    private $storageService;

    /**
     * CampaignListener constructor.
     * @param CampaignFactory $campaignFactory
     */
    public function __construct(
        StorageServiceInterface $storageService,
        CampaignFactory $campaignFactory,
        LayoutFactory $layoutFactory
    ) {
        $this->storageService = $storageService;
        $this->campaignFactory = $campaignFactory;
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
        // Delete any Campaigns
        foreach ($this->campaignFactory->getByOwnerId($user->userId) as $campaign) {
            /* @var Campaign $campaign */
            $campaign->delete();
        }
    }

    /**
     * @inheritDoc
     */
    public function reassignAllTo($user, $newUser)
    {
        // Reassign campaigns
        $this->storageService->update('UPDATE `campaign` SET userId = :userId WHERE userId = :oldUserId', [
            'userId' => $newUser->userId,
            'oldUserId' => $user->userId
        ]);
    }

    /**
     * @inheritDoc
     */
    public function countChildren($user)
    {
        $campaigns = $this->campaignFactory->getByOwnerId($user->userId);

        $count = count($campaigns);
        $this->getLogger()->debug(sprintf('Counted Campaign Children on %d, there are %d', $user->userId, $count));

        return $count;
    }
}
