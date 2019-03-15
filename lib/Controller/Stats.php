<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2009-2016 Daniel Garner
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
namespace Xibo\Controller;

use Xibo\Exception\AccessDeniedException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\UserFactory;
use Xibo\Factory\UserGroupFactory;
use Xibo\Helper\ByteFormatter;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;
use Xibo\Storage\StorageServiceInterface;
use Xibo\Storage\TimeSeriesStoreInterface;

/**
 * Class Stats
 * @package Xibo\Controller
 */
class Stats extends Base
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
     * @var DisplayFactory
     */
    private $displayFactory;

    /**
     * @var DisplayGroupFactory
     */
    private $displayGroupFactory;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /** @var  LayoutFactory */
    private $layoutFactory;

    /** @var  UserFactory */
    private $userFactory;

    /** @var  UserGroupFactory */
    private $userGroupFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param StorageServiceInterface $store
     * @param TimeSeriesStoreInterface $timeSeriesStore
     * @param DisplayFactory $displayFactory
     * @param LayoutFactory $layoutFactory
     * @param MediaFactory $mediaFactory
     * @param UserFactory $userFactory
     * @param UserGroupFactory $userGroupFactory
     * @param DisplayGroupFactory $displayGroupFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $store, $timeSeriesStore, $displayFactory, $layoutFactory, $mediaFactory, $userFactory, $userGroupFactory, $displayGroupFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->store = $store;
        $this->timeSeriesStore = $timeSeriesStore;
        $this->displayFactory = $displayFactory;
        $this->layoutFactory = $layoutFactory;
        $this->mediaFactory = $mediaFactory;
        $this->userFactory = $userFactory;
        $this->userGroupFactory = $userGroupFactory;
        $this->displayGroupFactory = $displayGroupFactory;
    }

    /**
     * Stats page
     */
    function displayPage()
    {
        $data = [
            // List of Displays this user has permission for
            'defaults' => [
                'fromDate' => $this->getDate()->getLocalDate(time() - (86400 * 35)),
                'fromDateOneDay' => $this->getDate()->getLocalDate(time() - 86400),
                'toDate' => $this->getDate()->getLocalDate()
            ]
        ];

        $this->getState()->template = 'statistics-page';
        $this->getState()->setData($data);
    }

    /**
     * Stats page
     */
    function displayProofOfPlayPage()
    {
        $data = [
            // List of Displays this user has permission for
            'defaults' => [
                'fromDate' => $this->getDate()->getLocalDate(time() - (86400 * 35)),
                'fromDateOneDay' => $this->getDate()->getLocalDate(time() - 86400),
                'toDate' => $this->getDate()->getLocalDate()
            ]
        ];

        $this->getState()->template = 'stats-proofofplay-page';
        $this->getState()->setData($data);
    }

    /**
     * @SWG\Definition(
     *  definition="StatisticsData",
     *  @SWG\Property(
     *      property="type",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="display",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="layout",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="media",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="numberPlays",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="duration",
     *      type="integer"
     *  ),
     *  @SWG\Property(
     *      property="minStart",
     *      type="string"
     *  ),
     *  @SWG\Property(
     *      property="maxEnd",
     *      type="string"
     *  )
     * )
     *
     *
     * Shows the stats grid
     *
     * @SWG\Get(
     *  path="/stats",
     *  operationId="statsSearch",
     *  tags={"statistics"},
     *  @SWG\Parameter(
     *      name="type",
     *      in="formData",
     *      description="The type of stat to return. Layout|Media|Widget or All",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="fromDt",
     *      in="formData",
     *      description="The start date for the filter. Default = 24 hours ago",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="toDt",
     *      in="formData",
     *      description="The end date for the filter. Default = now.",
     *      type="string",
     *      required=false
     *   ),
     *  @SWG\Parameter(
     *      name="displayId",
     *      in="formData",
     *      description="An optional display Id to filter",
     *      type="integer",
     *      required=false
     *   ),
     *   @SWG\Parameter(
     *      name="layoutId",
     *      description="An optional array of layout Id to filter",
     *      in="formData",
     *      required=false,
     *      type="array",
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *   @SWG\Parameter(
     *      name="mediaId",
     *      description="An optional array of media Id to filter",
     *      in="formData",
     *      required=false,
     *      type="array",
     *      @SWG\Items(
     *          type="integer"
     *      )
     *  ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation",
     *      @SWG\Schema(
     *          type="array",
     *          @SWG\Items(
     *              ref="#/definitions/StatisticsData"
     *          )
     *      )
     *  )
     * )
     */
    public function grid()
    {
        $fromDt = $this->getSanitizer()->getDate('fromDt', $this->getSanitizer()->getDate('statsFromDt', $this->getDate()->parse()->addDay(-1)));
        $toDt = $this->getSanitizer()->getDate('toDt', $this->getSanitizer()->getDate('statsToDt', $this->getDate()->parse()));
        $displayId = $this->getSanitizer()->getInt('displayId');
        $layoutIds = $this->getSanitizer()->getIntArray('layoutId');
        $mediaIds = $this->getSanitizer()->getIntArray('mediaId');
        $type = strtolower($this->getSanitizer()->getString('type'));
        $tags = $this->getSanitizer()->getString('tags');
        $tagsType = $this->getSanitizer()->getString('tagsType');
        $exactTags = $this->getSanitizer()->getCheckbox('exactTags');

        // What if the fromdt and todt are exactly the same?
        // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
        if ($fromDt == $toDt) {
            $toDt->addDay(1);
        }

        // Format param dates
        $fromDt = $this->getDate()->getLocalDate($fromDt);
        $toDt = $this->getDate()->getLocalDate($toDt);

        $this->getLog()->debug('Converted Times received are: FromDt=' . $fromDt . '. ToDt=' . $toDt);

        // Do not filter by display if super admin and no display is selected
        // Super admin will be able to see stat records of deleted display, we will not filter by display later
        $displayIds = [];
        if (!$this->getUser()->isSuperAdmin()) {
            // Get an array of display id this user has access to.
            foreach ($this->displayFactory->query() as $display) {
                $displayIds[] = $display->displayId;
            }

            if (count($displayIds) <= 0)
                throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

            // Set displayIds as [-1] if the user selected a display for which they don't have permission
            if ($displayId != 0) {
                if (!in_array($displayId, $displayIds)) {
                    $displayIds = [-1];
                } else {
                    $displayIds = [$displayId];
                }
            }
        } else {
            if ($displayId != 0) {
                $displayIds = [$displayId];
            }
        }

        // Sorting?
        $filterBy = $this->gridRenderFilter();
        $sortOrder = $this->gridRenderSort();

        $columns = [];
        if (is_array($sortOrder))
            $columns = $sortOrder;

        // Paging
        $start = 0;
        $length = 0;
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {

            $start = intval($this->getSanitizer()->getInt('start', $filterBy), 0);
            $length = $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        // Call the time series interface getStatsReport
        $result =  $this->timeSeriesStore->getStatsReport($fromDt, $toDt, $displayIds, $layoutIds, $mediaIds, $type, $columns, $tags, $tagsType, $exactTags, $start, $length);

        // Sanitize results
        $rows = [];
        foreach ($result['statData'] as $row) {
            $entry = [];

            $widgetId = $this->getSanitizer()->int($row['widgetId']);
            $widgetName = $this->getSanitizer()->string($row['media']);
            // If the media name is empty, and the widgetid is not, then we can assume it has been deleted.
            $widgetName = ($widgetName == '' &&  $widgetId != 0) ? __('Deleted from Layout') : $widgetName;
            $displayName = $this->getSanitizer()->string($row['display']);
            $layoutName = $this->getSanitizer()->string($row['layout']);

            $entry['type'] = $this->getSanitizer()->string($row['type']);
            $entry['displayId'] = $this->getSanitizer()->int(($row['displayId']));
            $entry['display'] = ($displayName != '') ? $displayName : __('Not Found');
            $entry['layout'] = ($layoutName != '') ? $layoutName :  __('Not Found');
            $entry['media'] = $widgetName;
            $entry['numberPlays'] = $this->getSanitizer()->int($row['numberPlays']);
            $entry['duration'] = $this->getSanitizer()->int($row['duration']);
            $entry['minStart'] = $this->getDate()->getLocalDate($this->getDate()->parse($row['minStart']));
            $entry['maxEnd'] = $this->getDate()->getLocalDate($this->getDate()->parse($row['maxEnd']));
            $entry['layoutId'] = $this->getSanitizer()->int($row['layoutId']);
            $entry['widgetId'] = $this->getSanitizer()->int($row['widgetId']);
            $entry['mediaId'] = $this->getSanitizer()->int($row['mediaId']);

            $rows[] = $entry;
        }

        // Paging
        if ($result['count'] > 0) {
            $this->getState()->recordsTotal = intval($result['totalStats']);
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($rows);

    }

    /**
     * Bandwidth Data
     */
    public function bandwidthData()
    {
        $fromDt = $this->getSanitizer()->getDate('fromDt', $this->getSanitizer()->getDate('bandwidthFromDt'));
        $toDt = $this->getSanitizer()->getDate('toDt', $this->getSanitizer()->getDate('bandwidthToDt'));

        // Get an array of display id this user has access to.
        $displayIds = [];

        foreach ($this->displayFactory->query() as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0)
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

        // Get some data for a bandwidth chart
        $dbh = $this->store->getConnection();

        $displayId = $this->getSanitizer()->getInt('displayId');
        $params = array(
            'month' => $this->getDate()->getLocalDate($fromDt->setDateTime($fromDt->year, $fromDt->month, 1, 0, 0), 'U'),
            'month2' => $this->getDate()->getLocalDate($toDt->addMonth(1)->setDateTime($toDt->year, $toDt->month, 1, 0, 0), 'U')
        );

        $SQL = 'SELECT display.display, IFNULL(SUM(Size), 0) AS size ';

        if ($displayId != 0)
            $SQL .= ', bandwidthtype.name AS type ';

        $SQL .= ' FROM `bandwidth`
                LEFT OUTER JOIN `display`
                ON display.displayid = bandwidth.displayid AND display.displayId IN (' . implode(',', $displayIds) . ') ';

        if ($displayId != 0)
            $SQL .= '
                    INNER JOIN bandwidthtype
                    ON bandwidthtype.bandwidthtypeid = bandwidth.type
                ';

        $SQL .= '  WHERE month > :month
                AND month < :month2 ';

        if ($displayId != 0) {
            $SQL .= ' AND display.displayid = :displayid ';
            $params['displayid'] = $displayId;
        }

        $SQL .= 'GROUP BY display.display ';

        if ($displayId != 0)
            $SQL .= ' , bandwidthtype.name ';

        $SQL .= 'ORDER BY display.display';

        $sth = $dbh->prepare($SQL);

        $sth->execute($params);

        // Get the results
        $results = $sth->fetchAll();

        $maxSize = 0;
        foreach ($results as $library) {
            $maxSize = ($library['size'] > $maxSize) ? $library['size'] : $maxSize;
        }

        // Decide what our units are going to be, based on the size
        $base = floor(log($maxSize) / log(1024));

        $labels = [];
        $data = [];
        $backgroundColor = [];

        foreach ($results as $row) {

            // label depends whether we are filtered by display
            if ($displayId != 0) {
                $labels[] = $row['type'];
            } else {
                $labels[] = $row['display'] === null ? __('Deleted Displays') : $row['display'];
            }
            $backgroundColor[] = ($row['display'] === null) ? 'rgb(255,0,0)' : 'rgb(11, 98, 164)';
            $data[] = round((double)$row['size'] / (pow(1024, $base)), 2);
        }

        // Set up some suffixes
        $suffixes = array('bytes', 'k', 'M', 'G', 'T');

        $this->getState()->extra = [
            'labels' => $labels,
            'data' => $data,
            'backgroundColor' => $backgroundColor,
            'postUnits' => (isset($suffixes[$base]) ? $suffixes[$base] : '')
        ];
    }

    /**
     * Output CSV Form
     */
    public function exportForm()
    {
        $this->getState()->template = 'statistics-form-export';
    }

    /**
     * Outputs a CSV of stats
     */
    public function export()
    {
        // We are expecting some parameters
        $fromDt = $this->getSanitizer()->getDate('fromDt');
        $toDt = $this->getSanitizer()->getDate('toDt');
        $displayId = $this->getSanitizer()->getInt('displayId');

        // Do not filter by display if super admin and no display is selected
        // Super admin will be able to see stat records of deleted display, we will not filter by display later
        $displayIds = [];
        if (!$this->getUser()->isSuperAdmin()) {
            // Get an array of display id this user has access to.
            foreach ($this->displayFactory->query() as $display) {
                $displayIds[] = $display->displayId;
            }

            if (count($displayIds) <= 0)
                throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

            // Set displayIds as [-1] if the user selected a display for which they don't have permission
            if ($displayId != 0) {
                if (!in_array($displayId, $displayIds)) {
                    $displayIds = [-1];
                } else {
                    $displayIds = [$displayId];
                }
            }
        } else {
            if ($displayId != 0) {
                $displayIds = [$displayId];
            }
        }

        // Format param dates
        $fromDt = $this->getDate()->getLocalDate($fromDt);
        $toDt = $this->getDate()->getLocalDate($toDt);

        // Get result set
        $resultSet =  $this->timeSeriesStore->getStats($fromDt, $toDt, $displayIds);

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Type', 'FromDT', 'ToDT', 'Layout', 'Display', 'Media', 'Tag']);

        while ($row = $resultSet->getNextRow() ) {

            $displayName = $this->getSanitizer()->string($row['display']);
            $layoutName = $this->getSanitizer()->string($row['layout']);

            // Read the columns
            $type = $this->getSanitizer()->string($row['type']);
            $fromDt = $this->getSanitizer()->string($row['start']);
            $toDt = $this->getSanitizer()->string($row['end']);
            $layout = ($layoutName != '') ? $layoutName :  __('Not Found');;
            $display = ($displayName != '') ? $displayName : __('Not Found');
            $media = isset($row['media']) ? $this->getSanitizer()->string($row['media']): '';
            $tag = isset($row['tag']) ? $this->getSanitizer()->string($row['tag']): '';

            fputcsv($out, [$type, $fromDt, $toDt, $layout, $display, $media, $tag]);
        }

        fclose($out);

        // We want to output a load of stuff to the browser as a text file.
        $app = $this->getApp();
        $app->response()->header('Content-Type', 'text/csv');
        $app->response()->header('Content-Disposition', 'attachment; filename="stats.csv"');
        $app->response()->header('Content-Transfer-Encoding', 'binary"');
        $app->response()->header('Accept-Ranges', 'bytes');
        $this->setNoOutput(true);
    }

    /**
     * Summary Report Form
     */
    public function summaryReportForm()
    {
        $data = [
            'defaults' => [
                'fromDate' => $this->getDate()->getLocalDate(time() - (86400 * 35)),
                'fromDateOneDay' => $this->getDate()->getLocalDate(time() - 86400),
                'toDate' => $this->getDate()->getLocalDate()
            ]
        ];

        $this->getState()->template = 'stats-form-summaryreport';
        $this->getState()->setData($data);
    }

    /**
     * Summary Report
     */
    public function summaryReportData()
    {
        $fromDt = $this->getSanitizer()->getDate('fromDt', $this->getSanitizer()->getDate('statsFromDt', $this->getDate()->parse()->addDay(-1)));
        $toDt = $this->getSanitizer()->getDate('toDt', $this->getSanitizer()->getDate('statsToDt', $this->getDate()->parse()));
        $type = strtolower($this->getSanitizer()->getString('type'));
        $layoutId = $this->getSanitizer()->getInt('layoutId');
        $mediaId = $this->getSanitizer()->getInt('mediaId');
        $reportFilter = $this->getSanitizer()->getString('reportFilter');
        $groupByFilter = $this->getSanitizer()->getString('groupByFilter');
        
        // What if the fromdt and todt are exactly the same?
        // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
        if ($fromDt == $toDt) {
            $toDt->addDay(1);
        }

        $diff_in_days = $toDt->diffInDays($fromDt);

        // Format param dates
        $fromDt = $this->getDate()->getLocalDate($fromDt);
        $toDt = $this->getDate()->getLocalDate($toDt);

        // Get an array of display id this user has access to.
        $displayIds = [];

        foreach ($this->displayFactory->query() as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0)
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

        // Get some data for a bandwidth chart
        $dbh = $this->store->getConnection();

        $labels = [];
        $countData = [];
        $durationData = [];
        $backgroundColor = [];
        $borderColor = [];

        if (($mediaId != '') || ($layoutId != '')) {

            $select = ' 
        
        SELECT 
            B.week_start,
            B.week_end,
            DATE_FORMAT(STR_TO_DATE(MONTH(start), \'%m\'), \'%b\') AS shortMonth, 
            MONTH(start) as monthNo, 
            YEAR(start) as yearDate, 
            start, 
            CONVERT(SUM(B.actual_diff), SIGNED INTEGER) as Duration, 
            SUM(count) as NumberPlays     
        
        FROM (
                
            SELECT
                 *,
                YEARWEEK(periods.start, 3) AS yearweek,
                DATE_SUB(periods.start, INTERVAL WEEKDAY(periods.start) DAY) as week_start,
                DATE_SUB(DATE_ADD(DATE_SUB(periods.start, INTERVAL WEEKDAY(periods.start) DAY), INTERVAL 1 WEEK), INTERVAL 1 DAY ) as week_end,
                
                GREATEST(periods.start, stat_start) AS actual_start,
                LEAST(periods.end, stat_end) AS actual_end,
                LEAST(stat.duration, UNIX_TIMESTAMP(LEAST(periods.end, stat_end)) - UNIX_TIMESTAMP(GREATEST(periods.start, stat_start))) AS actual_diff
            FROM
            ( 
                SELECT
                
                    
        ';

            if ($reportFilter == '') {
                $range = $diff_in_days;

                // START FROM TODATE THEN DECREASE BY ONE DAY TILL FROMDATE
                $select .= '  
                DATE_FORMAT(
                    DATE_FORMAT(
                        \''.$toDt.'\',
                        \'%Y-%m-%d 00:00:00\') - INTERVAL c.number DAY,
                    \'%Y-%m-%d 00:00:00\') AS start,
                    
                DATE_FORMAT(
                    DATE_ADD(
                        (DATE_FORMAT(
                            \''.$toDt.'\',
                            \'%Y-%m-%d 00:00:00\') - INTERVAL c.number DAY),
                        INTERVAL 1 DAY),
                    \'%Y-%m-%d 00:00:00\') AS end ';

            } elseif (($reportFilter == 'today')) {
                $range = 23;

                // START FROM LASTHOUR OF TODAY THEN DECREASE BY ONE HOUR
                $select .= '  
                DATE_FORMAT(
                    DATE_FORMAT(CURDATE(), \'%Y-%m-%d 23:00:00\') - INTERVAL c.number HOUR, 
                    \'%Y-%m-%d %H:00:00\') AS start,                    
                DATE_FORMAT(
                    DATE_ADD((DATE_FORMAT(CURDATE(), \'%Y-%m-%d 23:00:00\') - INTERVAL c.number HOUR), INTERVAL 1 HOUR), 
                    \'%Y-%m-%d %H:00:00\') AS end ';

            } elseif (($reportFilter == 'yesterday')) {
                $range = 23;

                // START FROM LASTHOUR OF YESTERDAY THEN DECREASE BY ONE HOUR
                $select .= '  
                DATE_FORMAT(
                    DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), \'%Y-%m-%d 23:00:00\') - INTERVAL c.number HOUR, 
                    \'%Y-%m-%d %H:00:00\') AS start,
                DATE_FORMAT(
                    DATE_ADD((DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), \'%Y-%m-%d 23:00:00\') - INTERVAL 2 HOUR), INTERVAL 1 HOUR),
                    \'%Y-%m-%d %H:00:00\') AS end ';

            } elseif (($reportFilter == 'lastweek')) {
                $range = 6;

                // START FROM LASTDAY OF LASTWEEK THEN DECREASE BY ONE DAY
                $select .= '                    
                DATE_FORMAT(
                    DATE_FORMAT(
                        DATE_SUB(
                            DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY),
                            INTERVAL 1 DAY),
                        \'%Y-%m-%d 00:00:00\') - INTERVAL c.number DAY,
                    \'%Y-%m-%d 00:00:00\') AS start,
                    
                DATE_FORMAT(
                    DATE_ADD(
                        (DATE_FORMAT(
                            DATE_SUB(
                                DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY),
                                INTERVAL 1 DAY),
                            \'%Y-%m-%d 00:00:00\') - INTERVAL c.number DAY),
                        INTERVAL 1 DAY),
                    \'%Y-%m-%d 00:00:00\') AS end ';

            } elseif (($reportFilter == 'thisweek')) {
                $range = 6;

                // START FROM LASTDAY OF THISWEEK THEN DECREASE BY ONE DAY
                $select .= '                    
                DATE_FORMAT(
                    DATE_FORMAT(
                        DATE_SUB(
                            DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 WEEK),
                            INTERVAL 1 DAY),
                        \'%Y-%m-%d 00:00:00\') - INTERVAL c.number DAY,
                    \'%Y-%m-%d 00:00:00\') AS start,
                    
                DATE_FORMAT(
                    DATE_ADD(
                        DATE_FORMAT(
                            DATE_SUB( 
                                DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 WEEK),
                                INTERVAL 1 DAY),
                        \'%Y-%m-%d 00:00:00\') - INTERVAL c.number DAY,
                        INTERVAL 1 DAY),
                    \'%Y-%m-%d 00:00:00\') AS end ';

            } elseif (($reportFilter == 'thismonth')) {
                $range = 30;

                // START FROM LASTDAY OF THISMONTH THEN DECREASE BY ONE DAY
                $select .= '                    
                DATE_FORMAT(
                    DATE_FORMAT(
                        LAST_DAY(CURDATE()),
                        \'%Y-%m-%d 00:00:00\') - INTERVAL c.number DAY,
                    \'%Y-%m-%d 00:00:00\') AS start,
                    
                DATE_FORMAT(
                    DATE_ADD(
                        DATE_FORMAT(
                            LAST_DAY(CURDATE()),
                        \'%Y-%m-%d 00:00:00\') - INTERVAL c.number DAY,
                        INTERVAL 1 DAY),
                    \'%Y-%m-%d 00:00:00\') AS end ';

            } elseif (($reportFilter == 'lastmonth')) {
                $range = 30;

                // START FROM LASTDAY OF LASTMONTH THEN DECREASE BY ONE DAY
                $select .= '                    
                DATE_FORMAT(
                    DATE_FORMAT(
                        LAST_DAY(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) ,
                        \'%Y-%m-%d 00:00:00\') - INTERVAL c.number DAY,
                    \'%Y-%m-%d 00:00:00\') AS start,
                    
                DATE_FORMAT(
                    DATE_ADD(
                        DATE_FORMAT(
                            LAST_DAY(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) ,
                        \'%Y-%m-%d 00:00:00\') - INTERVAL c.number DAY,
                        INTERVAL 1 DAY),
                    \'%Y-%m-%d 00:00:00\') AS end ';

            } elseif (($reportFilter == 'thisyear')) {
                $range = 365;

                // START FROM LASTDAY OF THISYEAR THEN DECREASE BY ONE DAY
                $select .= '                    
                DATE_FORMAT(
                    DATE_FORMAT(
                        LAST_DAY(DATE_ADD(NOW(), INTERVAL 12-MONTH(NOW()) MONTH)),
                        \'%Y-%m-%d 00:00:00\') - INTERVAL c.number DAY,
                    \'%Y-%m-%d 00:00:00\') AS start,
        
                DATE_FORMAT(
                    DATE_ADD(
                        DATE_FORMAT(
                            LAST_DAY(DATE_ADD(NOW(), INTERVAL 12-MONTH(NOW()) MONTH)),
                        \'%Y-%m-%d 00:00:00\') - INTERVAL c.number DAY,
                        INTERVAL 1 DAY),
                    \'%Y-%m-%d 00:00:00\') AS end ';
            } elseif (($reportFilter == 'lastyear')) {
                $range = 365;

                // START FROM LASTDAY OF LASTYEAR THEN DECREASE BY ONE DAY
                $select .= '                    
                DATE_FORMAT(
                    DATE_FORMAT(
                        DATE_SUB(LAST_DAY(DATE_ADD(NOW(), INTERVAL 12-MONTH(NOW()) MONTH)), INTERVAL 1 YEAR),
                        \'%Y-%m-%d 00:00:00\') - INTERVAL c.number DAY,
                    \'%Y-%m-%d 00:00:00\') AS start,
                    
                DATE_FORMAT(
                    DATE_ADD(
                        DATE_FORMAT(
                            DATE_SUB(LAST_DAY(DATE_ADD(NOW(), INTERVAL 12-MONTH(NOW()) MONTH)), INTERVAL 1 YEAR),
                        \'%Y-%m-%d 00:00:00\') - INTERVAL c.number DAY,
                        INTERVAL 1 DAY),
                    \'%Y-%m-%d 00:00:00\') AS end ';
            }

            $periods = '            
                FROM               
                (SELECT 
                    singles + tens + hundreds number
                FROM
                    (SELECT 0 singles UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 
                    UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) singles
                    JOIN (SELECT 0 tens UNION ALL SELECT 10 UNION ALL SELECT 20 UNION ALL SELECT 30 UNION ALL SELECT 40 UNION ALL SELECT 50 
                    UNION ALL SELECT 60 UNION ALL SELECT 70 UNION ALL SELECT 80 UNION ALL SELECT 90) tens
                    JOIN (SELECT 0 hundreds UNION ALL SELECT 100 UNION ALL SELECT 200 UNION ALL SELECT 300 UNION ALL SELECT 400 UNION ALL SELECT 500 
                    UNION ALL SELECT 600 UNION ALL SELECT 700 UNION ALL SELECT 800 UNION ALL SELECT 900) hundreds
                    ORDER BY number DESC) c
                    WHERE
                    c.number BETWEEN 0 AND '.$range.'
                ) periods        
            ';


            $body = '
                LEFT OUTER JOIN
                
                (SELECT 
                    layout.Layout,
                    IFNULL(`media`.name, IFNULL(`widgetoption`.value, `widget`.type)) AS Media,
                    stat.mediaId,
                    stat.`start` as stat_start,
                    stat.`end` as stat_end,
                    stat.duration,
                    stat.`count`
                     
                    FROM stat
                    
                    LEFT OUTER JOIN layout
                        ON layout.layoutID = stat.layoutID
                    LEFT OUTER JOIN `widget`
                        ON `widget`.widgetId = stat.widgetId
                    LEFT OUTER JOIN `widgetoption`
                        ON `widgetoption`.widgetId = `widget`.widgetId
                        AND `widgetoption`.type = \'attrib\'
                        AND `widgetoption`.option = \'name\'
                    LEFT OUTER JOIN `media`
                        ON `media`.mediaId = `stat`.mediaId
                    WHERE stat.type <> \'displaydown\' 
            ';

            // Displays
            if (count($displayIds) > 0 ) {
                $body .= ' AND stat.displayID IN (' . implode(',', $displayIds) . ') ';
            }

            // Type filter
            if (($type == 'layout') && ($layoutId != '')) {
                $body .= ' AND `stat`.type = \'layout\' 
                       AND `stat`.layoutId = '.$layoutId;
            } elseif (($type == 'media') && ($mediaId != '')) {
                $body .= ' AND `stat`.type = \'media\' AND IFNULL(`media`.mediaId, 0) <> 0 
                       AND `stat`.mediaId = '.$mediaId;
            }

            $params = [
                'fromDt' => $fromDt,
                'toDt' => $toDt
            ];

            if ($reportFilter == '') {
                $body .= ' AND stat.start < DATE_ADD(:toDt, INTERVAL 1 DAY)  AND stat.end >= :fromDt ';
            } elseif (($reportFilter == 'today')) {
                $body .= ' AND stat.`start` >= CURDATE() AND stat.`start` < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
			AND stat.`end` <= DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), \'%Y-%m-%d 01:00:00\') ';
            } elseif (($reportFilter == 'yesterday')) {
                $body .= ' AND stat.`start` >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND stat.`start` < CURDATE()
			AND stat.`end` <= DATE_FORMAT(CURDATE(), \'%Y-%m-%d 01:00:00\') ';
            }
            // where start is less than last day of the week
            // and end is greater than first day of the week
            elseif (($reportFilter == 'lastweek')) {
                $body .= ' AND stat.`start` < DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)			
            AND stat.`end` >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 WEEK) ';
            }
            // where start is less than last day of the week
            // and end is greater than first day of the week
            elseif (($reportFilter == 'thisweek')) {
                $body .= ' AND stat.`start` < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 WEEK)
            AND stat.`end` >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) ';
            }

            // where start is less than last day of the month + 1 day
            // and end is greater than first day of the month
            // DATE_FORMAT(NOW() ,'%Y-%m-01') as firstdaythismonth,
            // LAST_DAY(CURDATE()) as lastdaythismonth,
            elseif (($reportFilter == 'thismonth')) {
                $body .= ' AND stat.`start` < DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY)
            AND stat.`end` >= DATE_FORMAT(NOW() ,\'%Y-%m-01\') ';
            }

            // where start is less than last day of the month + 1 day
            // and end is greater than first day of the month
            // DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH) ,'%Y-%m-01') as firstdaylastmonth,
            // LAST_DAY(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) as lastdaylastmonth,
            elseif (($reportFilter == 'lastmonth')) {
                $body .= ' AND stat.`start` <  DATE_ADD(LAST_DAY(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), INTERVAL 1 DAY )
            AND stat.`end` >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH) ,\'%Y-%m-01\') ';
            }
            // where start is less than last day of the year + 1 day
            // and end is greater than first day of the year
            // MAKEDATE(YEAR(NOW()),1) as firstdaythisyear,
            // LAST_DAY(DATE_ADD(NOW(), INTERVAL 12-MONTH(NOW()) MONTH)) as lastdaythisyear
            elseif (($reportFilter == 'thisyear')) {
                $body .= ' AND stat.`start` < DATE_ADD(LAST_DAY(DATE_ADD(NOW(), INTERVAL 12-MONTH(NOW()) MONTH)), INTERVAL 1 DAY)
            AND stat.`end` >= MAKEDATE(YEAR(NOW()),1) ';
            }

            // where start is less than last day of the year + 1 day
            // and end is greater than first day of the year
            // MAKEDATE(YEAR(NOW() - INTERVAL 1 YEAR),1) as firstdaylastyear,
            // DATE_SUB(LAST_DAY(DATE_ADD(NOW(), INTERVAL 12-MONTH(NOW()) MONTH)), INTERVAL 1 YEAR) as lastdaylastyear,
            elseif (($reportFilter == 'lastyear')) {
                $body .= ' AND stat.`start` < DATE_ADD(DATE_SUB(LAST_DAY(DATE_ADD(NOW(), INTERVAL 12-MONTH(NOW()) MONTH)), INTERVAL 1 YEAR), INTERVAL 1 DAY)
            AND stat.`end` >= MAKEDATE(YEAR(NOW() - INTERVAL 1 YEAR),1) ';
            }

            $body .= ' ) stat               
            ON stat_start < periods.`end`
            AND stat_end > periods.`start`
            ';

            if ($reportFilter == '') {
                $body .= ' WHERE periods.`start` >= :fromDt
            AND periods.`end` <= DATE_ADD(:toDt, INTERVAL 1 DAY) ';
            }
            // where periods start is greater than or equal today and
            // periods end is less than or equal today + 1 day i.e. nextday
            elseif (($reportFilter == 'today')) {
                $body .= ' WHERE periods.`start` >= CURDATE()
            AND periods.`end` <= DATE_ADD(CURDATE(), INTERVAL 1 DAY) ';
            }
            // where periods start is greater than or equal yesterday and
            // periods end is less than or equal today
            elseif (($reportFilter == 'yesterday')) {
                $body .= ' WHERE periods.`start` >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
	        AND periods.`end` <= CURDATE() ';
            }
            // DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 WEEK) as lastweekmonday,
            // DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 DAY) lastweeklastday,
            // where periods start is greater than or equal lastweekmonday and
            // periods end is less than or equal lastdaylastweek + 1 day
            elseif (($reportFilter == 'lastweek')) {
                $body .= ' WHERE periods.`start` >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 WEEK)
            AND periods.`end` <= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) '; //??
            }
            // DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) as thisweekmonday,
            // DATE_SUB(DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 WEEK), INTERVAL 1 DAY ) as thisweeklastday,
            // where periods start is greater than or equal thisweekmonday and
            // periods end is less than or equal lastdaylastweek + 1 day
            elseif (($reportFilter == 'thisweek')) {
                $body .= ' WHERE periods.`start` >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
            AND periods.`end` <= DATE_SUB(DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 WEEK), INTERVAL 1 DAY ) ';
            }
            // where periods start is greater than or equal firstdaythismonth and
            // periods end is less than lastdaythismonth + 1 day
            elseif (($reportFilter == 'thismonth')) {
                $body .= ' 
                WHERE
                    periods.`start` >= DATE_FORMAT(NOW() ,\'%Y-%m-01\')
                    AND periods.`end` <=  DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY) ';
            }
            // where periods start is greater than firstdaylastmonth and
            // periods end is less than lastdaylastmonth + 1 day
            elseif (($reportFilter == 'lastmonth')) {
                $body .= '  
                WHERE    
                    periods.`start` >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH) ,\'%Y-%m-01\')
                    AND periods.`end` <= DATE_ADD(LAST_DAY(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), INTERVAL 1 DAY) ';
            }
            // where periods start is greater than or equal firstdaythisyear and
            // periods end is less than lastdaythisyear + 1 day
            elseif (($reportFilter == 'thisyear')) {
                $body .= ' 
                WHERE
                    periods.`start` >= MAKEDATE(YEAR(NOW()),1)
                    AND periods.`end` <=  DATE_ADD(LAST_DAY(DATE_ADD(NOW(), INTERVAL 12-MONTH(NOW()) MONTH)), INTERVAL 1 DAY)';
            }
            // where periods start is greater than firstdaylastyear and
            // periods end is less than lastdaylastyear + 1 day
            elseif (($reportFilter == 'lastyear')) {
                $body .= '  
                WHERE    
                    periods.`start` >= MAKEDATE(YEAR(NOW() - INTERVAL 1 YEAR),1)
                    AND periods.`end` <= DATE_ADD(DATE_SUB(LAST_DAY(DATE_ADD(NOW(), INTERVAL 12-MONTH(NOW()) MONTH)), INTERVAL 1 YEAR), INTERVAL 1 DAY) ';
            }

            $body .= '  
            ORDER BY periods.`start`, stat_start
	        )B ';


            if ($groupByFilter == 'byweek') {
                $body .= '  
                    GROUP BY yearweek ';
            } elseif ($groupByFilter == 'bymonth') {

                if (($reportFilter == 'thisyear') || ($reportFilter == 'lastyear')) {
                    $body .= '  
                        GROUP BY shortMonth
                        ORDER BY monthNo ';
                } else {
                    $body .= '  
                        GROUP BY yearDate, monthNo ';
                }

            } else {
                $body .= '  
                    GROUP BY B.start ';
            }

            /*Execute sql statement*/
            $sql = $select .$periods. $body;
            $this->getLog()->debug($sql);

            $sth = $dbh->prepare($sql);
            $sth->execute($params);

            // Get the results
            $results = $sth->fetchAll();

            foreach ($results as $row) {
                // Label
                $tsLabel = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s');

                if ($reportFilter == '') {
                    $tsLabel = $tsLabel->format('Y-m-d'); // as dates. by day (default)

                    if ($groupByFilter == 'byweek') {
                        $week_end = $this->getDate()->parse($row['week_end'], 'Y-m-d H:i:s')->format('Y-m-d');
                        if ($week_end >= $toDt){
                            $week_end = $this->getDate()->parse($toDt, 'Y-m-d H:i:s')->format('Y-m-d');
                        }
                        $tsLabel .= ' - ' . $week_end;
                    } elseif ($groupByFilter == 'bymonth') {
                        $tsLabel = $row['shortMonth']. ' '.$row['yearDate'];

                    }

                } elseif (($reportFilter == 'today') || ($reportFilter == 'yesterday')) {
                    $tsLabel = $tsLabel->format('g:i A'); // hourly format (default)

                } elseif(($reportFilter == 'lastweek') || ($reportFilter == 'thisweek') ) {
                    $tsLabel = $tsLabel->format('D'); // Mon, Tues, etc.  by day (default)

                } elseif (($reportFilter == 'thismonth') || ($reportFilter == 'lastmonth')) {
                    $tsLabel = $tsLabel->format('Y-m-d'); // as dates. by day (default)

                    if ($groupByFilter == 'byweek') {
                        $week_end = $this->getDate()->parse($row['week_end'], 'Y-m-d H:i:s')->format('Y-m-d');

                        $start_m = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->format('M');
                        $week_end_m = $this->getDate()->parse($row['week_end'], 'Y-m-d H:i:s')->format('M');

                        if ($week_end_m != $start_m){
                            $week_end = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->endOfMonth()->format('Y-m-d');
                        }
                        $tsLabel .= ' - ' . $week_end;
                    }

                }  elseif (($reportFilter == 'thisyear') || ($reportFilter == 'lastyear')) {
                    $tsLabel = $row['shortMonth']; // Jan, Feb, etc.  by month (default)

                    if ($groupByFilter == 'byday') {
                        $tsLabel = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->format('Y-m-d');

                    } elseif ($groupByFilter == 'byweek') {
                        $week_start = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->format('M d');
                        $week_end = $this->getDate()->parse($row['week_end'], 'Y-m-d H:i:s')->format('M d');

                        $week_start_y = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->format('Y');
                        $week_end_y = $this->getDate()->parse($row['week_end'], 'Y-m-d H:i:s')->format('Y');

                        if ($week_end_y != $week_start_y){
                            $week_end = $this->getDate()->parse($row['start'], 'Y-m-d H:i:s')->endOfYear()->format('M-d');
                        }
                        $tsLabel = $week_start .' - ' . $week_end;
                    }

                }

                // Chart labels in xaxis
                $labels[] = $tsLabel;

                $backgroundColor[] = 'rgb(95, 186, 218, 0.6)';
                $borderColor[] = 'rgb(240,93,41, 0.8)';

                $count = $this->getSanitizer()->int($row['NumberPlays']);
                $countData[] = ($count == '') ? 0 : $count;

                $duration = $this->getSanitizer()->int($row['Duration']);
                $durationData[] = ($duration == '') ? 0 : $duration;
            }

        }

        // Return data to build chart
        $this->getState()->extra = [
            'labels' => $labels,
            'countData' => $countData,
            'durationData' => $durationData,
            'backgroundColor' => $backgroundColor,
            'borderColor' => $borderColor,
        ];

    }

    /**
     * Stats page
     */
    function displayLibraryPage()
    {
        $this->getState()->template = 'stats-library-page';
        $data = [];

        // Set up some suffixes
        $suffixes = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');

        // Widget for the library usage pie chart
        try {
            if ($this->getUser()->libraryQuota != 0) {
                $libraryLimit = $this->getUser()->libraryQuota * 1024;
            } else {
                $libraryLimit = $this->getConfig()->getSetting('LIBRARY_SIZE_LIMIT_KB') * 1024;
            }

            // Library Size in Bytes
            $params = [];
            $sql = 'SELECT IFNULL(SUM(FileSize), 0) AS SumSize, type FROM `media` WHERE 1 = 1 ';
            $this->mediaFactory->viewPermissionSql('Xibo\Entity\Media', $sql, $params, '`media`.mediaId', '`media`.userId');
            $sql .= ' GROUP BY type ';

            $sth = $this->store->getConnection()->prepare($sql);
            $sth->execute($params);

            $results = $sth->fetchAll();

            // Do we base the units on the maximum size or the library limit
            $maxSize = 0;
            if ($libraryLimit > 0) {
                $maxSize = $libraryLimit;
            } else {
                // Find the maximum sized chunk of the items in the library
                foreach ($results as $library) {
                    $maxSize = ($library['SumSize'] > $maxSize) ? $library['SumSize'] : $maxSize;
                }
            }

            // Decide what our units are going to be, based on the size
            $base = ($maxSize == 0) ? 0 : floor(log($maxSize) / log(1024));

            $libraryUsage = [];
            $libraryLabels = [];
            $totalSize = 0;
            foreach ($results as $library) {
                $libraryUsage[] = round((double)$library['SumSize'] / (pow(1024, $base)), 2);
                $libraryLabels[] = ucfirst($library['type']) . ' ' . $suffixes[$base];

                $totalSize = $totalSize + $library['SumSize'];
            }

            // Do we need to add the library remaining?
            if ($libraryLimit > 0) {
                $remaining = round(($libraryLimit - $totalSize) / (pow(1024, $base)), 2);

                $libraryUsage[] = $remaining;
                $libraryLabels[] = __('Free') . ' ' . $suffixes[$base];
            }

            // What if we are empty?
            if (count($results) == 0 && $libraryLimit <= 0) {
                $libraryUsage[] = 0;
                $libraryLabels[] = __('Empty');
            }

            $data['libraryLimitSet'] = ($libraryLimit > 0);
            $data['libraryLimit'] = (round((double)$libraryLimit / (pow(1024, $base)), 2)) . ' ' . $suffixes[$base];
            $data['librarySize'] = ByteFormatter::format($totalSize, 1);
            $data['librarySuffix'] = $suffixes[$base];
            $data['libraryWidgetLabels'] = json_encode($libraryLabels);
            $data['libraryWidgetData'] = json_encode($libraryUsage);

        } catch (\Exception $exception) {
            $this->getLog()->error('Error rendering the library stats page widget');
        }

        $data['users'] = $this->userFactory->query();
        $data['groups'] = $this->userGroupFactory->query();

        $this->getState()->setData($data);
    }

    public function libraryUsageGrid()
    {
        $params = [];
        $select = '
            SELECT `user`.userId,
                `user`.userName,
                IFNULL(SUM(`media`.FileSize), 0) AS bytesUsed,
                COUNT(`media`.mediaId) AS numFiles
        ';
        $body = '     
              FROM `user`
                LEFT OUTER JOIN `media`
                ON `media`.userID = `user`.UserID
              WHERE 1 = 1
        ';

        // Restrict on the users we have permission to see
        // Normal users can only see themselves
        $permissions = '';
        if ($this->getUser()->userTypeId == 3) {
            $permissions .= ' AND user.userId = :currentUserId ';
            $filterBy['currentUserId'] = $this->getUser()->userId;
        }
        // Group admins can only see users from their groups.
        else if ($this->getUser()->userTypeId == 2) {
            $permissions .= '
                AND user.userId IN (
                    SELECT `otherUserLinks`.userId
                      FROM `lkusergroup`
                        INNER JOIN `group`
                        ON `group`.groupId = `lkusergroup`.groupId
                            AND `group`.isUserSpecific = 0
                        INNER JOIN `lkusergroup` `otherUserLinks`
                        ON `otherUserLinks`.groupId = `group`.groupId
                     WHERE `lkusergroup`.userId = :currentUserId
                )
            ';
            $params['currentUserId'] = $this->getUser()->userId;
        }

        // Filter by userId
        if ($this->getSanitizer()->getInt('userId') !== null) {
            $body .= ' AND user.userId = :userId ';
            $params['userId'] = $this->getSanitizer()->getInt('userId');
        }

        // Filter by groupId
        if ($this->getSanitizer()->getInt('groupId') !== null) {
            $body .= ' AND user.userId IN (SELECT userId FROM `lkusergroup` WHERE groupId = :groupId) ';
            $params['groupId'] = $this->getSanitizer()->getInt('groupId');
        }

        $body .= $permissions;
        $body .= '            
            GROUP BY `user`.userId,
              `user`.userName
        ';


        // Sorting?
        $filterBy = $this->gridRenderFilter();
        $sortOrder = $this->gridRenderSort();

        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $select . $body . $order . $limit;
        $rows = [];

        foreach ($this->store->select($sql, $params) as $row) {
            $entry = [];

            $entry['userId'] = $this->getSanitizer()->int($row['userId']);
            $entry['userName'] = $this->getSanitizer()->string($row['userName']);
            $entry['bytesUsed'] = $this->getSanitizer()->int($row['bytesUsed']);
            $entry['bytesUsedFormatted'] = ByteFormatter::format($this->getSanitizer()->int($row['bytesUsed']), 2);
            $entry['numFiles'] = $this->getSanitizer()->int($row['numFiles']);

            $rows[] = $entry;
        }

        // Paging
        if ($limit != '' && count($rows) > 0) {
            $results = $this->store->select('SELECT COUNT(*) AS total FROM `user` ' . $permissions, $params);
            $this->getState()->recordsTotal = intval($results[0]['total']);
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($rows);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function timeDisconnectedGrid()
    {
        $fromDt = $this->getSanitizer()->getDate('fromDt', $this->getSanitizer()->getDate('availabilityFromDt'));
        $toDt = $this->getSanitizer()->getDate('toDt', $this->getSanitizer()->getDate('availabilityToDt'));

        $displayId = $this->getSanitizer()->getInt('displayId');
        $displayGroupId = $this->getSanitizer()->getInt('displayGroupId');
        $tags = $this->getSanitizer()->getString('tags');
        $onlyLoggedIn = $this->getSanitizer()->getCheckbox('onlyLoggedIn') == 1;

        // What if the fromdt and todt are exactly the same?
        // in this case assume an entire day from midnight on the fromdt to midnight on the todt (i.e. add a day to the todt)
        if ($fromDt == $toDt) {
            $toDt->addDay(1);
        }

        // Get an array of display id this user has access to.
        $displayIds = [];

        foreach ($this->displayFactory->query() as $display) {
            $displayIds[] = $display->displayId;
        }

        if (count($displayIds) <= 0)
            throw new InvalidArgumentException(__('No displays with View permissions'), 'displays');

        // Get an array of display groups this user has access to
        $displayGroupIds = [];

        foreach ($this->displayGroupFactory->query(null, ['isDisplaySpecific' => -1]) as $displayGroup) {
            $displayGroupIds[] = $displayGroup->displayGroupId;
        }

        if (count($displayGroupIds) <= 0)
            throw new InvalidArgumentException(__('No display groups with View permissions'), 'displayGroup');

        $params = array(
            'start' => $fromDt->format('U'),
            'end' => $toDt->format('U')
        );

        $SQL = '
            SELECT display.display, display.displayId,
            SUM(LEAST(IFNULL(`end`, :end), :end) - GREATEST(`start`, :start)) AS duration,
            :end - :start as filter ';

        if ($tags != '') {
            $SQL .= ', (SELECT GROUP_CONCAT(DISTINCT tag)
              FROM tag
                INNER JOIN lktagdisplaygroup
                  ON lktagdisplaygroup.tagId = tag.tagId
                WHERE lktagdisplaygroup.displayGroupId = displaygroup.DisplayGroupID
                GROUP BY lktagdisplaygroup.displayGroupId) AS tags ';
        }


        $SQL .= 'FROM `displayevent`
                INNER JOIN `display`
                ON display.displayId = `displayevent`.displayId ';

        if ($displayGroupId != 0) {
            $SQL .= 'INNER JOIN `lkdisplaydg`
                        ON lkdisplaydg.DisplayID = display.displayid ';
        }

        if ($tags != '') {
            $SQL .= 'INNER JOIN `lkdisplaydg`
                        ON lkdisplaydg.DisplayID = display.displayid
                     INNER JOIN `displaygroup`
                        ON displaygroup.displaygroupId = lkdisplaydg.displaygroupId
                         AND `displaygroup`.isDisplaySpecific = 1 ';
        }

        $SQL .= 'WHERE `start` <= :end
                  AND IFNULL(`end`, :end) >= :start
                  AND :end <= UNIX_TIMESTAMP(NOW())
                  AND display.displayId IN (' . implode(',', $displayIds) . ') ';

        if ($displayGroupId != 0) {
            $SQL .= '
                     AND lkdisplaydg.displaygroupid = :displayGroupId ';
            $params['displayGroupId'] = $displayGroupId;
        }

        if ($tags != '') {
            if (trim($tags) === '--no-tag') {
                $SQL .= ' AND `displaygroup`.displaygroupId NOT IN (
                    SELECT `lktagdisplaygroup`.displaygroupId
                     FROM tag
                        INNER JOIN `lktagdisplaygroup`
                        ON `lktagdisplaygroup`.tagId = tag.tagId
                    )
                ';
            } else {
                $operator = $this->getSanitizer()->getCheckbox('exactTags') == 1 ? '=' : 'LIKE';

                $SQL .= " AND `displaygroup`.displaygroupId IN (
                SELECT `lktagdisplaygroup`.displaygroupId
                  FROM tag
                    INNER JOIN `lktagdisplaygroup`
                    ON `lktagdisplaygroup`.tagId = tag.tagId
                ";
                $i = 0;

                foreach (explode(',', $tags) as $tag) {
                    $i++;

                    if ($i == 1)
                        $SQL .= ' WHERE `tag` ' . $operator . ' :tags' . $i;
                    else
                        $SQL .= ' OR `tag` ' . $operator . ' :tags' . $i;

                    if ($operator === '=')
                        $params['tags' . $i] = $tag;
                    else
                        $params['tags' . $i] = '%' . $tag . '%';
                }

                $SQL .= " ) ";
            }
        }

        if ($displayId != 0) {
            $SQL .= ' AND display.displayId = :displayId ';
            $params['displayId'] = $displayId;
        }

        if ($onlyLoggedIn) {
            $SQL .= ' AND `display`.loggedIn = 1 ';
        }

        $SQL .= '
            GROUP BY display.display
        ';

        // Sorting?
        $filterBy = $this->gridRenderFilter();
        $sortOrder = $this->gridRenderSort();

        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';

        // Paging
        if ($filterBy !== null && $this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        $sql = $SQL . $order . $limit;
        $maxDuration = 0;
        $rows = [];

        foreach ($this->store->select($sql, $params) as $row) {
            $maxDuration = $maxDuration + $this->getSanitizer()->double($row['duration']);
        }

        if ($maxDuration > 86400) {
            $postUnits = __('Days');
            $divisor = 86400;
        }
        else if ($maxDuration > 3600) {
            $postUnits = __('Hours');
            $divisor = 3600;
        }
        else {
            $postUnits = __('Minutes');
            $divisor = 60;
        }

        foreach ($this->store->select($sql, $params) as $row) {
            $entry = [];
            $entry['displayId'] = $this->getSanitizer()->int(($row['displayId']));
            $entry['display'] = $this->getSanitizer()->string(($row['display']));
            $entry['timeDisconnected'] =  round($this->getSanitizer()->double($row['duration']) / $divisor, 2);
            $entry['timeConnected'] =  round($this->getSanitizer()->double($row['filter'] / $divisor) - $entry['timeDisconnected'], 2);
            $entry['postUnits'] = $postUnits;

            $rows[] = $entry;
        }

        $this->getState()->template = 'grid';
        $this->getState()->setData($rows);
    }
}
