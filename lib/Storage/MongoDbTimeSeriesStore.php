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

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Driver\Exception\AuthenticationException;
use MongoDB\Driver\Exception\ConnectionException;
use MongoDB\Driver\Exception\ConnectionTimeoutException;
use MongoDB\Driver\Exception\ExecutionTimeoutException;
use Xibo\Exception\GeneralException;
use Xibo\Exception\InvalidArgumentException;
use Xibo\Exception\NotFoundException;
use Xibo\Exception\XiboException;
use Xibo\Factory\CampaignFactory;
use Xibo\Factory\DisplayFactory;
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;

/**
 * Class MongoDbTimeSeriesStore
 * @package Xibo\Storage
 */
class MongoDbTimeSeriesStore implements TimeSeriesStoreInterface
{
    /** @var LogServiceInterface */
    private $log;

    /** @var DateServiceInterface */
    private $dateService;

    /** @var array */
    private $config;

    /** @var \MongoDB\Client */
    private $client;

    private $table = 'stat';

    private $periodTable = 'period';

    private $stats = [];
    private $mediaIds = [];
    private $widgetIds = [];
    private $tagFilterDg = [];
    private $layoutIds = [];

    /** @var  MediaFactory */
    protected $mediaFactory;

    /** @var  WidgetFactory */
    protected $widgetFactory;

    /** @var  LayoutFactory */
    protected $layoutFactory;

    /** @var  DisplayFactory */
    protected $displayFactory;

    /** @var  DisplayGroupFactory */
    protected $displayGroupFactory;

    /** @var  CampaignFactory */
    protected $campaignFactory;

    /**
     * @inheritdoc
     */
    public function __construct($config = null)
    {

        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function setDependencies($log, $date, $layoutFactory = null, $campaignFactory = null, $mediaFactory = null, $widgetFactory = null, $displayFactory = null, $displayGroupFactory = null)
    {
        $this->log = $log;
        $this->dateService = $date;
        $this->mediaFactory = $mediaFactory;
        $this->widgetFactory = $widgetFactory;
        $this->layoutFactory = $layoutFactory;
        $this->displayFactory = $displayFactory;
        $this->displayGroupFactory = $displayGroupFactory;
        $this->campaignFactory = $campaignFactory;

        try {
            $uri = isset($this->config['uri']) ? $this->config['uri'] : 'mongodb://' . $this->config['host'] . ':' . $this->config['port'];
            $this->client = new Client($uri, [
                'username' => $this->config['username'],
                'password' => $this->config['password']
            ], (array_key_exists('driverOptions', $this->config) ? $this->config['driverOptions'] : []));
        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->critical($e->getMessage());
        }

        return $this;
    }

    /** @inheritdoc */
    public function addStat($statData)
    {

        $statData['statDate'] = new UTCDateTime($statData['statDate']->format('U') * 1000);

        $statData['start'] = new UTCDateTime($statData['fromDt']->format('U') * 1000);
        $statData['end'] = new UTCDateTime($statData['toDt']->format('U') * 1000);
        $statData['eventName'] = $statData['tag'];
        unset($statData['fromDt']);
        unset($statData['toDt']);
        unset($statData['tag']);

        unset($statData['scheduleId']);

        // Make an empty array to collect tags into
        $tagFilter = [];

        // Media name
        $mediaName = null;
        if (!empty($statData['mediaId'])) {

            if (array_key_exists($statData['mediaId'], $this->mediaIds)) {

                $mediaName = $this->mediaIds[$statData['mediaId']]['name'];
                $mediaTags = $this->mediaIds[$statData['mediaId']]['tags'];

            } else {

                try {
                    $media = $this->mediaFactory->getById($statData['mediaId']);

                    $mediaName = $media->name;
                    $mediaTags = $media->tags;

                    // Put media name and tags to memory
                    $this->mediaIds[$statData['mediaId']]['name'] = $mediaName;
                    $this->mediaIds[$statData['mediaId']]['tags'] = $mediaTags;

                } catch (NotFoundException $error) {
                    // Media not found ignore and log the stat
                    $this->log->error('Media not found. Media Id: '. $statData['mediaId'] .',Layout Id: '. $statData['layoutId']
                        .', FromDT: '.$statData['start'].', ToDt: '.$statData['end'].', Type: '.$statData['type']
                        .', Duration: '.$statData['duration'] .', Count '.$statData['count']);

                    return;
                }
            }

            $statData['mediaName'] = $mediaName;

            if (isset($mediaTags)) {
                $arrayOfTags = explode(',', $mediaTags);
                for ($i=0; $i<count($arrayOfTags); $i++) {
                    if (isset($arrayOfTags[$i]) ) {

                        if (!empty($arrayOfTags[$i]))
                            $tagFilter['media'][$i]['tag'] = $arrayOfTags[$i];
                    }
                }
            }
        }

        // Widget name
        $widgetName = null;
        if (!empty($statData['widgetId'])) {

            if (array_key_exists($statData['widgetId'], $this->widgetIds)) {

                $widgetName = $this->widgetIds[$statData['widgetId']]['name'];

            } else {

                try {
                    $widget = $this->widgetFactory->getById($statData['widgetId']);

                    if($widget != null) {
                        $widget->load();
                        $widgetName = isset($mediaName) ? $mediaName : $widget->getOptionValue('name', $widget->type);

                        // Put widget name to memory
                        $this->widgetIds[$statData['widgetId']]['name'] = $widgetName;
                    }

                } catch (NotFoundException $error) {
                    // Widget not found, ignore and log the stat
                    $this->log->error('Widget not found. Widget Id: '. $statData['widgetId'] .',Layout Id: '. $statData['layoutId']
                        .', FromDT: '.$statData['start'].', ToDt: '.$statData['end'].', Type: '.$statData['type']
                        .', Duration: '.$statData['duration'] .', Count '.$statData['count']);

                    return;
                }
            }

            // SET widgetName
            $statData['widgetName'] = $widgetName;

        }

        // Display name
        try {
            $display = $this->displayFactory->getById($statData['displayId']);
        } catch (NotFoundException $error) {
            // Display not found, ignore and log the stat
            $this->log->error('Display not found. Display Id: '. $statData['displayId'] .',Layout Id: '. $statData['layoutId']
                .', FromDT: '.$statData['start'].', ToDt: '.$statData['end'].', Type: '.$statData['type']
                .', Duration: '.$statData['duration'] .', Count '.$statData['count']);

            return;
        }

        $statData['displayName'] = $display->display;

        // Layout data
        $layoutName = null;
        $layoutTags = null;

        // For a type "event" we have layoutid 0 so is campaignId
        // otherwise we should try and resolve the campaignId
        $campaignId = 0;
        if ($statData['type'] != 'event') {
            if (array_key_exists($statData['layoutId'], $this->layoutIds)) {

                $campaignId = $this->layoutIds[$statData['layoutId']]['campaignId'];
                $layoutName = $this->layoutIds[$statData['layoutId']]['name'];
                $layoutTags = $this->layoutIds[$statData['layoutId']]['tags'];
            } else {

                try {

                    // Get the layout campaignId - this can give us a campaignId of a layoutId which id was replaced when draft to published
                    $campaignId = $this->layoutFactory->getCampaignIdFromLayoutHistory($statData['layoutId']);

                    // Get the layout by campaignId
                    $layout = $this->layoutFactory->getByParentCampaignId($campaignId);

                    $this->log->debug('Found layout : '. $statData['layoutId']);

                    $campaignId = $layout->campaignId;
                    $layoutName = $layout->layout;
                    $layoutTags = $layout->tags;

                    // Put in memory
                    $this->layoutIds[$statData['layoutId']]['name'] = $layoutName;
                    $this->layoutIds[$statData['layoutId']]['tags'] = $layoutTags;
                    $this->layoutIds[$statData['layoutId']]['campaignId'] = $campaignId;


                } catch (NotFoundException $error) {
                    // All we can do here is log
                    // we shouldn't ever get in this situation because the campaignId we used above will have
                    // already been looked up in the layouthistory table.
                    $this->log->alert('Error processing statistic into MongoDB. Layout not found. Stat is: ' . json_encode($statData));
                    return;
                } catch (XiboException $error) {
                    $this->log->error('Layout not found. Layout Id: '. $statData['layoutId']);
                    return;
                }

            }

        }

        // Get layout Campaign ID
        $statData['campaignId'] = (int) $campaignId;

        $statData['layoutName'] = $layoutName;

        // Layout tags
        if (isset($layoutTags)) {
            $arrayOfTags = explode(',', $layoutTags);
            for ($i=0; $i<count($arrayOfTags); $i++) {
                if (isset($arrayOfTags[$i]) ) {

                    if (!empty($arrayOfTags[$i]))
                        $tagFilter['layout'][$i]['tag'] = $arrayOfTags[$i];
                }
            }
        }

        // Display tags
       if (array_key_exists($display->displayGroupId, $this->tagFilterDg)) {

           if (isset($this->tagFilterDg[$display->displayGroupId])) {
               $tagFilter['dg'] = $this->tagFilterDg[$display->displayGroupId];
           }

       } else {

           try {

               $arrayOfTags = array_filter(explode(',', $this->displayGroupFactory->getById($display->displayGroupId)->tags));
               $arrayOfTagValues = array_filter(explode(',', $this->displayGroupFactory->getById($display->displayGroupId)->tagValues));

               for ($i=0; $i<count($arrayOfTags); $i++) {
                   if (isset($arrayOfTags[$i]) && (isset($arrayOfTagValues[$i]) && $arrayOfTagValues[$i] !== 'NULL' )) {
                       $tagFilter['dg'][$i]['tag'] = $arrayOfTags[$i];
                       $tagFilter['dg'][$i]['val'] = $arrayOfTagValues[$i];
                   } else {
                       $tagFilter['dg'][$i]['tag'] = $arrayOfTags[$i];
                   }
               }

               // Put in memory
               $this->tagFilterDg[$display->displayGroupId] = $tagFilter['dg'];


           } catch (NotFoundException $notFoundException) {
               $this->log->alert('Error processing statistic into MongoDB. Display not found. Stat is: ' . json_encode($statData));
               return;
           }
       }

        // TagFilter array
        $statData['tagFilter'] = $tagFilter;

        $this->stats[] = $statData;

    }

    /** @inheritdoc */
    public function addStatFinalize()
    {

        // Insert statistics
        $collection = $this->client->selectCollection($this->config['database'], $this->table);
        try {
            $collection->insertMany($this->stats);

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        // Create a period collection if it doesnot exist
        $collectionPeriod = $this->client->selectCollection($this->config['database'], $this->periodTable);

        try {
            $cursor = $collectionPeriod->findOne(['name' => 'period']);

            if (count($cursor) <= 0 ) {
                $this->log->error('Period collection does not exist in Mongo DB.');
                // Period collection created
                $collectionPeriod->insertOne(['name' => 'period']);
                $this->log->debug('Period collection created.');

            }

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

    }

    /** @inheritdoc */
    public function getEarliestDate()
    {
        $collection = $this->client->selectCollection($this->config['database'], $this->table);
        try {
            $earliestDate = $collection->aggregate([
                [
                    '$group' => [
                        '_id' => [],
                        'minDate' => ['$min' => '$statDate'],
                    ]
                ]
            ])->toArray();

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        if(count($earliestDate) > 0) {
            return [
                'minDate' => $earliestDate[0]['minDate']->toDateTime()->format('U')
            ];
        }
        return [];
    }

    /** @inheritdoc */
    public function getStats($filterBy = [])
    {
        // do we consider that the fromDt and toDt will always be provided?
        $fromDt = isset($filterBy['fromDt']) ? $filterBy['fromDt'] : null;
        $toDt = isset($filterBy['toDt']) ? $filterBy['toDt'] : null;
        $statDate = isset($filterBy['statDate']) ? $filterBy['statDate'] : null;

        // In the case of user switches from mysql to mongo - laststatId were saved as integer
        if (isset($filterBy['statId'])) {
            if (is_numeric($filterBy['statId'])) {
                throw new InvalidArgumentException(__('Invalid statId provided'), 'statId');
            }
            else {
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

        // Limit
        $start = isset($filterBy['start']) ? $filterBy['start'] : null;
        $length = isset($filterBy['length']) ? $filterBy['length'] : null;

        // Match query
        $match = [];

        // fromDt/toDt Filter
        if (($fromDt != null) && ($toDt != null)) {
            $fromDt = new UTCDateTime($fromDt->format('U')*1000);
            $match['$match']['end'] = [ '$gt' => $fromDt];

            $toDt = new UTCDateTime($toDt->format('U')*1000);
            $match['$match']['start'] = [ '$lte' => $toDt];
        }

        // statDate Filter
        // get the next stats from the given date
        if ($statDate != null) {
            $statDate = new UTCDateTime($statDate->format('U')*1000);
            $match['$match']['statDate'] = [ '$gte' => $statDate];
        }

        if (!empty($statId)) {
            $match['$match']['_id'] = [ '$gt' => new ObjectId($statId)];
        }

        // Displays Filter
        if (count($displayIds) != 0) {
            $match['$match']['displayId'] = [ '$in' => $displayIds ];
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
                $this->log->error('Empty campaignIds.');
            }
        }

        // Campaign Filter
        if ($campaignId != null) {
            if (count($campaignIds) != 0) {
                $match['$match']['campaignId'] = ['$in' => $campaignIds];
            } else {
                // we wont get any match as we store layoutspecific campaignid in stat
                $match['$match']['campaignId'] = ['$eq' => $campaignId];
            }
        }

        // Type Filter
        if ($type != null) {
            $match['$match']['type'] = $type;
        }

        // Layout Filter
        if (count($layoutIds) != 0) {
            // Get campaignIds for selected layoutIds
            $campaignIds = [];
            foreach ($layoutIds as $layoutId) {
                try {
                    $campaignIds[] = $this->layoutFactory->getCampaignIdFromLayoutHistory($layoutId);
                } catch (NotFoundException $notFoundException) {
                    // Ignore the missing one
                    $this->getLog()->debug('Filter for Layout without Layout History Record, layoutId is ' . $layoutId);
                }
            }
            $match['$match']['campaignId'] = [ '$in' => $campaignIds ];
        }

        // Media Filter
        if (count($mediaIds) != 0) {
            $match['$match']['mediaId'] = [ '$in' => $mediaIds ];
        }

        $collection = $this->client->selectCollection($this->config['database'], $this->table);
        try {
           $query = [
               $match,
               [
                   '$project' => [
                       'id'=> '$_id',
                       'type'=> 1,
                       'start'=> 1,
                       'end'=> 1,
                       'layout'=> '$layoutName',
                       'display'=> '$displayName',
                       'media'=> '$mediaName',
                       'tag'=> '$eventName',
                       'duration'=> '$duration',
                       'count'=> '$count',
                       'displayId'=> 1,
                       'layoutId'=> 1,
                       'widgetId'=> 1,
                       'mediaId'=> 1,
                       'statDate'=> 1,
                   ]
               ],
           ];

           if ($start !== null && $length !== null) {
               $query[]['$skip'] =  $start;
               $query[]['$limit'] = $length;
           }

           $cursor = $collection->aggregate($query);

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        $result = new TimeSeriesMongoDbResults($cursor);

        // Get total
        try {
            $totalQuery = [
                $match,
                [
                    '$group' => [
                        '_id'=> null,
                        'count' => ['$sum' => 1],
                    ]
                ],
            ];
            $totalCursor = $collection->aggregate($totalQuery);

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }
        $totalCount = $totalCursor->toArray();

        // Total
        $result->totalCount = (count($totalCount) > 0) ? $totalCount[0]['count'] : 0;

        return $result;
    }

    /** @inheritdoc */
    public function deleteStats($maxage, $fromDt = null, $options = [])
    {
        // Set default options
        $options = array_merge([
            'maxAttempts' => 10,
            'statsDeleteSleep' => 3,
            'limit' => 1000,
        ], $options);

        // we dont use $options['limit'] anymore.
        // we delete all the records at once based on filter criteria (no-limit approach)

        $toDt = new UTCDateTime($maxage->format('U')*1000);

        $collection = $this->client->selectCollection($this->config['database'], $this->table);

        $rows = 1;
        $count = 0;

        if ($fromDt != null) {

            $start = new UTCDateTime($fromDt->format('U')*1000);
            $filter =  [
                'start' => ['$lte' => $toDt],
                'end' => ['$gt' => $start]
            ];

        } else {

            $filter =  [
                'start' => ['$lte' => $toDt]
            ];
        }

        try {
            $deleteResult = $collection->deleteMany(
                $filter
            );
            $rows = $deleteResult->getDeletedCount();


        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        $count += $rows;

        // Give MongoDB time to recover
        if ($rows > 0) {
            $this->log->debug('Stats delete effected ' . $rows . ' rows, sleeping.');
            sleep($options['statsDeleteSleep']);
        }


        return $count;

    }

    /** @inheritdoc */
    public function getEngine()
    {
        return 'mongodb';
    }

    /** @inheritdoc */
    public function executeQuery($options = [])
    {
        $this->log->debug('Execute MongoDB query.');

        $options = array_merge([
            'allowDiskUse' => true
        ], $options);

        // Aggregate command options
        $aggregateConfig['allowDiskUse'] = $options['allowDiskUse'];
        if (!empty($options['maxTimeMS'])) {
            $aggregateConfig['maxTimeMS']= $options['maxTimeMS'];
        }

        $collection = $this->client->selectCollection($this->config['database'], $options['collection']);
        try {
            $cursor = $collection->aggregate($options['query'], $aggregateConfig);

            // log query
            $this->log->debug($cursor);

            $results = $cursor->toArray();

        } catch (\MongoDB\Driver\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
            throw new GeneralException($e->getMessage());
        }

        return $results;

    }
}