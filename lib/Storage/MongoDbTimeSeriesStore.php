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

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use Xibo\Exception\NotFoundException;
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

    private $config;

    private $client;

    private $table = 'stat';

    private $periodTable = 'period';

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
    public function setDependencies($log, $date, $layoutFactory = null, $mediaFactory = null, $widgetFactory = null, $displayFactory = null, $displayGroupFactory = null)
    {
        $this->log = $log;
        $this->dateService = $date;
        $this->mediaFactory = $mediaFactory;
        $this->widgetFactory = $widgetFactory;
        $this->layoutFactory = $layoutFactory;
        $this->displayFactory = $displayFactory;
        $this->displayGroupFactory = $displayGroupFactory;

        try {
            $this->client = new Client('mongodb://'.$this->config['host'].':'. $this->config['port'],
                [
                    'username' => $this->config['username'],
                    'password' => $this->config['password']
                ]);
        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        return $this;
    }

    /** @inheritdoc */
    public function addStat($statData)
    {
        foreach ($statData as $k => $stat) {

            $statData[$k]['start'] = new UTCDateTime($this->dateService->parse($statData[$k]['fromDt'])->format('U')*1000);
            unset($statData[$k]['fromDt']);

            $statData[$k]['end'] = new UTCDateTime($this->dateService->parse($statData[$k]['toDt'])->format('U')*1000);
            unset($statData[$k]['toDt']);

            $statData[$k]['eventName'] = $statData[$k]['tag'];
            unset($statData[$k]['tag']);

            unset($statData[$k]['scheduleId']);
            unset($statData[$k]['statDate']);

            // Media name
            $mediaName = null;
            if ($stat['mediaId'] != null) {
                try {
                    $media = $this->mediaFactory->getById($stat['mediaId']);
                } catch (NotFoundException $error) {
                    // Media not found ignore and log the stat
                    $this->log->error('Media not found. Media Id: '. $stat['mediaId'] .',Layout Id: '. $stat['layoutId']
                        .', FromDT: '.$statData[$k]['start'].', ToDt: '.$statData[$k]['end'].', Type: '.$stat['type']
                        .', Duration: '.$stat['duration'] .', Count '.$stat['count']);

                    // unset($statData[$k]);
                    continue;
                }
                 $mediaName = $media->name; //dont remove used later
                 $statData[$k]['mediaName'] = $mediaName;
                $tagFilter['media'] = explode(',', $media->tags);
            }

            // Widget name
            if ($stat['widgetId'] != null) {
                try {
                    $widget = $this->widgetFactory->getById($stat['widgetId']);
                } catch (NotFoundException $error) {
                    // Widget not found, ignore and log the stat
                    $this->log->error('Widget not found. Widget Id: '. $stat['widgetId'] .',Layout Id: '. $stat['layoutId']
                        .', FromDT: '.$statData[$k]['start'].', ToDt: '.$statData[$k]['end'].', Type: '.$stat['type']
                        .', Duration: '.$stat['duration'] .', Count '.$stat['count']);

                    // unset($statData[$k]);
                    continue;
                }

                if($widget != null) {
                    $widget->load();
                    $widgetName = isset($mediaName) ? $mediaName : $widget->getOptionValue('name', $widget->type);
                    $statData[$k]['widgetName'] = $widgetName;
                }
            }

            // Display name
            try {
                $display = $this->displayFactory->getById($stat['displayId']); //TODO what if you dont find the displayId
            } catch (NotFoundException $error) {
                // Display not found, ignore and log the stat
                $this->log->error('Display not found. Display Id: '. $stat['displayId'] .',Layout Id: '. $stat['layoutId']
                    .', FromDT: '.$statData[$k]['start'].', ToDt: '.$statData[$k]['end'].', Type: '.$stat['type']
                    .', Duration: '.$stat['duration'] .', Count '.$stat['count']);

                // unset($statData[$k]);
                continue;
            }

            $statData[$k]['displayName'] = $display->display;

            // Layout data
            $layoutName = null;
            $layoutTags = null;

            if ($stat['type'] != 'event') {

                try {
                    $layout = $this->layoutFactory->getByParentCampaignId($stat['campaignId']);

                    $this->log->debug('Found layout : '. $stat['layoutId']);

                    $campaignId = $layout->campaignId;
                    $layoutName = $layout->layout;
                    $layoutTags = $layout->tags;

                    // Get layout Campaign ID
                    $statData[$k]['campaignId'] = (int) $campaignId;

                    // Layout tags
                    $tagFilter['layout'] = $layoutTags;

                } catch (NotFoundException $error) {
                    // All we can do here is log
                    // we shouldn't ever get in this situation because the campaignId we used above will have
                    // already been looked up in the layouthistory table.
                    $this->log->alert('Error processing statistic into MongoDB. Stat is: ' . json_encode($stat));
                }

                // Display tags
                $tagFilter['dg'] = explode(',', $this->displayGroupFactory->getById($display->displayGroupId)->tags);

                // TagFilter array
                $statData[$k]['tagFilter'] = $tagFilter;

            }

            $statData[$k]['layoutName'] = $layoutName;
        }

        // Insert statistics
        $collection = $this->client->selectCollection($this->config['database'], $this->table);
        try {
            $collection->insertMany($statData);

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
    public function getStatsReport($fromDt, $toDt, $displayIds, $layoutIds, $mediaIds, $type, $columns, $tags, $tagsType, $exactTags, $start = null, $length = null)
    {

        $fromDt = new UTCDateTime($this->dateService->parse($fromDt)->format('U')*1000);
        $toDt = new UTCDateTime($this->dateService->parse($toDt)->addDay()->format('U')*1000);

        // Filters the documents to pass only the documents that
        // match the specified condition(s) to the next pipeline stage.
        $match =  [
            '$match' => [
                'end' => [ '$gt' => $fromDt],
                'start' => [ '$lte' => $toDt]
            ]
        ];

        // Display Filter
        if (count($displayIds) > 0) {
            $match['$match']['displayId'] = [ '$in' => $displayIds ];
        }

        // Type Filter
        if ($type != null) {
            $match['$match']['type'] = $type;
        }

        // Tag Filter
        if ($tags != null) {

            $tagsArray = explode(',', $tags);

            // When exact match is not desired
            if ($exactTags != 1) {
                $tagsArray = array_map(function ($tag) { return new \MongoDB\BSON\Regex('.*'.$tag. '.*', 'i'); }, $tagsArray);
            }

            if (count($tagsArray) > 0) {
                $match['$match']['tagFilter.' . $tagsType] = [
                    '$exists' => true,
                    '$in' => $tagsArray
                ];
            }
        }

        // Layout Filter
        if (count($layoutIds) != 0) {
            $this->log->debug($layoutIds, JSON_PRETTY_PRINT);
            // Get campaignIds for selected layoutIds
            $campaignIds = [];
            foreach ($layoutIds as $layoutId) {
                try {
                    $campaignIds[] = $this->layoutFactory->getCampaignIdFromLayoutHistory($layoutId);
                } catch (NotFoundException $notFoundException) {
                    // Ignore the missing one
                    $this->log->debug('Filter for Layout without Layout History Record, layoutId is ' . $layoutId);
                }
            }
            $match['$match']['campaignId'] = [ '$in' => $campaignIds ];
        }

        // Media Filter
        if (count($mediaIds) != 0) {
            $this->log->debug($mediaIds, JSON_PRETTY_PRINT);
            $match['$match']['mediaId'] = [ '$in' => $mediaIds ];
        }

        // For sorting
        // The selected column has a key
        $temp = [
            '_id.type' => 'type',
            '_id.display' => 'display',
            'layout' => 'layout',
            'media' => 'media',
            'eventName' => 'eventName',
            'layoutId' => 'layoutId',
            'widgetId' => 'widgetId',
            '_id.displayId' => 'displayId',
            'numberPlays' => 'numberPlays',
            'minStart' => 'minStart',
            'maxEnd' => 'maxEnd',
            'duration' => 'duration',
        ];

        // Remove ` and DESC from the array strings
        $cols = [];
        foreach ($columns as $column) {
            $str = str_replace("`","",str_replace(" DESC","",$column));
            if (\strpos($column, 'DESC') !== false) {
                $cols[$str] = -1;
            } else {
                $cols[$str] = 1;

            }
        }

        // The selected column key gets stored in an array with value 1 or -1 (for DESC)
        $array = [];
        foreach ($cols as $k => $v) {
            if (array_search($k, $temp))
                $array[array_search($k, $temp)] = $v;
        }

        $order = ['_id.type'=> 1]; // default sorting by type
        if ($array != null) {
            $order = $array;
        }

        $project = [
            '$project' => [
                'campaignId' =>  1,
                'mediaId' =>  1,
                'mediaName'=> 1,
                'media'=> [ '$ifNull' => [ '$mediaName', '$widgetName' ] ],
                'eventName' => 1,
                'widgetId' =>  1,
                'widgetName' =>  1,
                'layoutId' =>  1,
                'layoutName' =>  1,
                'displayId' =>  1,
                'displayName' =>  1,
                'start' => 1,
                'end' => 1,
                'type' => 1,
                'duration' => 1,
                'count' => 1,
                'total' => ['$sum' => 1],
            ]
        ];

        $group = [
            '$group' => [
                '_id' => [
                    'type' => '$type',
                    'campaignId'=> [ '$ifNull' => [ '$campaignId', '$layoutId' ] ],
                    'mediaorwidget'=> [ '$ifNull' => [ '$mediaId', '$widgetId' ] ],
                    'displayId'=> [ '$ifNull' => [ '$displayId', null ] ],
                    'display'=> '$displayName',
                    // we don't need to group by media name and widget name

                ],

                'media'=> [ '$first' => '$media'],
                'eventName'=> [ '$first' => '$eventName'],
                'mediaId' => ['$first' => '$mediaId'],
                'widgetId' => ['$first' => '$widgetId' ],

                'layout' => ['$first' => '$layoutName'],

                // use the last layoutId to say that is the latest layoutId
                'layoutId' => ['$last' => '$layoutId'],

                'minStart' => ['$min' => '$start'],
                'maxEnd' => ['$max' => '$end'],
                'numberPlays' => ['$sum' => '$count'],
                'duration' => ['$sum' => '$duration'],
                'total' => ['$max' => '$total'],
            ],
        ];

        $collection = $this->client->selectCollection($this->config['database'], $this->table);
        try {
            $cursor = $collection->aggregate([
                // Filters the documents to pass only the documents that
                // match the specified condition(s) to the next pipeline stage.
                $match,
                $project,
                $group,
                ['$skip' => $start],
                ['$limit' => $length],
                ['$sort' => $order],
            ]);

            $result = $cursor->toArray();

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        $this->log->debug($cursor, JSON_PRETTY_PRINT);
        $this->log->debug($result, JSON_PRETTY_PRINT);

        $totalStats = 0;
        try {
            $totalStatCursor = $collection->aggregate([
                $match,
                $project,
                $group,
                [
                    '$group' => [
                        '_id' => [],
                        'totals' => ['$sum' => '$total'],

                    ],
                ]
            ]);

            $resTotal = $totalStatCursor->toArray();

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        if (count($resTotal) > 0) {
            $totalStats = $resTotal[0]['totals'];
        }

        $rows = [];

        foreach ($result as $row) {
            $entry = [];

            $entry['type'] = $row['_id']['type'];
            $entry['displayId'] = $row['_id']['displayId'];
            $entry['display'] = isset($row['_id']['display']) ? $row['_id']['display']: 'No display';
            $entry['layout'] = isset($row['layout']) ? $row['layout']: 'No layout';
            $entry['media'] = isset($row['media']) ? $row['media'] : 'No media' ;
            $entry['numberPlays'] = $row['numberPlays'];
            $entry['duration'] = $row['duration'];
            $entry['minStart'] = $row['minStart']->toDateTime()->format('U');
            $entry['maxEnd'] = $row['maxEnd']->toDateTime()->format('U');
            $entry['layoutId'] = $row['layoutId'];
            $entry['widgetId'] = $row['widgetId'];
            $entry['mediaId'] = $row['mediaId'];
            $entry['tag'] = $row['eventName'];

            $rows[] = $entry;
        }

        return [
            'statData' => $rows,
            'count' => count($rows),
            'totalStats' => $totalStats
        ];
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
                        'minDate' => ['$min' => '$start'],
                    ]
                ]
            ])->toArray();

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        return $earliestDate;

    }

    /** @inheritdoc */
    public function getStats($fromDt, $toDt, $displayIds = null)
    {
        $fromDt = new UTCDateTime($this->dateService->parse($fromDt)->format('U')*1000);
        $toDt = new UTCDateTime($this->dateService->parse($toDt)->addDay()->format('U')*1000);

        $match =  [
            '$match' => [
                'end' => [ '$gt' => $fromDt],
                'start' => [ '$lte' => $toDt]
            ]
        ];

        if (count($displayIds) != 0) {
            $this->log->debug($displayIds, JSON_PRETTY_PRINT);
            $match['$match']['displayId'] = [ '$in' => $displayIds ];
        }

        $collection = $this->client->selectCollection($this->config['database'], $this->table);
        try {
            $cursor = $collection->aggregate([
                $match,
                [
                    '$project' => [
                        'type'=> 1,
                        'start'=> 1,
                        'end'=> 1,
                        'layout'=> '$layoutName',
                        'display'=> '$displayName',
                        'media'=> '$mediaName',
                        'tag'=> '$eventName',
                        'displayId'=> 1,
                        'layoutId'=> 1,
                        'widgetId'=> 1,
                        'mediaId'=> 1,

                    ]
                ]
            ]);
        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        $result = new TimeSeriesMongoDbResults($cursor);

        return $result;

    }

    /** @inheritdoc */
    public function deleteStats($maxage, $fromDt = null, $options = [])
    {

        $fromDt = date(DATE_ISO8601, strtotime($fromDt));
        $toDt = date(DATE_ISO8601, strtotime($maxage));

        $collection = $this->client->selectCollection($this->config['database'], $this->table);

        $i = 0;
        $rows = 1;
        $options = array_merge([
            'maxAttempts' => 10,
            'statsDeleteSleep' => 3,
            'limit' => 10000,
        ], $options);

        $count = 0;
        while ($rows > 0) {

            $i++;

            if ($fromDt != null) {
                $match =  [
                    '$match' => [
                        '$expr' => [
                            '$and' => [
                                [
                                    '$lte' => [
                                        '$start', [
                                            '$dateFromString' => [
                                                'dateString' => $toDt
                                            ]
                                        ]
                                    ]
                                ],
                                [
                                    '$gt' => [
                                        '$end', [
                                            '$dateFromString' => [
                                                'dateString' => $fromDt
                                            ]
                                        ]
                                    ]
                                ],
                            ]
                        ]
                    ]
                ];
            } else {
                $match =  [
                    '$match' => [
                        '$expr' => [
                            '$and' => [
                                [
                                    '$lte' => [
                                        '$start', [
                                            '$dateFromString' => [
                                                'dateString' => $toDt
                                            ]
                                        ]
                                    ]
                                ],
                            ]
                        ]
                    ]
                ];
            }

            try{
                $findResult = $collection->aggregate(
                    [
                        $match
                    ],
                    ['limit' => $options['limit']]
                )->toArray();

            } catch (\MongoDB\Exception\RuntimeException $e) {
                $this->log->error($e->getMessage());
            }

            $idsArray = array_map(function ($res) { return $res['_id']; }, $findResult);

            try {
                $deleteResult = $collection->deleteMany([
                    '_id' => ['$in' => $idsArray]
                ]);

            } catch (\MongoDB\Exception\RuntimeException $e) {
                $this->log->error($e->getMessage());
                throw new \RuntimeException('Stats cannot be deleted.');
            }

            $rows = $deleteResult->getDeletedCount();
            $count += $rows;

            // Give MongoDB time to recover
            if ($rows > 0) {
                $this->log->debug('Stats delete effected ' . $rows . ' rows, sleeping.');
                sleep($options['statsDeleteSleep']);
            }

            // Break if we've exceeded the maximum attempts.
            if ($i >= $options['maxAttempts'])
                break;
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

        $collection = $this->client->selectCollection($this->config['database'], $options['collection']);
        try {
            $cursor = $collection->aggregate($options['query'], ['allowDiskUse' => $options['allowDiskUse']]);

            // log query
            $this->log->debug($cursor);

            $results = $cursor->toArray();

        } catch (\MongoDB\Exception\RuntimeException $e) {
            $this->log->error($e->getMessage());
        }

        return $results;

    }
}