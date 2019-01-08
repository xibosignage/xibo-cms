<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2016 Spring Signage Ltd
 * (TimeSeriesStoreInterface.php)
 */

namespace Xibo\Storage;

use Jenssegers\Date\Date;
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
     */
    public function setDependencies($logger);

    /**
     * Add Media statistics
     * @param $statData array
     */
    public function addMediaStat($statData);

    /**
     * Add Layout statistics
     * @param $statData array
     */
    public function addLayoutStat($statData);

    /**
     * Add Tag statistics
     * @param $statData array
     */
    public function addTagStat($statData);

    /**
     * Retrieve statistics
     * @param $fromDt string
     * @param $toDt string
     * @param $displayIds array
     * @param $layoutIds array[mixed]|null
     * @param $mediaIds array[mixed]|null
     * @param $type mixed
     * @param $columns array
     * @param $start int
     * @param $length int
     * @return array[array statData, int count, int totalStats]
     */
    public function getStatsReport($fromDt, $toDt, $displayIds, $layoutIds, $mediaIds, $type, $columns, $start = null, $length = null);

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
     * @return array[array statData]
     */
    public function getStats($fromDt, $toDt, $displayIds = null);

    /**
     * Delete statistics
     * @param $fromDt string|null
     * @param $toDt string
     * @param $options array
     * @return array
     */
    public function deleteStats($toDt, $fromDt = null, $options = []);



}