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

        $toDt = $this->dateService->parse($toDt)->startOfDay()->addDay()->format('Y-m-d H:i:s'); // added a day

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
                AND stat.start <= :toDt
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

                // old layout and latest layout have same tags
                // old layoutId replaced with latest layoutId in the lktaglayout table and
                // join with layout history to get campaignId then we can show old layouts that have no tag
                if ($tagsType === 'layout') {
                    $body .= ' AND `stat`.campaignId NOT IN (
                        SELECT 
                            `layouthistory`.campaignId
                        FROM
                        (
                            SELECT `lktaglayout`.layoutId
                            FROM tag
                            INNER JOIN `lktaglayout`
                            ON `lktaglayout`.tagId = tag.tagId ) B
                        LEFT OUTER JOIN
                        `layouthistory` ON `layouthistory`.layoutId = B.layoutId 
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
                // old layout and latest layout have same tags
                // old layoutId replaced with latest layoutId in the lktaglayout table and
                // join with layout history to get campaignId then we can show old layouts that have given tag
                if ($tagsType === 'layout') {
                    $body .= " AND `stat`.campaignId IN (
                        SELECT 
                            `layouthistory`.campaignId
                        FROM
                        (
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
                if ($tagsType === 'layout') {
                    $body .= " ) B
                        LEFT OUTER JOIN
                        `layouthistory` ON `layouthistory`.layoutId = B.layoutId ) ";
                }
                else {
                    $body .= " ) ";
                }
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

            $body .= '  AND `stat`.campaignId IN (SELECT campaignId from layouthistory where layoutId IN (' . trim($layoutSql, ',') . ')) ';
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
    public function executeQuery($options = [])
    {
        $query = $options['query'];
        $params = $options['params'];

        $dbh = $this->store->getConnection();

        $sth = $dbh->prepare($query);
        $sth->execute($params);

        // Get the results
        $results = $sth->fetchAll();

        return $results;
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

    /** @inheritdoc */
    public function getStatisticStore()
    {
        return 'mysql';
    }

}