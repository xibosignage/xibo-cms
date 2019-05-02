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
use Xibo\Factory\DisplayGroupFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Factory\WidgetFactory;
use Xibo\Factory\LayoutFactory;
use Xibo\Factory\DisplayFactory;
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
                $media = $this->mediaFactory->getById($stat['mediaId']);
                $mediaName = $media->name; //dont remove used later
                $statData[$k]['mediaName'] = $mediaName;
                $tagFilter['media'] = explode(',', $media->tags);
            }

            // Widget name
            if ($stat['widgetId'] != null) {
                $widget = $this->widgetFactory->getById($stat['widgetId']);
                if($widget != null) {
                    $widget->load();
                    $widgetName = isset($mediaName) ? $mediaName : $widget->getOptionValue('name', $widget->type);
                    $statData[$k]['widgetName'] = $widgetName;
                }
            }

            // Display name
            $display = $this->displayFactory->getById($stat['displayId']);
            $statData[$k]['displayName'] = $display->display;

            // Layout data
            $layoutName = null;
            $layoutTags = null;

            if ($stat['type'] != 'event') {

                try {
                    $layout = $this->layoutFactory->getById($stat['layoutId']);

                    $this->log->debug('Found layout : '. $stat['layoutId']);

                    $campaignId = $layout->campaignId;
                    $layoutName = $layout->layout;
                    $layoutTags = $layout->tags;

                } catch (NotFoundException $error) {

                    $this->log->debug('Layout not Found. Search in layout history for latest layout.');

                    // an old layout which has been deleted still plays on the player
                    // so we will get layout not found
                    // Hence, get the latest layout

                    $campaignId = $this->layoutFactory->getCampaignIdFromLayoutHistory($stat['layoutId']);

                    if ($campaignId !== null) {

                        $this->log->debug('CampaignId is: '.$campaignId);

                        $latestLayoutId = $this->layoutFactory->getLatestLayoutIdFromLayoutHistory($campaignId);

                        $this->log->debug('Latest layoutId: '.$latestLayoutId);

                        // Latest layout
                        $layout = $this->layoutFactory->getById($latestLayoutId);
                        $layoutName = $layout->layout;
                        $layoutTags = $layout->tags;

                    }

                }

                // Get layout Campaign ID
                $statData[$k]['campaignId'] = (int) $campaignId;

                // Layout tags
                $tagFilter['layout'] = explode(',', $layoutTags);

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
                $campaignIds[] = $this->layoutFactory->getCampaignIdFromLayoutHistory($layoutId);
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
            $entry['minStart'] = $row['minStart']->toDateTime()->format('Y-m-d H:i:s');
            $entry['maxEnd'] = $row['maxEnd']->toDateTime()->format('Y-m-d H:i:s');
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
                        'start'=> [
                            '$dateToString' => [
                                'format' => '%Y-%m-%d %H:%M:%S',
                                'date' => '$start'
                            ]
                        ],
                        'end'=> [
                            '$dateToString' => [
                                'format' => '%Y-%m-%d %H:%M:%S',
                                'date' => '$end'
                            ]
                        ],
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
    public function getDailySummaryReport($displayIds, $diffInDays, $type, $layoutId, $mediaId, $reportFilter, $groupByFilter = null, $fromDt = null, $toDt = null)
    {
        if ( (($type == 'media') && ($mediaId != '')) ||
            (($type == 'layout') && ($layoutId != '')) ) {

            $fromDt = $this->dateService->parse($fromDt)->startOfDay();
            $toDt = $this->dateService->parse($toDt)->startOfDay()->addDay();

            $yesterday = $this->dateService->parse()->startOfDay()->subDay();
            $today = $this->dateService->parse()->startOfDay();
            $nextday = $this->dateService->parse()->startOfDay()->addDay();

            $firstdaythisweek = $this->dateService->parse()->startOfWeek();
            $lastdaythisweek = $this->dateService->parse()->endOfWeek()->addSecond();

            $firstdaylastweek = $this->dateService->parse()->startOfWeek()->subWeek();
            $lastdaylastweek = $this->dateService->parse()->endOfWeek()->subWeek()->addSecond();

            $firstdaythismonth = $this->dateService->parse()->startOfMonth();
            $lastdaythismonth = $this->dateService->parse()->endOfMonth()->addSecond();

            $firstdaylastmonth = $this->dateService->parse()->startOfMonth()->subMonth();
            $lastdaylastmonth = $this->dateService->parse()->endOfMonth()->subMonth()->addSecond();

            $firstdaythisyear = $this->dateService->parse()->startOfYear();
            $lastdaythisyear = $this->dateService->parse()->endOfYear()->addSecond();

            $firstdaylastyear = $this->dateService->parse()->startOfYear()->subYear();
            $lastdaylastyear = $this->dateService->parse()->endOfYear()->subYear()->addSecond();

            if ($reportFilter == '') {

                $hour = 24;

                if ($groupByFilter == 'byweek') {
                    $input = range(0,  ceil($diffInDays / 7 ) );
                } elseif ($groupByFilter == 'bymonth') {
                    $startOfMonthFromDt = new UTCDateTime($this->dateService->parse($fromDt)->startOfMonth()->format('U')*1000);
                    $input = range(0, ceil($diffInDays / 30));
                } else {
                    $input = range(0, $diffInDays);
                }

                $periodStart = new UTCDateTime($this->dateService->parse($fromDt)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($toDt)->format('U')*1000);
            }

            // where start is less than last hour of the day + 1 hour (i.e., nextday of today)
            // and end is greater than or equal first hour of the day
            elseif (($reportFilter == 'today')) {

                $hour = 1;
                $input = range(0, 23);

                $periodStart = new UTCDateTime($this->dateService->parse($today)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($nextday)->format('U')*1000);
            }

            // where start is less than last hour of the day + 1 hour (i.e., today)
            // and end is greater than or equal first hour of the day
            elseif (($reportFilter == 'yesterday')) {

                $hour = 1;
                $input = range(0, 23);

                $periodStart = new UTCDateTime($this->dateService->parse($yesterday)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($today)->format('U')*1000);
            }

            // where start is less than last day of the week
            // and end is greater than or equal first day of the week
            elseif (($reportFilter == 'thisweek')) {

                $hour = 24;
                $input = range(0, 6);

                $periodStart = new UTCDateTime($this->dateService->parse($firstdaythisweek)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($lastdaythisweek)->format('U')*1000);
            }

            // where start is less than last day of the week
            // and end is greater than or equal first day of the week
            elseif (($reportFilter == 'lastweek')) {

                $hour = 24;
                $input = range(0, 6);

                $periodStart = new UTCDateTime($this->dateService->parse($firstdaylastweek)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($lastdaylastweek)->format('U')*1000);
            }

            // where start is less than last day of the month + 1 day
            // and end is greater than or equal first day of the month
            elseif (($reportFilter == 'thismonth')) {

                $hour = 24;

                if ($groupByFilter == 'byweek') {
                    $input = range(0, 5);
                } else {
                    $input = range(0, 30);
                }

                $periodStart = new UTCDateTime($this->dateService->parse($firstdaythismonth)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($lastdaythismonth)->format('U')*1000);
            }

            // where start is less than last day of the month + 1 day
            // and end is greater than or equal first day of the month
            elseif (($reportFilter == 'lastmonth')) {

                $hour = 24;

                if ($groupByFilter == 'byweek') {
                    $input = range(0, 5);
                } else {
                    $input = range(0, 30);
                }

                $periodStart = new UTCDateTime($this->dateService->parse($firstdaylastmonth)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($lastdaylastmonth)->format('U')*1000);
            }

            // where start is less than last day of the year + 1 day
            // and end is greater than or equal first day of the year
            elseif (($reportFilter == 'thisyear')) {

                $hour = 24;

                if ($groupByFilter == 'byweek') {
                    $input = range(0, 53);
                } elseif ($groupByFilter == 'bymonth') {
                    $input = range(0, 11);
                } else {
                    $input = range(0, 365);
                }


                $periodStart = new UTCDateTime($this->dateService->parse($firstdaythisyear)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($lastdaythisyear)->format('U')*1000);
            }

            // where start is less than last day of the year + 1 day
            // and end is greater than or equal first day of the year
            elseif (($reportFilter == 'lastyear')) {

                $hour = 24;

                if ($groupByFilter == 'byweek') {
                    $input = range(0, 53);
                } elseif ($groupByFilter == 'bymonth') {
                    $input = range(0, 11);
                } else {
                    $input = range(0, 365);
                }

                $periodStart = new UTCDateTime($this->dateService->parse($firstdaylastyear)->format('U')*1000);
                $periodEnd = new UTCDateTime($this->dateService->parse($lastdaylastyear)->format('U')*1000);
            }

            // Type filter
            if (($type == 'layout') && ($layoutId != '')) {

                // Get the campaign ID
                $campaignId = $this->layoutFactory->getCampaignIdFromLayoutHistory($layoutId);

                $matchType = [
                    '$eq' => [ '$type', 'layout' ]
                ];
                $matchId = [
                    '$eq' => [ '$campaignId', $campaignId ]
                ];
            } elseif (($type == 'media') && ($mediaId != '')) {
                $matchType = [
                    '$eq' => [ '$type', 'media' ]
                ];
                $matchId = [
                    '$eq' => [ '$mediaId', $mediaId ]
                ];
            }


            if ($groupByFilter == 'byweek') {

                // PERIOD GENERATION
                $projectMap = [
                    '$project' => [
                        'periods' =>  [
                            '$map' => [
                                'input' => $input,
                                'as' => 'number',
                                'in' => [
                                    'start' => [
                                        '$max' => [
                                            $periodStart,
                                            [
                                                '$add' => [
                                                    [
                                                        '$subtract' => [
                                                            $periodStart,
                                                            [
                                                                '$multiply' => [
                                                                    [
                                                                        '$subtract' => [
                                                                            [
                                                                                '$isoDayOfWeek' => $periodStart
                                                                            ],
                                                                            1
                                                                        ]
                                                                    ] ,
                                                                    86400000
                                                                ]
                                                            ]
                                                        ]
                                                    ],
                                                    [
                                                        '$multiply' => [
                                                            '$$number',
                                                            604800000
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ],
                                    'end' => [
                                        '$min' => [
                                            $periodEnd,
                                            [
                                                '$add' => [
                                                    [
                                                        '$add' => [
                                                            [
                                                                '$subtract' => [
                                                                    $periodStart,
                                                                    [
                                                                        '$multiply' => [
                                                                            [
                                                                                '$subtract' => [
                                                                                    [
                                                                                        '$isoDayOfWeek' => $periodStart
                                                                                    ],
                                                                                    1
                                                                                ]
                                                                            ] ,
                                                                            86400000
                                                                        ]
                                                                    ]
                                                                ]
                                                            ],
                                                            604800000
                                                        ]
                                                    ],
                                                    [
                                                        '$multiply' => [
                                                            '$$number',
                                                            604800000
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ],
                                ]
                            ]
                        ]
                    ]
                ];

                // GROUP BY
                $groupBy = [
                    'weekNo' => '$weekNo',
                ];

            } elseif ($groupByFilter == 'bymonth') {

                // period start becomes start of the month of the selected from date in the case of date range selection
                if ($reportFilter != '') {
                    $startOfMonthFromDt = $periodStart;
                }

                $projectMap = [
                    '$project' => [
                        'periods' => [
                            '$map' => [
                                'input' => $input,
                                'as' => 'number',
                                'in' => [
                                    'start' => [
                                        '$dateFromParts' => [
                                            'year' => [
                                                '$year' => $startOfMonthFromDt
                                            ],
                                            'month' => [
                                                '$add' => [
                                                    '$$number',
                                                    [
                                                        '$month' => [
                                                            '$add' => [
                                                                $startOfMonthFromDt,
                                                                [
                                                                    '$multiply' => [
                                                                        $hour * 3600000,
                                                                        '$$number'
                                                                    ]
                                                                ]
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ],
                                            'day' => [
                                                '$dayOfMonth' => $startOfMonthFromDt
                                            ]
                                        ]
                                    ],
                                    'end' => [
                                        '$dateFromParts' => [
                                            'year' => [
                                                '$year' => $startOfMonthFromDt
                                            ],
                                            'month' => [
                                                '$add' => [
                                                    1,
                                                    [
                                                        '$add' => [
                                                            '$$number',
                                                            [
                                                                '$month' => [
                                                                    '$add' => [
                                                                        $startOfMonthFromDt,
                                                                        [
                                                                            '$multiply' => [
                                                                                $hour * 3600000,
                                                                                '$$number'
                                                                            ]
                                                                        ]
                                                                    ]
                                                                ]
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ],
                                            'day' => [
                                                '$dayOfMonth' => $startOfMonthFromDt
                                            ]
                                        ]
                                    ],
                                ]
                            ]
                        ]
                    ]
                ];

                // GROUP BY
                $groupBy = [
                    'monthNo' => '$monthNo'
                ];

            } else {

                // PERIOD GENERATION
                $projectMap = [
                    '$project' => [
                        'periods' =>  [
                            '$map' => [
                                'input' => $input,
                                'as' => 'number',
                                'in' => [
                                    'start' => [
                                        '$add' => [
                                            $periodStart,
                                            [
                                                '$multiply' => [
                                                    $hour*3600000,
                                                    '$$number'
                                                ]
                                            ]
                                        ]
                                    ],
                                    'end' => [
                                        '$add' => [
                                            [
                                                '$add' => [
                                                    $periodStart,
                                                    [
                                                        '$multiply' => [
                                                            $hour*3600000,
                                                            '$$number'
                                                        ]
                                                    ]
                                                ]
                                            ]
                                            , $hour*3600000
                                        ]
                                    ],
                                ]
                            ]
                        ]
                    ]
                ];

                // GROUP BY
                $groupBy = [
                    'period_start' => '$period_start'
                ];
            }

            // PERIODS QUERY
            $collectionPeriod = $this->client->selectCollection($this->config['database'], 'period');

            // STAT AGGREGATION QUERY
            $collection = $this->client->selectCollection($this->config['database'], $this->table);

            try {

                // Periods
                $cursorPeriod = $collectionPeriod->aggregate([

                    $projectMap,

                    // periods needs to be unwind to merge next
                    [
                        '$unwind' => '$periods'
                    ],

                    // replace the root to eliminate _id and get only periods
                    [
                        '$replaceRoot' => [
                            'newRoot' => '$periods'
                        ]
                    ],

                    [
                        '$project' => [
                            'start' => 1,
                            'end' => 1,
                            'monthNo' => [
                                '$month' =>  '$start'
                            ],
                            'yearDate' => [
                                '$isoWeekYear' =>  '$start'
                            ],
                            'weekNo' => [
                                '$isoWeek' =>  '$start'
                            ],
                        ]
                    ],

                    [
                        '$addFields' => [
                            'shortMonth' => [
                                '$let' => [
                                    'vars' => [
                                        'monthString' => ['NA', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
                                    ],
                                    'in' => [
                                        '$arrayElemAt' => [
                                            '$$monthString', '$monthNo'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],

                    [
                        '$match' => [
                            'start' => [
                                '$lt' => $periodEnd
                            ],
                            'end' => [
                                '$gt' => $periodStart
                            ]
                        ]
                    ],

                ]);
                $periods = $cursorPeriod->toArray();

                // Stats
                $cursor = $collection->aggregate([
                    [
                        '$match' => [
                            'start' =>  [
                                '$lt' => $periodEnd
                            ],
                            'end' => [
                                '$gt' => $periodStart
                            ],
                        ]
                    ],

                    [
                        '$lookup' => [
                            'from' => 'period',
                            'let' => [
                                'stat_start' => '$start',
                                'stat_end' => '$end',
                                'stat_duration' => '$duration',
                                'stat_count' => '$count',
                            ],
                            'pipeline' => [
                                $projectMap,
                                [
                                    '$unwind' => '$periods'
                                ],

                            ],
                            'as' => 'statdata'
                        ]
                    ],

                    [
                        '$unwind' => '$statdata'
                    ],

                    [
                        '$match' => [
                            '$expr' => [
                                '$and' => [

                                    // match media id / layout id
                                    $matchType,
                                    $matchId,

                                    // display ids
                                    [
                                        '$in' => [ '$displayId', $displayIds ]
                                    ],

                                    // stat.start < period end AND stat.end > period start
                                    // for example, when report filter 'today' is selected
                                    // where start is less than last hour of the day + 1 hour (i.e., nextday of today)
                                    // and end is greater than or equal first hour of the day
                                    [
                                        '$lt' => [ '$start', '$statdata.periods.end' ]
                                    ],
                                    [
                                        '$gt' => [ '$end', '$statdata.periods.start' ]
                                    ],
                                ]
                            ]
                        ]

                    ],

                    [
                        '$project' => [
                            '_id' => 1,
                            'count' => 1,
                            'duration' => 1,
                            'start' => 1,
                            'end' => 1,
                            'period_start' => '$statdata.periods.start',
                            'period_end' => '$statdata.periods.end',
                            'monthNo' => [
                                '$month' =>  '$statdata.periods.start'
                            ],
                            'yearDate' => [
                                '$isoWeekYear' =>  '$statdata.periods.start'
                            ],
                            'weekNo' => [
                                '$isoWeek' =>  '$statdata.periods.start'
                            ],
                            'actualStart' => [
                                '$max' => [ '$start', '$statdata.periods.start' ]
                            ],
                            'actualEnd' => [
                                '$min' => [ '$end', '$statdata.periods.end' ]
                            ],
                            'actualDiff' => [
                                '$min' => [
                                    '$duration',
                                    [
                                        '$divide' => [
                                            [
                                                '$subtract' => [
                                                    ['$min' => [ '$end', '$statdata.periods.end' ]],
                                                    ['$max' => [ '$start', '$statdata.periods.start' ]]
                                                ]
                                            ], 1000
                                        ]
                                    ]
                                ]
                            ],

                        ]

                    ],

                    [
                        '$group' => [
                            '_id' => $groupBy,
                            'period_start' => ['$first' => '$period_start'],
                            'monthNo' => ['$first' => '$monthNo'],
                            'yearDate' => ['$first' => '$yearDate'],
                            'weekNo' => ['$first' => '$weekNo'],
                            'NumberPlays' => ['$sum' => '$count'],
                            'Duration' => ['$sum' => '$actualDiff'],
                            'end' => ['$first' => '$end'],
                        ]
                    ],

                    [
                        '$project' => [
                            'start' => '$_id.period_start',
                            'end' => '$end',
                            'period_start' => 1,
                            'NumberPlays' => 1,
                            'Duration' => 1,
                            'monthNo' => 1,
                            'yearDate' => 1,
                            'weekNo' => 1,
                        ]
                    ],

                    // mongodb doesnot have monthname
                    // so we use addFields to add month name (shortMonth) in a $let aggregation (map monthNo with monthString)
                    [
                        '$addFields' => [
                            'shortMonth' => [
                                '$let' => [
                                    'vars' => [
                                        'monthString' => ['NA', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
                                    ],
                                    'in' => [
                                        '$arrayElemAt' => [
                                            '$$monthString', '$monthNo'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],

                ], ['allowDiskUse'=> true]);

                // log query
                $this->log->debug($cursor);

                $results = $cursor->toArray();

                // Run period loop and map the stat result for each period
                $resultArray = [];
                foreach ($periods as $key => $period) {

                    // Format to datetime string
                    $start = $period['start']->toDateTime()->format('Y-m-d H:i:s');

                    // end is weekEnd in byweek filter
                    $end = $period['end']->toDateTime()->format('Y-m-d H:i:s');

                    $matched = false;
                    foreach ($results as $k => $result) {

                        if( $result['period_start'] == $period['start'] ) {

                            $NumberPlays = $result['NumberPlays'];
                            $Duration = $result['Duration'];
                            $monthNo = $result['monthNo'];
                            $yearDate = $result['yearDate'];
                            $weekNo = $result['weekNo'];
                            $shortMonth = $result['shortMonth'];

                            $matched = true;
                            break;
                        }
                    }

                    $resultArray[$key]['start'] = $start;

                    // end is weekEnd in byweek filter
                    if ($groupByFilter == 'byweek') {
                        $resultArray[$key]['weekEnd'] = $end;
                    }

                    if($matched == true) {
                        $resultArray[$key]['NumberPlays'] = $NumberPlays;
                        $resultArray[$key]['Duration'] = $Duration;
                        $resultArray[$key]['monthNo'] = $monthNo;
                        $resultArray[$key]['yearDate'] = $yearDate;
                        $resultArray[$key]['weekNo'] = $weekNo;
                        $resultArray[$key]['shortMonth'] = $shortMonth;

                    } else {
                        $resultArray[$key]['NumberPlays'] = 0;
                        $resultArray[$key]['Duration'] = 0;
                        $resultArray[$key]['monthNo'] = $period['monthNo'];
                        $resultArray[$key]['yearDate'] = $period['yearDate'];
                        $resultArray[$key]['weekNo'] = $period['weekNo'];
                        $resultArray[$key]['shortMonth'] = $period['shortMonth'];

                    }
                }

            } catch (\MongoDB\Exception\RuntimeException $e) {
                $this->log->error($e->getMessage());
            }

            return $resultArray;

        } else {
            return [];
        }
    }
}