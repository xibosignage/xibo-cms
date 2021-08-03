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
use Xibo\Entity\User;
use Xibo\Event\UserDeleteEvent;
use Xibo\Factory\ActionFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Storage\StorageServiceInterface;

class ActionListener implements OnUserDeleteInterface
{
    /**
     * @var ActionFactory
     */
    private $actionFactory;
    /**
     * @var StorageServiceInterface
     */
    private $store;

    use ListenerLoggerTrait;

    public function __construct(StorageServiceInterface $store, ActionFactory $actionFactory)
    {
        $this->store = $store;
        $this->actionFactory = $actionFactory;
    }

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

    /* @inheritDoc */
    public function deleteChildren(User $user, EventDispatcherInterface $dispatcher, User $systemUser)
    {
        // Delete any Actions
        foreach ($this->actionFactory->getByOwnerId($user->userId) as $action) {
            $action->delete();
        }
    }

    /* @inheritDoc */
    public function reassignAllTo(User $user, User $newUser, User $systemUser)
    {
        // Reassign Actions
        $this->store->update('UPDATE `action` SET ownerId = :userId WHERE ownerId = :oldUserId', [
            'userId' => $newUser->userId,
            'oldUserId' => $user->userId
        ]);
    }

    /* @inheritDoc */
    public function countChildren(User $user)
    {
        $actions = $this->actionFactory->getByOwnerId($user->userId);
        $this->getLogger()->debug(sprintf('Counted Children Actions on User ID %d, there are %d', $user->userId, count($actions)));

        return count($actions);
    }
}
