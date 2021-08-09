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
