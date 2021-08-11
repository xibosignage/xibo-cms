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

use Xibo\Event\PlaylistMaxNumberChangedEvent;
use Xibo\Storage\StorageServiceInterface;

class OnPlaylistMaxNumberChange
{
    /**
     * @var StorageServiceInterface
     */
    private $storageService;

    public function __construct(StorageServiceInterface $storageService)
    {
        $this->storageService = $storageService;
    }

    public function __invoke(PlaylistMaxNumberChangedEvent $event)
    {
        $this->storageService->update('UPDATE `playlist` SET maxNumberOfItems = :newLimit WHERE isDynamic = 1 AND maxNumberOfItems > :newLimit', [
            'newLimit' => $event->getNewLimit()
        ]);
    }
}
