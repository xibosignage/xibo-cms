<?php
/**
 * Copyright (C) 2021 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\Listener\OnUserDelete;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\Campaign;
use Xibo\Entity\User;
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
        CampaignFactory $campaignFactory
    ) {
        $this->storageService = $storageService;
        $this->campaignFactory = $campaignFactory;
    }

    /**
     * @inheritDoc
     */
    public function __invoke(UserDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $user = $event->getUser();
        $function = $event->getFunction();
        $newUser = $event->getNewUser();
        $systemUser = $event->getSystemUser();

        if ($function === 'delete') {
            $this->deleteChildren($user, $dispatcher, $systemUser);
        } elseif ($function === 'reassignAll') {
            $this->reassignAllTo($user, $newUser, $systemUser);
        } elseif ($function === 'countChildren') {
            $event->setReturnValue($event->getReturnValue() + $this->countChildren($user));
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteChildren(User $user, EventDispatcherInterface $dispatcher, User $systemUser)
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
    public function reassignAllTo(User $user, User $newUser, User $systemUser)
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
        $this->getLogger()->debug(sprintf('Counted Children Campaign on User ID %d, there are %d', $user->userId, $count));

        return $count;
    }
}
