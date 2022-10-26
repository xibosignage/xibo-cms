<?php
/*
 * Copyright (C) 2022 Xibo Signage Ltd
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

use Carbon\Carbon;
use http\Exception\RuntimeException;
use Psr\Log\NullLogger;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\ReportResult;
use Xibo\Helper\Translate;
use Xibo\Service\LogServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;
use Xibo\Support\Exception\InvalidArgumentException;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Trait ReportDefaultTrait
 * @package Xibo\Report
 */
trait ReportDefaultTrait
{
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
     * @var Request
     */
    private $request;

    /** @var \Xibo\Entity\User */
    private $user;

    /**
     * Set common dependencies.
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @return $this
     */
    public function setCommonDependencies($store, $timeSeriesStore)
    {
        $this->store = $store;
        $this->timeSeriesStore = $timeSeriesStore;
        $this->logService = new NullLogger();
        return $this;
    }

    /**
     * @param LogServiceInterface $logService
     * @return $this
     */
    public function useLogger(LogServiceInterface $logService)
    {
        $this->logService = $logService;

        return $this;
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
     * @return \Xibo\Entity\User
     * @throws \Xibo\Support\Exception\NotFoundException
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set user Id
     * @param \Xibo\Entity\User $user
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get chart script
     * @param ReportResult $results
     * @return string
     */
    public function getReportChartScript($results)
    {
        return null;
    }

    /**
     * Generate saved report name
     * @param SanitizerInterface $sanitizedParams
     * @return string
     */
    public function generateSavedReportName(SanitizerInterface $sanitizedParams)
    {
        $saveAs = sprintf(__('%s report'), ucfirst($sanitizedParams->getString('filter')));

        return $saveAs. ' '. Carbon::now()->format('Y-m-d');
    }

    /**
     * Get a temporary table representing the periods covered
     * @param Carbon $fromDt
     * @param Carbon $toDt
     * @param string $groupByFilter
     * @param string $table
     * @param string $customLabel Custom Label
     * @return string
     * @throws InvalidArgumentException
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
                $fromDt->locale(Translate::GetLocale())->startOfWeek();
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
            } elseif ($groupByFilter == 'byday') {
                $periods->execute([
                    'id' => $loopDate->year . $loopDate->month . $loopDate->day,
                    'customLabel' => $loopDate->format($customLabel),
                    'label' => $loopDate->format('Y-m-d'),
                    'start' => $loopDate->format('U'),
                    'end' => $loopDate->addDay()->format('U')
                ]);
            } elseif ($groupByFilter == 'byweek') {
                $weekNo = $loopDate->locale(Translate::GetLocale())->week();

                $periods->execute([
                    'id' => $loopDate->weekOfYear . $loopDate->year,
                    'customLabel' => $loopDate->format($customLabel),
                    'label' => $loopDate->format('Y-m-d') . '(w' . $weekNo . ')',
                    'start' => $loopDate->format('U'),
                    'end' => $loopDate->addWeek()->format('U')
                ]);
            } elseif ($groupByFilter == 'bymonth') {
                $periods->execute([
                    'id' => $loopDate->year . $loopDate->month,
                    'customLabel' => $loopDate->format($customLabel),
                    'label' => $loopDate->format('M'),
                    'start' => $loopDate->format('U'),
                    'end' => $loopDate->addMonth()->format('U')
                ]);
            } elseif ($groupByFilter == 'bydayofweek') {
                $periods->execute([
                    'id' => $loopDate->dayOfWeek,
                    'customLabel' => $loopDate->format($customLabel),
                    'label' => $loopDate->format('D'),
                    'start' => $loopDate->format('U'),
                    'end' => $loopDate->addDay()->format('U')
                ]);
            } elseif ($groupByFilter == 'bydayofmonth') {
                $periods->execute([
                    'id' => $loopDate->day,
                    'customLabel' => $loopDate->format($customLabel),
                    'label' => $loopDate->format('d'),
                    'start' => $loopDate->format('U'),
                    'end' => $loopDate->addDay()->format('U')
                ]);
            } else {
                $this->getLog()->error('Unknown Grouping Selected ' . $groupByFilter);
                throw new InvalidArgumentException(__('Unknown Grouping ') . $groupByFilter, 'groupByFilter');
            }
        }

        $this->getLog()->debug(json_encode($this->store->select('SELECT * FROM ' . $table, []), JSON_PRETTY_PRINT));

        return $table;
    }

    /**
     * Get an array of displayIds we should pass into the query,
     *  if an exception is thrown, we should stop the report and return no results.
     * @param \Xibo\Support\Sanitizer\SanitizerInterface $params
     * @return array displayIds
     * @throws \Xibo\Support\Exception\GeneralException
     */
    private function getDisplayIdFilter(SanitizerInterface $params): array
    {
        $displayIds = [];

        // Filters
        $displayId = $params->getInt('displayId');
        $displayGroupIds = $params->getIntArray('displayGroupId', ['default' => null]);

        if ($displayId !== null) {
            // Don't bother checking if we are a super admin
            if (!$this->getUser()->isSuperAdmin()) {
                $display = $this->displayFactory->getById($displayId);
                if ($this->getUser()->checkViewable($display)) {
                    $displayIds[] = $displayId;
                }
            } else {
                $displayIds[] = $displayId;
            }
        } else {
            // If we are NOT a super admin OR we have some display group filters
            // get an array of display id this user has access to.
            // we cannot rely on the logged-in user because this will be run by the task runner which is a sysadmin
            if (!$this->getUser()->isSuperAdmin() || $displayGroupIds !== null) {
                // This will be the displayIds the user has access to, and are in the displayGroupIds provided.
                foreach ($this->displayFactory->query(
                    null,
                    [
                        'userCheckUserId' => $this->getUser()->userId,
                        'displayGroupIds' => $displayGroupIds,
                    ]
                ) as $display) {
                    $displayIds[] = $display->displayId;
                }
            }
        }

        // If we are a super admin without anything filtered, the object of this method is to return an empty
        // array.
        // If we are any other user, we must return something in the array.
        if (!$this->getUser()->isSuperAdmin() && count($displayIds) <= 0) {
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');
        }

        return $displayIds;
    }
}
