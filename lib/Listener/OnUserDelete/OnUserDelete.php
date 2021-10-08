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

    public function __construct(StorageServiceInterface $store)
    {
        $this->store = $store;
    }

    /**
     * @inheritDoc
     */
    public function __invoke(UserDeleteEvent $event)
    {
        $user = $event->getUser();
        $function = $event->getFunction();

        if ($function === 'delete' || $function === 'reassignAll') {
            $this->deleteChildren($user);
        }
    }

    // when we delete a User with or without reassign the session and oauth clients should always be removed
    // other objects that can be owned by the user are deleted in their respective listeners.
    private function deleteChildren($user)
    {
        // Delete oAuth clients
        $this->store->update('DELETE FROM `oauth_clients` WHERE userId = :userId', ['userId' => $user->userId]);

        $this->store->update('DELETE FROM `session` WHERE userId = :userId', ['userId' => $user->userId]);
    }
}
