<?php
/*
 * Copyright (C) 2020 Xibo Signage Ltd
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

namespace Xibo\Report;

use Slim\Http\Request;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;

trait ReportTrait
{

    /**
     * @var \Xibo\Helper\ApplicationState
     */
    private $state;

    /**
     * @var StorageServiceInterface
     */
    private $store;

    /**
     * @var TimeSeriesStoreInterface
     */
    private $timeSeriesStore;

    /**
     * @var LogServiceInterface
     */
    private $logService;

    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    /**
     * @var DateServiceInterface
     */
    private $dateService;

    /**
     * @var SanitizerServiceInterface
     */
    private $sanitizerService;

    /**
     * @var Request
     */
    private $request;

    private $userId;

    /**
     * Set common dependencies.
     * @param \Xibo\Helper\ApplicationState $state
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param LogServiceInterface $log
     * @param ConfigServiceInterface $config
     * @param DateServiceInterface $date
     * @param SanitizerServiceInterface $sanitizer
     * @return $this
     */
    public function setCommonDependencies($state, $store, $timeSeriesStore, $log, $config, $date, $sanitizer)
    {
        $this->state = $state;
        $this->store = $store;
        $this->timeSeriesStore = $timeSeriesStore;
        $this->logService = $log;
        $this->configService = $config;
        $this->dateService = $date;
        $this->sanitizerService = $sanitizer;
        return $this;
    }

    /**
     * Get the Application State
     * @return \Xibo\Helper\ApplicationState
     */
    protected function getState()
    {
        return $this->state;
    }

    /**
     * Get Store
     * @return StorageServiceInterface
     */
    protected function getStore()
    {
        return $this->store;
    }

    /**
     * Get TimeSeriesStore
     * @return TimeSeriesStoreInterface
     */
    protected function getTimeSeriesStore()
    {
        return $this->timeSeriesStore;
    }

    /**
     * Get Log
     * @return LogServiceInterface
     */
    protected function getLog()
    {
        return $this->logService;
    }

    /**
     * Get Config
     * @return ConfigServiceInterface
     */
    public function getConfig()
    {
        return $this->configService;
    }

    /**
     * Get Date
     * @return DateServiceInterface
     */
    protected function getDate()
    {
        return $this->dateService;
    }

    /**
     * Get Sanitizer
     * @return SanitizerServiceInterface
     */
    protected function getSanitizer()
    {
        return $this->sanitizerService;
    }

    /**
     * Get Request
     * @return Request
     */
    private function getRequest()
    {
        if ($this->request == null)
            throw new \RuntimeException('....... called before Request has been set');

        return $this->request;
    }

    public function generateHourPeriods($filterRangeStart, $filterRangeEnd, $start,$end, $ranges) {

        $periodData = []; // to generate periods table

        // Generate all hours of the period
        foreach ($ranges as $range) {

            $startHour = $start->addHour()->format('U');

            // Remove the period which crossed the end range
            if ($startHour >= $filterRangeEnd) {
                continue;
            }

            // Period start
            $periodData[$range]['start'] = $startHour;
            if ($periodData[$range]['start'] < $filterRangeStart) {
                $periodData[$range]['start'] = $filterRangeStart;
            }

            // Period end
            $periodData[$range]['end'] = $end->addHour()->format('U');
            if ($periodData[$range]['end'] > $filterRangeEnd) {
                $periodData[$range]['end'] = $filterRangeEnd;
            }

            $hourofday = $this->dateService->parse($periodData[$range]['start'], 'U')->hour;

            // groupbycol =  hour
            $periodData[$range]['groupbycol'] = $hourofday;

        }

        return $periodData;
    }

    public function generateDayPeriods($filterRangeStart, $filterRangeEnd, $start, $end, $ranges, $groupByFilter = null) {

        $periodData = []; // to generate periods table

        // Generate all days of the period
        foreach ($ranges as $range) {

            $startDay = $start->addDay()->format('U');

            // Remove the period which crossed the end range
            if ($startDay >= $filterRangeEnd) {
                continue;
            }
            // Period start
            $periodData[$range]['start'] = $startDay;
            if ($periodData[$range]['start'] < $filterRangeStart) {
                $periodData[$range]['start'] = $filterRangeStart;
            }

            // Period end
            $periodData[$range]['end'] = $end->addDay()->format('U');
            if ($periodData[$range]['end'] > $filterRangeEnd) {
                $periodData[$range]['end'] = $filterRangeEnd;
            }

            if ($groupByFilter == 'bydayofweek') {
                $groupbycol = $this->dateService->parse($periodData[$range]['start'], 'U')->dayOfWeekIso;
            } else {
                $groupbycol = $this->dateService->parse($periodData[$range]['start'], 'U')->day;
            }

            // groupbycol =  dayofweek
            $periodData[$range]['groupbycol'] = $groupbycol;

        }
        return $periodData;

    }

    /**
     * Get a temporary table representing the periods covered
     * @param \Jenssegers\Date\Date $fromDt
     * @param \Jenssegers\Date\Date $toDt
     * @param string $groupByFilter
     * @param string $table
     * @param string $customLabel Custom Label
     * @return string
     * @throws \Xibo\Exception\InvalidArgumentException
     */
    public function getTemporaryPeriodsTable($fromDt, $toDt, $groupByFilter, $table = 'temp_periods', $customLabel = 'Y-m-d H:i:s')
    {
        // My from/to dt represent the entire range we're interested in.
        // we need to generate periods according to our grouping, within that range.
        // Clone them so as to not effect the calling object
        $fromDt = $fromDt->copy();
        $toDt = $toDt->copy();

        // our from/to dates might not sit nicely inside our period groupings
        // for example if we look at June, by week, the 1st of June is a Saturday, week 22.
        // NB:
        // FromDT/ToDt should always be at the start of the day.
        switch ($groupByFilter) {
            case 'byweek':
                $fromDt->startOfWeek();
                break;

            case 'bymonth':
                $fromDt->startOfMonth();
                break;
        }

        // Temporary Periods Table
        // -----------------------
        // we will use a temporary table for this.
        // Drop table if exists

        $this->getStore()->getConnection()->exec('
                DROP TABLE IF EXISTS `' . $table . '`');

        $this->getStore()->getConnection()->exec('
                CREATE TEMPORARY TABLE `' . $table . '` (
                    id INT,
                    customLabel VARCHAR(20),
                    label VARCHAR(20),
                    start INT,
                    end INT
                );
            ');

        // Prepare an insert statement
        $periods = $this->getStore()->getConnection()->prepare('
                INSERT INTO `' . $table . '` (id, customLabel, label, start, end) 
                VALUES (:id, :customLabel, :label, :start, :end)
            ');


        // Loop until we've covered all periods needed
        $loopDate = $fromDt->copy();
        while ($toDt > $loopDate) {
            // We add different periods for each type of grouping
            if ($groupByFilter == 'byhour') {
                $periods->execute([
                    'id' => $loopDate->hour,
                    'customLabel' => $loopDate->format($customLabel),
                    'label' => $loopDate->format('g:i A'),
                    'start' => $loopDate->format('U'),
                    'end' => $loopDate->addHour()->format('U')
                ]);
            } else if ($groupByFilter == 'byday') {
                $periods->execute([
                    'id' => $loopDate->year . $loopDate->month . $loopDate->day,
                    'customLabel' => $loopDate->format($customLabel),
                    'label' => $loopDate->format('Y-m-d'),
                    'start' => $loopDate->format('U'),
                    'end' => $loopDate->addDay()->format('U')
                ]);
            } else if ($groupByFilter == 'byweek') {
                $periods->execute([
                    'id' => $loopDate->weekOfYear . $loopDate->year,
                    'customLabel' => $loopDate->format($customLabel),
                    'label' => $loopDate->format('Y-m-d (\wW)'),
                    'start' => $loopDate->format('U'),
                    'end' => $loopDate->addWeek()->format('U')
                ]);
            } else if ($groupByFilter == 'bymonth') {
                $periods->execute([
                    'id' => $loopDate->year . $loopDate->month,
                    'customLabel' => $loopDate->format($customLabel),
                    'label' => $loopDate->format('M'),
                    'start' => $loopDate->format('U'),
                    'end' => $loopDate->addMonth()->format('U')
                ]);
            } else if ($groupByFilter == 'bydayofweek') {
                $periods->execute([
                    'id' => $loopDate->dayOfWeek,
                    'customLabel' => $loopDate->format($customLabel),
                    'label' => $loopDate->format('D'),
                    'start' => $loopDate->format('U'),
                    'end' => $loopDate->addDay()->format('U')
                ]);
            } else if ($groupByFilter == 'bydayofmonth') {
                $periods->execute([
                    'id' => $loopDate->day,
                    'customLabel' => $loopDate->format($customLabel),
                    'label' => $loopDate->format('d'),
                    'start' => $loopDate->format('U'),
                    'end' => $loopDate->addDay()->format('U')
                ]);
            } else {
                $this->getLog()->error('Unknown Grouping Selected ' . $groupByFilter);
                throw new InvalidArgumentException('Unknown Grouping ' . $groupByFilter, 'groupByFilter');
            }
        }

        $this->getLog()->debug(json_encode($this->store->select('SELECT * FROM ' . $table, []), JSON_PRETTY_PRINT));

        return $table;
    }

    public function getUserId() {

        return $this->userId;

    }

    public function setUserId($userId) {

        $this->userId = $userId;

        return;
    }

    /**
     * Set the filter
     * @param array[Optional] $extraFilter
     * @return array
     */
    public function gridRenderFilter($extraFilter = [])
    {
        // Handle filtering
        $filter = [
            'start' => $this->getSanitizer()->getInt('start', 0),
            'length' => $this->getSanitizer()->getInt('length', 10)
        ];

        $search = $this->getSanitizer()->getStringArray('search');
        if (is_array($search) && isset($search['value'])) {
            $filter['search'] = $search['value'];
        }
        else if ($search != '') {
            $filter['search'] = $search;
        }

        // Merge with any extra filter items that have been provided
        $filter = array_merge($extraFilter, $filter);

        return $filter;
    }

    /**
     * Set the sort order
     * @return array
     */
    public function gridRenderSort()
    {
        $columns = $this->getSanitizer()->getParam('columns', null);
        if ($columns === null || !is_array($columns) || count($columns) <= 0) {
            return null;
        }

        $order = $this->getSanitizer()->getParam('order', null);
        if ($order === null || !is_array($order) || count($order) <= 0) {
            return null;
        }

        return array_map(function ($element) use ($columns) {
            return ((isset($columns[$element['column']]['name']) && $columns[$element['column']]['name'] != '')
                    ? '`' . $columns[$element['column']]['name'] . '`'
                    : '`' . $columns[$element['column']]['data'] . '`')
                . (($element['dir'] == 'desc') ? ' DESC' : '');
        }, $order);
    }
}
