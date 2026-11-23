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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Xibo\Entity\User;
use Xibo\Event\UserDeleteEvent;

interface OnUserDeleteInterface
{
    /**
     * Listen to the UserDeleteEvent
     *
     * @param UserDeleteEvent $event
     * @return mixed
     */
    public function __invoke(UserDeleteEvent $event, $eventName, EventDispatcherInterface $dispatcher);

    /**
     * Delete Objects owned by the User we want to delete
     *
     * @param User $user
     * @return mixed
     */
    public function deleteChildren(User $user, EventDispatcherInterface $dispatcher, User $systemUser);

    /**
     * Reassign objects to a new User
     *
     * @param User $user
     * @param User $newUser
     * @return mixed
     */
    public function reassignAllTo(User $user, User $newUser, User $systemUser);

    /**
     * Count Children, return count of objects owned by the User we want to delete
     *
     * @param User $user
     * @return mixed
     */
    public function countChildren(User $user);
}
