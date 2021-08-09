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
use Xibo\Factory\RegionFactory;
use Xibo\Listener\ListenerLoggerTrait;

class RegionListener implements OnUserDeleteInterface
{
    use ListenerLoggerTrait;

    /**
     * @var RegionFactory
     */
    private $regionFactory;

    public function __construct(RegionFactory $regionFactory)
    {
        $this->regionFactory = $regionFactory;
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

    public function deleteChildren(User $user, EventDispatcherInterface $dispatcher, User $systemUser)
    {
        foreach ($this->regionFactory->getbyOwnerId($user->userId) as $region) {
            $region->delete();
        }
    }

    public function reassignAllTo(User $user, User $newUser, User $systemUser)
    {
        $regions = $this->regionFactory->getbyOwnerId($user->userId);

        $this->getLogger()->debug(sprintf('Counted Children Regions on User ID %d, there are %d', $user->userId, count($regions)));

        foreach ($regions as $region) {
            $region->setOwner($newUser->userId, true);
            $region->save([
                'validate' => false,
                'audit' => false,
                'notify' => false
            ]);
        }

        $this->getLogger()->debug(sprintf('Finished reassign Regions, there are %d children', $this->countChildren($user)));
    }

    public function countChildren(User $user)
    {
        $regions = $this->regionFactory->getbyOwnerId($user->userId);

        $this->getLogger()->debug(sprintf('Counted Children Regions on User ID %d, there are %d', $user->userId, count($regions)));

        return count($regions);
    }
}
