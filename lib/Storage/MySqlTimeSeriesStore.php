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

use Jenssegers\Date\Date;
use Xibo\Exception\GeneralException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\XiboException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;

/**
 * Class MySqlTimeSeriesStore
 * @package Xibo\Storage
 */
class MySqlTimeSeriesStore implements TimeSeriesStoreInterface
{
    // Keep all stats in this array after processing
    private $stats = [];
    private $layoutCampaignIds = [];
    private $layoutIdsNotFound = [];

    /** @var StorageServiceInterface */
    private $store;

    /** @var LogServiceInterface */
    private $log;

    /** @var DateServiceInterface */
    private $dateService;

    /** @var  LayoutFactory */
    protected $layoutFactory;

    /** @var  CampaignFactory */
    protected $campaignFactory;

    /**
     * @inheritdoc
     */
    public function __construct($config = null)
    {

    }

    /**
     * @inheritdoc
     */
    public function setDependencies($log, $date, $layoutFactory = null, $campaignFactory = null, $mediaFactory = null, $widgetFactory = null, $displayFactory = null)
    {
        $this->log = $log;
        $this->dateService = $date;
        $this->layoutFactory = $layoutFactory;
        $this->campaignFactory = $campaignFactory;
        return $this;
    }

    /** @inheritdoc */
    public function addStat($statData)
    {

        // For a type "event" we have layoutid 0 so is campaignId
        // otherwise we should try and resolve the campaignId
        $campaignId = 0;
        if ($statData['type'] != 'event') {

            if (array_key_exists($statData['layoutId'], $this->layoutCampaignIds)) {
                $campaignId = $this->layoutCampaignIds[$statData['layoutId']];
            } else {

                try {

                    // Get the layout campaignId
                    $campaignId = $this->layoutFactory->getCampaignIdFromLayoutHistory($statData['layoutId']);

                    // Put layout campaignId to memory
                    $this->layoutCampaignIds[$statData['layoutId']] = $campaignId;

                } catch (XiboException $error) {

                    if (!in_array($statData['layoutId'], $this->layoutIdsNotFound)) {
                        $this->layoutIdsNotFound[] = $statData['layoutId'];
                        $this->log->error('Layout not found. Layout Id: '. $statData['layoutId']);
                    }
                    return;
                }
            }
        }


        // Set to Unix Timestamp
        $statData['statDate'] = $statData['statDate']->format('U');
        $statData['fromDt'] = $statData['fromDt']->format('U');
        $statData['toDt'] = $statData['toDt']->format('U');
        $statData['campaignId'] = $campaignId;
        $statData['displayId'] = $statData['display']->displayId;
        $statData['engagements'] = json_encode($statData['engagements']);
        unset($statData['display']);

        $this->stats[] = $statData;

    }

    /** @inheritdoc */
    public function addStatFinalize()
    {

        if (count($this->stats) > 0) {

            $sql = 'INSERT INTO `stat` (`type`, statDate, start, `end`, scheduleID, displayID, campaignID, layoutID, mediaID, Tag, `widgetId`, duration, `count`, `engagements`) VALUES ';
            $placeHolders = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

            $sql = $sql . implode(', ', array_fill(1, count($this->stats), $placeHolders));

            // Flatten the array
            $data = [];
            foreach ($this->stats as $stat) {
                // Be explicit about the order of the keys
                $ordered = [
                    'type' => $stat['type'],
                    'statDate' => $stat['statDate'],
                    'fromDt' => $stat['fromDt'],
                    'toDt' => $stat['toDt'],
                    'scheduleId' => $stat['scheduleId'],
                    'displayId' => $stat['displayId'],
                    'campaignId' => $stat['campaignId'],
                    'layoutId' => $stat['layoutId'],
                    'mediaId' => $stat['mediaId'],
                    'tag' => $stat['tag'],
                    'widgetId' => $stat['widgetId'],
                    'duration' => $stat['duration'],
                    'count' => $stat['count'],
                    'engagements' => $stat['engagements']
                ];

                // Add each value to another array in order
                foreach ($ordered as $field) {
                    $data[] = $field;
                }
            }

            $this->store->isolated($sql, $data);

        }

    }

    /** @inheritdoc */
    public function getEarliestDate()
    {
        $result = $this->store->select('SELECT MIN(start) AS minDate FROM `stat`', []);
        $earliestDate = $result[0]['minDate'];

        return ($earliestDate === null)
            ? null
            : Date::createFromFormat('U', $result[0]['minDate']);
    }

    /** @inheritdoc */
    public function getStats($filterBy = [])
    {
        $fromDt = isset($filterBy['fromDt']) ? $filterBy['fromDt'] : null;
        $toDt = isset($filterBy['toDt']) ? $filterBy['toDt'] : null;
        $statDate = isset($filterBy['statDate']) ? $filterBy['statDate'] : null;
        $statDateLessThan = isset($filterBy['statDateLessThan']) ? $filterBy['statDateLessThan'] : null;

        // In the case of user switches from  mongo to mysql - laststatId were saved as Mongo ObjectId string
        if (isset($filterBy['statId'])) {
            if (!is_numeric($filterBy['statId'])) {
                throw new InvalidArgumentException(__('Invalid statId provided'), 'statId');
            } else {
                $statId = $filterBy['statId'];
            }
        } else {
            $statId = null;
        }

        $type = isset($filterBy['type']) ? $filterBy['type'] : null;
        $displayIds = isset($filterBy['displayIds']) ? $filterBy['displayIds'] : [];
        $layoutIds = isset($filterBy['layoutIds']) ? $filterBy['layoutIds'] : [];
        $mediaIds = isset($filterBy['mediaIds']) ? $filterBy['mediaIds'] : [];
        $campaignId = isset($filterBy['campaignId']) ? $filterBy['campaignId'] : null;
        $eventTag = isset($filterBy['eventTag']) ? $filterBy['eventTag'] : null;

        // Tag embedding
        $embedDisplayTags = isset($filterBy['displayTags']) ? $filterBy['displayTags'] : false;
        $embedLayoutTags = isset($filterBy['layoutTags']) ? $filterBy['layoutTags'] : false;
        $embedMediaTags = isset($filterBy['mediaTags']) ? $filterBy['mediaTags'] : false;

        // Limit
        $start = isset($filterBy['start']) ? $filterBy['start'] : null;
        $length = isset($filterBy['length']) ? $filterBy['length'] : null;

        $params = [];
        $select = 'SELECT stat.statId, 
            stat.statDate, 
            stat.type, 
            stat.displayId, 
            stat.widgetId, 
            stat.layoutId, 
            stat.mediaId, 
            stat.campaignId, 
            stat.start as start, 
            stat.end as end, 
            stat.tag, 
            stat.duration, 
            stat.count, 
            stat.engagements, 
            display.Display as display, 
            layout.Layout as layout, 
            media.Name AS media ';

        if ($embedDisplayTags) {
            $select .= ', 
                (
                  SELECT GROUP_CONCAT(DISTINCT CONCAT(tag, \'|\', IFNULL(value, \'null\'))) 
                    FROM tag 
                      INNER JOIN lktagdisplaygroup 
                      ON lktagdisplaygroup.tagId = tag.tagId 
                      INNER JOIN `displaygroup`
                      ON lktagdisplaygroup.displayGroupId = displaygroup.displayGroupId
                        AND `displaygroup`.isDisplaySpecific = 1 
                      INNER JOIN `lkdisplaydg`
                      ON lkdisplaydg.displayGroupId = displaygroup.displayGroupId
                   WHERE lkdisplaydg.displayId = stat.displayId 
                  GROUP BY lktagdisplaygroup.displayGroupId
                ) AS displayTags
            ';
        }

        if ($embedMediaTags) {
            $select .= ', 
                (
                  SELECT GROUP_CONCAT(DISTINCT CONCAT(tag, \'|\', IFNULL(value, \'null\'))) 
                    FROM tag 
                      INNER JOIN lktagmedia 
                      ON lktagmedia.tagId = tag.tagId 
                   WHERE lktagmedia.mediaId = media.mediaId 
                  GROUP BY lktagmedia.mediaId
                ) AS mediaTags
            ';
        }

        if ($embedLayoutTags) {
            $select .= ', 
                (
                  SELECT GROUP_CONCAT(DISTINCT CONCAT(tag, \'|\', IFNULL(value, \'null\'))) 
                    FROM tag 
                      INNER JOIN lktaglayout 
                      ON lktaglayout.tagId = tag.tagId 
                   WHERE lktaglayout.layoutId = layout.layoutId 
                  GROUP BY lktaglayout.layoutId
                ) AS layoutTags
            ';
        }

        $body = '
        FROM stat
            LEFT OUTER JOIN display
            ON stat.DisplayID = display.DisplayID
            LEFT OUTER JOIN layout
            ON layout.LayoutID = stat.LayoutID
            LEFT OUTER JOIN media
            ON media.mediaID = stat.mediaID
            LEFT OUTER JOIN widget
            ON widget.widgetId = stat.widgetId
         WHERE 1 = 1 ';

        // fromDt/toDt Filter
        if (($fromDt != null) && ($toDt != null)) {
            $body .= ' AND stat.end > '. $fromDt->format('U') . ' AND stat.start <= '. $toDt->format('U');
        } else if (($fromDt != null) && empty($toDt)) {
            $body .= ' AND stat.start >= '. $fromDt->format('U');
        }

        // statDate Filter
        // get the next stats from the given date
        if ($statDate != null) {
            $body .= ' AND stat.statDate >= ' . $statDate->format('U');
        }
        if ($statDateLessThan != null) {
            $body .= ' AND stat.statDate < ' . $statDateLessThan->format('U');
        }

        if ($statId != null) {
            $body .= ' AND stat.statId > '. $statId;
        }

        if (count($displayIds) > 0) {
            $body .= ' AND stat.displayID IN (' . implode(',', $displayIds) . ')';
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

        // Event Tag Filter
        if ($eventTag) {
            $body .= ' AND `stat`.tag = :eventTag';
            $params['eventTag'] = $eventTag;
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

            $body .= '  AND `stat`.campaignId IN (SELECT campaignId FROM `layouthistory` WHERE layoutId IN (' . trim($layoutSql, ',') . ')) ';
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

        // Campaign
        // --------
        // Filter on Layouts linked to a Campaign
        if ($campaignId != null) {
            $body .= ' AND stat.campaignId IN (
                    SELECT lkcampaignlayout.campaignId 
                      FROM `lkcampaignlayout`
                        INNER JOIN `campaign`
                        ON `lkcampaignlayout`.campaignId = `campaign`.campaignId
                            AND `campaign`.isLayoutSpecific = 1
                        INNER JOIN `lkcampaignlayout` lkcl 
                        ON lkcl.layoutid = lkcampaignlayout.layoutId
                     WHERE lkcl.campaignId = :campaignId 
                ) ';
            $params['campaignId'] = $campaignId;
        }

        // Sorting
        $body .= ' ORDER BY stat.statId ';

        $limit = '';
        if ($start !== null && $length !== null) {
            $limit = ' LIMIT ' . $start . ', ' . $length;
        }

        // Total count
        $resTotal = [];
        if ($start !== null && $length !== null) {
            $resTotal = $this->store->select('
              SELECT COUNT(*) AS total FROM (   ' . $select . $body . ') total
            ', $params);
        }

        // Run our query using a connection object (to save memory)
        $connection = $this->store->getConnection();
        $connection->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        // Execute sql statement
        $sql = $select . $body. $limit;

        $statement = $connection->prepare($sql);

        // Execute
        $statement->execute($params);
        $this->log->sql($sql, $params);

        $result = new TimeSeriesMySQLResults($statement);

        // Total
        $result->totalCount = isset($resTotal[0]['total']) ? $resTotal[0]['total'] : 0;

        return $result;
    }

    /** @inheritdoc */
    public function getExportStatsCount($filterBy = [])
    {

        $fromDt = isset($filterBy['fromDt']) ? $filterBy['fromDt'] : null;
        $toDt = isset($filterBy['toDt']) ? $filterBy['toDt'] : null;
        $displayIds = isset($filterBy['displayIds']) ? $filterBy['displayIds'] : [];

        $params = [];
        $sql = ' SELECT COUNT(*) AS total FROM `stat`  WHERE 1 = 1 ';

        // fromDt/toDt Filter
        if (($fromDt != null) && ($toDt != null)) {
            $sql .= ' AND stat.end > '. $fromDt->format('U') . ' AND stat.start <= '. $toDt->format('U');
        }

        if (count($displayIds) > 0) {
            $sql .= ' AND stat.displayID IN (' . implode(',', $displayIds) . ')';
        }

        // Total count
        $resTotal = $this->store->select($sql, $params);

        // Total
        $totalCount = isset($resTotal[0]['total']) ? $resTotal[0]['total'] : 0;

        return $totalCount;
    }

    /** @inheritdoc */
    public function deleteStats($maxage, $fromDt = null, $options = [])
    {
        // Set some default options
        $options = array_merge([
            'maxAttempts' => 10,
            'statsDeleteSleep' => 3,
            'limit' => 10000,
        ], $options);

        // Convert to a simple type so that we can pass by reference to bindParam.
        $maxage = $maxage->format('U');

        try {
            $i = 0;
            $rows = 1;

            if ($fromDt !== null) {
                // Convert to a simple type so that we can pass by reference to bindParam.
                $fromDt = $fromDt->format('U');

                // Prepare a delete statement which we will use multiple times
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

                // Break if we've exceeded the maximum attempts, assuming that has been provided
                if ($options['maxAttempts'] > -1 && $i >= $options['maxAttempts']) {
                    break;
                }
            }

            $this->log->debug('Deleted Stats back to ' . $maxage . ' in ' . $i . ' attempts');

            return $count;
        }
        catch (\PDOException $e) {
            $this->log->error($e->getMessage());
            throw new GeneralException('Stats cannot be deleted.');
        }
    }

    /** @inheritdoc */
    public function executeQuery($options = [])
    {
        $this->log->debug('Execute MySQL query.');

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
    public function getEngine()
    {
        return 'mysql';
    }

}