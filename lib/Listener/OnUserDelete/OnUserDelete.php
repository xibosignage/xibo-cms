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

use Xibo\Event\UserDeleteEvent;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Storage\StorageServiceInterface;

class OnUserDelete
{
    use ListenerLoggerTrait;

    /** @var StorageServiceInterface */
    private $store;

    public function __construct(StorageServiceInterface $store) {
        $this->store = $store;
    }

    /**
     * @param UserDeleteEvent $event
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function __invoke(UserDeleteEvent $event)
    {
        $user = $event->getUser();
        $function = $event->getFunction();
        $newUser = $event->getNewUser();

        if ($function === 'delete') {
            $this->deleteChildren($user);
        } else if ($function === 'reassignAll') {
            $this->reassignAllTo($user, $newUser);
        }
    }

    private function deleteChildren($user)
    {
        // Delete Actions
        $this->store->update('DELETE FROM `action` WHERE ownerId = :userId', ['userId' => $user->userId]);
        // Delete oAuth clients
        $this->store->update('DELETE FROM `oauth_clients` WHERE userId = :userId', ['userId' => $user->userId]);
        // Delete user specific entities
        $this->store->update('DELETE FROM `resolution` WHERE userId = :userId', ['userId' => $user->userId]);

        $this->store->update('DELETE FROM `session` WHERE userId = :userId', ['userId' => $user->userId]);
    }

    private function reassignAllTo($user, $newUser)
    {
        // Reassign display profiles
        $this->store->update('UPDATE `displayprofile` SET userId = :userId WHERE userId = :oldUserId', [
            'userId' => $newUser->userId,
            'oldUserId' => $user->userId
        ]);

        // Reassign resolutions
        $this->store->update('UPDATE `resolution` SET userId = :userId WHERE userId = :oldUserId', [
            'userId' => $newUser->userId,
            'oldUserId' => $user->userId
        ]);

        // Reassign saved_resports
        $this->store->update('UPDATE `saved_report` SET userId = :userId WHERE userId = :oldUserId', [
            'userId' => $newUser->userId,
            'oldUserId' => $user->userId
        ]);

        // Reassign Actions
        $this->store->update('UPDATE `action` SET ownerId = :userId WHERE ownerId = :oldUserId', [
            'userId' => $newUser->userId,
            'oldUserId' => $user->userId
        ]);

        // Delete oAuth Clients - security concern
        $this->store->update('DELETE FROM `oauth_clients` WHERE userId = :userId', ['userId' => $user->userId]);
    }

    /**
     * @param $user
     */
    private function countChildren($user)
    {

    }
}