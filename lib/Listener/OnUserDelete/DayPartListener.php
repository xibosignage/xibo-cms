<?php
/*
 * Copyright (c) 2022 Xibo Signage Ltd
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
use Xibo\Factory\DayPartFactory;
use Xibo\Factory\ScheduleFactory;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Service\DisplayNotifyServiceInterface;
use Xibo\Storage\StorageServiceInterface;

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

    /** @var DisplayNotifyServiceInterface */
    private $displayNotifyService;

    public function __construct(
        StorageServiceInterface $storageService,
        DayPartFactory $dayPartFactory,
        ScheduleFactory $scheduleFactory,
        DisplayNotifyServiceInterface $displayNotifyService
    ) {
        $this->storageService = $storageService;
        $this->dayPartFactory = $dayPartFactory;
        $this->scheduleFactory = $scheduleFactory;
        $this->displayNotifyService = $displayNotifyService;
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
        // system dayParts cannot be deleted, if this user owns them reassign to systemUser
        foreach ($this->dayPartFactory->getByOwnerId($user->userId) as $dayPart) {
            if ($dayPart->isSystemDayPart()) {
                $dayPart->setOwner($systemUser->userId);
                $dayPart->save(['recalculateHash' => false]);
            } else {
                $dayPart->setScheduleFactory($this->scheduleFactory, $this->displayNotifyService)->delete();
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function reassignAllTo(User $user, User $newUser, User $systemUser)
    {
        // Reassign Dayparts
        foreach ($this->dayPartFactory->getByOwnerId($user->userId) as $dayPart) {
            ($dayPart->isSystemDayPart()) ? $dayPart->setOwner($systemUser->userId) : $dayPart->setOwner($newUser->getOwnerId());
            $dayPart->save(['recalculateHash' => false]);
        }
    }

    /**
     * @inheritDoc
     */
    public function countChildren(User $user)
    {
        $dayParts = $this->dayPartFactory->getByOwnerId($user->userId);
        $count = count($dayParts);
        $this->getLogger()->debug(sprintf('Counted Children DayParts on User ID %d, there are %d', $user->userId, $count));

        return $count;
    }
}
