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
use Xibo\Factory\LayoutFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;

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
        // Set to Unix Timestamp
        foreach ($statData as $k => $stat) {
            $statData[$k]['statDate'] = $statData[$k]['statDate']->format('U');
            $statData[$k]['fromDt'] = $statData[$k]['fromDt']->format('U');
            $statData[$k]['toDt'] = $statData[$k]['toDt']->format('U');
        }

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
    public function getEarliestDate()
    {
        $earliestDate = $this->store->select('SELECT MIN(statDate) AS minDate FROM `stat`', []);

        return [
            'minDate' => $earliestDate[0]['minDate']
        ];
    }

    /** @inheritdoc */
    public function getStats($filterBy = [])
    {

        $fromDt = isset($filterBy['fromDt']) ? $filterBy['fromDt'] : null;
        $toDt = isset($filterBy['toDt']) ? $filterBy['toDt'] : null;
        $statDate = isset($filterBy['statDate']) ? $filterBy['statDate'] : null;
        $statId = isset($filterBy['statId']) ? $filterBy['statId'] : null;

        if ($statDate == null) {

            // Check whether fromDt and toDt are provided
            if (($fromDt == null) && ($toDt == null)) {
                throw new InvalidArgumentException(__("Either fromDt/toDt or statDate should be provided"), 'fromDt/toDt/statDate');
            }

            if ($fromDt == null) {
                throw new InvalidArgumentException(__("Fromdt cannot be null"), 'fromDt');
            }

            if ($toDt == null) {
                throw new InvalidArgumentException(__("Todt cannot be null"), 'toDt');
            }
        } else {
            if (($fromDt != null) || ($toDt != null)) {
                throw new InvalidArgumentException(__("Either fromDt/toDt or statDate should be provided"), 'fromDt/toDt/statDate');
            }
        }

        $type = isset($filterBy['type']) ? $filterBy['type'] : null;
        $displayIds = isset($filterBy['displayIds']) ? $filterBy['displayIds'] : [];
        $layoutIds = isset($filterBy['layoutIds']) ? $filterBy['layoutIds'] : [];
        $mediaIds = isset($filterBy['mediaIds']) ? $filterBy['mediaIds'] : [];
        $campaignId = isset($filterBy['campaignId']) ? $filterBy['campaignId'] : null;

        // Limit
        $start = isset($filterBy['start']) ? $filterBy['start'] : null;
        $length = isset($filterBy['length']) ? $filterBy['length'] : null;

        $params = [];
        $select = ' SELECT stat.statId, stat.statDate, stat.type, stat.displayId, stat.widgetId, stat.layoutId, stat.mediaId, stat.start as start, stat.end as end, stat.tag, stat.duration, stat.count, 
        display.Display as display, layout.Layout as layout, media.Name AS media ';

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
        } else { // statDate Filter
            // get the next stats from the given date
            // we only get next chunk of stats from the laststatdate to todate
            $body .= ' AND stat.statDate >= '. $statDate->format('U');
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

        // Campaign selection
        // ------------------
        // Get all the layouts of that campaign.
        // Then get all the campaigns of the layouts
        $campaignIds = [];
        if ($campaignId != null) {
            try {
                $campaign = $this->campaignFactory->getById($campaignId);
                $layouts = $this->layoutFactory->getByCampaignId($campaign->campaignId);
                if (count($layouts) > 0) {
                    foreach ($layouts as $layout) {
                        $campaignIds[] = $layout->campaignId;
                    }
                }
            } catch (NotFoundException $notFoundException) {
                $this->log->error('CampaignIds not Found.');
            }
        }

        // Campaign Filter
        if ($campaignId != null) {
            if (count($campaignIds) != 0) {
                $body .= ' AND stat.campaignId IN (' . implode(',', $campaignIds) . ')';
            } else {
                // we wont get any match as we store layoutspecific campaignid in stat
                $body .= ' AND stat.campaignId = '. $campaignId;
            }
        }

        $body .= " ORDER BY stat.statId ";

        $limit = '';
        if ($start !== null && $length !== null) {
            $limit = ' LIMIT ' . $start . ', ' . $length;
        }


        // Total count
        $resTotal = [];
        if ($start !== null && $length !== null) {
            $resTotal = $this->store->select('
              SELECT COUNT(*) AS total FROM (   ' . $select. $body . ') total
            ', $params);
        }

        // Run our query using a connection object (to save memory)
        $connection = $this->store->getConnection();
        $connection->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        /*Execute sql statement*/
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
            throw new \RuntimeException('Stats cannot be deleted.');
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