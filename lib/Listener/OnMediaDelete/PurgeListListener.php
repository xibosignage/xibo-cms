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

namespace Xibo\Listener\OnMediaDelete;

use Carbon\Carbon;
use Xibo\Event\MediaDeleteEvent;
use Xibo\Helper\DateFormatHelper;
use Xibo\Listener\ListenerLoggerTrait;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Storage\StorageServiceInterface;

class PurgeListListener
{
    use ListenerLoggerTrait;

    /**
     * @var StorageServiceInterface
     */
    private $store;
    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    public function __construct(StorageServiceInterface $store, ConfigServiceInterface $configService)
    {
        $this->store = $store;
        $this->configService = $configService;
    }

    public function __invoke(MediaDeleteEvent $event)
    {
        // storedAs
        if ($event->isSetToPurge()) {
            $this->store->insert('INSERT INTO `purge_list` (mediaId, storedAs, expiryDate) VALUES (:mediaId, :storedAs, :expiryDate)', [
                'mediaId' => $event->getMedia()->mediaId,
                'storedAs' => $event->getMedia()->storedAs,
                'expiryDate' => Carbon::now()->addDays($this->configService->getSetting('DEFAULT_PURGE_LIST_TTL'))->format(DateFormatHelper::getSystemFormat())
            ]);
        }
    }
}
