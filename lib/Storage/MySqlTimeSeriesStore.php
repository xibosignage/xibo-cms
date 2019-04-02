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

use Xibo\Factory\LayoutFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;

/**
 * Class MySqlTimeSeriesStore
 * @package Xibo\Storage
 */
class MySqlTimeSeriesStore implements TimeSeriesStoreInterface
{
    /** @var StorageServiceInterface */
    private $store;

    /** @var LogServiceInterface */
    private $log;

    /** @var DateServiceInterface */
    private $dateService;

    /** @var  LayoutFactory */
    protected $layoutFactory;

    /**
     * @inheritdoc
     */
    public function __construct($config = null)
    {

    }

    /**
     * @inheritdoc
     */
    public function setDependencies($log, $date, $layoutFactory = null, $mediaFactory = null, $widgetFactory = null, $displayFactory = null)
    {
        $this->log = $log;
        $this->dateService = $date;
        $this->layoutFactory = $layoutFactory;
        return $this;
    }

    /** @inheritdoc */
    public function addStat($statData)
    {
        $sql = 'INSERT INTO `stat` (`type`, statDate, start, `end`, scheduleID, displayID, campaignID, layoutID, mediaID, Tag, `widgetId`, duration, `count`) VALUES ';
        $placeHolders = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        $sql = $sql . implode(', ', array_fill(1, count($statData), $placeHolders));

        // Flatten the array
        $data = [];
        foreach ($statData as $stat) {
            foreach ($stat as $field) {
                $data[] = $field;
            }
        }

        $this->store->isolated($sql, $data);

    }

    /** @inheritdoc */
    public function getStatsReport($fromDt, $toDt, $displayIds, $layoutIds, $mediaIds, $type, $columns, $tags, $tagsType, $exactTags, $start = null, $length = null)
    {

        // Media on Layouts Ran
        $select = '
          SELECT stat.type,
              display.Display,
              IFNULL(layout.Layout, 
              (SELECT `layout` FROM `layout` WHERE layoutId = (SELECT  MAX(layoutId) FROM  layouthistory  WHERE
                            campaignId = stat.campaignId))) AS Layout,
              IFNULL(`media`.name, IFNULL(`widgetoption`.value, `widget`.type)) AS Media,
              SUM(stat.count) AS NumberPlays,
              SUM(stat.duration) AS Duration,
              MIN(start) AS MinStart,
              MAX(end) AS MaxEnd,
              stat.tag,
              stat.layoutId,
              stat.mediaId,
              stat.widgetId,
              stat.displayId
        ';

        $body = '
            FROM stat
              LEFT OUTER JOIN display
              ON stat.DisplayID = display.DisplayID
              LEFT OUTER JOIN layouthistory 
              ON layouthistory.LayoutID = stat.LayoutID              
              LEFT OUTER JOIN layout
              ON layout.LayoutID = layouthistory.layoutId
              LEFT OUTER JOIN `widget`
              ON `widget`.widgetId = stat.widgetId
              LEFT OUTER JOIN `widgetoption`
              ON `widgetoption`.widgetId = `widget`.widgetId
                AND `widgetoption`.type = \'attrib\'
                AND `widgetoption`.option = \'name\'
              LEFT OUTER JOIN `media`
              ON `media`.mediaId = `stat`.mediaId
              ';

        if ($tags != '' ) {
            if ($tagsType === 'dg') {
                $body .= 'INNER JOIN `lkdisplaydg`
                        ON lkdisplaydg.DisplayID = display.displayid
                     INNER JOIN `displaygroup`
                        ON displaygroup.displaygroupId = lkdisplaydg.displaygroupId
                         AND `displaygroup`.isDisplaySpecific = 1 ';
            }
        }

        $body .= ' WHERE stat.type <> \'displaydown\'
                AND stat.end > :fromDt
                AND stat.start <= DATE_ADD(:toDt, INTERVAL 1 DAY)
        ';

        // Filter by display
        if (count($displayIds) > 0 ) {
            $body .= ' AND stat.displayID IN (' . implode(',', $displayIds) . ') ';
        }

        $params = [
            'fromDt' => $fromDt,
            'toDt' => $toDt
        ];

        if ($tags != '') {
            if (trim($tags) === '--no-tag') {
                if ($tagsType === 'dg') {
                    $body .= ' AND `displaygroup`.displaygroupId NOT IN (
                    SELECT `lktagdisplaygroup`.displaygroupId
                     FROM tag
                        INNER JOIN `lktagdisplaygroup`
                        ON `lktagdisplaygroup`.tagId = tag.tagId
                        )
                        ';
                }
                if ($tagsType === 'layout') {
                    $body .= ' AND `layout`.layoutId NOT IN (
                    SELECT `lktaglayout`.layoutId
                     FROM tag
                        INNER JOIN `lktaglayout`
                        ON `lktaglayout`.tagId = tag.tagId
                        )
                        ';
                }
                if ($tagsType === 'media') {
                    $body .= ' AND `media`.mediaId NOT IN (
                    SELECT `lktagmedia`.mediaId
                     FROM tag
                        INNER JOIN `lktagmedia`
                        ON `lktagmedia`.tagId = tag.tagId
                        )
                        ';
                }
            } else {
                $operator = $exactTags == 1 ? '=' : 'LIKE';
                if ($tagsType === 'dg') {
                    $body .= " AND `displaygroup`.displaygroupId IN (
                        SELECT `lktagdisplaygroup`.displaygroupId
                          FROM tag
                            INNER JOIN `lktagdisplaygroup`
                            ON `lktagdisplaygroup`.tagId = tag.tagId
                        ";
                }
                if ($tagsType === 'layout') {
                    $body .= " AND `layout`.layoutId IN (
                        SELECT `lktaglayout`.layoutId
                          FROM tag
                            INNER JOIN `lktaglayout`
                            ON `lktaglayout`.tagId = tag.tagId
                    ";
                }
                if ($tagsType === 'media') {
                    $body .= " AND `media`.mediaId IN (
                        SELECT `lktagmedia`.mediaId
                          FROM tag
                            INNER JOIN `lktagmedia`
                            ON `lktagmedia`.tagId = tag.tagId
                    ";
                }
                $i = 0;
                foreach (explode(',', $tags) as $tag) {
                    $i++;
                    if ($i == 1)
                        $body .= ' WHERE `tag` ' . $operator . ' :tags' . $i;
                    else
                        $body .= ' OR `tag` ' . $operator . ' :tags' . $i;
                    if ($operator === '=')
                        $params['tags' . $i] = $tag;
                    else
                        $params['tags' . $i] = '%' . $tag . '%';
                }
                $body .= " ) ";
            }
        }

        // Type filter
        if ($type == 'layout') {
            $body .= ' AND `stat`.type = \'layout\' ';
        } else if ($type == 'media') {
            $body .= ' AND `stat`.type = \'media\' AND IFNULL(`media`.mediaId, 0) <> 0 ';
        } else if ($type == 'widget') {
            $body .= ' AND `stat`.type = \'widget\' AND IFNULL(`widget`.widgetId, 0) <> 0 ';
        } else if ($type == 'event') {
            $body .= ' AND `stat`.type = \'event\' ';
        }

        // Layout Filter
        if (count($layoutIds) != 0) {

            $layoutSql = '';
            $i = 0;
            foreach ($layoutIds as $layoutId) {
                $i++;
                $layoutSql .= ':layoutId_' . $i . ',';
                $params['layoutId_' . $i] = $layoutId;
            }

            $body .= '  AND `stat`.campaignId IN (SELECT campaignId from layouthistory where layoutId IN (' . trim($layoutSql, ',') . '))';
        }

        // Media Filter
        if (count($mediaIds) != 0) {

            $mediaSql = '';
            $i = 0;
            foreach ($mediaIds as $mediaId) {
                $i++;
                $mediaSql .= ':mediaId_' . $i . ',';
                $params['mediaId_' . $i] = $mediaId;
            }

            $body .= ' AND `media`.mediaId IN (' . trim($mediaSql, ',') . ')';
        }

        $body .= 'GROUP BY stat.type, display.Display, stat.displayId, stat.campaignId, IFNULL(stat.mediaId, stat.widgetId), 
        IFNULL(`media`.name, IFNULL(`widgetoption`.value, `widget`.type)) ';

        $order = '';
        if ($columns != null)
            $order = 'ORDER BY ' . implode(',', $columns);

        $limit= '';
        if ($length != null)
            $limit = ' LIMIT ' . $start . ', ' . $length;

        /*Execute sql statement*/
        $sql = $select . $body . $order . $limit;

        $rows = [];
        foreach ($this->store->select($sql, $params) as $row) {
            $entry = [];

            $entry['type'] = $row['type'];
            $entry['displayId'] = $row['displayId'];
            $entry['display'] = $row['Display'];
            $entry['layout'] = $row['Layout'];
            $entry['media'] = $row['Media'];
            $entry['numberPlays'] = $row['NumberPlays'];
            $entry['duration'] = $row['Duration'];
            $entry['minStart'] = $row['MinStart'];
            $entry['maxEnd'] = $row['MaxEnd'];
            $entry['layoutId'] = $row['layoutId'];
            $entry['widgetId'] = $row['widgetId'];
            $entry['mediaId'] = $row['mediaId'];
            $entry['tag'] = $row['tag'];

            $rows[] = $entry;
        }

        // Paging
        $results = [];
        if ($limit != '' && count($rows) > 0) {
            $results = $this->store->select('
              SELECT COUNT(*) AS total FROM (SELECT stat.type, display.Display, layout.Layout, IFNULL(`media`.name, IFNULL(`widgetoption`.value, `widget`.type)) ' . $body . ') total
            ', $params);
        }

        return [
            'statData' => $rows,
            'count' => count($rows),
            'totalStats' => isset($results[0]['total']) ? $results[0]['total'] : 0
        ];

    }

    /** @inheritdoc */
    public function getEarliestDate()
    {
        $earliestDate = $this->store->select('SELECT MIN(statDate) AS minDate FROM `stat`', []);

        return $earliestDate;
    }

    /** @inheritdoc */
    public function getStats($fromDt, $toDt, $displayIds = null)
    {
        $sql = '
        SELECT stat.*, display.Display as display, layout.Layout as layout, media.Name AS media
          FROM stat
            LEFT OUTER JOIN display
            ON stat.DisplayID = display.DisplayID
            LEFT OUTER JOIN layout
            ON layout.LayoutID = stat.LayoutID
            LEFT OUTER JOIN media
            ON media.mediaID = stat.mediaID
         WHERE 1 = 1
          AND stat.end > :fromDt
          AND stat.start <= :toDt
          
        ';

        if (count($displayIds) > 0) {
            $sql .= ' AND stat.displayID IN (' . implode(',', $displayIds) . ')';
        }

        $params = [
            'fromDt' => $fromDt,
            'toDt' => $toDt
        ];

        $sql .= " ORDER BY stat.start ";

        // Run our query using a connection object (to save memory)
        $connection = $this->store->getConnection();
        $statement = $connection->prepare($sql);

        // Execute
        $statement->execute($params);
        $this->log->sql($sql, $params);

        $result = new TimeSeriesMySQLResults($statement);

        return $result;

    }

    /** @inheritdoc */
    public function deleteStats($maxage, $fromDt = null, $options = [])
    {
        try {
            $i = 0;
            $rows = 1;
            $options = array_merge([
                'maxAttempts' => 10,
                'statsDeleteSleep' => 3,
                'limit' => 10000,
            ], $options);

            if ($fromDt != null) {
                $delete = $this->store->getConnection()
                    ->prepare('DELETE FROM `stat` WHERE stat.statDate >= :fromDt AND stat.statDate < :toDt ORDER BY statId LIMIT :limit');

                $delete->bindParam(':fromDt', $fromDt, \PDO::PARAM_STR);
                $delete->bindParam(':toDt', $maxage, \PDO::PARAM_STR);
                $delete->bindParam(':limit', $options['limit'], \PDO::PARAM_INT);
            } else {
                $delete = $this->store->getConnection()
                    ->prepare('DELETE FROM `stat` WHERE stat.statDate < :maxage LIMIT :limit');
                $delete->bindParam(':maxage', $maxage, \PDO::PARAM_STR);
                $delete->bindParam(':limit', $options['limit'], \PDO::PARAM_INT);
            }

            $count = 0;
            while ($rows > 0) {

                $i++;

                // Run the delete
                $delete->execute();

                // Find out how many rows we've deleted
                $rows = $delete->rowCount();
                $count += $rows;

                // We shouldn't be in a transaction, but commit anyway just in case
                $this->store->commitIfNecessary();

                // Give SQL time to recover
                if ($rows > 0) {
                    $this->log->debug('Stats delete effected ' . $rows . ' rows, sleeping.');
                    sleep($options['statsDeleteSleep']);
                }

                // Break if we've exceeded the maximum attempts.
                if ($i >= $options['maxAttempts'])
                    break;
            }

            $this->log->debug('Deleted Stats back to ' . $maxage . ' in ' . $i . ' attempts');

            return $count;
        }
        catch (\PDOException $e) {
            $this->log->error($e->getMessage());
            throw new \RuntimeException('Stats cannot be deleted.');
        }
    }

    /** @inheritdoc */
    public function getDailySummaryReport($displayIds, $diffInDays, $type, $layoutId, $mediaId, $reportFilter, $groupByFilter = null, $fromDt = null, $toDt = null)
    {
        if ( (($type == 'media') && ($mediaId != '')) ||
            (($type == 'layout') && ($layoutId != '')) ) {

            $fromDt = $this->dateService->parse($fromDt)->startOfDay()->format('Y-m-d H:i:s');;
            $toDt = $this->dateService->parse($toDt)->startOfDay()->addDay()->format('Y-m-d H:i:s'); // added a day

            $yesterday = $this->dateService->parse()->startOfDay()->subDay()->format('Y-m-d H:i:s');
            $today = $this->dateService->parse()->startOfDay()->format('Y-m-d H:i:s');
            $nextday = $this->dateService->parse()->startOfDay()->addDay()->format('Y-m-d H:i:s');

            $firstdaythisweek = $this->dateService->parse()->startOfWeek()->format('Y-m-d H:i:s');
            $lastdaythisweek = $this->dateService->parse()->endOfWeek()->addSecond()->format('Y-m-d H:i:s'); // added a second

            $firstdaylastweek = $this->dateService->parse()->startOfWeek()->subWeek()->format('Y-m-d H:i:s');
            $lastdaylastweek = $this->dateService->parse()->endOfWeek()->addSecond()->subWeek()->format('Y-m-d H:i:s'); // added a second

            $firstdaythismonth = $this->dateService->parse()->startOfMonth()->format('Y-m-d H:i:s');
            $lastdaythismonth = $this->dateService->parse()->endOfMonth()->addSecond()->format('Y-m-d H:i:s'); // added a second

            $firstdaylastmonth = $this->dateService->parse()->startOfMonth()->subMonth()->format('Y-m-d H:i:s');
            $lastdaylastmonth = $this->dateService->parse()->endOfMonth()->addSecond()->subMonth()->format('Y-m-d H:i:s');

            $firstdaythisyear = $this->dateService->parse()->startOfYear()->format('Y-m-d H:i:s');
            $lastdaythisyear = $this->dateService->parse()->endOfYear()->addSecond()->format('Y-m-d H:i:s');

            $firstdaylastyear = $this->dateService->parse()->startOfYear()->subYear()->format('Y-m-d H:i:s');
            $lastdaylastyear = $this->dateService->parse()->endOfYear()->addSecond()->subYear()->format('Y-m-d H:i:s');

            // Get data for daily summary chart
            $dbh = $this->store->getConnection();

            $select = ' 
            
            SELECT 
                B.weekStart,
                B.weekEnd,
                DATE_FORMAT(STR_TO_DATE(MONTH(start), \'%m\'), \'%b\') AS shortMonth, 
                MONTH(start) as monthNo, 
                YEAR(start) as yearDate, 
                start, 
                SUM(count) as NumberPlays, 
                CONVERT(SUM(B.actualDiff), SIGNED INTEGER) as Duration  
            
            FROM (
                    
                SELECT
                     *,
                    YEARWEEK(periods.start, 3) AS yearWeek,
                    DATE_SUB(periods.start, INTERVAL WEEKDAY(periods.start) DAY) as weekStart,
                    DATE_SUB(DATE_ADD(DATE_SUB(periods.start, INTERVAL WEEKDAY(periods.start) DAY), INTERVAL 1 WEEK), INTERVAL 1 DAY ) as weekEnd,
                    
                    GREATEST(periods.start, statStart) AS actualStart,
                    LEAST(periods.end, statEnd) AS actualEnd,
                    LEAST(stat.duration, UNIX_TIMESTAMP(LEAST(periods.end, statEnd)) - UNIX_TIMESTAMP(GREATEST(periods.start, statStart))) AS actualDiff
                FROM
                ( 
                    SELECT                
                        
            ';

            if ($reportFilter == '') {
                $range = $diffInDays;

                // START FROM TODATE THEN DECREASE BY ONE DAY TILL FROMDATE
                $select .= '  
                DATE_SUB(DATE_SUB("'.$toDt.'", INTERVAL 1 DAY), INTERVAL c.number DAY) AS start,
                    
                DATE_ADD(
                        DATE_SUB(DATE_SUB("'.$toDt.'", INTERVAL 1 DAY) , INTERVAL c.number DAY), 
                        INTERVAL 1 DAY) AS end ';

            } elseif (($reportFilter == 'today')) {
                $range = 23;

                // START FROM LASTHOUR OF TODAY THEN DECREASE BY ONE HOUR
                $select .= '  
                DATE_FORMAT("'.$today.'",
                        \'%Y-%m-%d 23:00:00\') - INTERVAL c.number HOUR AS start,
                DATE_ADD(DATE_FORMAT("'.$today.'",
                            \'%Y-%m-%d 23:00:00\'),
                    INTERVAL 1 HOUR) - INTERVAL c.number HOUR AS end ';

            } elseif (($reportFilter == 'yesterday')) {
                $range = 23;

                // START FROM LASTHOUR OF YESTERDAY THEN DECREASE BY ONE HOUR
                $select .= '  
                DATE_FORMAT("'.$yesterday.'",
                        \'%Y-%m-%d 23:00:00\') - INTERVAL c.number HOUR AS start,
                DATE_ADD(DATE_FORMAT("'.$yesterday.'",
                            \'%Y-%m-%d 23:00:00\'),
                    INTERVAL 1 HOUR) - INTERVAL c.number HOUR AS end ';

            } elseif (($reportFilter == 'thisweek')) {
                $range = 6;

                // START FROM (LASTDAY OF THISWEEK - 1 DAY) THEN DECREASE BY ONE DAY
                $select .= '
                DATE_SUB(DATE_SUB("'.$lastdaythisweek.'",  INTERVAL 1 DAY), 
                    INTERVAL c.number DAY) AS start,       
                    
                DATE_SUB("'.$lastdaythisweek.'",  INTERVAL c.number DAY) AS end ';

            } elseif (($reportFilter == 'lastweek')) {
                $range = 6;

                // START FROM (LASTDAY OF LASTWEEK - 1 DAY) THEN DECREASE BY ONE DAY
                $select .= '
                DATE_SUB(DATE_SUB("'.$lastdaylastweek.'",  INTERVAL 1 DAY), 
                    INTERVAL c.number DAY) AS start,
                    
                DATE_SUB("'.$lastdaylastweek.'",  INTERVAL c.number DAY) AS end ';

            } elseif (($reportFilter == 'thismonth')) {
                $range = 30;

                // START FROM (LASTDAY OF THISMONTH - 1 DAY) THEN DECREASE BY ONE DAY
                $select .= '                    
                DATE_SUB(DATE_SUB("'.$lastdaythismonth.'",  INTERVAL 1 DAY), 
                    INTERVAL c.number DAY) AS start,       
                    
                DATE_SUB("'.$lastdaythismonth.'",  INTERVAL c.number DAY) AS end ';

            } elseif (($reportFilter == 'lastmonth')) {
                $range = 30;

                // START FROM (LASTDAY OF LASTMONTH - 1 DAY) THEN DECREASE BY ONE DAY
                $select .= '                    
                DATE_SUB(DATE_SUB("'.$lastdaylastmonth.'",  INTERVAL 1 DAY), 
                    INTERVAL c.number DAY) AS start,       
                    
                DATE_SUB("'.$lastdaylastmonth.'",  INTERVAL c.number DAY) AS end ';

            } elseif (($reportFilter == 'thisyear')) {
                $range = 365;

                // START FROM (LASTDAY OF THISYEAR - 1 DAY) THEN DECREASE BY ONE DAY
                $select .= '                    
                DATE_SUB(DATE_SUB("'.$lastdaythisyear.'",  INTERVAL 1 DAY), 
                    INTERVAL c.number DAY) AS start,       
                    
                DATE_SUB("'.$lastdaythisyear.'",  INTERVAL c.number DAY) AS end ';

            } elseif (($reportFilter == 'lastyear')) {
                $range = 365;

                // START FROM (LASTDAY OF LASTYEAR - 1 DAY) THEN DECREASE BY ONE DAY
                $select .= '                    
                DATE_SUB(DATE_SUB("'.$lastdaylastyear.'",  INTERVAL 1 DAY), 
                    INTERVAL c.number DAY) AS start,       
                    
                DATE_SUB("'.$lastdaylastyear.'",  INTERVAL c.number DAY) AS end ';
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
                    c.number BETWEEN 0 AND ' . $range . '
                ) periods        
            ';


            $body = '
                LEFT OUTER JOIN
                
                (SELECT 
                    layout.Layout,
                    IFNULL(`media`.name, IFNULL(`widgetoption`.value, `widget`.type)) AS Media,
                    stat.mediaId,
                    stat.`start` as statStart,
                    stat.`end` as statEnd,
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
            if (count($displayIds) > 0) {
                $body .= ' AND stat.displayID IN (' . implode(',', $displayIds) . ') ';
            }

            // Type filter
            if (($type == 'layout') && ($layoutId != '')) {
                $body .= ' AND `stat`.type = \'layout\' 
                       AND `stat`.campaignId = (SELECT campaignId FROM layouthistory WHERE layoutId = ' . $layoutId. ') ';
            } elseif (($type == 'media') && ($mediaId != '')) {
                $body .= ' AND `stat`.type = \'media\' AND IFNULL(`media`.mediaId, 0) <> 0 
                       AND `stat`.mediaId = ' . $mediaId;
            }

            $params = [
                'fromDt' => $fromDt,
                'toDt' => $toDt
            ];

            if ($reportFilter == '') {
                $body .= ' AND stat.start < DATE_ADD(:toDt, INTERVAL 1 DAY)  
            AND stat.end >= :fromDt ';
            }

            // where start is less than last hour of the day + 1 hour (i.e., nextday of today)
            // and end is greater than or equal first hour of the day
            elseif (($reportFilter == 'today')) {
                $body .= ' AND stat.`start` < "'.$nextday.'"
            AND stat.`end` >= "'.$today.'" ';
            }

            // where start is less than last hour of the day + 1 hour (i.e., today)
            // and end is greater than or equal first hour of the day
            elseif (($reportFilter == 'yesterday')) {
                $body .= ' AND stat.`start` <  "'.$today.'" 
			AND stat.`end` >= "'.$yesterday.'"  ';
            }

            // where start is less than last day of the week
            // and end is greater than or equal first day of the week
            elseif (($reportFilter == 'thisweek')) {
                $body .= ' AND stat.`start` < "'.$lastdaythisweek.'"
            AND stat.`end` >= "'.$firstdaythisweek.'" ';
            }

            // where start is less than last day of the week
            // and end is greater than or equal first day of the week
            elseif (($reportFilter == 'lastweek')) {
                $body .= ' AND stat.`start` < "'.$lastdaylastweek.'"		
            AND stat.`end` >= "'.$firstdaylastweek.'" ';
            }

            // where start is less than last day of the month
            // and end is greater than or equal first day of the month
            elseif (($reportFilter == 'thismonth')) {
                $body .= ' AND stat.`start` <  "'.$lastdaythismonth.'"
            AND stat.`end` >= "'.$firstdaythismonth.'" ';
            }

            // where start is less than last day of the month
            // and end is greater than or equal first day of the month
            elseif (($reportFilter == 'lastmonth')) {
                $body .= ' AND stat.`start` <  "'.$lastdaylastmonth.'"
            AND stat.`end` >= "'.$firstdaylastmonth.'" ';
            }

            // where start is less than last day of the year
            // and end is greater than or equal first day of the year
            elseif (($reportFilter == 'thisyear')) {
                $body .= ' AND stat.`start` < "'.$lastdaythisyear.'"
            AND stat.`end` >= "'.$firstdaythisyear.'" ';
            }

            // where start is less than last day of the year
            // and end is greater than or equal first day of the year
            elseif (($reportFilter == 'lastyear')) {
                $body .= ' AND stat.`start` < "'.$lastdaylastyear.'"
            AND stat.`end` >= "'.$firstdaylastyear.'" ';
            }

            $body .= ' ) stat               
            ON statStart < periods.`end`
            AND statEnd > periods.`start`
            ';

            if ($reportFilter == '') {
                $body .= ' WHERE periods.`start` >= :fromDt
            AND periods.`end` <= DATE_ADD(:toDt, INTERVAL 1 DAY) ';
            }
            // where periods start is greater than or equal today and
            // periods end is less than or equal today + 1 day i.e. nextday
            elseif (($reportFilter == 'today')) {
                $body .= ' WHERE periods.`start` >= "'.$today.'"
            AND periods.`end` <= "'.$nextday.'" ';
            }
            // where periods start is greater than or equal yesterday and
            // periods end is less than or equal today
            elseif (($reportFilter == 'yesterday')) {
                $body .= ' WHERE periods.`start` >= "'.$yesterday.'"
	        AND periods.`end` <= "'.$today.'" ';
            }
            // where periods start is greater than or equal thisweekmonday and
            // periods end is less than or equal lastdaythisweek
            elseif (($reportFilter == 'thisweek')) {
                $body .= ' WHERE periods.`start` >= "'.$firstdaythisweek.'"
            AND periods.`end` <= "'.$lastdaythisweek.'" ';
            }
            // where periods start is greater than or equal lastweekmonday and
            // periods end is less than or equal lastdaylastweek
            elseif (($reportFilter == 'lastweek')) {
                $body .= ' WHERE periods.`start` >= "'.$firstdaylastweek.'"
            AND periods.`end` <=  "'.$lastdaylastweek.'" ';
            }
            // where periods start is greater than or equal firstdaythismonth and
            // periods end is less than lastdaythismonth
            elseif (($reportFilter == 'thismonth')) {
                $body .= ' 
                WHERE
                    periods.`start` >= "'.$firstdaythismonth.'"
                    AND periods.`end` <=  "'.$lastdaythismonth.'" ';
            }
            // where periods start is greater than or equal firstdaylastmonth and
            // periods end is less than lastdaylastmonth
            elseif (($reportFilter == 'lastmonth')) {
                $body .= '  
                WHERE    
                    periods.`start` >= "'.$firstdaylastmonth.'"
                    AND periods.`end` <= "'.$lastdaylastmonth.'" ';
            }
            // where periods start is greater than or equal firstdaythisyear and
            // periods end is less than lastdaythisyear
            elseif (($reportFilter == 'thisyear')) {
                $body .= ' 
                WHERE
                    periods.`start` >= "'.$firstdaythisyear.'"
                    AND periods.`end` <= "'.$lastdaythisyear.'" ';
            }
            // where periods start is greater than or equal firstdaylastyear and
            // periods end is less than lastdaylastyear
            elseif (($reportFilter == 'lastyear')) {
                $body .= '  
                WHERE    
                    periods.`start` >= "'.$firstdaylastyear.'"
                    AND periods.`end` <= "'.$lastdaylastyear.'" ';
            }

            $body .= '  
            ORDER BY periods.`start`, statStart
	        )B ';


            if ($groupByFilter == 'byweek') {
                $body .= '  
                    GROUP BY yearWeek ';
            } elseif ($groupByFilter == 'bymonth') {

                if (($reportFilter == 'thisyear') || ($reportFilter == 'lastyear')) {
                    $body .= '  
                        GROUP BY monthNo
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
            $sql = $select . $periods . $body;
            $this->log->debug($sql);

            $sth = $dbh->prepare($sql);
            $sth->execute($params);

            // Get the results
            $results = $sth->fetchAll();

            return $results;

        } else {
            return [];
        }
    }

    /**
     * @param StorageServiceInterface $store
     * @return $this
     */
    public function setStore($store)
    {
        $this->store = $store;
        return $this;
    }

}