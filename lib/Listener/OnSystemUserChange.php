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

namespace Xibo\Listener;

use Xibo\Event\SystemUserChangedEvent;
use Xibo\Storage\StorageServiceInterface;

class OnSystemUserChange
{
    /**
     * @var StorageServiceInterface
     */
    private $storageService;

    public function __construct(StorageServiceInterface $storageService)
    {
        $this->storageService = $storageService;
    }

    public function __invoke(SystemUserChangedEvent $event)
    {
        // Reassign Module files
        $this->storageService->update('UPDATE `media` SET userId = :userId WHERE userId = :oldUserId AND type = \'module\'', [
            'userId' => $event->getNewSystemUser()->userId,
            'oldUserId' => $event->getOldSystemUser()->userId
        ]);

        // Reassign Display specific Display Groups
        $this->storageService->update('UPDATE `displaygroup` SET userId = :userId WHERE userId = :oldUserId AND isDisplaySpecific = 1', [
            'userId' => $event->getNewSystemUser()->userId,
            'oldUserId' => $event->getOldSystemUser()->userId
        ]);

        // Reassign system dayparts
        $this->storageService->update('UPDATE `daypart` SET userId = :userId WHERE userId = :oldUserId AND (isCustom = 1 OR isAlways = 1)', [
            'userId' => $event->getNewSystemUser()->userId,
            'oldUserId' => $event->getOldSystemUser()->userId
        ]);
    }
}
