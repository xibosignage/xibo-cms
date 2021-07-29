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
        // we do not want to delete Display specific Display Groups, reassign to systemUser instead.
        foreach ($this->displayGroupFactory->getByOwnerId($user->userId, -1) as $displayGroup) {
            if ($displayGroup->isDisplaySpecific === 1) {
                $displayGroup->setOwner($systemUser->userId);
                $displayGroup->save(['saveTags' => false, 'manageDynamicDisplayLinks' => false]);
            } else {
                $displayGroup->load();
                $dispatcher->dispatch(DisplayGroupLoadEvent::$NAME, new DisplayGroupLoadEvent($displayGroup));
                $displayGroup->delete();
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function reassignAllTo(User $user, User $newUser, User $systemUser)
    {
        foreach ($this->displayGroupFactory->getByOwnerId($user->userId, -1) as $displayGroup) {
            ($displayGroup->isDisplaySpecific === 1) ? $displayGroup->setOwner($systemUser->userId) : $displayGroup->setOwner($newUser->getOwnerId());
            $displayGroup->save(['saveTags' => false, 'manageDynamicDisplayLinks' => false]);
        }
    }

    /**
     * @inheritDoc
     */
    public function countChildren(User $user)
    {
        $displayGroups = $this->displayGroupFactory->getByOwnerId($user->userId, -1);

        $count = count($displayGroups);
        $this->getLogger()->debug(sprintf('Counted Children  Display Group on User ID %d, there are %d', $user->userId, $count));

        return $count;
    }
}
