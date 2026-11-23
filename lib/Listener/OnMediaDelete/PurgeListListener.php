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
                'expiryDate' => Carbon::now()
                    ->addDays((int) $this->configService->getSetting('DEFAULT_PURGE_LIST_TTL'))
                    ->format(DateFormatHelper::getSystemFormat())
            ]);
        }
    }
}
