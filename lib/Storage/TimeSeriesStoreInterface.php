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

namespace Xibo\Storage;

use Carbon\Carbon;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\LogServiceInterface;
use Xibo\Support\Exception\GeneralException;

/**
 * Interface TimeSeriesStoreInterface
 * @package Xibo\Service
 */
interface TimeSeriesStoreInterface
{
    /**
     * Time series constructor.
     * @param array $config
     */
    public function __construct($config = null);

    /**
     * Set Time series Dependencies
     * @param LogServiceInterface $logger
     * @param LayoutFactory $layoutFactory
     * @param CampaignFactory $campaignFactory
     * @param MediaFactory $mediaFactory
     * @param WidgetFactory $widgetFactory
     * @param DisplayFactory $displayFactory
     * @param \Xibo\Entity\DisplayGroup $displayGroupFactory
     */
    public function setDependencies(
        $logger,
        $layoutFactory,
        $campaignFactory,
        $mediaFactory,
        $widgetFactory,
        $displayFactory,
        $displayGroupFactory
    );

    /**
     * @param \Xibo\Storage\StorageServiceInterface $store
     * @return $this
     */
    public function setStore($store);

    /**
     * Process and add a single statdata to array
     * @param $statData array
     */
    public function addStat($statData);

    /**
     * Write statistics to DB
     */
    public function addStatFinalize();

    /**
     * Get the earliest date
     * @return \Carbon\Carbon|null
     */
    public function getEarliestDate();

    /**
     * Get statistics
     * @param $filterBy array[mixed]|null
     * @param $isBufferedQuery bool Option to set buffered queries in MySQL
     * @throws GeneralException
     * @return TimeSeriesResultsInterface
     */
    public function getStats($filterBy = [], $isBufferedQuery = false);

    /**
     * Get total count of export statistics
     * @param $filterBy array[mixed]|null
     * @throws GeneralException
     * @return TimeSeriesResultsInterface
     */
    public function getExportStatsCount($filterBy = []);

    /**
     * Delete statistics
     * @param $toDt Carbon
     * @param $fromDt Carbon|null
     * @param $options array
     * @throws GeneralException
     * @return int number of deleted stat records
     * @throws \Exception
     */
    public function deleteStats($toDt, $fromDt = null, $options = []);

    /**
     * Execute query
     * @param $options array|[]
     * @throws GeneralException
     * @return array
     */
    public function executeQuery($options = []);

    /**
     * Get the statistic store
     * @return string
     */
    public function getEngine();

}