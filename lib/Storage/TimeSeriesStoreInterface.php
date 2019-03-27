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
     */
    public function setDependencies($logger, $date, $mediaFactory = null, $widgetFactory = null, $layoutFactory = null, $displayFactory = null);

    /**
     * Add statistics
     * @param $statData array
     */
    public function addStat($statData);

    /**
     * Retrieve statistics
     * @param $fromDt string
     * @param $toDt string
     * @param $displayIds array
     * @param $layoutIds array[mixed]|null
     * @param $mediaIds array[mixed]|null
     * @param $type mixed
     * @param $columns array
     * @param $tags string
     * @param $tagsType string
     * @param $exactTags mixed
     * @param $start int
     * @param $length int
     * @return array[array statData, int count, int totalStats]
     */
    public function getStatsReport($fromDt, $toDt, $displayIds, $layoutIds, $mediaIds, $type, $columns, $tags, $tagsType, $exactTags, $start = null, $length = null);

    /**
     * Get the earliest date
     * @return array
     */
    public function getEarliestDate();

    /**
     * Get statistics
     * @param $fromDt string
     * @param $toDt string
     * @param $displayIds array
     * @return TimeSeriesResultsInterface
     */
    public function getStats($fromDt, $toDt, $displayIds = null);

    /**
     * Delete statistics
     * @param $fromDt string|null
     * @param $toDt string
     * @param $options array
     * @return int number of deleted stat records
     * @throws \Exception
     */
    public function deleteStats($toDt, $fromDt = null, $options = []);

    /**
     * Get the daily summary report
     * @param $displayIds array
     * @param $diffInDays int
     * @param $type string
     * @param $layoutId int
     * @param $mediaId int
     * @param $reportFilter string
     * @param $groupByFilter string|null
     * @param $fromDt string|null
     * @param $toDt string|null
     * @return array[string weekStart, string weekEnd, string shortMonth, int monthNo, int yearDate, string start, int NumberPlays, int Duration]
     */
    public function getDailySummaryReport($displayIds, $diffInDays, $type, $layoutId, $mediaId, $reportFilter, $groupByFilter = null, $fromDt = null, $toDt = null );


}