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


namespace Xibo\Service;

use Stash\Interfaces\PoolInterface;
use Xibo\Entity\Display;
use Xibo\Factory\ScheduleFactory;
use Xibo\Storage\StorageServiceInterface;

/**
 * Interface DisplayNotifyServiceInterface
 * @package Xibo\Service
 */
interface DisplayNotifyServiceInterface
{
    /**
     * DisplayNotifyServiceInterface constructor.
     * @param ConfigServiceInterface $config
     * @param StorageServiceInterface $store
     * @param LogServiceInterface $log
     * @param PoolInterface $pool
     * @param PlayerActionServiceInterface $playerActionService
     * @param ScheduleFactory $scheduleFactory
     */
    public function __construct($config, $store, $log, $pool, $playerActionService, $scheduleFactory);

    /**
     * Initialise
     * @return $this
     */
    public function init();

    /**
     * @return $this
     */
    public function collectNow();

    /**
     * @return $this
     */
    public function collectLater();

    /**
     * Process Queue of Display Notifications
     * @return $this
     */
    public function processQueue();

    /**
     * Notify by Display Id
     * @param $displayId
     */
    public function notifyByDisplayId($displayId);

    /**
     * Notify by Display Group Id
     * @param $displayGroupId
     */
    public function notifyByDisplayGroupId($displayGroupId);

    /**
     * Notify by CampaignId
     * @param $campaignId
     */
    public function notifyByCampaignId($campaignId);

    /**
     * Notify by DataSetId
     * @param $dataSetId
     */
    public function notifyByDataSetId($dataSetId);

    /**
     * Notify by PlaylistId
     * @param $playlistId
     */
    public function notifyByPlaylistId($playlistId);

    /**
     * Notify By Layout Code
     * @param $code
     */
    public function notifyByLayoutCode($code);

    /**
     * Notify by Menu Board ID
     * @param $menuId
     */
    public function notifyByMenuBoardId($menuId);

    /**
     * Notify that data has been updated for this display
     * @param \Xibo\Entity\Display $display
     * @param int $widgetId
     * @return void
     */
    public function notifyDataUpdate(Display $display, int $widgetId): void;
}
