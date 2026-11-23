<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
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
        $this->store->update('DELETE FROM `oauth_lkclientuser` WHERE userId = :userId', ['userId' => $user->userId]);

        $this->store->update('DELETE FROM `oauth_clients` WHERE userId = :userId', ['userId' => $user->userId]);

        $this->store->update('DELETE FROM `session` WHERE userId = :userId', ['userId' => $user->userId]);
    }
}
