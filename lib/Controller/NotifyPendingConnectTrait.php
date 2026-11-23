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

namespace Xibo\Controller;

use Carbon\Carbon;
use Xibo\Helper\DateFormatHelper;

/**
 * Shared notification logic for pending display connections.
 *
 * Used by display add flows. Expects the consuming class to have $notificationFactory
 * and $userGroupFactory properties, and to extend Base (for getUser / getLog).
 */
trait NotifyPendingConnectTrait
{
    /**
     * Raise a notification recording a connection the CMS is waiting on.
     *
     * Pending connections live in the cache, which cannot be enumerated or swept - once an entry
     * expires there is nothing left to notice its absence. So the notice is raised up front and
     * removed again when the Player arrives. One that is still sitting in the operator's
     * notifications is therefore exactly the case worth telling them about: the setup never
     * completed, and the pending connection has been (or is about to be) discarded.
     *
     * @param string $code the code the operator is working with
     * @param \Carbon\Carbon $expiresAt when the pending connection is discarded
     * @param string $resumeUrl where the operator can pick the setup back up
     * @return int|null the notification id, so it can be cleared once the Player connects
     */
    private function notifyPendingConnect(string $code, Carbon $expiresAt, string $resumeUrl): ?int
    {
        try {
            // Assigning the operator's own group keeps this out of everybody else's notifications:
            // Notification::manageAssignments expands assigned groups into lknotificationuser.
            $groups = $this->userGroupFactory->query(null, [
                'disableUserCheck' => 1,
                'userId' => $this->getUser()->userId,
                'isUserSpecific' => 1,
            ]);

            if (count($groups) <= 0) {
                return null;
            }

            $notification = $this->notificationFactory->createEmpty();
            $notification->subject = __('Display setup waiting for a Player to connect');
            $notification->body = sprintf(
                __('A display setup using code %s is waiting for its Player to connect. It expires'
                    . ' at %s, after which the pending connection is discarded and the setup has to'
                    . ' be started again. <a href="%s">Return to Add Display</a>.'),
                htmlspecialchars($code),
                $expiresAt->format(DateFormatHelper::getSystemFormat()),
                htmlspecialchars($resumeUrl)
            );
            $notification->createDt = Carbon::now()->format('U');
            $notification->releaseDt = Carbon::now()->format('U');
            $notification->isInterrupt = 0;
            $notification->isSystem = 0;
            $notification->userId = $this->getUser()->userId;
            $notification->type = 'display';

            foreach ($groups as $group) {
                $notification->assignUserGroup($group);
            }

            $notification->save();

            return $notification->notificationId;
        } catch (\Exception $e) {
            // A missing notification must never stop a display being added.
            $this->getLog()->error('notifyPendingConnect: ' . $e->getMessage());
            return null;
        }
    }
}
