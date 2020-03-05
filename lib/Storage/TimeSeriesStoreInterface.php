<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
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

namespace Xibo\Storage;

use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;

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
     * @param DateServiceInterface $date
     * @param MediaFactory $mediaFactory
     * @param WidgetFactory $widgetFactory
     * @param LayoutFactory $layoutFactory
     * @param DisplayFactory $displayFactory
     * @param CampaignFactory $campaignFactory
     */
    public function setDependencies($logger, $date, $layoutFactory = null, $campaignFactory = null, $mediaFactory = null, $widgetFactory = null, $displayFactory = null);

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
     * @return array
     */
    public function getEarliestDate();

    /**
     * Get statistics
     * @param $filterBy array[mixed]|null
     * @return TimeSeriesResultsInterface
     */
    public function getStats($filterBy = []);

    /**
     * Get total count of export statistics
     * @param $filterBy array[mixed]|null
     * @return TimeSeriesResultsInterface
     */
    public function getExportStatsCount($filterBy = []);

    /**
     * Delete statistics
     * @param $toDt \Jenssegers\Date\Date
     * @param $fromDt \Jenssegers\Date\Date|null
     * @param $options array
     * @return int number of deleted stat records
     * @throws \Exception
     */
    public function deleteStats($toDt, $fromDt = null, $options = []);

    /**
     * Execute query
     * @param $options array|[]
     * @return array
     */
    public function executeQuery($options = []);

    /**
     * Get the statistic store
     * @return string
     */
    public function getEngine();

}